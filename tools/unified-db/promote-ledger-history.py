"""Copy stored financial history, keeping financial IDs and all numeric types."""
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)

TABLES = ('coa_categories','journal_requiry','journal_detail','repayment_schedule',
          'loan_payments','transactions_posting','transactions_requiry',
          'till_account','till_transaction','till_transaction_reject')

def run():
    for table in TABLES:
        if s.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="+s.literal(table)+';'):
            raise RuntimeError('Ledger target exists; refusing to overwrite.')
    report = {}
    for table in TABLES:
        source = 'beloan_migration_stage.tb_'+table
        print('Copying '+table,flush=True)
        s.target('CREATE TABLE `'+table+'` LIKE '+source+'; START TRANSACTION; INSERT INTO `'+table+'` SELECT * FROM '+source+'; COMMIT;')
        actual = int(s.target('SELECT COUNT(*) FROM `'+table+'`;')[0])
        expected = int(s.target('SELECT COUNT(*) FROM '+source+';')[0])
        rows = s.target('CHECKSUM TABLE '+source+', `'+table+'` EXTENDED;')
        checksums = [row.split('\t')[-1] for row in rows]
        if actual!=expected or len(checksums)!=2 or 'NULL' in checksums or checksums[0]!=checksums[1]:
            raise RuntimeError('Ledger row checksum/count mismatch; do not switch apps.')
        report[table] = {'rows':actual,'source_rows':expected,'extended_row_checksum_matches':True}
        (s.ROOT/'outputs/unified-db/ledger-history-result.json').write_text(json.dumps({'tables':report,'status':'COPYING'},indent=2)+'\n')
        print(table+' verified: '+str(actual)+' rows',flush=True)
    # Preserve accounts/year distribution, not only global debit/credit totals.
    def journal_sql(schema,prefix):
        return ('SELECT d.coa_id,YEAR(j.entry_date),COUNT(*),SUM(d.debit),SUM(d.credit) FROM '+schema+'.'+prefix+'journal_detail d LEFT JOIN '+schema+'.'+prefix+'journal_requiry j ON j.id=d.journal_id GROUP BY d.coa_id,YEAR(j.entry_date) ORDER BY d.coa_id,YEAR(j.entry_date);')
    before = s.target(journal_sql('beloan_migration_stage','tb_'))
    after = s.target(journal_sql('bsunits_beloan_final',''))
    if before!=after:
        raise RuntimeError('Journal account/year distribution mismatch.')
    result = {'tables':report,'status':'VERIFIED','journal_account_year_groups':len(before),'journal_account_year_groups_match':True,'limits':'Extended row checksums are non-cryptographic; account/year journal totals also compared. Original IDs and financial values retained. Remaining financial auxiliary tables and application adapters still required.'}
    (s.ROOT/'outputs/unified-db/ledger-history-result.json').write_text(json.dumps(result,indent=2)+'\n')
    print('Ledger history verified.',flush=True)

if __name__ == '__main__':
    run()
