"""Read-only readiness checks; never post, recalculate or change balances."""
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging', HERE / 'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)


def run():
    manifest = json.loads((s.ROOT / 'outputs/unified-db/financial-auxiliary-result.json').read_text())
    auxiliary = manifest['tables']
    ledger = json.loads((s.ROOT / 'outputs/unified-db/ledger-history-result.json').read_text())['tables']
    result = {}
    for table in sorted(set(auxiliary) | set(ledger)):
        source = 'tb_currency' if table == 'currencies' else 'tb_' + table
        # Fixed identifiers originate from checked-in promotion manifests.
        if not table.replace('_', '').isalnum():
            raise ValueError('Invalid manifest identifier')
        counts = s.target('SELECT (SELECT COUNT(*) FROM `' + table + '`),'
                          '(SELECT COUNT(*) FROM beloan_migration_stage.`' + source + '`);')[0].split('\t')
        mapped = auxiliary.get(table, {}).get('remapped_property_columns', [])
        columns = s.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='beloan_migration_stage' AND TABLE_NAME=" + s.literal(source) + ' ORDER BY ORDINAL_POSITION;')
        unchanged = [c for c in columns if c not in mapped]
        def checksum(alias):
            return 'SHA2(JSON_OBJECT(' + ','.join(s.literal(c) + ',' + alias + '.`' + c + '`' for c in unchanged) + '),256)'
        if mapped:
            differences = int(s.target('SELECT COUNT(*) FROM `' + table + '` n JOIN beloan_migration_stage.`' + source + '` o ON n.id=o.id WHERE ' + checksum('n') + '<>' + checksum('o') + ';')[0])
        else:
            sums = [r.split('\t')[-1] for r in s.target('CHECKSUM TABLE `' + table + '`, beloan_migration_stage.`' + source + '` EXTENDED;')]
            differences = int(len(sums) != 2 or 'NULL' in sums or sums[0] != sums[1])
        result[table] = {'rows': int(counts[0]), 'staged_rows': int(counts[1]),
                         'unchanged_fields_match': differences == 0,
                         'preserved': counts[0] == counts[1] and differences == 0}
        print(table + ': ' + ('PASS' if result[table]['preserved'] else 'REVIEW'), flush=True)
    report = {'status': 'PRESERVED' if all(r['preserved'] for r in result.values()) else 'REVIEW_REQUIRED',
              'tables': result,
              'limits': 'Frozen-stage preservation check. Checksums are not cryptographic proof. Property mappings were verified separately during promotion. Does not validate live posting, exports, permissions or final cutover.',
              'blocking_code_findings': ['Original posting report equates project company IDs with branch IDs; explicit branch scope is required.',
                                         'Original cashier posting uses the default source connection; full transaction must move together before activation.']}
    (s.ROOT / 'outputs/unified-db/finance-adapter-readiness.json').write_text(json.dumps(report, indent=2) + '\n')
    if report['status'] != 'PRESERVED':
        raise RuntimeError('Finance preservation differences require investigation')


if __name__ == '__main__':
    run()
