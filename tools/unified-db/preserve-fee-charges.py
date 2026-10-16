"""Preserve omitted historical fee charges; no calculation or posting."""
import importlib.util
import json
from pathlib import Path

HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
s=importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)

def run():
    exists=s.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fee_charges';")
    if not exists:
        s.target('CREATE TABLE fee_charges LIKE beloan_migration_stage.tb_fee_charges; INSERT INTO fee_charges SELECT * FROM beloan_migration_stage.tb_fee_charges;')
    counts=s.target('SELECT (SELECT COUNT(*) FROM fee_charges),(SELECT COUNT(*) FROM beloan_migration_stage.tb_fee_charges);')[0].split('\t')
    checks=[r.split('\t')[-1] for r in s.target('CHECKSUM TABLE fee_charges,beloan_migration_stage.tb_fee_charges EXTENDED;')]
    if counts[0]!=counts[1] or len(checks)!=2 or 'NULL' in checks or checks[0]!=checks[1]:
        raise RuntimeError('Fee preservation differs; refusing compatibility activation')
    s.target('CREATE OR REPLACE SQL SECURITY INVOKER VIEW beloan_compat_fee_charges AS SELECT * FROM fee_charges;')
    for table in ['fee_charges','beloan_compat_fee_charges','loan_cost_fees','beloan_compat_loan_cost_fees']:
        s.target("GRANT SELECT ON bsunits_beloan_final."+table+" TO 'unified_beloan_posting_reader'@'%';")
    report={'status':'PRESERVED','rows':int(counts[0]),'extended_checksum_matches':True,'scope':'Frozen staging copied with original IDs/types/amounts. No source writes, recalculation, posting or default connection switch. Non-cryptographic checksum.'}
    (s.ROOT/'outputs/unified-db/fee-charge-preservation.json').write_text(json.dumps(report,indent=2)+'\n')
    print('Historical fee charges preserved: '+counts[0]+' unchanged rows.')

if __name__=='__main__':run()
