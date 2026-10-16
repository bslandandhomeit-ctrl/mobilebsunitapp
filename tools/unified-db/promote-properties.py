"""Populate the canonical property catalog; preserve finance and source-only units.

One BS Units master wins for current catalog values. Exact unique hierarchy/code
matches become source aliases. Ambiguous Beloan IDs remain unmapped for review.
Availability is UNVERIFIED until request and loan state reconciliation completes.
"""
import collections
import decimal
import importlib.util
import json
import pathlib
import uuid

HERE = pathlib.Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
staging = importlib.util.module_from_spec(spec)
spec.loader.exec_module(staging)
FIELDS = ('street','street_corner','street_size','floor','land_size_width','land_size_length',
          'land_area','building_size_width','building_size_length','building_area','gross_area',
          'total_area_size','living_room','kitchen','bedroom','bathroom','swimming_pool',
          'block','unit_zone','construction_overall_progress')

def snapshots(database,table):
    columns = staging.mysql(staging.TARGET_CONTAINER,'local-only-unified',database,
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='
        +staging.literal(database)+' AND TABLE_NAME='+staging.literal(table)+' ORDER BY ORDINAL_POSITION;')
    expression = ','.join("'"+c+"',`"+c+'`' for c in columns)
    return [json.loads(row,parse_float=str) for row in staging.mysql(staging.TARGET_CONTAINER,
        'local-only-unified',database,'SELECT JSON_OBJECT('+expression+') FROM `'+table+'` ORDER BY id;')]

def natural_key(*parts):
    return tuple(str(part).strip().casefold() if part is not None else '' for part in parts)

def unique_matches(source,target):
    """Return only keys having exactly one row in each source, never a first-row guess."""
    a,b = collections.defaultdict(list),collections.defaultdict(list)
    for key,record in source:
        a[key].append(record)
    for key,record in target:
        b[key].append(record)
    matches = [(a[k][0],b[k][0]) for k in sorted(a.keys() & b.keys()) if all(k) and len(a[k])==len(b[k])==1]
    return matches,a,b

def insert(table,columns,rows):
    for offset in range(0,len(rows),200):
        yield 'INSERT INTO `'+table+'` ('+','.join('`'+c+'`' for c in columns)+') VALUES '+','.join(
            '('+','.join(staging.literal(v) for v in row)+')' for row in rows[offset:offset+200])+';'

