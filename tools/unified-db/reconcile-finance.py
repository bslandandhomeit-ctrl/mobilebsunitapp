"""Compare aggregate finance baselines of the original and raw staging copy.

No recalculation, posting, schedule regeneration, source edits or personal-row exports.
"""
import importlib.util
import json
import pathlib

HERE = pathlib.Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
staging = importlib.util.module_from_spec(spec)
spec.loader.exec_module(staging)
FINANCIAL_COLUMNS = {
    'tb_loans':['unit_sale_price','final_price','diposit_amount','original_amount','loan_amount','down_payment'],
    'tb_sale_order':['final_price','diposit_amount','discount_promotion','discount_other'],
    'tb_sale_items':['unit_sale_price'],
    'tb_client_loan_accounts':['balance','old_balance','balance_downpayment'],
    'tb_drawdown_account':['balance','old_balance','old_balance_2'],
    'tb_repayment_schedule':['principal','interest','total_payment','fee','other_fee','balance'],
    'tb_loan_payments':['paid_principal','paid_interest','paid_fee','paid_other_fee','penalty_amount','waived_penalty'],
    'tb_journal_detail':['debit','credit','p_debit','p_credit','b_debit','b_credit'],
}

def aggregate_sql(table,columns):
    # Only fixed identifiers above are used. Fixed decimal arithmetic avoids sum-order drift.
    expressions = ['COUNT(*)']
    for column in columns:
        expressions += ['COUNT(`'+column+'`)','COALESCE(SUM(CAST(`'+column+'` AS DECIMAL(50,8))),0)']
    return 'SELECT '+','.join(expressions)+' FROM `'+table+'`;'

def main():
    manifest = json.loads((staging.ROOT/'outputs'/'unified-db'/'finance-stage-result.json').read_text())
    if manifest['status'] != 'VERIFIED':
        raise RuntimeError('Full raw staging must finish and pass its table checks before reconciliation.')
    source,password,database,_ = staging.SOURCES['beloan']
    comparisons = {}
    mismatches = []
    for table,columns in FINANCIAL_COLUMNS.items():
        sql = aggregate_sql(table,columns)
        original = staging.mysql(source,password,database,sql)[0].split('\t')
        copied = staging.mysql(staging.TARGET_CONTAINER,'local-only-unified','beloan_migration_stage',sql)[0].split('\t')
        labels = ['row_count']
        for column in columns:
            labels += [column+':nonnull_count',column+':sum_decimal8']
        comparisons[table] = {'original':dict(zip(labels,original)),
                              'staging':dict(zip(labels,copied)),'matches':original==copied}
        if original != copied:
            mismatches.append(table)
        print(table+' '+('matches' if original==copied else 'requires investigation'),flush=True)
    # Year/account grouping detects shifted postings that could hide inside total sums.
    journal_sql = '''SELECT d.coa_id,YEAR(j.entry_date),COUNT(*),
      SUM(d.debit),SUM(d.credit) FROM tb_journal_detail d
      LEFT JOIN tb_journal_requiry j ON j.id=d.journal_id
      GROUP BY d.coa_id,YEAR(j.entry_date) ORDER BY d.coa_id,YEAR(j.entry_date);'''
    original = staging.mysql(source,password,database,journal_sql)
    copied = staging.mysql(staging.TARGET_CONTAINER,'local-only-unified','beloan_migration_stage',journal_sql)
    grouped_matches = original==copied
    report = {'source':'land_home_v2','staging':'beloan_migration_stage',
        'raw_copy_run_id':manifest['run_id'],'financial_comparisons':comparisons,
        'mismatched_tables':mismatches,'journal_account_year_groups':len(original),
        'journal_account_year_groups_match':grouped_matches,
        'status':'MATCHED' if not mismatches and grouped_matches else 'REVIEW_REQUIRED',
        'limits':'Aggregate baseline, not a complete row-level audit. Float-based fields compared at eight decimal places. No original financial values are rounded or rewritten.'}
    (staging.ROOT/'outputs'/'unified-db'/'financial-reconciliation.json').write_text(json.dumps(report,indent=2))
    if mismatches or not grouped_matches:
        raise RuntimeError('Financial baseline differences need investigation; do not promote.')
    print('All financial baselines and account/year posting groups match.',flush=True)

if __name__ == '__main__':
    main()
