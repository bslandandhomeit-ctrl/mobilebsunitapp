"""Stage property snapshots and candidate matches. Never merges or edits source rows."""
import collections
import datetime
import decimal
import hashlib
import json
import pathlib
import re
import subprocess
import uuid

ROOT = pathlib.Path(__file__).resolve().parents[2]
TARGET_CONTAINER = 'bsunits-beloan-unified-local-db-1'
TARGET_DATABASE = 'bsunits_beloan_final'
SOURCES = {
    'bsunits': ('bsunits-mobile-local-db-1', 'local-only-bsunits', 'bsunits_backup_20261005', ''),
    'beloan': ('beloan-local-db-1', 'local-only-beloan', 'land_home_v2', 'tb_'),
}

def mysql(container, password, database, sql):
    result = subprocess.run([
        'docker', 'exec', '-i', '-e', 'MYSQL_PWD=' + password, container,
        'mysql', '-uroot', '--default-character-set=utf8mb4', '-N', '-B', '--raw', database,
    ], input=sql, text=True, capture_output=True)
    if result.returncode:
        # Do not print database error payloads, which can contain source records.
        error = re.search(r'ERROR (\d+) \(([^)]+)\)', result.stderr)
        code = ' '.join(error.groups()) if error else 'client error'
        raise RuntimeError('Database command failed in ' + container + ': ' + code + '; source rows withheld.')
    return result.stdout.splitlines()

def target(sql):
    return mysql(TARGET_CONTAINER, 'local-only-unified', TARGET_DATABASE, sql)

def literal(value):
    if value is None:
        return 'NULL'
    if not isinstance(value, str):
        value = json.dumps(value, ensure_ascii=False, separators=(',', ':'))
    return "CONVERT(X'" + value.encode('utf8').hex() + "' USING utf8mb4) COLLATE utf8mb4_unicode_ci"

def candidate_key(parts):
    normalized = tuple(str(v).strip().casefold() if v is not None else '' for v in parts)
    if not all(normalized):
        return None
    return hashlib.sha256(json.dumps(normalized, ensure_ascii=False).encode()).hexdigest()

def comparable(value, numeric=False):
    if value is None:
        return None
    if numeric:
        try:
            return format(decimal.Decimal(str(value)).normalize(), 'f')
        except decimal.InvalidOperation:
            pass
    return str(value).strip()

def classify(members):
    by_source = collections.Counter(m['source'] for m in members)
    if any(m['incomplete'] for m in members):
        return 'INCOMPLETE_KEY'
    if any(n > 1 for n in by_source.values()):
        return 'AMBIGUOUS'
    return 'SOURCE_ONLY' if len(by_source) == 1 else 'UNAMBIGUOUS'

def conflicts(members, entity):
    fields = {
        'project': {'name_en': ('name_en', 'dealer_en', False),
                    'published': ('is_published', 'published', True)},
        'unit_type': {k: (k,k,True) for k in ('is_contractable','annual_management_fee',
                      'contract_transfer_fee','management_fee_per_square')},
        'unit': {k: (k,k,True) for k in ('price','saleable','land_area','building_area',
                 'total_area_size','bedroom','bathroom','living_room','kitchen','swimming_pool')},
    }[entity]
    if entity == 'unit':
        fields['floor'] = ('floor','floor',False)
        fields['deleted_at'] = ('deleted_at','deleted_at',False)
    result = {}
    for canonical, (bs, be, numeric) in fields.items():
        values = [{
            'source': m['source'], 'source_id': m['id'],
            'value': m['payload'].get(bs if m['source'] == 'bsunits' else be),
        } for m in members]
        if len({comparable(v['value'], numeric) for v in values}) > 1:
            result[canonical] = values
    return result

def raw_snapshots(source, table):
    container, password, database, _ = SOURCES[source]
    columns = mysql(container,password,database,
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
        "WHERE TABLE_SCHEMA='" + database + "' AND TABLE_NAME='" + table + "' ORDER BY ORDINAL_POSITION;")
    if not columns or any(not c.replace('_','').isalnum() for c in columns):
        raise RuntimeError('Unexpected schema for ' + table)
    expression = ','.join("'" + c + "',`" + c + '`' for c in columns)
    data = mysql(container,password,database,f'SELECT JSON_OBJECT({expression}) FROM `{table}` ORDER BY id;')
    return [(json.loads(line, parse_float=str),line) for line in data]

def snapshots(source, table):
    return [record for record,_ in raw_snapshots(source,table)]

