import importlib.util,json,subprocess
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
DB='unified_request_write_test';USER='unified_request_writer_test';created=False;user_created=False
if m.target('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='+m.literal(DB)):raise RuntimeError('Scratch exists')
try:
 m.target('CREATE DATABASE '+DB);created=True
 for table in ['unit_deposit_requests','unit_contract_requests','unit_contract_request_attachments']:m.target('CREATE TABLE '+DB+'.'+table+' LIKE bsunits_beloan_final.'+table)
 m.target('CREATE TABLE '+DB+'.unit_actions LIKE bsunits_migration_stage.unit_actions;CREATE TABLE '+DB+'.canonical_units LIKE bsunits_beloan_final.units')
 m.target('INSERT INTO '+DB+'.unit_deposit_requests SELECT * FROM bsunits_beloan_final.unit_deposit_requests LIMIT 1;UPDATE '+DB+".unit_deposit_requests SET user_id=1,status='APPROVED',is_selected_payment_option=1")
 m.target('INSERT INTO '+DB+'.canonical_units SELECT u.* FROM bsunits_beloan_final.units u JOIN '+DB+'.unit_deposit_requests d ON d.unit_id=u.id;UPDATE '+DB+'.canonical_units SET current_action_id=NULL;CREATE VIEW '+DB+'.units AS SELECT id,code,current_action_id AS unit_action_id,deleted_at FROM '+DB+'.canonical_units')
 m.target("CREATE USER '"+USER+"'@'%' IDENTIFIED BY 'local-only-request-write-test'");user_created=True
 m.target('GRANT SELECT,INSERT,UPDATE,DELETE ON '+DB+".* TO '"+USER+"'@'%'")
 r=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-contract-request-create.php').read_bytes(),capture_output=True)
 try:d=json.loads(r.stdout)
 except ValueError:raise RuntimeError('Contract controller test failed; sensitive diagnostics withheld')
 if r.returncode or d.get('status')!='PASSED':raise RuntimeError('Contract controller test failed: '+json.dumps(d))
 (HERE.parents[1]/'outputs/unified-db/contract-request-create-test-result.json').write_text(json.dumps(d,indent=2)+'\n');print(json.dumps(d,indent=2))
finally:
 if created:m.target('DROP DATABASE '+DB)
 if user_created:m.target("DROP USER '"+USER+"'@'%'")
