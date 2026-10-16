"""Verify preserved login identities without displaying identifiers or credentials."""
import importlib.util,json
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
def count(sql):return int(m.target(sql)[0])
report={}
for scope,schema,table,identifier,enabled in [('bsunits','bsunits_migration_stage','users','phone_number','active'),('beloan','beloan_migration_stage','tb_users','username','status')]:
 where="l.source_system="+m.literal(scope)
 join=' FROM login_identities l JOIN '+schema+'.'+table+' s ON s.id=l.source_user_id WHERE '+where
 checks={
 'missing_login_identities':count('SELECT COUNT(*) FROM '+schema+'.'+table+" s LEFT JOIN login_identities l ON l.source_user_id=s.id AND "+where+' WHERE l.actor_id IS NULL'),
 'extra_login_identities':count('SELECT COUNT(*) FROM login_identities l LEFT JOIN '+schema+'.'+table+' s ON s.id=l.source_user_id WHERE '+where+' AND s.id IS NULL'),
 'password_hash_changes':count('SELECT COUNT(*)'+join+' AND NOT(BINARY l.password_hash <=> BINARY s.password)'),
 'login_identifier_changes':count('SELECT COUNT(*)'+join+' AND NOT(BINARY l.login_identifier <=> BINARY s.`'+identifier+'`)'),
 'enabled_state_changes':count('SELECT COUNT(*)'+join+' AND NOT(l.source_enabled <=> s.`'+enabled+'`)'),
 'actor_identity_changes':count("SELECT COUNT(*) FROM login_identities l JOIN actors a ON a.id=l.actor_id WHERE "+where+' AND (a.source_system<>l.source_system OR a.source_user_id<>l.source_user_id)'),
 }
 cols=m.target('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='+m.literal(schema)+' AND TABLE_NAME='+m.literal(table)+' ORDER BY ORDINAL_POSITION')
 fields=[c for c in cols if c not in ('password','remember_token')]
 expected='JSON_OBJECT('+','.join(m.literal(c)+',s.`'+c+'`' for c in fields)+')'
 checks['profile_role_branch_approval_changes']=count('SELECT COUNT(*) FROM actors a JOIN '+schema+'.'+table+' s ON s.id=a.source_user_id WHERE a.source_system='+m.literal(scope)+' AND NOT(BINARY JSON_COMPACT(a.profile) <=> BINARY JSON_COMPACT('+expected+'))')
 checks['credentials_in_actor_profile']=count("SELECT COUNT(*) FROM actors a WHERE a.source_system="+m.literal(scope)+" AND (JSON_CONTAINS_PATH(a.profile,'one','$.password','$.remember_token')=1)")
 if any(checks.values()):raise RuntimeError('Identity parity mismatch for '+scope+': '+json.dumps(checks))
 report[scope]={'users':count('SELECT COUNT(*) FROM '+schema+'.'+table),'profile_fields_checked':len(fields),'checks':checks}
result={'status':'PASSED','sources':report,'scope':'Read-only full source-qualified login/profile preservation, including existing branch/role/approval fields. No hashes, personal identifiers or tokens output. Runtime login/provider and session writes remain unverified.'}
(HERE.parents[1]/'outputs/unified-db/login-identity-parity-result.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result,indent=2))
