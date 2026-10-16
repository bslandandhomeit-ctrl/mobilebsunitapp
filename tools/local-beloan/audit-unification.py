"""Read-only source audit. Exports aggregate metrics and schema, never customer rows."""
import collections
import datetime
import json
import pathlib
import subprocess

ROOT = pathlib.Path(__file__).resolve().parents[2]
SOURCES = {
    'bsunits': ('bsunits-mobile-local-db-1', 'local-only-bsunits', 'bsunits_backup_20261005', ''),
    'beloan': ('beloan-local-db-1', 'local-only-beloan', 'land_home_v2', 'tb_'),
}

def query(source, sql):
    container, password, database, _ = SOURCES[source]
    return subprocess.check_output([
        'docker', 'exec', '-e', 'MYSQL_PWD=' + password, container,
        'mysql', '-uroot', '-N', '-B', database, '-e', sql,
    ], text=True).splitlines()

report = {'audited_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
          'scope': 'Read-only schema and aggregate property-key audit; candidate matches are not approved merges.',
          'sources': {}}
keys = {}
for source, (_, _, database, prefix) in SOURCES.items():
    schema = [line.split('\t') for line in query(source,
        "SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_KEY "
        "FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='" + database + "' ORDER BY TABLE_NAME,ORDINAL_POSITION")]
    rows = [line.split('\t') for line in query(source,
        f"SELECT p.short_code,t.short_code,u.code FROM {prefix}units u "
        f"JOIN {prefix}unit_types t ON t.id=u.unit_type_id JOIN {prefix}projects p ON p.id=t.project_id")]
    normalized = [tuple(v.strip().casefold() for v in row) for row in rows]
    complete = [key for key in normalized if all(v and v != 'null' for v in key)]
    counter = collections.Counter(complete)
    keys[source] = counter
    counts = {}
    for table in ('projects', 'unit_types', 'units'):
        counts[table] = int(query(source, f'SELECT COUNT(*) FROM {prefix}{table}')[0])
    report['sources'][source] = {'database': database, 'schema': schema,
        'counts': counts, 'joined_unit_rows': len(rows),
        'incomplete_property_keys': len(rows) - len(complete),
        'duplicate_property_key_groups': sum(v > 1 for v in counter.values())}
common = keys['bsunits'].keys() & keys['beloan'].keys()
report['property_candidates'] = {
    'key': '(trimmed case-insensitive project short_code, unit_type short_code, unit code)',
    'matching_key_groups': len(common),
    'unambiguous_one_to_one_groups': sum(keys['bsunits'][k] == keys['beloan'][k] == 1 for k in common),
    'ambiguous_groups': sum(keys['bsunits'][k] != 1 or keys['beloan'][k] != 1 for k in common),
    'bsunits_only_key_groups': len(keys['bsunits'].keys() - keys['beloan'].keys()),
    'beloan_only_key_groups': len(keys['beloan'].keys() - keys['bsunits'].keys()),
}
destination = ROOT / 'outputs' / 'unified-db' / 'source-audit.json'
destination.parent.mkdir(parents=True, exist_ok=True)
destination.write_text(json.dumps(report, indent=2))
print(json.dumps({'sources': {s: {k:v for k,v in d.items() if k != 'schema'} for s,d in report['sources'].items()},
    'property_candidates': report['property_candidates']}, indent=2))
