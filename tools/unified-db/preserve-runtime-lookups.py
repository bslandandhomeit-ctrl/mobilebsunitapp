"""Preserve original lookup contracts without changing runtime connections."""
import importlib.util,json
from pathlib import Path
spec=importlib.util.spec_from_file_location('stage',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
NAMES=['payment_options','payment_term','holiday','image_types','product_brand','product_category','product_type','product_products_type','locales','locale_titles','industries','country_descriptions','countries','province','districts','commune','village','products']
report={}
for name in NAMES:
 source='beloan_migration_stage.tb_'+name;target='beloan_reference_'+name;view='beloan_compat_'+name
 if not m.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='beloan_migration_stage' AND TABLE_NAME="+m.literal('tb_'+name)):raise RuntimeError('Lookup source missing: '+name)
 if not m.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="+m.literal(target)):m.target('CREATE TABLE '+target+' LIKE '+source+'; INSERT INTO '+target+' SELECT * FROM '+source+';')
 checks=[row.split('\t')[-1] for row in m.target('CHECKSUM TABLE '+source+','+target+' EXTENDED;')]
 if len(checks)!=2 or 'NULL' in checks or checks[0]!=checks[1]:raise RuntimeError('Reference differs; refusing overwrite: '+name)
 occupied=m.target("SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="+m.literal(view))
 if occupied and occupied!=['VIEW']:raise RuntimeError('View name occupied: '+name)
 if name=='payment_options':
  m.target("GRANT SELECT ON bsunits_beloan_final.source_record_map TO 'unified_beloan_posting_reader'@'%';")
  cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="+m.literal(target)+" ORDER BY ORDINAL_POSITION")
  fields=['mapping.target_id AS unit_type_id' if col=='unit_type_id' else 'p.`'+col+'`' for col in cols]
  m.target('CREATE OR REPLACE SQL SECURITY INVOKER VIEW '+view+' AS SELECT '+','.join(fields)+",p.unit_type_id AS source_unit_type_id FROM "+target+" p LEFT JOIN source_record_map mapping ON mapping.source_system='beloan' AND mapping.source_table='tb_unit_types' AND mapping.source_id=(CAST(p.unit_type_id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)")
 else:m.target('CREATE OR REPLACE ALGORITHM=MERGE SQL SECURITY INVOKER VIEW '+view+' AS SELECT * FROM '+target)
 for table in [target,view]:m.target("GRANT SELECT ON bsunits_beloan_final."+table+" TO 'unified_beloan_posting_reader'@'%';")
 report[name]={'rows':int(m.target('SELECT COUNT(*) FROM '+target)[0]),'original_ids_and_checksum_preserved':True}
Path('outputs/unified-db/runtime-lookup-preservation.json').write_text(json.dumps({'status':'PRESERVED','tables':report,'scope':'Original lookup/product references; no shared customer/unit duplication, runtime switch or write permissions.'},indent=2)+'\n')
print('Verified '+str(len(report))+' original lookup/reference tables.')
