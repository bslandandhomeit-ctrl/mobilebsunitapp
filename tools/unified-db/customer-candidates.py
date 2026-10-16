"""Identify review candidates only; never merge people by name or phone."""
import collections
import datetime
import importlib.util
import json
import pathlib

HERE = pathlib.Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
staging = importlib.util.module_from_spec(spec)
spec.loader.exec_module(staging)

def identity_number(value):
    if value is None:
        return None
    value = str(value).strip().casefold()
    return None if not value or value in ('0','n/a','na','none','null','-') else value

def valid_birthdate(value):
    try:
        return datetime.date.fromisoformat(str(value))
    except ValueError:
        return None

def name_key(value):
    return ' '.join(str(value or '').casefold().split())

def load(run_id,source,table):
    return [json.loads(row,parse_float=str) for row in staging.target(
        'SELECT source_payload FROM migration_source_records WHERE run_id='+staging.literal(run_id)
        +' AND source_system='+staging.literal(source)+' AND source_table='+staging.literal(table)+';')]

def run():
    run_id = staging.target("SELECT id FROM migration_runs WHERE domain_scope='BUSINESS' "
        "AND status='READY_FOR_REVIEW' ORDER BY completed_at DESC LIMIT 1;")[0]
    staging.target('''CREATE TABLE IF NOT EXISTS migration_customer_candidates (
      run_id CHAR(36) NOT NULL, source_contract_id VARCHAR(100) NOT NULL,
      buyer_number TINYINT UNSIGNED NOT NULL, beloan_client_id VARCHAR(100) NOT NULL,
      evidence JSON NOT NULL, decision ENUM('PENDING','LINK','KEEP_SEPARATE') NOT NULL DEFAULT 'PENDING',
      reviewed_by VARCHAR(100) NULL, reviewed_at TIMESTAMP NULL, review_reason TEXT NULL,
      PRIMARY KEY(run_id,source_contract_id,buyer_number,beloan_client_id),
      FOREIGN KEY(run_id) REFERENCES migration_runs(id)
    ) ENGINE=InnoDB;''')
    clients = {str(r['id']) for r in load(run_id,'beloan','tb_clients')}
    general = collections.defaultdict(list)
    for row in load(run_id,'beloan','tb_client_cbc_general'):
        general[str(row['client_id'])].append(row)
    source = staging.SOURCES['beloan']
    national_types = set(staging.mysql(source[0],source[1],source[2],
        "SELECT id FROM tb_cbc_identification_types WHERE code='N';"))
    identities = collections.defaultdict(set)
    for row in load(run_id,'beloan','tb_client_cbc_identification'):
        number = identity_number(row.get('id_number'))
        client = str(row.get('client_id'))
        if number and str(row.get('id_type_id')) in national_types and client in clients:
            identities[number].add(client)
    metrics = collections.Counter()
    metrics['beloan_national_id_keys_shared_by_multiple_accounts'] = sum(len(ids)>1 for ids in identities.values())
    batches = []
    for contract in load(run_id,'bsunits','contracts'):
        for buyer in (1,2):
            prefix = 'customer'+str(buyer)+'_'
            if not name_key(contract.get(prefix+'name')) and not identity_number(contract.get(prefix+'nid')):
                continue
            metrics['contract_buyer_snapshots'] += 1
            number = identity_number(contract.get(prefix+'nid'))
            if not number:
                metrics['buyer_snapshots_without_usable_national_id'] += 1
                continue
            matching_clients = identities.get(number,set())
            if not matching_clients:
                metrics['buyer_snapshots_without_national_id_candidate'] += 1
            else:
                metrics['buyer_snapshots_with_national_id_candidate'] += 1
            for client in matching_clients:
                birthdate = valid_birthdate(contract.get(prefix+'birthdate'))
                bs_gender = {'1':'M','2':'F'}.get(str(contract.get(prefix+'gender')))
                people = general.get(client,[])
                evidence = {'national_id_exact_after_trim_casefold':True,
                    'birthdate_matches':bool(birthdate and any(valid_birthdate(p.get('date_of_birth'))==birthdate for p in people)),
                    'gender_matches':bool(bs_gender and any(p.get('gender')==bs_gender for p in people)),
                    'name_matches':any(name_key(contract.get(prefix+'name')) in (
                        name_key(str(p.get('family_name') or '')+' '+str(p.get('first_name') or '')),
                        name_key(str(p.get('family_name_kh') or '')+' '+str(p.get('first_name_kh') or '')),
                    ) for p in people),
                    'national_id_shared_by_multiple_accounts':len(matching_clients)>1,
                    'policy':'Review required; an ID-number match does not automatically merge accounts or people.'}
                if evidence['birthdate_matches'] and evidence['gender_matches'] and evidence['name_matches']:
                    metrics['candidate_links_with_name_birthdate_gender_support'] += 1
                batches.append('('+','.join(staging.literal(v) for v in [run_id,str(contract['id']),
                    str(buyer),client,evidence])+')')
    statements = ['START TRANSACTION;']
    for offset in range(0,len(batches),200):
        statements.append('INSERT IGNORE INTO migration_customer_candidates '
            '(run_id,source_contract_id,buyer_number,beloan_client_id,evidence) VALUES '
            +','.join(batches[offset:offset+200])+';')
    statements.append('COMMIT;')
    staging.target('\n'.join(statements))
    actual = int(staging.target('SELECT COUNT(*) FROM migration_customer_candidates WHERE run_id='
                               +staging.literal(run_id)+';')[0])
    if actual != len(batches):
        raise RuntimeError('Customer candidate reconciliation failed.')
    metrics['review_candidate_links'] = actual
    output = {'run_id':run_id,'metrics':dict(metrics),
        'scope':'Candidate links only. No customer merge or financial writes. No personal data in this report.'}
    (staging.ROOT/'outputs'/'unified-db'/'customer-candidate-result.json').write_text(json.dumps(output,indent=2))
    print(json.dumps(output,indent=2))

if __name__ == '__main__':
    run()
