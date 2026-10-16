<?php

namespace App\Services;

use App\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class UnifiedUnitPriceService
{
    protected function decimal($value)
    {
        if (!is_string($value) || !preg_match('/^[0-9]{1,16}(?:\.[0-9]{1,2})?$/D', $value)) {
            throw new \InvalidArgumentException('Price must be a non-negative decimal string with at most two decimal places.');
        }
        $parts = explode('.', $value);
        $whole = ltrim($parts[0], '0');
        return ($whole === '' ? '0' : $whole).'.'.str_pad($parts[1] ?? '', 2, '0');
    }

    public function update(User $user, $unitId, $amount, $expectedPrice, $requestKey, $reason = null)
    {
        if (!config('unified_catalog.enabled') || !config('unified_catalog.writes_enabled')) {
            throw new \LogicException('Unified catalog writes are disabled.');
        }
        // Same permission required by the original admin unit-update route.
        if (!$user->can('update-unit')) {
            throw new AuthorizationException('Unit update permission required.');
        }
        $amount = $this->decimal($amount);
        $expectedPrice = $this->decimal($expectedPrice);
        if (!is_string($requestKey) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $requestKey)
            || ($reason !== null && (!is_string($reason) || mb_strlen($reason) > 500))) {
            throw new \InvalidArgumentException('Invalid price-change key or reason.');
        }
        config(['database.connections.unified_catalog_write' => config('unified_catalog.write_database')]);
        $db = DB::connection('unified_catalog_write');
        return $db->transaction(function () use ($db, $user, $unitId, $amount, $expectedPrice, $requestKey, $reason) {
            $actorId = $db->table('actor_source_map')->where('source_system', 'bsunits')
                ->where('source_user_id', $user->getKey())->value('actor_id');
            if (!$actorId) {
                throw new AuthorizationException('Source actor has no verified migration mapping.');
            }
            $unit = $db->table('units')->where('id', $unitId)->where('catalog_state', 'CURRENT')
                ->whereNull('deleted_at')->lockForUpdate()->first();
            if (!$unit) {
                throw (new ModelNotFoundException)->setModel('Unit', [$unitId]);
            }
            // A locking read sees a committed retry even under REPEATABLE READ.
            $existing = $db->table('unit_price_changes')->where('request_key', $requestKey)->lockForUpdate()->first();
            if ($existing) {
                if ((string) $existing->unit_id !== (string) $unitId
                    || (string) $existing->actor_id !== (string) $actorId
                    || $this->decimal($existing->new_price) !== $amount
                    || $this->decimal($existing->old_price) !== $expectedPrice) {
                    throw new \DomainException('Price-change key was already used for a different request.');
                }
                return ['unit_id' => $existing->unit_id, 'price' => $existing->new_price, 'change_id' => $existing->id];
            }
            if ($unit->list_price === null || $this->decimal($unit->list_price) !== $expectedPrice) {
                throw new \DomainException('Unit price changed; reload before updating.');
            }
            $changeId = $db->table('unit_price_changes')->insertGetId([
                'unit_id' => $unitId, 'actor_id' => $actorId, 'old_price' => $expectedPrice,
                'new_price' => $amount, 'request_key' => $requestKey, 'reason' => $reason,
            ]);
            $db->table('units')->where('id', $unitId)->update(['list_price' => $amount]);
            return ['unit_id' => $unitId, 'price' => $amount, 'change_id' => $changeId];
        }, 3);
    }
}
