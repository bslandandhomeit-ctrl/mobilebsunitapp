import importlib.util,subprocess,json
from pathlib import Path
s=importlib.util.spec_from_file_location('s',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
db='unified_customer_edit_test'
if m.target("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='"+db+"'"):raise RuntimeError('Scratch exists; refusing overwrite.')
try:
 m.target('CREATE DATABASE '+db)
 for t in ['customer_accounts','customer_account_evidence','customer_account_change_audit','customer_account_members','persons','sales']:m.target(f'CREATE TABLE {db}.{t} LIKE {t}')
 m.target(f"CREATE TABLE {db}.beloan_actor_aliases(source_user_id INT PRIMARY KEY,actor_id INT);INSERT INTO {db}.beloan_actor_aliases VALUES(1,1);INSERT INTO {db}.customer_accounts(id,display_name,account_type,source_system,source_id) VALUES(1,'Test Account','SINGLE','test',1); GRANT SELECT,INSERT,UPDATE ON {db}.* TO 'unified_beloan_customer_writer'@'%'")
 p=subprocess.run(['docker','exec','-i','beloan-local','php'],input=Path(__file__).with_name('test-customer-editor.php').read_text(),text=True,capture_output=True)
 if p.returncode:raise RuntimeError(p.stderr.strip() or 'Editor test failed.')
 if not p.stdout.lstrip().startswith('{'):raise RuntimeError(p.stdout[:500]+' '+p.stderr[:200])
 report=json.loads(p.stdout);Path('outputs/unified-db/customer-editor-test-result.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
finally:m.target(f"REVOKE ALL PRIVILEGES ON {db}.* FROM 'unified_beloan_customer_writer'@'%'; DROP DATABASE IF EXISTS {db}")