def main():
    for filename in ('bsunits-stage-result.json','finance-stage-result.json'):
        if json.loads((staging.ROOT/'outputs'/'unified-db'/filename).read_text())['status']!='VERIFIED':
            raise RuntimeError('Both raw snapshots must pass verification before property promotion.')
    occupied = staging.target('SELECT (SELECT COUNT(*) FROM organisations)+(SELECT COUNT(*) FROM projects)'
        '+(SELECT COUNT(*) FROM unit_types)+(SELECT COUNT(*) FROM units)+(SELECT COUNT(*) FROM source_record_map);')[0]
    if occupied!='0':
        raise RuntimeError('Canonical property tables are occupied; refusing a repeat promotion.')
    staging.target('ALTER TABLE organisations ADD COLUMN IF NOT EXISTS attributes JSON NULL;'
        'ALTER TABLE projects ADD COLUMN IF NOT EXISTS attributes JSON NULL;'
        "ALTER TABLE units ADD COLUMN IF NOT EXISTS catalog_state ENUM('CURRENT','LEGACY_ONLY') NOT NULL DEFAULT 'CURRENT';"
        "ALTER TABLE units ADD COLUMN IF NOT EXISTS availability_state ENUM('UNVERIFIED','AVAILABLE','RESERVED','SOLD','UNAVAILABLE') NOT NULL DEFAULT 'UNVERIFIED';"
        "ALTER TABLE source_record_map ADD COLUMN IF NOT EXISTS mapping_basis VARCHAR(100) NOT NULL DEFAULT 'SOURCE_ID';")
    bsdb,bedb = 'bsunits_migration_stage','beloan_migration_stage'
    companies = snapshots(bsdb,'companies')
    bsprojects,betprojects = snapshots(bsdb,'projects'),snapshots(bedb,'tb_projects')
    bstypes,bettypes = snapshots(bsdb,'unit_types'),snapshots(bedb,'tb_unit_types')
    bsunits,betunits = snapshots(bsdb,'units'),snapshots(bedb,'tb_units')
    bsp = {p['id']:p for p in bsprojects}
    bep = {p['id']:p for p in betprojects}
    bst = {t['id']:t for t in bstypes}
    bet = {t['id']:t for t in bettypes}
    def project_key(p): return natural_key(p.get('short_code'))
    def type_key(t,projects): return natural_key(projects[t['project_id']]['short_code'],t['short_code'])
    def unit_key(u,types,projects):
        t = types[u['unit_type_id']]
        return natural_key(projects[t['project_id']]['short_code'],t['short_code'],u['code'])
    project_matches,_,_ = unique_matches([(project_key(p),p) for p in bsprojects],[(project_key(p),p) for p in betprojects])
    type_matches,_,_ = unique_matches([(type_key(t,bsp),t) for t in bstypes],[(type_key(t,bep),t) for t in bettypes])
    unit_matches,bskeys,bekeys = unique_matches([(unit_key(u,bst,bsp),u) for u in bsunits],
        [(unit_key(u,bet,bep),u) for u in betunits])
    be_type_map = {b['id']:a['id'] for a,b in type_matches}
    run_id = str(uuid.uuid4())
    maps = []
    def mapping(system,table,source_id,entity,target_id,basis):
        maps.append([system,table,str(source_id),entity,str(target_id),run_id,basis])
    company_rows = [[str(c['id']),c.get('name_km'),c.get('name_en'),c.get('name_zh'),c.get('tax_no'),c] for c in companies]
    for c in companies: mapping('bsunits','companies',c['id'],'organisation',c['id'],'BS_UNITS_MASTER')
    project_rows = [[str(p['id']),str(p['company_id']),p['short_code'],p.get('name'),p.get('name_en'),
        p.get('name_zh'),str(p['is_published']),p.get('deleted_at'),p] for p in bsprojects]
    for p in bsprojects: mapping('bsunits','projects',p['id'],'project',p['id'],'BS_UNITS_MASTER')
    for a,b in project_matches: mapping('beloan','tb_projects',b['id'],'project',a['id'],'UNIQUE_PROJECT_CODE')
    type_rows = [[str(t['id']),str(t['project_id']),t['short_code'],t.get('name'),t.get('name_en'),
        t.get('name_zh'),t,t.get('deleted_at')] for t in bstypes]
    for t in bstypes: mapping('bsunits','unit_types',t['id'],'unit_type',t['id'],'BS_UNITS_MASTER')
    for a,b in type_matches: mapping('beloan','tb_unit_types',b['id'],'unit_type',a['id'],'UNIQUE_PROJECT_TYPE_CODE')
    units = []
    for u in bsunits:
        price = decimal.Decimal(str(u['price']))
        if price<0 or price.quantize(decimal.Decimal('.01'))!=price:
            raise RuntimeError('BS Units price cannot be imported losslessly at two decimal places.')
        units.append([str(u['id']),str(u['unit_type_id']),u['code'],str(price),None,'0',
            {k:u.get(k) for k in FIELDS},u.get('deleted_at'),'CURRENT','UNVERIFIED'])
        mapping('bsunits','units',u['id'],'unit',u['id'],'BS_UNITS_MASTER')
    for a,b in unit_matches: mapping('beloan','tb_units',b['id'],'unit',a['id'],'UNIQUE_PROJECT_TYPE_UNIT_CODE')
    next_id = max(u['id'] for u in bsunits)+1
    legacy_count = 0
    unresolved = []
    for key,records in sorted(bekeys.items()):
        if key in bskeys:
            if len(records)!=1 or len(bskeys[key])!=1:
                unresolved.extend(str(u['id']) for u in records)
            continue
        if not all(key) or len(records)!=1 or records[0]['unit_type_id'] not in be_type_map:
            unresolved.extend(str(u['id']) for u in records)
            continue
        u = records[0]
        units.append([str(next_id),str(be_type_map[u['unit_type_id']]),u['code'],None,None,'0',
            {k:u.get(k) for k in FIELDS},u.get('deleted_at'),'LEGACY_ONLY','UNVERIFIED'])
        mapping('beloan','tb_units',u['id'],'unit',next_id,'BELOAN_ONLY_PRESERVED')
        legacy_count += 1
        next_id += 1
    staging.target('INSERT INTO migration_runs(id,status,source_databases,domain_scope) VALUES ('
        +staging.literal(run_id)+",'LOADING',"+staging.literal({'bsunits':bsdb,'beloan':bedb})+",'PROPERTY_PROMOTION');")
    statements = ['START TRANSACTION;']
    statements.extend(insert('organisations',['id','name_km','name_en','name_zh','tax_number','attributes'],company_rows))
    statements.extend(insert('projects',['id','organisation_id','code','name_km','name_en','name_zh','is_published','deleted_at','attributes'],project_rows))
    statements.extend(insert('unit_types',['id','project_id','code','name_km','name_en','name_zh','attributes','deleted_at'],type_rows))
    statements.extend(insert('units',['id','unit_type_id','code','list_price','currency','is_saleable','specifications',
                                    'deleted_at','catalog_state','availability_state'],units))
    statements.extend(insert('source_record_map',['source_system','source_table','source_id','entity_type','target_id','run_id','mapping_basis'],maps))
    report = {'run_id':run_id,'organisations':len(company_rows),'projects':len(project_rows),'unit_types':len(type_rows),
        'current_bsunits_units':len(bsunits),'preserved_beloan_only_units':legacy_count,'canonical_units':len(units),
        'beloan_shared_unit_aliases':len(unit_matches),'source_mapping_rows':len(maps),
        'unresolved_beloan_unit_ids':unresolved,
        'scope':'Canonical property rehearsal only; availability unverified, apps not switched. No loan/sale/contract amounts changed.'}
    statements.append("UPDATE migration_runs SET status='READY_FOR_REVIEW',completed_at=CURRENT_TIMESTAMP,summary="
        +staging.literal(report)+' WHERE id='+staging.literal(run_id)+';')
    statements.append('COMMIT;')
    try:
        staging.target('\n'.join(statements))
    except Exception:
        staging.target("UPDATE migration_runs SET status='FAILED' WHERE id="+staging.literal(run_id)+';')
        raise
    # Check record mappings, parent FKs and every authoritative list price in SQL.
    price_mismatches = int(staging.target('SELECT COUNT(*) FROM units u JOIN bsunits_migration_stage.units s '
        "ON s.id=u.id WHERE u.catalog_state='CURRENT' AND NOT(u.list_price <=> CAST(s.price AS DECIMAL(18,2))); ")[0])
    if price_mismatches:
        raise RuntimeError('Canonical unit price verification failed; do not connect apps.')
    actual_units = int(staging.target('SELECT COUNT(*) FROM units;')[0])
    actual_maps = int(staging.target('SELECT COUNT(*) FROM source_record_map WHERE run_id='+staging.literal(run_id)+';')[0])
    if actual_units!=len(units) or actual_maps!=len(maps):
        raise RuntimeError('Canonical property count verification failed.')
    report.update({'verified_price_mismatches':price_mismatches,'verified_unit_count':actual_units,
                   'verified_mapping_count':actual_maps})
    (staging.ROOT/'outputs'/'unified-db'/'property-promotion-result.json').write_text(json.dumps(report,indent=2))
    print(json.dumps({k:v for k,v in report.items() if k!='unresolved_beloan_unit_ids'},indent=2))
    print('Unresolved ambiguous Beloan unit rows: '+str(len(unresolved)))

if __name__ == '__main__':
    main()
