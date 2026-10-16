<?php
namespace App\Services;

/** PHP 5.6 compatible catalog boundary; never substitutes a signed loan price. */
class UnifiedUnitCatalog
{
    private $connection;
    private $enabled;

    public function __construct(\Illuminate\Database\Connection $connection, $enabled = false)
    {
        $this->connection = $connection;
        $this->enabled = (bool) $enabled;
    }

    public static function configured()
    {
        $settings = app('config')->get('unified_catalog');
        if (!$settings || empty($settings['enabled'])) {
            throw new \LogicException('Unified Beloan catalog is disabled.');
        }
        app('config')->set('database.connections.unified_catalog', $settings['database']);
        return new static(app('db')->connection('unified_catalog'), true);
    }

    private function query()
    {
        if (!$this->enabled) {
            throw new \LogicException('Unified Beloan catalog is disabled.');
        }
        if ($this->connection->getTablePrefix() !== '') {
            throw new \LogicException('Unified connection must have an empty table prefix.');
        }
        return $this->connection->table('units as u')
            ->join('unit_types as t', 't.id', '=', 'u.unit_type_id')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->select('u.id', 'u.code', 'u.list_price', 'u.currency', 'u.catalog_state',
                'u.availability_state', 'u.is_saleable', 'u.specifications',
                't.id as unit_type_id', 't.code as type_code', 'p.id as project_id',
                'p.code as project_code', 'p.name_en as project_name');
    }

    private function data($row)
    {
        if (!$row) { return null; }
        $row = (object) $row;
        return array(
            'unified_unit_id' => $row->id, 'code' => $row->code,
            'current_unit_price' => $row->list_price, 'currency' => $row->currency,
            'catalog_state' => $row->catalog_state, 'availability' => $row->availability_state,
            'eligible_for_sale' => (bool) $row->is_saleable,
            'unit_type_id' => $row->unit_type_id, 'type_code' => $row->type_code,
            'project_id' => $row->project_id, 'project_code' => $row->project_code,
            'project_name' => $row->project_name,
            'specifications' => json_decode($row->specifications, true)
        );
    }

    public function findByLegacyUnitId($id)
    {
        if (!preg_match('/^[1-9][0-9]*$/D', (string) $id)) {
            throw new \InvalidArgumentException('A positive Beloan unit ID is required.');
        }
        $row = $this->query()->join('beloan_unit_aliases as m', 'm.target_id', '=', 'u.id')
            ->where('m.source_id', (string) $id)->first();
        return $this->data($row);
    }

    public function currentUnits($limit = 20, $offset = 0, $availableOnly = false)
    {
        $query = $this->query()->where('u.catalog_state', 'CURRENT')
            ->whereNull('u.deleted_at')->where('p.is_published', 1)->where('u.is_saleable', 1);
        if ($availableOnly) { $query->where('u.availability_state', 'AVAILABLE'); }
        $rows = $query->orderBy('u.code')->orderBy('u.id')
            ->take(max(1, min(100, (int) $limit)))->skip(max(0, (int) $offset))->get();
        return array_map(array($this, 'data'), $rows);
    }
}
