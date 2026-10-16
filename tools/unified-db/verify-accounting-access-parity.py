"""Read-only parity of actual Beloan permission assignments in the unified database."""
import importlib.util,json
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
def scalar(sql):return int(m.target(sql)[0])
checks={
'missing_source_assignments':scalar("""SELECT COUNT(*) FROM beloan_migration_stage.tb_user_permission s JOIN actor_source_map a ON a.source_system='beloan' AND a.source_user_id=s.user_id JOIN access_permissions p ON p.app_scope='beloan' AND p.source_permission_id=s.permission_id LEFT JOIN access_user_permissions u ON u.actor_id=a.actor_id AND u.permission_id=p.id WHERE u.actor_id IS NULL"""),
'extra_unified_assignments':scalar("""SELECT COUNT(*) FROM access_user_permissions u JOIN actors a ON a.id=u.actor_id JOIN access_permissions p ON p.id=u.permission_id LEFT JOIN beloan_migration_stage.tb_user_permission s ON s.user_id=a.source_user_id AND s.permission_id=p.source_permission_id WHERE a.source_system='beloan' AND (p.app_scope<>'beloan' OR s.user_id IS NULL)"""),
'permission_action_or_code_changes':scalar("""SELECT COUNT(*) FROM access_permissions p JOIN beloan_migration_stage.tb_permission s ON s.id=p.source_permission_id WHERE p.app_scope='beloan' AND (NOT(BINARY JSON_UNQUOTE(JSON_EXTRACT(p.attributes,'$.code')) <=> BINARY s.code) OR NOT(BINARY JSON_UNQUOTE(JSON_EXTRACT(p.attributes,'$.action_name')) <=> BINARY s.action_name))"""),
'missing_user_role_assignments':scalar("""SELECT COUNT(*) FROM beloan_migration_stage.tb_users s JOIN actor_source_map a ON a.source_system='beloan' AND a.source_user_id=s.id JOIN access_roles r ON r.app_scope='beloan' AND r.source_role_id=s.role_id LEFT JOIN access_user_roles u ON u.actor_id=a.actor_id AND u.role_id=r.id WHERE u.actor_id IS NULL"""),
'cross_application_role_links':scalar("""SELECT COUNT(*) FROM access_user_roles u JOIN actors a ON a.id=u.actor_id JOIN access_roles r ON r.id=u.role_id WHERE a.source_system<>r.app_scope"""),
'cross_application_permission_links':scalar("""SELECT COUNT(*) FROM access_user_permissions u JOIN actors a ON a.id=u.actor_id JOIN access_permissions p ON p.id=u.permission_id WHERE a.source_system<>p.app_scope"""),
}
if any(checks.values()):raise RuntimeError('Access parity mismatch: '+json.dumps(checks))
rows=m.target("""SELECT a.source_user_id,p.name,JSON_UNQUOTE(JSON_EXTRACT(p.attributes,'$.action_name')) FROM access_user_permissions u JOIN actors a ON a.id=u.actor_id JOIN access_permissions p ON p.id=u.permission_id WHERE a.source_system='beloan' AND p.app_scope='beloan'""")
allowed=set();assigned=set()
for row in rows:
 uid,code,actions=row.split('\t',2);assigned.add(uid)
 if code=='ALL_FUNCTIONS' or 'TRAIL_BALANCE' in actions.split(','):allowed.add(uid)
report={'status':'PASSED','checks':checks,'beloan_users':scalar('SELECT COUNT(*) FROM beloan_migration_stage.tb_users'),'users_with_permissions':len(assigned),'users_with_trial_balance_permission':len(allowed),'permission_links_checked':len(rows),'scope':'Actual migrated assignments and original permission action semantics; read-only. Does not enable unified authentication or validate staff IP/login/approval flags.'}
(HERE.parents[1]/'outputs/unified-db/accounting-access-parity-result.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
