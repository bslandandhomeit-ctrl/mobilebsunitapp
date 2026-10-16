"""Exercise original Beloan authentication provider through disposable unified projections."""
import importlib.util,json,subprocess
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
DB='unified_auth_provider_test';USER='unified_auth_test';created=False;user_created=False
if m.target('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='+m.literal(DB)):raise RuntimeError('Scratch exists; refusing overwrite')
try:
 m.target('CREATE DATABASE '+DB);created=True
 m.target("CREATE USER '"+USER+"'@'%' IDENTIFIED BY 'local-only-auth-test'");user_created=True
 for table in ['branches','actors','login_identities','access_roles','access_permissions','access_user_roles','access_user_permissions']:
  m.target('GRANT SELECT ON bsunits_beloan_final.'+table+" TO '"+USER+"'@'%'")
 m.target("GRANT SELECT ON beloan_migration_stage.tb_accessible TO '"+USER+"'@'%'")
 m.target('GRANT SELECT ON '+DB+".* TO '"+USER+"'@'%'")
 cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='beloan_migration_stage' AND TABLE_NAME='tb_users' ORDER BY ORDINAL_POSITION")
 fields=[]
 for c in cols:
  expr={'id':'a.source_user_id','password':'l.password_hash','remember_token':'NULL'}.get(c,"NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.profile,'$."+c+"')),'null')")
  fields.append(expr+' AS `'+c+'`')
 views={
 'company_branch':'SELECT id,code AS branch_code,name AS branch_name FROM bsunits_beloan_final.branches',
 'accessible':'SELECT * FROM beloan_migration_stage.tb_accessible',
 'users':"SELECT "+','.join(fields)+" FROM bsunits_beloan_final.actors a JOIN bsunits_beloan_final.login_identities l ON l.actor_id=a.id AND l.source_system='beloan' WHERE a.source_system='beloan'",
 'roles':"SELECT source_role_id AS id,JSON_UNQUOTE(JSON_EXTRACT(attributes,'$.role')) AS role,name AS role_name FROM bsunits_beloan_final.access_roles WHERE app_scope='beloan'",
 'permission':"SELECT source_permission_id AS id,name AS code,JSON_UNQUOTE(JSON_EXTRACT(attributes,'$.action_name')) AS action_name,JSON_UNQUOTE(JSON_EXTRACT(attributes,'$.action_type')) AS action_type FROM bsunits_beloan_final.access_permissions WHERE app_scope='beloan'",
 'user_permission':"SELECT a.source_user_id AS user_id,p.source_permission_id AS permission_id FROM bsunits_beloan_final.access_user_permissions u JOIN bsunits_beloan_final.actors a ON a.id=u.actor_id JOIN bsunits_beloan_final.access_permissions p ON p.id=u.permission_id WHERE a.source_system='beloan' AND p.app_scope='beloan'"}
 for name,sql in views.items():m.target('CREATE SQL SECURITY INVOKER VIEW '+DB+'.tb_'+name+' AS '+sql)
 result=subprocess.run(['docker','exec','-i','beloan-local','php'],input=(HERE/'test-unified-auth-provider.php').read_bytes(),capture_output=True)
 try:report=json.loads(result.stdout)
 except ValueError:raise RuntimeError('Provider rehearsal failed; diagnostics withheld to protect login identities. stderr bytes='+str(len(result.stderr)))
 if result.returncode or report.get('status')!='PASSED':raise RuntimeError('Provider rehearsal failed: '+json.dumps(report))
 expected=int(m.target('SELECT COUNT(*) FROM beloan_migration_stage.tb_users u LEFT JOIN beloan_migration_stage.tb_roles r ON r.id=u.role_id WHERE r.id IS NULL')[0])
 if report['missing_role_accounts']!=expected:raise RuntimeError('Unified provider dropped a valid role')
 report['source_missing_roles_preserved']=True
 source_missing=int(m.target("SELECT COUNT(*) FROM beloan_migration_stage.tb_users u LEFT JOIN beloan_migration_stage.tb_company_branch b ON b.id=u.branch_id WHERE (u.role_id NOT IN (1,2) OR u.role_id IS NULL) AND (b.branch_code IS NULL OR b.branch_code='')")[0])
 if source_missing!=report['staff_missing_branch_accounts']:raise RuntimeError('Migrated branch assignment differs from source')
 report['source_missing_branches_preserved']=True
 (HERE.parents[1]/'outputs/unified-db/unified-auth-provider-test-result.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
finally:
 if created:m.target('DROP DATABASE '+DB)
 if user_created:m.target("DROP USER '"+USER+"'@'%'")
