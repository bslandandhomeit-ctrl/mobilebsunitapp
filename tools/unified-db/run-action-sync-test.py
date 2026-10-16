import importlib.util,subprocess,json
from pathlib import Path
s=importlib.util.spec_from_file_location('stage',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
scratch='unified_action_sync_test'
if m.target("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='"+scratch+"'"):
 raise RuntimeError('Scratch schema already exists; refusing overwrite.')
try:
 m.target('''CREATE DATABASE unified_action_sync_test;
CREATE TABLE unified_action_sync_test.units LIKE units;
CREATE TABLE unified_action_sync_test.unit_actions LIKE unit_actions;
INSERT INTO unified_action_sync_test.units SELECT * FROM units WHERE catalog_state='CURRENT' AND availability_state='AVAILABLE' AND is_saleable=1 ORDER BY id LIMIT 1;
GRANT SELECT,INSERT,UPDATE,DELETE ON unified_action_sync_test.* TO 'unified_unit_action_writer'@'%';''')
 p=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=Path(__file__).with_name('test-unit-action-sync.php').read_text(),text=True,capture_output=True)
 if p.returncode:raise RuntimeError(p.stderr.strip() or 'PHP sync test failed; output withheld.')
 report=json.loads(p.stdout);Path('outputs/unified-db/unit-action-sync-test-result.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
finally:
 m.target("REVOKE ALL PRIVILEGES ON unified_action_sync_test.* FROM 'unified_unit_action_writer'@'%'; DROP DATABASE IF EXISTS unified_action_sync_test;")
