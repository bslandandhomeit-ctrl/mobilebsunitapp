"""Preserve customer/sale/request/loan source snapshots and verify source relationships.

No customer merges, source edits, financial recalculation or app connection changes.
Original JSON numeric text is retained verbatim, avoiding float rounding in snapshots.
"""
import collections
import datetime
import hashlib
import importlib.util
import json
import pathlib
import uuid

HERE = pathlib.Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('property_staging',HERE/'stage-properties.py')
staging = importlib.util.module_from_spec(spec)
spec.loader.exec_module(staging)

TABLES = {
    'beloan': {
        'tb_clients':'customer_account', 'tb_client_cbc_general':'customer_person',
        'tb_client_cbc_identification':'identification', 'tb_client_cbc_address':'address',
        'tb_client_cbc_contact':'contact', 'tb_client_cbc_spouse':'spouse',
        'tb_client_cbc_employer':'employer', 'tb_save_draft':'draft',
        'tb_sale_order':'sale', 'tb_sale_items':'sale_item',
        'tb_client_loan_accounts':'loan_account', 'tb_drawdown_account':'drawdown',
        'tb_loans':'loan',
    },
    'bsunits': {
        'unit_hold_requests':'hold_request','unit_deposit_requests':'deposit_request',
        'unit_contract_requests':'contract_request','contracts':'contract',
        'unit_contract_request_attachments':'request_attachment',
        'contract_attachments':'contract_attachment',
    },
}
RELATIONS = {
    'beloan': {
        **{t:[('client_id','tb_clients','id')] for t in (
            'tb_client_cbc_general','tb_client_cbc_identification','tb_client_cbc_address',
            'tb_client_cbc_contact','tb_client_cbc_spouse','tb_client_cbc_employer','tb_save_draft')},
        'tb_sale_order':[('client_id','tb_clients','id')],
        'tb_sale_items':[('sale_id','tb_sale_order','id'),('unit_id','tb_units','id')],
        'tb_client_loan_accounts':[('client_id','tb_clients','id'),('sale_id','tb_sale_order','id')],
        'tb_drawdown_account':[('client_id','tb_clients','id'),
            ('client_loan_id','tb_client_loan_accounts','id'),('sale_id','tb_sale_order','id')],
        'tb_loans':[('client_id','tb_clients','id'),('loan_account_id','tb_client_loan_accounts','id'),
            ('unit_id','tb_units','id'),('drawdown_acc','tb_drawdown_account','account_no')],
    },
    'bsunits': {
        'unit_hold_requests':[('unit_id','units','id')],
        'unit_deposit_requests':[('unit_id','units','id')],
        'unit_contract_requests':[('unit_id','units','id'),
            ('unit_deposit_request_id','unit_deposit_requests','id'),('contract_id','contracts','id')],
        'contracts':[('unit_id','units','id'),('unit_contract_request_id','unit_contract_requests','id')],
        'unit_contract_request_attachments':[('unit_contract_request_id','unit_contract_requests','id')],
        'contract_attachments':[('contract_id','contracts','id')],
    },
}

def relationship(value, index):
    if value is None or str(value).strip() in ('','0'):
        return 'EMPTY',[]
    matches = index.get(str(value),[])
    return ('MISSING' if not matches else 'MATCHED' if len(matches)==1 else 'AMBIGUOUS'),matches

def insert_batches(statements):
    for statement in statements:
        staging.target(statement)

