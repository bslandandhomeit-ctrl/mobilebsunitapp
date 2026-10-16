"""Preserve source-qualified staff identities; never merge by name or phone."""
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)

def run():
    if s.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='actors';"):
        raise RuntimeError('Actor target exists; refusing to overwrite.')
    s.target('''CREATE TABLE actors (
      id BIGINT UNSIGNED PRIMARY KEY, display_name TEXT NULL,
      source_system VARCHAR(20) NOT NULL, source_user_id BIGINT UNSIGNED NOT NULL,
      profile JSON NOT NULL, UNIQUE KEY source_identity(source_system,source_user_id)
    ) ENGINE=InnoDB;
    CREATE TABLE login_identities (
      source_system VARCHAR(20) NOT NULL, source_user_id BIGINT UNSIGNED NOT NULL,
      actor_id BIGINT UNSIGNED NOT NULL, login_identifier VARCHAR(255) NULL,
      email VARCHAR(255) NULL, password_hash VARCHAR(500) NULL,
      source_enabled TINYINT NULL,
      PRIMARY KEY(source_system,source_user_id),
      FOREIGN KEY(actor_id) REFERENCES actors(id)
    ) ENGINE=InnoDB;
    CREATE TABLE actor_source_map (
      source_system VARCHAR(20) NOT NULL, source_user_id BIGINT UNSIGNED NOT NULL,
      actor_id BIGINT UNSIGNED NOT NULL,
      PRIMARY KEY(source_system,source_user_id), FOREIGN KEY(actor_id) REFERENCES actors(id)
    ) ENGINE=InnoDB;''')
    offset = int(s.target('SELECT COALESCE(MAX(id),0) FROM bsunits_migration_stage.users;')[0])
    statements = ['START TRANSACTION;']
    for source,schema,table,login,enabled,addition in (
        ('bsunits','bsunits_migration_stage','users','phone_number','active',0),
        ('beloan','beloan_migration_stage','tb_users','username','status',offset)):
        cols = s.target('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='+s.literal(schema)+' AND TABLE_NAME='+s.literal(table)+' ORDER BY ORDINAL_POSITION;')
        # Credentials live only in the private login identity table, not public actor DTOs.
        profile = 'JSON_OBJECT('+','.join(s.literal(col)+',`'+col+'`' for col in cols if col not in ('password','remember_token'))+')'
        statements.extend([
            'INSERT INTO actors SELECT id+'+str(addition)+',name,'+s.literal(source)+',id,'+profile+' FROM '+schema+'.'+table+';',
            'INSERT INTO actor_source_map SELECT '+s.literal(source)+',id,id+'+str(addition)+' FROM '+schema+'.'+table+';',
            'INSERT INTO login_identities SELECT '+s.literal(source)+',id,id+'+str(addition)+','+login+',email,password,'+enabled+' FROM '+schema+'.'+table+';',
        ])
    statements.append('COMMIT;')
    s.target('\n'.join(statements))
    checks = {}
    for source,schema,table in (('bsunits','bsunits_migration_stage','users'),('beloan','beloan_migration_stage','tb_users')):
        actual = int(s.target('SELECT COUNT(*) FROM actors WHERE source_system='+s.literal(source)+';')[0])
        expected = int(s.target('SELECT COUNT(*) FROM '+schema+'.'+table+';')[0])
        mismatch = int(s.target('SELECT COUNT(*) FROM login_identities l JOIN '+schema+'.'+table+' u ON u.id=l.source_user_id WHERE l.source_system='+s.literal(source)+' AND NOT(BINARY l.password_hash <=> BINARY u.password);')[0])
        if actual!=expected or mismatch:
            raise RuntimeError('Actor/credential coverage failed; details withheld.')
        checks[source] = {'actors':actual,'source_users':expected,'password_hash_differences':mismatch}
    report = {'sources':checks,'merged_login_accounts':0,'scope':'Source-qualified actor/login identities. Names or phones do not merge users; privileges remain app-scoped pending adapters. Password hashes preserved privately; old session tokens retained only in raw staging, not activated.'}
    (s.ROOT/'outputs/unified-db/actor-identity-result.json').write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps(report,indent=2))

if __name__ == '__main__':
    run()
