import importlib.util,json,subprocess
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
DB='unified_passport_test';USER=DB;created=False;user_created=False
if m.target('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='+m.literal(DB)):raise RuntimeError('Scratch exists')
try:
 m.target('CREATE DATABASE '+DB);created=True
 for table in ['actors','login_identities']:m.target('CREATE TABLE '+DB+'.'+table+' LIKE bsunits_beloan_final.'+table)
 for table in ['oauth_clients','oauth_access_tokens','oauth_refresh_tokens','oauth_auth_codes','oauth_personal_access_clients','roles','model_has_roles']:m.target('CREATE TABLE '+DB+'.'+table+' LIKE bsunits_migration_stage.'+table)
 m.target('CREATE TABLE '+DB+'.unified_request_notifications LIKE bsunits_beloan_final.unified_request_notifications;GRANT SELECT,UPDATE(read_at,updated_at) ON '+DB+".unified_request_notifications TO 'unified_notification_reader'@'%'")
 m.target('INSERT INTO '+DB+".roles SELECT * FROM bsunits_migration_stage.roles WHERE name='agent'")
 m.target('CREATE VIEW '+DB+".users AS SELECT a.source_user_id AS id,a.display_name AS name,l.login_identifier AS phone_number,l.password_hash AS password,l.source_enabled AS active,JSON_UNQUOTE(JSON_EXTRACT(a.profile,'$.verified')) AS verified FROM "+DB+'.actors a JOIN '+DB+".login_identities l ON l.actor_id=a.id AND l.source_system='bsunits' WHERE a.source_system='bsunits'")
 m.target("CREATE USER '"+USER+"'@'%' IDENTIFIED BY 'local-only-passport-test'");user_created=True;m.target('GRANT SELECT,INSERT,UPDATE,DELETE ON '+DB+".* TO '"+USER+"'@'%'")
 r=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-passport-token-lifecycle.php').read_bytes(),capture_output=True)
 try:d=json.loads(r.stdout)
 except ValueError:raise RuntimeError('Passport test failed; token/credential diagnostics withheld')
 if r.returncode or d.get('status')!='PASSED':raise RuntimeError('Passport test failed: '+json.dumps(d))
 (HERE.parents[1]/'outputs/unified-db/passport-token-test-result.json').write_text(json.dumps(d,indent=2)+'\n');print(json.dumps(d,indent=2))
finally:
 if created:m.target('DROP DATABASE '+DB)
 if user_created:m.target("DROP USER '"+USER+"'@'%'")
