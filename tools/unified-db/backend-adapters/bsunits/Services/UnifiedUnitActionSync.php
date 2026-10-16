<?php
namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/** Retryable local bridge; source request writes remain authoritative during transition. */
class UnifiedUnitActionSync
{
    private $source;
    private $target;
    public function __construct(Connection $source, Connection $target) {
        $this->source = $source; $this->target = $target;
    }
    public static function configured() {
        if (!config('unified_catalog.action_sync_enabled')) {
            throw new \LogicException('Unit action synchronization is disabled.');
        }
        if (DB::connection()->transactionLevel() !== 0) {
            throw new \LogicException('Cannot synchronize uncommitted source changes.');
        }
        config(['database.connections.unified_actions' => config('unified_catalog.action_database')]);
        return new static(DB::connection(), DB::connection('unified_actions'));
    }
    public static function enqueue($action) {
        $action->getConnection()->table('unified_unit_action_outbox')->updateOrInsert(
            ['unit_id' => $action->unit_id],
            ['action_id' => $action->id, 'queued_at' => now()->toDateTimeString()]
        );
    }
    public function drain($limit = 100) {
        if ($limit < 1 || $limit > 500) throw new \InvalidArgumentException('Invalid synchronization batch size.');
        $ids = $this->source->table('unified_unit_action_outbox')->orderBy('unit_id')->limit($limit)->pluck('unit_id');
        $completed = 0;
        foreach ($ids as $id) {
            $completed += $this->source->transaction(function () use ($id) {
                $event = $this->source->table('unified_unit_action_outbox')->where('unit_id', $id)->lockForUpdate()->first();
                if (!$event) return 0;
                $unit = $this->source->table('units')->where('id', $id)->first();
                if (!$unit || (string)$unit->unit_action_id !== (string)$event->action_id) {
                    throw new \LogicException('Source unit action does not match queued event.');
                }
                $action = $this->source->table('unit_actions')->where('id', $event->action_id)->where('unit_id', $id)->first();
                if (!$action) throw new \LogicException('Queued source action is missing.');
                $this->target->transaction(function () use ($unit, $action) {
                    $canonical = $this->target->table('units')->where('id', $unit->id)->lockForUpdate()->first();
                    if (!$canonical || $canonical->catalog_state !== 'CURRENT') {
                        throw new \LogicException('Canonical current unit is missing.');
                    }
                    // BS action IDs are preserved, immutable and increasing. Never move backwards.
                    if ($canonical->current_action_id !== null && $canonical->current_action_id > $action->id) return;
                    $existing = $this->target->table('unit_actions')->where('id', $action->id)->first();
                    if ($existing) {
                        foreach ((array)$action as $field => $value) {
                            if (!property_exists($existing, $field)
                                || (($existing->$field === null) !== ($value === null))
                                || (string)$existing->$field !== (string)$value) {
                                throw new \LogicException('Existing action differs from source; refusing overwrite.');
                            }
                        }
                    } else {
                        $this->target->table('unit_actions')->insert((array)$action);
                    }
                    $state = 'UNVERIFIED';
                    if ($unit->deleted_at !== null || !$unit->saleable) $state = 'UNAVAILABLE';
                    elseif ($action->action === 'AVAILABLE') $state = 'AVAILABLE';
                    elseif (in_array($action->action, ['HOLD', 'DEPOSIT'], true) || ($action->action === 'CONTRACT' && $action->status_action === 'PENDING')) $state = 'RESERVED';
                    elseif (in_array($action->action, ['CONTRACT', 'HANDOVERED'], true)) $state = 'SOLD';
                    elseif (in_array($action->action, ['UNAVAILABLE', 'NON-CONTRACT', 'REPAIR-MAINTENANCE'], true)) $state = 'UNAVAILABLE';
                    $this->target->table('units')->where('id', $unit->id)->update([
                        'current_action_id' => $action->id, 'availability_state' => $state,
                        'is_saleable' => $unit->deleted_at === null && (bool)$unit->saleable,
                    ]);
                });
                // Target commits first. A crash here leaves the source event for an idempotent retry.
                $this->source->table('unified_unit_action_outbox')->where('unit_id', $id)->where('action_id', $event->action_id)->delete();
                return 1;
            });
        }
        return $completed;
    }
}
