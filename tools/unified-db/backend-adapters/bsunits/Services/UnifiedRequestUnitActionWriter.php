<?php
namespace App\Services;

/** Same-connection writes for requests running on the canonical database. */
class UnifiedRequestUnitActionWriter
{
    public static function apply($action)
    {
        $db = $action->getConnection();
        if ($db->transactionLevel() < 1) throw new \LogicException('Canonical unit action requires a transaction.');
        $table = config('unified_catalog.request_unit_table', 'units');
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $table)) throw new \LogicException('Invalid canonical unit table.');
        $unit = $db->table($table)->where('id', $action->unit_id)->lockForUpdate()->first();
        if (!$unit || $unit->catalog_state !== 'CURRENT') throw new \LogicException('Canonical current unit is missing.');
        if ($unit->current_action_id !== null && $unit->current_action_id > $action->id) return;
        $state = 'UNVERIFIED';
        if ($unit->deleted_at !== null || !$unit->is_saleable) $state = 'UNAVAILABLE';
        elseif ($action->action === 'AVAILABLE') $state = 'AVAILABLE';
        elseif (in_array($action->action, ['HOLD', 'DEPOSIT'], true) || ($action->action === 'CONTRACT' && $action->status_action === 'PENDING')) $state = 'RESERVED';
        elseif (in_array($action->action, ['CONTRACT', 'HANDOVERED'], true)) $state = 'SOLD';
        elseif (in_array($action->action, ['UNAVAILABLE', 'NON-CONTRACT', 'REPAIR-MAINTENANCE'], true)) $state = 'UNAVAILABLE';
        $db->table($table)->where('id', $unit->id)->update([
            'current_action_id' => $action->id, 'availability_state' => $state,
        ]);
    }
}
