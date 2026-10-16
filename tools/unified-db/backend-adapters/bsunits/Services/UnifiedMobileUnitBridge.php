<?php
namespace App\Services;

use App\Unit;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/** Transitional read bridge: canonical catalog, legacy media/promotions. Never saves models. */
class UnifiedMobileUnitBridge
{
    public function listing(array $filters, array $embeds = [])
    {
        $this->synchronize();
        $page = app(UnifiedCatalogRepository::class)->units($filters);
        $ids = $page->getCollection()->pluck('id')->all();
        $legacy = Unit::whereIn('id', $ids)->with($embeds)->get()->keyBy('id');
        $page->setCollection($page->getCollection()->map(function ($data) use ($legacy) {
            $unit = $legacy->get($data['id']);
            if (!$unit) {
                throw new \LogicException('Canonical unit has no legacy support record.');
            }
            return $this->attach($unit, $data);
        }));
        return $page;
    }

    public function detail($id, array $embeds = [])
    {
        $this->synchronize();
        $data = app(UnifiedCatalogRepository::class)->unit($id);
        if (!$data) {
            throw (new ModelNotFoundException)->setModel(Unit::class, [$id]);
        }
        return $this->attach(Unit::with($embeds)->findOrFail($id), $data);
    }

    protected function synchronize()
    {
        if (config('unified_catalog.action_sync_enabled')) {
            $sync = UnifiedUnitActionSync::configured();
            // Never return a filtered page while pending actions could change its membership.
            do { $count = $sync->drain(500); } while ($count === 500);
        }
    }

    protected function attach(Unit $unit, array $data)
    {
        // Internal relation is stripped by the API resource before serialization.
        $unit->setRelation('_unifiedCatalog', collect($data));
        return $unit;
    }
}
