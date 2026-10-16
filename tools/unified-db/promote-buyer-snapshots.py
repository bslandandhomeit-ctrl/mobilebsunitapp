"""Immutable migration snapshots, with reviewed identity links kept separate."""
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('candidates', HERE/'customer-candidates.py')
c = importlib.util.module_from_spec(spec)
spec.loader.exec_module(c)
s = c.staging

def buyer_payload(contract, buyer):
    prefix = 'customer'+str(buyer)+'_'
    return {key[len(prefix):]:value for key,value in contract.items() if key.startswith(prefix)}

def run():
    run_id = s.target("SELECT id FROM migration_runs WHERE domain_scope='BUSINESS' AND status='READY_FOR_REVIEW' ORDER BY completed_at DESC LIMIT 1;")[0]
    s.target('''CREATE TABLE IF NOT EXISTS contract_buyer_snapshots (
      source_contract_id BIGINT UNSIGNED NOT NULL, buyer_number TINYINT UNSIGNED NOT NULL,
      source_run_id CHAR(36) NOT NULL, buyer_snapshot JSON NOT NULL,
      snapshot_checksum CHAR(64) NOT NULL,
      person_id BIGINT UNSIGNED NULL,
      link_state ENUM('PENDING','VERIFIED') NOT NULL DEFAULT 'PENDING',
      PRIMARY KEY(source_contract_id,buyer_number),
      FOREIGN KEY(source_run_id) REFERENCES migration_runs(id),
      FOREIGN KEY(person_id) REFERENCES persons(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;''')
    if int(s.target('SELECT COUNT(*) FROM contract_buyer_snapshots;')[0]):
        raise RuntimeError('Buyer target occupied; refusing to overwrite signed snapshots.')
    values = []
    for contract in c.load(run_id,'bsunits','contracts'):
        for buyer in (1,2):
            payload = buyer_payload(contract,buyer)
            if not c.name_key(payload.get('name')) and not c.identity_number(payload.get('nid')):
                continue
            literal = s.literal(payload)
            values.append('('+','.join((s.literal(str(contract['id'])),s.literal(str(buyer)),s.literal(run_id),literal,'SHA2('+literal+',256)'))+')')
    statements = ['START TRANSACTION;']
    for offset in range(0,len(values),200):
        statements.append('INSERT INTO contract_buyer_snapshots(source_contract_id,buyer_number,source_run_id,buyer_snapshot,snapshot_checksum) VALUES '+','.join(values[offset:offset+200])+';')
    statements.append('COMMIT;')
    s.target('\n'.join(statements))
    actual = int(s.target('SELECT COUNT(*) FROM contract_buyer_snapshots;')[0])
    mismatch = int(s.target('SELECT COUNT(*) FROM contract_buyer_snapshots WHERE snapshot_checksum<>SHA2(buyer_snapshot,256);')[0])
    if actual != len(values) or mismatch:
        raise RuntimeError('Buyer snapshot reconciliation failed.')
    result = {'signed_buyer_snapshots':actual,'checksum_mismatches':mismatch,'verified_person_links':0,'scope':'Signed buyer values preserved; uncertain identity links remain PENDING.'}
    (s.ROOT/'outputs/unified-db/buyer-snapshot-result.json').write_text(json.dumps(result,indent=2)+'\n')
    print(json.dumps(result,indent=2))

if __name__ == '__main__':
    run()
