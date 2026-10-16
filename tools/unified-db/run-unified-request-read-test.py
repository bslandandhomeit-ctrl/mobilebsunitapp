import importlib.util,json,subprocess
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
DB='unified_request_read_test';USER='unified_request_test';created=False;user_created=False
if m.target('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='+m.literal(DB)):raise RuntimeError('Scratch exists; refusing overwrite')
try:
 m.target('CREATE DATABASE '+DB);created=True;m.target("CREATE USER '"+USER+"'@'%' IDENTIFIED BY 'local-only-request-test'");user_created=True
 tables=['unit_hold_requests','unit_deposit_requests','unit_contract_requests','unit_contract_request_attachments']
 for t in tables+['actors','access_roles','access_user_roles']:m.target('GRANT SELECT ON bsunits_beloan_final.'+t+" TO '"+USER+"'@'%'")
 m.target('GRANT SELECT ON '+DB+".* TO '"+USER+"'@'%'")
 for t in tables:m.target('CREATE SQL SECURITY INVOKER VIEW '+DB+'.'+t+' AS SELECT * FROM bsunits_beloan_final.'+t)
 cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bsunits_migration_stage' AND TABLE_NAME='users' ORDER BY ORDINAL_POSITION")
 fields=[('source_user_id' if c=='id' else 'NULL' if c in ['password','remember_token'] else "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(profile,'$."+c+"')),'null')")+' AS `'+c+'`' for c in cols]
 m.target('CREATE SQL SECURITY INVOKER VIEW '+DB+'.users AS SELECT '+','.join(fields)+" FROM bsunits_beloan_final.actors WHERE source_system='bsunits'")
 role_columns=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bsunits_migration_stage' AND TABLE_NAME='roles' ORDER BY ORDINAL_POSITION")
 role_fields=[('source_role_id' if c=='id' else 'name' if c=='name' else "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(attributes,'$."+c+"')),'null')")+' AS `'+c+'`' for c in role_columns]
 m.target('CREATE SQL SECURITY INVOKER VIEW '+DB+'.roles AS SELECT '+','.join(role_fields)+" FROM bsunits_beloan_final.access_roles WHERE app_scope='bsunits'")
 m.target('CREATE SQL SECURITY INVOKER VIEW '+DB+".model_has_roles AS SELECT r.source_role_id AS role_id,'App\\\\User' COLLATE utf8mb4_unicode_ci AS model_type,a.source_user_id AS model_id FROM bsunits_beloan_final.access_user_roles u JOIN bsunits_beloan_final.actors a ON a.id=u.actor_id JOIN bsunits_beloan_final.access_roles r ON r.id=u.role_id WHERE a.source_system='bsunits' AND r.app_scope='bsunits'")
 roles=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-bs-role-middleware.php').read_bytes(),capture_output=True)
 try:role_report=json.loads(roles.stdout)
 except ValueError:raise RuntimeError('BS role middleware failed; sensitive diagnostics withheld')
 if roles.returncode or role_report.get('status')!='PASSED':raise RuntimeError('BS role middleware failed: '+json.dumps(role_report))
 (HERE.parents[1]/'outputs/unified-db/bs-role-middleware-test-result.json').write_text(json.dumps(role_report,indent=2)+'\n');print(json.dumps(role_report,indent=2))
 r=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-unified-request-read.php').read_bytes(),capture_output=True)
 try:d=json.loads(r.stdout)
 except ValueError:raise RuntimeError('Request read failed; sensitive output withheld')
 if r.returncode or d.get('status')!='PASSED':raise RuntimeError('Request read failed: '+json.dumps(d))
 (HERE.parents[1]/'outputs/unified-db/unified-request-read-test-result.json').write_text(json.dumps(d,indent=2)+'\n');print(json.dumps(d,indent=2))
finally:
 if created:m.target('DROP DATABASE '+DB)
 if user_created:m.target("DROP USER '"+USER+"'@'%'")
