"""Preserve original financial amendment evidence; IDs remain source-qualified."""
import importlib.util,json
from pathlib import Path
spec=importlib.util.spec_from_file_location('stage',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
target='beloan_unit_change_history';source='beloan_migration_stage.tb_change_unit'
columns=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='beloan_migration_stage' AND TABLE_NAME='tb_change_unit' ORDER BY ORDINAL_POSITION")
if not columns or any(not c.replace('_','').isalnum() for c in columns):raise RuntimeError('Unexpected source schema')
m.target('CREATE TABLE IF NOT EXISTS '+target+' LIKE '+source)
count=int(m.target('SELECT COUNT(*) FROM '+target)[0])
if count==0:m.target('INSERT INTO '+target+' SELECT * FROM '+source)
missing=int(m.target('SELECT COUNT(*) FROM '+source+' s LEFT JOIN '+target+' t ON t.id=s.id WHERE t.id IS NULL OR NOT ('+' AND '.join('s.`'+c+'` <=> t.`'+c+'`' for c in columns)+')')[0])
extra=int(m.target('SELECT COUNT(*) FROM '+target+' t LEFT JOIN '+source+' s ON s.id=t.id WHERE s.id IS NULL')[0])
if missing or extra:raise RuntimeError('Unit-change history differs; refusing overwrite')
for field,table in [('sale_id','sales'),('loan_id','loans'),('client_id','customer_accounts')]:
 if int(m.target('SELECT COUNT(*) FROM '+target+' h LEFT JOIN '+table+' c ON c.id=h.'+field+' WHERE h.'+field+' IS NOT NULL AND c.id IS NULL')[0]):raise RuntimeError('Historical transaction/account reference missing')
join=" LEFT JOIN source_record_map r ON r.source_system='beloan' AND r.source_table='tb_unit_types' AND BINARY r.source_id=BINARY CAST(h.old_unit_id AS CHAR)"
if int(m.target('SELECT COUNT(*) FROM '+target+' h'+join+' WHERE h.old_unit_id IS NOT NULL AND r.target_id IS NULL')[0]):raise RuntimeError('Historical unit type mapping missing')
projection=','.join('r.target_id AS old_unit_id' if c=='old_unit_id' else 'h.`'+c+'`' for c in columns)
m.target('CREATE OR REPLACE SQL SECURITY INVOKER VIEW beloan_compat_change_unit AS SELECT '+projection+',h.old_unit_id AS source_old_unit_type_id FROM '+target+' h'+join)
m.target("GRANT SELECT ON bsunits_beloan_final.beloan_unit_change_history TO 'unified_beloan_posting_reader'@'%';GRANT SELECT ON bsunits_beloan_final.source_record_map TO 'unified_beloan_posting_reader'@'%';GRANT SELECT ON bsunits_beloan_final.beloan_compat_change_unit TO 'unified_beloan_posting_reader'@'%'")
report={'status':'PRESERVED','rows':int(m.target('SELECT COUNT(*) FROM '+target)[0]),'columns_verified':len(columns),'changed_or_missing':missing,'extra':extra,'identity_scope':'Original Beloan IDs; evidence table, not canonical customer or unit master','runtime_cutover':False,'remaining':['Canonical sale/loan/customer reference adapter','Automatic amendment workflow']}
Path('outputs/unified-db/unit-change-history-preservation.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
