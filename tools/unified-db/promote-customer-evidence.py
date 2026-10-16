"""Preserve account-scoped customer evidence; do not infer person ownership."""
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging', HERE / 'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)

TABLES = ('tb_client_cbc_identification', 'tb_client_cbc_contact', 'tb_client_cbc_address')

def run():
    s.target('''CREATE TABLE IF NOT EXISTS customer_account_evidence (
      source_table VARCHAR(80) NOT NULL, source_id BIGINT UNSIGNED NOT NULL,
      account_id BIGINT UNSIGNED NOT NULL,
      evidence_kind ENUM('IDENTIFICATION','CONTACT','ADDRESS') NOT NULL,
      ownership_state ENUM('ACCOUNT_ONLY','PERSON_VERIFIED') NOT NULL DEFAULT 'ACCOUNT_ONLY',
      source_payload JSON NOT NULL, source_checksum CHAR(64) NOT NULL,
      PRIMARY KEY(source_table,source_id),
      FOREIGN KEY(account_id) REFERENCES customer_accounts(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;''')
    if int(s.target('SELECT COUNT(*) FROM customer_account_evidence;')[0]):
        raise RuntimeError('Evidence target occupied; refusing to overwrite.')
    statements = ['START TRANSACTION;']
    expected = {}
    orphans = {}
    for table in TABLES:
        columns = s.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='beloan_migration_stage' AND TABLE_NAME=" + s.literal(table) + ' ORDER BY ORDINAL_POSITION;')
        if not columns or any(not c.replace('_', '').isalnum() for c in columns):
            raise RuntimeError('Unexpected customer source schema.')
        payload = 'JSON_OBJECT(' + ','.join(s.literal(c)+',r.`'+c+'`' for c in columns) + ')'
        kind = {'tb_client_cbc_identification':'IDENTIFICATION', 'tb_client_cbc_contact':'CONTACT', 'tb_client_cbc_address':'ADDRESS'}[table]
        expected[table] = int(s.target('SELECT COUNT(*) FROM beloan_migration_stage.'+table+' r JOIN customer_accounts a ON a.id=r.client_id;')[0])
        orphans[table] = int(s.target('SELECT COUNT(*) FROM beloan_migration_stage.'+table+' r LEFT JOIN customer_accounts a ON a.id=r.client_id WHERE a.id IS NULL;')[0])
        statements.append('INSERT INTO customer_account_evidence(source_table,source_id,account_id,evidence_kind,source_payload,source_checksum) SELECT '+s.literal(table)+',r.id,r.client_id,'+s.literal(kind)+','+payload+',SHA2('+payload+',256) FROM beloan_migration_stage.'+table+' r JOIN customer_accounts a ON a.id=r.client_id;')
    statements.append('COMMIT;')
    s.target('\n'.join(statements))
    actual = {row.split('\t')[0]:int(row.split('\t')[1]) for row in s.target('SELECT source_table,COUNT(*) FROM customer_account_evidence GROUP BY source_table;')}
    if actual != expected:
        raise RuntimeError('Evidence source coverage mismatch.')
    mismatches = int(s.target('SELECT COUNT(*) FROM customer_account_evidence WHERE source_checksum<>SHA2(source_payload,256);')[0])
    if mismatches:
        raise RuntimeError('Evidence checksum mismatch.')
    report = {'canonical_account_evidence':actual,'orphans_preserved_in_staging':orphans,'checksum_mismatches':mismatches,'scope':'Account ownership retained; no inferred person assignment, identity merge or source writes.'}
    (s.ROOT/'outputs/unified-db/customer-evidence-result.json').write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps(report,indent=2))

if __name__ == '__main__':
    run()