def run():
    target((pathlib.Path(__file__).parent / 'schema.sql').read_text())
    run_id = str(uuid.uuid4())
    target('INSERT INTO migration_runs(id,status,source_databases) VALUES (' + literal(run_id) +
           ",'LOADING'," + literal({s:v[2] for s,v in SOURCES.items()}) + ');')
    groups = collections.defaultdict(list)
    counts = collections.Counter()
    rows = []
    try:
        for source, (_, _, _, prefix) in SOURCES.items():
            projects = snapshots(source, prefix+'projects')
            types = snapshots(source, prefix+'unit_types')
            units = snapshots(source, prefix+'units')
            project_by_id = {p['id']:p for p in projects}
            type_by_id = {t['id']:t for t in types}
            for entity, records, table in [('project',projects,prefix+'projects'),
                                           ('unit_type',types,prefix+'unit_types'),
                                           ('unit',units,prefix+'units')]:
                for record in records:
                    if entity == 'project':
                        parts = [record.get('short_code')]
                    elif entity == 'unit_type':
                        parent = project_by_id.get(record.get('project_id'), {})
                        parts = [parent.get('short_code'),record.get('short_code')]
                    else:
                        unit_type = type_by_id.get(record.get('unit_type_id'), {})
                        parent = project_by_id.get(unit_type.get('project_id'), {})
                        parts = [parent.get('short_code'),unit_type.get('short_code'),record.get('code')]
                    key = candidate_key(parts)
                    incomplete = key is None
                    if incomplete:
                        key = hashlib.sha256(f'{source}:{table}:{record["id"]}'.encode()).hexdigest()
                    member = {'source':source,'table':table,'id':str(record['id']),
                              'payload':record,'incomplete':incomplete}
                    groups[(entity,key)].append(member)
                    payload = json.dumps(record,ensure_ascii=False,separators=(',',':'))
                    rows.append('(' + ','.join(literal(v) for v in [run_id,source,table,
                        str(record['id']),entity,key,payload,hashlib.sha256(payload.encode()).hexdigest()]) + ')')
                    counts[source+':'+entity] += 1
        # Commit all source rows and candidate groups atomically for this run.
        statements = ['START TRANSACTION;']
        for i in range(0,len(rows),200):
            statements.append('INSERT INTO migration_source_records VALUES '+','.join(rows[i:i+200])+';')
        group_rows = []
        summary = collections.Counter()
        for (entity,key), members in groups.items():
            classification = classify(members)
            differences = conflicts(members,entity)
            summary[entity+':'+classification] += 1
            if differences:
                summary[entity+':groups_with_compared_field_conflicts'] += 1
            refs = [{k:m[k] for k in ('source','table','id')} for m in members]
            group_rows.append('('+','.join(literal(v) for v in [run_id,entity,key,classification,
                str(len(members)),refs,differences])+')')
        for i in range(0,len(group_rows),200):
            statements.append('INSERT INTO migration_match_groups '
                '(run_id,entity_type,candidate_key,classification,member_count,members,field_conflicts) VALUES '
                +','.join(group_rows[i:i+200])+';')
        report = {'run_id':run_id,'staged_records':dict(counts),'candidate_groups':dict(summary),
                  'scope':'Properties only. All merge decisions pending; zero canonical business rows migrated.',
                  'built_at':datetime.datetime.now(datetime.timezone.utc).isoformat()}
        statements.append("UPDATE migration_runs SET status='READY_FOR_REVIEW',completed_at=CURRENT_TIMESTAMP,summary="
            +literal(report)+' WHERE id='+literal(run_id)+';')
        statements.append('COMMIT;')
        target('\n'.join(statements))
        actual = int(target('SELECT COUNT(*) FROM migration_source_records WHERE run_id='+literal(run_id)+';')[0])
        grouped = int(target('SELECT SUM(member_count) FROM migration_match_groups WHERE run_id='+literal(run_id)+';')[0])
        if actual != len(rows) or grouped != actual:
            raise RuntimeError('Staging reconciliation failed.')
        report['verified_source_record_count'] = actual
        report['verified_group_member_count'] = grouped
        output = ROOT/'outputs'/'unified-db'/'property-staging-result.json'
        output.write_text(json.dumps(report,indent=2))
        print(json.dumps(report,indent=2))
    except Exception:
        target("UPDATE migration_runs SET status='FAILED' WHERE id="+literal(run_id)+';')
        raise

if __name__ == '__main__':
    run()
