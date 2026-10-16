"""Preserve original financial counterpart references, without master-data remapping."""
import importlib.util,json
from pathlib import Path
spec=importlib.util.spec_from_file_location('stage',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
report={}
for name in ['banks','dealers','dealer_banks','representatives']:
 source='beloan_migration_stage.tb_'+name;target='beloan_reference_'+name;view='beloan_compat_'+name
 exists=m.target("SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="+m.literal(target))
 if not exists:m.target('CREATE TABLE '+target+' LIKE '+source+'; INSERT INTO '+target+' SELECT * FROM '+source+';')
 checks=m.target('CHECKSUM TABLE '+source+', '+target+' EXTENDED;');values=[row.split('\t')[-1] for row in checks]
 if len(values)!=2 or 'NULL' in values or values[0]!=values[1]:raise RuntimeError('Existing reference differs; refusing overwrite: '+name)
 occupied=m.target("SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="+m.literal(view))
 if occupied and occupied!=['VIEW']:raise RuntimeError('Compatibility name occupied')
 m.target('CREATE OR REPLACE ALGORITHM=MERGE SQL SECURITY INVOKER VIEW '+view+' AS SELECT * FROM '+target+';')
 for table in [target,view]:m.target("GRANT SELECT ON bsunits_beloan_final."+table+" TO 'unified_beloan_posting_reader'@'%';")
 report[name]={'preserved_rows':int(m.target('SELECT COUNT(*) FROM '+target)[0]),'checksum_matches':True,'original_ids_preserved':True}
Path('outputs/unified-db/banking-reference-preservation.json').write_text(json.dumps({'status':'PRESERVED','tables':report,'scope':'Local financial counterpart references only. No runtime cutover or write grants.'},indent=2)+'\n')
print('Verified banking/dealer reference preservation.')
