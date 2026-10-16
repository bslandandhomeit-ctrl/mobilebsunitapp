"""Preserve local staff/history contracts and reuse unified access roles. No runtime switch."""
import importlib.util,json
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
report={}
for name in ['staffs','job_history']:
 source='beloan_migration_stage.tb_'+name;target='beloan_reference_'+name;view='beloan_compat_'+name
 if not m.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="+m.literal(target)):
  m.target('CREATE TABLE '+target+' LIKE '+source+'; INSERT INTO '+target+' SELECT * FROM '+source)
 checks=[r.split('\t')[-1] for r in m.target('CHECKSUM TABLE '+source+','+target+' EXTENDED')]
 if len(checks)!=2 or 'NULL' in checks or checks[0]!=checks[1]:raise RuntimeError('Preserved staff/history differs; refusing overwrite: '+name)
 occupied=m.target("SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="+m.literal(view))
 if occupied and occupied!=['VIEW']:raise RuntimeError('Compatibility name occupied: '+view)
 m.target('CREATE OR REPLACE ALGORITHM=MERGE SQL SECURITY INVOKER VIEW '+view+' AS SELECT * FROM '+target)
 for table in [target,view]:m.target("GRANT SELECT ON bsunits_beloan_final."+table+" TO 'unified_beloan_posting_reader'@'%'")
 report[name]={'rows':int(m.target('SELECT COUNT(*) FROM '+target)[0]),'source_checksum_preserved':True}
columns=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='beloan_migration_stage' AND TABLE_NAME='tb_roles' ORDER BY ORDINAL_POSITION")
fields=['source_role_id AS id' if c=='id' else "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(attributes,'$."+c+"')),'null') AS `"+c+'`' for c in columns]
view='beloan_compat_roles';occupied=m.target("SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="+m.literal(view))
if occupied and occupied!=['VIEW']:raise RuntimeError('Role view name occupied')
m.target('CREATE OR REPLACE SQL SECURITY INVOKER VIEW '+view+' AS SELECT '+','.join(fields)+" FROM access_roles WHERE app_scope='beloan'")
for table in ['access_roles',view]:m.target("GRANT SELECT ON bsunits_beloan_final."+table+" TO 'unified_beloan_posting_reader'@'%'")
conditions=' OR '.join('NOT (CAST(v.`'+c+'` AS BINARY) <=> CAST(s.`'+c+'` AS BINARY))' for c in columns)
differences=int(m.target('SELECT COUNT(*) FROM beloan_migration_stage.tb_roles s LEFT JOIN '+view+' v ON v.id=s.id WHERE v.id IS NULL OR '+conditions)[0])
if differences:raise RuntimeError('Unified role projection differs from original role fields')
report['roles']={'rows':int(m.target('SELECT COUNT(*) FROM '+view)[0]),'all_original_fields_equal':True,'reuses_unified_access_roles':True}
(HERE.parents[1]/'outputs/unified-db/branch-staff-reference-result.json').write_text(json.dumps({'status':'PREPARED','tables':report,'runtime_switched':False,'scope':'Local historical staff/history reference copies and unified role projection; SELECT grants only, no actor reassignment or business writes.'},indent=2)+'\n')
print('Verified staff/history preservation and unified role field parity; no runtime switch.')
