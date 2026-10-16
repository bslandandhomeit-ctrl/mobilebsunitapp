"""Read projections over shared masters; never create duplicate master records."""
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging', HERE / 'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)


def run():
    specs = ['street','street_corner','street_size','floor','land_size_width','land_size_length',
             'land_area','building_size_width','building_size_length','building_area','gross_area',
             'total_area_size','living_room','kitchen','bedroom','bathroom','swimming_pool']
    fields = ["NULLIF(JSON_UNQUOTE(JSON_EXTRACT(specifications,'$." + field + "')),'null') AS `" + field + '`' for field in specs]
    views = {
        'beloan_compat_units': 'SELECT id,unit_type_id,code,list_price AS price,is_saleable AS saleable,current_action_id AS unit_action_id,availability_state,catalog_state,deleted_at,' + ','.join(fields) + ' FROM units',
        'beloan_compat_unit_types': 'SELECT id,project_id,code AS short_code,name_km AS name,name_en,name_zh AS name_cn,deleted_at FROM unit_types',
        'beloan_compat_projects': 'SELECT p.id,h.source_project_id,h.bank_account,h.sale_representative_id,p.organisation_id,l.branch_id AS accounting_branch_id,p.code AS short_code,p.name_km AS dealer,p.name_en AS dealer_en,p.name_zh AS dealer_cn,p.is_published AS published,p.deleted_at FROM projects p LEFT JOIN beloan_project_reference_links h ON h.project_id=p.id LEFT JOIN (SELECT project_id,MIN(branch_id) AS branch_id FROM project_accounting_branch_links GROUP BY project_id HAVING COUNT(DISTINCT branch_id)=1) l ON l.project_id=p.id',
        'beloan_compat_company_branch': 'SELECT id,organisation_id,code AS branch_code,name AS branch_name,organisation_mapping_state FROM branches',
        'beloan_compat_clients': 'SELECT id,account_number AS cus_acc,display_name AS client_name,account_type AS account_cbc_type FROM customer_accounts',
    }
    expected = dict(zip(views, ['units','unit_types','projects','branches','customer_accounts']))
    results = {}
    for name, definition in views.items():
        kind = s.target('SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=' + s.literal(name) + ';')
        if kind and kind != ['VIEW']:
            raise RuntimeError('Projection name occupied by a physical table')
        s.target('CREATE OR REPLACE SQL SECURITY INVOKER VIEW `' + name + '` AS ' + definition + ';')
        counts = s.target('SELECT (SELECT COUNT(*) FROM `' + name + '`),(SELECT COUNT(*) FROM `' + expected[name] + '`);')[0].split('\t')
        if counts[0] != counts[1]:
            raise RuntimeError('Master projection duplicated or dropped rows')
        results[name] = {'canonical_table':expected[name], 'rows':int(counts[0]), 'one_row_per_master':True}
    checks = {
        'unit_price_differences': 'SELECT COUNT(*) FROM beloan_compat_units v JOIN units u ON u.id=v.id WHERE NOT(v.price <=> u.list_price);',
        'unit_availability_differences': 'SELECT COUNT(*) FROM beloan_compat_units v JOIN units u ON u.id=v.id WHERE NOT(v.availability_state <=> u.availability_state);',
        'project_organisation_differences': 'SELECT COUNT(*) FROM beloan_compat_projects v JOIN projects p ON p.id=v.id WHERE NOT(v.organisation_id <=> p.organisation_id);',
        'branch_identity_differences': 'SELECT COUNT(*) FROM beloan_compat_company_branch v JOIN branches b ON b.id=v.id WHERE NOT(v.branch_code <=> b.code);',
        'project_accounting_branch_differences': 'SELECT COUNT(*) FROM beloan_compat_projects v LEFT JOIN (SELECT project_id,MIN(branch_id) AS branch_id FROM project_accounting_branch_links GROUP BY project_id HAVING COUNT(DISTINCT branch_id)=1) l ON l.project_id=v.id WHERE NOT(v.accounting_branch_id <=> l.branch_id);',
    }
    checks = {key:int(s.target(sql)[0]) for key, sql in checks.items()}
    if any(checks.values()):
        raise RuntimeError('Master projection authority verification failed')
    report = {'status':'READ_PROJECTIONS_VERIFIED','views':results,'checks':checks,
              'limits':'Common read fields only; not a full write compatibility layer. No fake legacy unit status or project company_id: availability, organisation and accounting branch remain explicit. Legacy commission/runtime fields and project branch links need targeted adapters. Original customer full-form adapter remains active and is not replaced by the minimal client projection. No app connection switch.'}
    (s.ROOT/'outputs/unified-db/shared-read-projection-result.json').write_text(json.dumps(report,indent=2)+'\n')
    print('Five shared-master read projections verified; prices/availability preserved and company/branch separate.')


if __name__ == '__main__':
    run()