def run():
    staging.target((HERE/'schema.sql').read_text())
    staging.target('''CREATE TABLE IF NOT EXISTS migration_source_relationships (
      run_id CHAR(36) NOT NULL, source_system VARCHAR(20) NOT NULL,
      source_table VARCHAR(100) NOT NULL, source_id VARCHAR(100) NOT NULL,
      reference_column VARCHAR(100) NOT NULL, target_source_table VARCHAR(100) NOT NULL,
      target_source_ids JSON NOT NULL,
      reference_status ENUM('MATCHED','EMPTY','MISSING','AMBIGUOUS') NOT NULL,
      PRIMARY KEY(run_id,source_system,source_table,source_id,reference_column),
      FOREIGN KEY(run_id) REFERENCES migration_runs(id)
    ) ENGINE=InnoDB;''')
    run_id = str(uuid.uuid4())
    staging.target('INSERT INTO migration_runs(id,status,source_databases,domain_scope) VALUES ('
        +staging.literal(run_id)+",'LOADING',"+staging.literal({k:v[2] for k,v in staging.SOURCES.items()})
        +",'BUSINESS');")
    counts = {}
    relation_counts = collections.Counter()
    financial_hashes = {}
    try:
        for source, tables in TABLES.items():
            cache = {table:staging.raw_snapshots(source,table) for table in tables}
            indexes = {}
            for table, mappings in RELATIONS[source].items():
                for _,target,column in mappings:
                    if (target,column) not in indexes:
                        if target not in cache:
                            cache[target] = staging.raw_snapshots(source,target)
                        index = collections.defaultdict(list)
                        for record,_ in cache[target]:
                            index[str(record.get(column))].append(str(record['id']))
                        indexes[(target,column)] = index
            for table,entity in tables.items():
                records = cache[table]
                counts[source+':'+table] = len(records)
                checksum = hashlib.sha256()
                # Each table is committed atomically. Failed runs are never eligible for promotion.
                statements = ['START TRANSACTION;']
                for offset in range(0,len(records),200):
                    batch = []
                    edges = []
                    for record,payload in records[offset:offset+200]:
                        digest = hashlib.sha256(payload.encode()).hexdigest()
                        checksum.update((str(record['id'])+':'+digest+'\n').encode())
                        batch.append('('+','.join(staging.literal(v) for v in [run_id,source,
                            table,str(record['id']),entity,None,payload,digest])+')')
                        for column,target,key_column in RELATIONS[source].get(table,[]):
                            status,ids = relationship(record.get(column),indexes[(target,key_column)])
                            relation_counts[source+':'+table+'.'+column+':'+status] += 1
                            edges.append('('+','.join(staging.literal(v) for v in [run_id,source,
                                table,str(record['id']),column,target,ids,status])+')')
                    statements.append('INSERT INTO migration_source_records VALUES '+','.join(batch)+';')
                    if edges:
                        statements.append('INSERT INTO migration_source_relationships VALUES '+','.join(edges)+';')
                statements.append('COMMIT;')
                # One session per table preserves transaction atomicity.
                staging.target('\n'.join(statements))
                if entity in ('sale','sale_item','loan_account','drawdown','loan','contract','deposit_request'):
                    financial_hashes[source+':'+table] = checksum.hexdigest()
                print(source+':'+table+' staged '+str(len(records)),flush=True)
        actual = int(staging.target('SELECT COUNT(*) FROM migration_source_records WHERE run_id='
                                   +staging.literal(run_id)+';')[0])
        if actual != sum(counts.values()):
            raise RuntimeError('Business staging row count mismatch.')
        mismatches = int(staging.target('SELECT COUNT(*) FROM migration_source_records WHERE run_id='
            +staging.literal(run_id)+' AND SHA2(source_payload,256)<>payload_sha256;')[0])
        if mismatches:
            raise RuntimeError('Source snapshot checksum mismatch.')
        report = {'run_id':run_id,'domain_scope':'BUSINESS','record_counts':counts,
            'relationship_counts':dict(relation_counts),'financial_snapshot_hashes':financial_hashes,
            'verified_record_count':actual,'snapshot_checksum_mismatches':mismatches,
            'scope':'Source snapshots only. No customer merges or canonical financial writes.',
            'built_at':datetime.datetime.now(datetime.timezone.utc).isoformat()}
        staging.target("UPDATE migration_runs SET status='READY_FOR_REVIEW',completed_at=CURRENT_TIMESTAMP,summary="
            +staging.literal(report)+' WHERE id='+staging.literal(run_id)+';')
        output = staging.ROOT/'outputs'/'unified-db'/'business-staging-result.json'
        output.write_text(json.dumps(report,indent=2))
        print('Verified '+str(actual)+' snapshots; zero checksum mismatches.',flush=True)
    except Exception:
        staging.target("UPDATE migration_runs SET status='FAILED',completed_at=CURRENT_TIMESTAMP WHERE id="
                       +staging.literal(run_id)+';')
        raise

if __name__ == '__main__':
    run()
