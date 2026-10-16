"""Expose original finance column contracts without copying financial records.

Local view preparation only. Never switch app connections or alter balances.
"""
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging', HERE / 'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)


def run():
    out = s.ROOT / 'outputs/unified-db'
    auxiliary = json.loads((out / 'financial-auxiliary-result.json').read_text())['tables']
    ledger = json.loads((out / 'ledger-history-result.json').read_text())['tables']
    mapping = {('tb_currency' if t == 'currencies' else 'tb_' + t): t for t in set(auxiliary) | set(ledger)}
    mapping.update({'tb_client_loan_accounts': 'loan_accounts', 'tb_drawdown_account': 'drawdown_accounts', 'tb_loans': 'loans'})
    if s.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fee_charges';"):
        mapping['tb_fee_charges'] = 'fee_charges'
    plan = []
    for source, target in sorted(mapping.items()):
        view = 'beloan_compat_' + source[3:]
        if not all(name.replace('_', '').isalnum() for name in [source, target, view]):
            raise ValueError('Invalid manifest identifier')
        kind = s.target("SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" + s.literal(view) + ';')
        if kind and kind != ['VIEW']:
            raise RuntimeError('Compatibility name occupied by a physical table')
        original = s.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='beloan_migration_stage' AND TABLE_NAME=" + s.literal(source) + ' ORDER BY ORDINAL_POSITION;')
        canonical = s.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" + s.literal(target) + ';')
        columns = []
        for col in original:
            actual = 'customer_account_id' if source in ('tb_client_loan_accounts', 'tb_drawdown_account', 'tb_loans') and col == 'client_id' else 'legacy_contract_number' if source == 'tb_loans' and col == 'contract_id' else col
            if actual not in canonical:
                raise RuntimeError('Missing canonical field; compatibility must be explicit')
            columns.append('`' + actual + '` AS `' + col + '`')
        plan.append((view, target, original, columns))
    results = {}
    for view, target, original, columns in plan:
        s.target('CREATE OR REPLACE ALGORITHM=MERGE SQL SECURITY INVOKER VIEW `' + view + '` AS SELECT ' + ','.join(columns) + ' FROM `' + target + '`;')
        actual = s.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" + s.literal(view) + ' ORDER BY ORDINAL_POSITION;')
        counts = s.target('SELECT (SELECT COUNT(*) FROM `' + view + '`),(SELECT COUNT(*) FROM `' + target + '`);')[0].split('\t')
        if actual != original or counts[0] != counts[1]:
            raise RuntimeError('Original finance contract verification failed')
        results[view] = {'canonical_table': target, 'rows': int(counts[0]), 'original_columns_preserved': True}
    report = {'status': 'COMPATIBILITY_PREPARED', 'views': results,
              'app_connections_switched': False, 'financial_records_changed': False,
              'limits': 'Views expose the original finance table/column contracts over canonical records. Canonical property IDs remain canonical. Shared master projections, runtime tables, actor scope and write transaction tests are required before changing the default connection. No writer credentials granted.'}
    (out / 'finance-compatibility-result.json').write_text(json.dumps(report, indent=2) + '\n')
    print('Verified original finance columns and row counts across ' + str(len(results)) + ' views; no application switch.')


if __name__ == '__main__':
    run()
