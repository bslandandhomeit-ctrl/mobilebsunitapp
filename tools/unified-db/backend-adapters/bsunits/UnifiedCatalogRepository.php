<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Current shared catalog. Historical transaction amounts are never queried here. */
class UnifiedCatalogRepository
{
    protected function connection()
    {
        if (!config('unified_catalog.enabled')) {
            throw new \LogicException('Unified catalog reads are disabled.');
        }
        config(['database.connections.unified_catalog' => config('unified_catalog.database')]);
        return DB::connection('unified_catalog');
    }

    protected function unitsQuery()
    {
        return $this->connection()->table('units as u')
            ->join('unit_types as t', 't.id', '=', 'u.unit_type_id')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->leftJoin('unit_actions as a', 'a.id', '=', 'u.current_action_id')
            ->where('u.catalog_state', 'CURRENT')
            // Existing Unit->unitType->project relations use withTrashed().
            ->whereNull('u.deleted_at')
            ->where('p.is_published', true)
            ->select('u.id', 'u.code', 'u.list_price', 'u.currency', 'u.specifications',
                'u.is_saleable', 'u.availability_state', 'u.unit_type_id',
                'a.action', 'a.status_action', 't.code as type_code',
                't.name_km as type_name', 't.name_en as type_name_en',
                't.attributes as type_attributes', 'p.id as project_id',
                'p.code as project_code', 'p.name_km as project_name', 'p.name_en as project_name_en');
    }

    protected function unitData($row)
    {
        $attributes = json_decode($row->type_attributes, true) ?: [];
        return [
            'id' => $row->id, 'code' => $row->code, 'price' => $row->list_price,
            'currency' => $row->currency, 'saleable' => (bool) $row->is_saleable,
            'availability_state' => $row->availability_state,
            'availability_status' => $row->action,
            'action' => ['action' => $row->action, 'status_action' => $row->status_action],
            'specifications' => json_decode($row->specifications, true) ?: [],
            'unit_type_id' => $row->unit_type_id,
            'unit_type' => [
                'id' => $row->unit_type_id, 'short_code' => $row->type_code,
                'name' => $row->type_name, 'name_en' => $row->type_name_en,
                'feature_image_url' => $this->featureImage($attributes['feature_image_url'] ?? null),
                'project' => ['id' => $row->project_id, 'short_code' => $row->project_code,
                    'name' => $row->project_name, 'name_en' => $row->project_name_en],
            ],
        ];
    }

    protected function featureImage($path)
    {
        return $path === null || trim($path) === ''
            ? asset('img/no_image_preview.jpg') : asset('storage/'.$path);
    }

    public function unitTypes(array $filters = [])
    {
        $query = $this->connection()->table('unit_types as t')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->whereNull('t.deleted_at')->whereNull('p.deleted_at')->where('p.is_published', true);
        if (!empty($filters['project_id'])) {
            $query->where('t.project_id', $filters['project_id']);
        }
        $page = $query->select('t.id', 't.project_id', 't.code', 't.name_km', 't.name_en', 't.attributes')
            ->orderBy('t.name_km')->orderBy('t.id')
            ->paginate(max(1, min(100, (int) ($filters['per_page'] ?? 20))));
        $page->setCollection($page->getCollection()->map(function ($row) {
            $attributes = json_decode($row->attributes, true) ?: [];
            return ['id' => $row->id, 'project_id' => $row->project_id,
                'short_code' => $row->code, 'name' => $row->name_km, 'name_en' => $row->name_en,
                'feature_image_url' => $this->featureImage($attributes['feature_image_url'] ?? null)];
        }));
        return $page;
    }

    public function units(array $filters = [])
    {
        $query = $this->unitsQuery()->where('u.is_saleable', true);
        foreach (['unit_type_id' => 'u.unit_type_id', 'project_id' => 'p.id'] as $key => $column) {
            if (!empty($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }
        if (!empty($filters['term'])) {
            $query->where('u.code', 'like', '%'.$filters['term'].'%');
        }
        if (!empty($filters['status'])) {
            $query->whereIn('a.action', explode(',', $filters['status']));
        }
        $page = $query->orderBy('u.code')->orderBy('u.id')
            ->paginate(max(1, min(100, (int) ($filters['per_page'] ?? 20))));
        $page->setCollection($page->getCollection()->map(function ($row) {
            return $this->unitData($row);
        }));
        return $page;
    }

    public function unit($id)
    {
        $row = $this->unitsQuery()->where('u.id', $id)->first();
        return $row ? $this->unitData($row) : null;
    }

    public function projects()
    {
        return $this->connection()->table('projects')->whereNull('deleted_at')
            ->where('is_published', true)->orderBy('name_en')->get([
                'id', 'organisation_id', 'code', 'name_km', 'name_en', 'name_zh', 'attributes',
            ])->map(function ($row) {
                $attributes = json_decode($row->attributes, true) ?: [];
                $logo = $attributes['logo_url'] ?? null;
                $plan = $attributes['master_plan_url'] ?? null;
                return ['id' => $row->id, 'company_id' => $row->organisation_id,
                    'short_code' => $row->code, 'name' => $row->name_km,
                    'name_en' => $row->name_en, 'name_zh' => $row->name_zh,
                    'logo_url' => $logo === null ? null : asset('storage/'.$logo),
                    'master_plan_url' => $plan === null ? null : asset('storage/'.$plan),
                    'feature_image_url' => $this->featureImage($attributes['feature_image_url'] ?? null)];
            });
    }
}
