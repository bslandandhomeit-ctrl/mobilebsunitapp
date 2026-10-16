"""Preserve permissions in application scopes; never combine privileges by name."""
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)

def payload(schema,table,alias):
    cols = s.target('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='+s.literal(schema)+' AND TABLE_NAME='+s.literal(table)+' ORDER BY ORDINAL_POSITION;')
    return 'JSON_OBJECT('+','.join(s.literal(col)+','+alias+'.`'+col+'`' for col in cols)+')'

def run():
    if s.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='access_roles';"):
        raise RuntimeError('Access target exists; refusing to overwrite.')
    s.target('''CREATE TABLE access_roles (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, app_scope VARCHAR(20) NOT NULL,
      source_role_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NULL, attributes JSON NOT NULL,
      UNIQUE KEY source_role(app_scope,source_role_id)
    ) ENGINE=InnoDB;
    CREATE TABLE access_permissions (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, app_scope VARCHAR(20) NOT NULL,
      source_permission_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NULL, attributes JSON NOT NULL,
      UNIQUE KEY source_permission(app_scope,source_permission_id)
    ) ENGINE=InnoDB;
    CREATE TABLE access_role_permissions (
      role_id BIGINT UNSIGNED NOT NULL, permission_id BIGINT UNSIGNED NOT NULL,
      PRIMARY KEY(role_id,permission_id), FOREIGN KEY(role_id) REFERENCES access_roles(id),
      FOREIGN KEY(permission_id) REFERENCES access_permissions(id)
    ) ENGINE=InnoDB;
    CREATE TABLE access_user_roles (
      actor_id BIGINT UNSIGNED NOT NULL, role_id BIGINT UNSIGNED NOT NULL,
      PRIMARY KEY(actor_id,role_id), FOREIGN KEY(actor_id) REFERENCES actors(id),
      FOREIGN KEY(role_id) REFERENCES access_roles(id)
    ) ENGINE=InnoDB;
    CREATE TABLE access_user_permissions (
      actor_id BIGINT UNSIGNED NOT NULL, permission_id BIGINT UNSIGNED NOT NULL,
      PRIMARY KEY(actor_id,permission_id), FOREIGN KEY(actor_id) REFERENCES actors(id),
      FOREIGN KEY(permission_id) REFERENCES access_permissions(id)
    ) ENGINE=InnoDB;
    CREATE TABLE access_source_assignments (
      app_scope VARCHAR(20) NOT NULL, source_table VARCHAR(80) NOT NULL,
      record_checksum CHAR(64) NOT NULL, source_payload JSON NOT NULL,
      PRIMARY KEY(app_scope,source_table,record_checksum)
    ) ENGINE=InnoDB;''')
    statements = ['START TRANSACTION;']
    for scope,schema,role_table,role_name,perm_table,perm_name in (
        ('bsunits','bsunits_migration_stage','roles','name','permissions','name'),
        ('beloan','beloan_migration_stage','tb_roles','role_name','tb_permission','code')):
        statements.append('INSERT INTO access_roles(app_scope,source_role_id,name,attributes) SELECT '+s.literal(scope)+',r.id,r.`'+role_name+'`,'+payload(schema,role_table,'r')+' FROM '+schema+'.'+role_table+' r;')
        statements.append('INSERT INTO access_permissions(app_scope,source_permission_id,name,attributes) SELECT '+s.literal(scope)+',r.id,r.`'+perm_name+'`,'+payload(schema,perm_table,'r')+' FROM '+schema+'.'+perm_table+' r;')
    for scope,schema,table in (
        ('bsunits','bsunits_migration_stage','model_has_roles'),
        ('bsunits','bsunits_migration_stage','model_has_permissions'),
        ('bsunits','bsunits_migration_stage','role_has_permissions'),
        ('beloan','beloan_migration_stage','tb_user_permission')):
        expression = payload(schema,table,'r')
        statements.append('INSERT INTO access_source_assignments SELECT '+s.literal(scope)+','+s.literal(table)+',SHA2('+expression+',256),'+expression+' FROM '+schema+'.'+table+' r;')
    actor_join = " JOIN actor_source_map m ON m.source_system='bsunits' AND m.source_user_id=r.model_id "
    user_type = s.literal('App\\User')
    statements.extend([
      "INSERT INTO access_user_roles SELECT m.actor_id,a.id FROM bsunits_migration_stage.model_has_roles r"+actor_join+"JOIN access_roles a ON a.app_scope='bsunits' AND a.source_role_id=r.role_id WHERE r.model_type="+user_type+';',
      "INSERT INTO access_user_permissions SELECT m.actor_id,p.id FROM bsunits_migration_stage.model_has_permissions r"+actor_join+"JOIN access_permissions p ON p.app_scope='bsunits' AND p.source_permission_id=r.permission_id WHERE r.model_type="+user_type+';',
      "INSERT INTO access_role_permissions SELECT a.id,p.id FROM bsunits_migration_stage.role_has_permissions r JOIN access_roles a ON a.app_scope='bsunits' AND a.source_role_id=r.role_id JOIN access_permissions p ON p.app_scope='bsunits' AND p.source_permission_id=r.permission_id;",
      "INSERT INTO access_user_roles SELECT m.actor_id,a.id FROM beloan_migration_stage.tb_users r JOIN actor_source_map m ON m.source_system='beloan' AND m.source_user_id=r.id JOIN access_roles a ON a.app_scope='beloan' AND a.source_role_id=r.role_id;",
      "INSERT INTO access_user_permissions SELECT m.actor_id,p.id FROM beloan_migration_stage.tb_user_permission r JOIN actor_source_map m ON m.source_system='beloan' AND m.source_user_id=r.user_id JOIN access_permissions p ON p.app_scope='beloan' AND p.source_permission_id=r.permission_id;",
      'COMMIT;'])
    s.target('\n'.join(statements))
    counts = {table:int(s.target('SELECT COUNT(*) FROM '+table+';')[0]) for table in ('access_roles','access_permissions','access_user_roles','access_role_permissions','access_user_permissions','access_source_assignments')}
    source_roles = int(s.target('SELECT (SELECT COUNT(*) FROM bsunits_migration_stage.roles)+(SELECT COUNT(*) FROM beloan_migration_stage.tb_roles);')[0])
    source_permissions = int(s.target('SELECT (SELECT COUNT(*) FROM bsunits_migration_stage.permissions)+(SELECT COUNT(*) FROM beloan_migration_stage.tb_permission);')[0])
    if counts['access_roles']!=source_roles or counts['access_permissions']!=source_permissions:
        raise RuntimeError('Scoped permission catalog coverage failed.')
    exceptions = {
      'bsunits_role_assignments_missing_user':int(s.target("SELECT COUNT(*) FROM bsunits_migration_stage.model_has_roles r LEFT JOIN actor_source_map m ON m.source_system='bsunits' AND m.source_user_id=r.model_id WHERE m.actor_id IS NULL;")[0]),
      'beloan_permission_assignments_missing_user':int(s.target("SELECT COUNT(*) FROM beloan_migration_stage.tb_user_permission r LEFT JOIN actor_source_map m ON m.source_system='beloan' AND m.source_user_id=r.user_id WHERE m.actor_id IS NULL;")[0]),
      'beloan_users_missing_role':int(s.target("SELECT COUNT(*) FROM beloan_migration_stage.tb_users r LEFT JOIN access_roles a ON a.app_scope='beloan' AND a.source_role_id=r.role_id WHERE a.id IS NULL;")[0]),
    }
    report = {'counts':counts,'unresolved_source_references':exceptions,'scope':'Source-qualified roles/permissions and valid assignments preserved. No cross-application privilege merge. All original assignment rows remain available for unresolved-reference review; adapters must also preserve Beloan role levels and user-specific approval flags.'}
    (s.ROOT/'outputs/unified-db/access-control-result.json').write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps(report,indent=2))

if __name__ == '__main__':
    run()
