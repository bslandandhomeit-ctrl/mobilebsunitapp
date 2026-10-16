"""Apply the user-approved strict identity policy, never merge financial accounts."""
import collections
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('buyers',HERE/'promote-buyer-snapshots.py')
b = importlib.util.module_from_spec(spec)
spec.loader.exec_module(b)
c, s = b.c, b.s

def names(profile):
    result = set()
    for suffix in ('','_kh'):
        value = c.name_key(' '.join(str(profile.get(k+suffix) or '') for k in ('family_name','first_name','second_name','third_name')))
        if value:
            result.add(value)
    return result

def strict_keys(profile, national_ids):
    dob = c.valid_birthdate(profile.get('date_of_birth'))
    gender = str(profile.get('gender') or '').strip().upper()
    if len(national_ids)!=1 or not dob or gender not in ('M','F'):
        return set()
    return {(next(iter(national_ids)),str(dob),gender,name) for name in names(profile)}

def run():
    if s.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='person_source_map';"):
        raise RuntimeError('Identity resolution already recorded; refusing to rerun.')
    run_id = s.target("SELECT id FROM migration_runs WHERE domain_scope='BUSINESS' AND status='READY_FOR_REVIEW' ORDER BY completed_at DESC LIMIT 1;")[0]
    clients = {str(row['id']) for row in c.load(run_id,'beloan','tb_clients')}
    types = set(s.target("SELECT id FROM beloan_migration_stage.tb_cbc_identification_types WHERE code='N';"))
    ids = collections.defaultdict(set)
    for row in c.load(run_id,'beloan','tb_client_cbc_identification'):
        number = c.identity_number(row.get('id_number'))
        if str(row.get('id_type_id')) in types and number:
            ids[str(row['client_id'])].add(number)
    profiles = [row for row in c.load(run_id,'beloan','tb_client_cbc_general') if str(row['client_id']) in clients]
    parents = {int(row['id']):int(row['id']) for row in profiles}
    def root(pid):
        while parents[pid]!=pid:
            parents[pid] = parents[parents[pid]]
            pid = parents[pid]
        return pid
    keys = collections.defaultdict(set)
    for row in profiles:
        for key in strict_keys(row,ids[str(row['client_id'])]):
            keys[key].add(int(row['id']))
    for group in keys.values():
        roots = {root(pid) for pid in group}
        winner = min(roots)
        for pid in roots:
            parents[pid] = winner
    mapping = {pid:root(pid) for pid in parents}
    # Multiple accounts can safely share one person; no account is removed.
    statements = ['START TRANSACTION;', '''CREATE TABLE person_source_map (
      source_profile_id BIGINT UNSIGNED PRIMARY KEY,
      person_id BIGINT UNSIGNED NOT NULL,
      mapping_basis ENUM('SOURCE_PROFILE','STRICT_ID_NAME_DOB_GENDER') NOT NULL,
      policy_authority VARCHAR(100) NOT NULL,
      FOREIGN KEY(person_id) REFERENCES persons(id)
    ) ENGINE=InnoDB;''']
    # DDL commits implicitly; all following row changes remain atomic.
    statements.append('START TRANSACTION;')
    counts = collections.Counter(mapping.values())
    records = ['('+','.join((str(pid),str(target),s.literal('STRICT_ID_NAME_DOB_GENDER' if counts[target]>1 else 'SOURCE_PROFILE'),s.literal('User approved strict identity policy on 2026-10-07')))+')' for pid,target in mapping.items()]
    for offset in range(0,len(records),200):
        statements.append('INSERT INTO person_source_map VALUES '+','.join(records[offset:offset+200])+';')
    statements.extend(['DELETE FROM customer_account_members;',
      'INSERT INTO customer_account_members(account_id,person_id,source_applicant_type) SELECT g.client_id,m.person_id,g.applicant_type FROM beloan_migration_stage.tb_client_cbc_general g JOIN person_source_map m ON m.source_profile_id=g.id;',
      'DELETE p FROM persons p LEFT JOIN person_source_map m ON m.person_id=p.id WHERE m.person_id IS NULL;'])
    linked = 0
    ambiguous = 0
    for contract in c.load(run_id,'bsunits','contracts'):
        for buyer in (1,2):
            payload = b.buyer_payload(contract,buyer)
            number = c.identity_number(payload.get('nid'))
            dob = c.valid_birthdate(payload.get('birthdate'))
            gender = {'1':'M','2':'F'}.get(str(payload.get('gender')))
            name = c.name_key(payload.get('name'))
            if not number or not dob or not gender or not name:
                continue
            matches = {mapping[pid] for pid in keys.get((number,str(dob),gender,name),set())}
            if len(matches)==1:
                target = next(iter(matches))
                statements.append('UPDATE contract_buyer_snapshots SET person_id='+str(target)+",link_state='VERIFIED' WHERE source_contract_id="+s.literal(str(contract['id']))+' AND buyer_number='+str(buyer)+';')
                linked += 1
            elif matches:
                ambiguous += 1
    statements.append("UPDATE persons p SET identity_review_state='VERIFIED' WHERE EXISTS (SELECT 1 FROM person_source_map m WHERE m.person_id=p.id AND m.mapping_basis='STRICT_ID_NAME_DOB_GENDER') OR EXISTS (SELECT 1 FROM contract_buyer_snapshots b WHERE b.person_id=p.id AND b.link_state='VERIFIED');")
    statements.append('COMMIT;')
    s.target('\n'.join(statements))
    actual = int(s.target('SELECT COUNT(*) FROM persons;')[0])
    members = int(s.target('SELECT COUNT(*) FROM customer_account_members;')[0])
    links = int(s.target("SELECT COUNT(*) FROM contract_buyer_snapshots WHERE link_state='VERIFIED';")[0])
    if actual!=len(set(mapping.values())) or members!=len(profiles) or links!=linked:
        raise RuntimeError('Strict identity reconciliation failed.')
    report = {'source_profiles':len(profiles),'canonical_people':actual,'duplicate_profiles_collapsed':len(profiles)-actual,'accounts_merged':0,'account_memberships':members,'signed_buyer_links_verified':linked,'ambiguous_strict_buyer_matches':ambiguous,'policy':'Exact typed National ID + full name + valid DOB + gender; multiple National IDs remain unresolved. No signed snapshot values or loan accounts changed.'}
    (s.ROOT/'outputs/unified-db/customer-identity-result.json').write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps(report,indent=2))

if __name__ == '__main__':
    run()
