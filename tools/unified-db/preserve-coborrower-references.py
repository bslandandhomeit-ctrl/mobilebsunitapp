"""Preserve signed co-borrower links, mapping account identity without person merging."""
import importlib.util,json
from pathlib import Path
spec=importlib.util.spec_from_file_location('stage',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
source='beloan_migration_stage.tb_co_borrowers';target='beloan_reference_co_borrowers'
if not m.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='"+target+"'"):m.target('CREATE TABLE '+target+' LIKE '+source+'; INSERT INTO '+target+' SELECT * FROM '+source)
checks=[r.split('\t')[-1] for r in m.target('CHECKSUM TABLE '+source+','+target+' EXTENDED;')]
if len(checks)!=2 or 'NULL' in checks or checks[0]!=checks[1]:raise RuntimeError('Co-borrower history differs; refusing overwrite')
cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='"+target+"' ORDER BY ORDINAL_POSITION")
fields=['a.id AS customer_id' if c=='customer_id' else 'b.`'+c+'`' for c in cols]
m.target('CREATE OR REPLACE SQL SECURITY INVOKER VIEW beloan_compat_co_borrowers AS SELECT '+','.join(fields)+",b.customer_id AS source_customer_id FROM "+target+" b LEFT JOIN customer_accounts a ON a.source_system='beloan' AND a.source_id=b.customer_id")
for table in [target,'beloan_compat_co_borrowers']:m.target("GRANT SELECT ON bsunits_beloan_final."+table+" TO 'unified_beloan_posting_reader'@'%';")
missing=int(m.target('SELECT COUNT(*) FROM beloan_compat_co_borrowers WHERE customer_id IS NULL AND source_customer_id<>0')[0])
Path('outputs/unified-db/coborrower-reference-preservation.json').write_text(json.dumps({'status':'PRESERVED','rows':int(m.target('SELECT COUNT(*) FROM '+target)[0]),'original_checksum_preserved':True,'unresolved_nonzero_customer_references':missing,'scope':'Historical account links only, no person dedup or write cutover.'},indent=2)+'\n')
print('Co-borrower references preserved; unresolved nonzero customer references: '+str(missing))
