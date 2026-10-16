import importlib.util,subprocess,json
from pathlib import Path
s=importlib.util.spec_from_file_location('s',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
scratch='unified_deposit_conversion_test'
if m.target("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='"+scratch+"'"):raise RuntimeError('Existing scratch schema; refusing overwrite.')
try:
 m.target('CREATE DATABASE '+scratch)
 tables=['loan_accounts','approved_deposit_sale_amendments','projects','branches','units','unit_types','project_accounting_branch_links','currencies','customer_accounts','persons','customer_account_members','customer_account_evidence','sales','sale_items','approved_deposit_conversion_keys','approved_deposit_sale_terms','approved_deposit_sale_links','unified_id_allocators','beloan_ui_customer_profiles','beloan_ui_client_cbc_general','beloan_ui_client_cbc_contact']
 for t in tables:m.target(f'CREATE TABLE {scratch}.{t} LIKE {t}')
 m.target(f'''INSERT INTO {scratch}.units SELECT u.* FROM units u JOIN unit_types t ON t.id=u.unit_type_id WHERE u.catalog_state='CURRENT' AND (SELECT COUNT(DISTINCT branch_id) FROM project_accounting_branch_links WHERE project_id=t.project_id)=1 LIMIT 1;
INSERT INTO {scratch}.unit_types SELECT t.* FROM unit_types t JOIN {scratch}.units u ON u.unit_type_id=t.id;
INSERT INTO {scratch}.project_accounting_branch_links SELECT l.* FROM project_accounting_branch_links l JOIN {scratch}.unit_types t ON t.project_id=l.project_id;
INSERT INTO {scratch}.projects SELECT p.* FROM projects p JOIN {scratch}.unit_types t ON t.project_id=p.id;
INSERT INTO {scratch}.branches SELECT b.* FROM branches b JOIN {scratch}.project_accounting_branch_links l ON l.branch_id=b.id;
GRANT SELECT ON {scratch}.* TO 'unified_beloan_sales_reader'@'%';
INSERT INTO {scratch}.currencies SELECT * FROM currencies WHERE code='USD';
INSERT INTO {scratch}.unified_id_allocators VALUES ('customer_accounts',100000),('persons',100000);
CREATE TABLE {scratch}.bsunits_actor_aliases AS SELECT * FROM bsunits_actor_aliases LIMIT 1;
GRANT SELECT,INSERT,UPDATE,DELETE ON {scratch}.* TO 'unified_deposit_writer'@'%';''')
 script=Path(__file__).with_name('test-approved-deposit-conversion.php').read_text()
 p=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=script,text=True,capture_output=True)
 if p.returncode:raise RuntimeError(p.stderr.strip() or 'PHP conversion failed; output withheld.')
 report=json.loads(p.stdout)
 m.target(f'INSERT INTO {scratch}.loan_accounts SELECT * FROM bsunits_beloan_final.loan_accounts LIMIT 1')
 m.target(f"CREATE TABLE {scratch}.approved_deposit_sale_revisions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,sale_id BIGINT UNSIGNED NOT NULL,deposit_request_id BIGINT UNSIGNED NOT NULL,before_snapshot LONGTEXT NOT NULL,after_snapshot LONGTEXT NOT NULL,before_sha256 CHAR(64) NOT NULL,after_sha256 CHAR(64) NOT NULL,approved_actor_id BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,UNIQUE KEY revision_once(sale_id,after_sha256)) ENGINE=InnoDB")
 amendment=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=Path(__file__).with_name('test-sale-amendment.php').read_text(),text=True,capture_output=True)
 if amendment.returncode:raise RuntimeError('Amendment test failed: '+amendment.stdout)
 report['sale_amendment']=json.loads(amendment.stdout)
 m.target(f'CREATE TABLE {scratch}.test_loan_account_template LIKE {scratch}.loan_accounts;INSERT INTO {scratch}.test_loan_account_template SELECT * FROM bsunits_beloan_final.loan_accounts LIMIT 1')
 m.target(f'CREATE TABLE {scratch}.amendment_test_barrier(id INT PRIMARY KEY)')
 amendment_rounds=[]
 payload=Path(__file__).with_name('test-sale-amendment-worker.php').read_text()
 for round_id in range(10):
  same_request=round_id<5
  m.target(f'DELETE FROM {scratch}.amendment_test_barrier')
  before=int(m.target(f'SELECT COUNT(*) FROM {scratch}.approved_deposit_sale_amendments')[0])
  sale_count=int(m.target(f'SELECT COUNT(*) FROM {scratch}.sales')[0])
  account_count=int(m.target(f'SELECT COUNT(*) FROM {scratch}.customer_accounts')[0])
  jobs=[]
  try:
   for worker_id in [1,2]:
    request_id=99990300+round_id*10+(0 if same_request else worker_id)
    job=subprocess.Popen(['docker','exec','-i','-e','AMENDMENT_WORKER_ID='+str(worker_id),'-e','AMENDMENT_REQUEST_ID='+str(request_id),'bsunits-mobile-local-app-1','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
    jobs.append(job);job.stdin.write(payload);job.stdin.close();job.stdin=None
   outcomes=[]
   for job in jobs:
    out,err=job.communicate(timeout=30)
    if job.returncode:raise RuntimeError('Amendment race worker failed: '+str({k:v for k,v in json.loads(out).items() if k in ['type','database_code']}))
    outcomes.append(json.loads(out)['outcome'])
  finally:
   for job in jobs:
    if job.poll() is None:job.kill();job.communicate()
  expected=['applied','replay'] if same_request else ['applied','denied']
  if sorted(outcomes)!=expected:raise RuntimeError('Amendment race inconsistent: '+str(outcomes))
  if int(m.target(f'SELECT COUNT(*) FROM {scratch}.approved_deposit_sale_amendments')[0])!=before+1 or int(m.target(f'SELECT COUNT(*) FROM {scratch}.sales')[0])!=sale_count or int(m.target(f'SELECT COUNT(*) FROM {scratch}.customer_accounts')[0])!=account_count:raise RuntimeError('Amendment race duplicated audit/customer/sale')
  amendment_rounds.append({'mode':'same_request' if same_request else 'competing_replacements','one_amendment_only':True})
 report['sale_amendment']['parallel_rounds']=len(amendment_rounds)
 report['sale_amendment']['same_request_and_competing_replacements']='PASSED'
 report['sale_amendment']['scope']='Explicit service on disposable target; financed denial, chains and ten two-process races verified; automatic queue and finance account-creation synchronization pending'
 m.target(f"CREATE TABLE {scratch}.unit_deposit_requests LIKE unit_deposit_requests;CREATE TABLE {scratch}.test_deposit_template AS SELECT * FROM unit_deposit_requests LIMIT 1;CREATE TABLE {scratch}.unified_approved_deposit_outbox (deposit_request_id BIGINT UNSIGNED PRIMARY KEY,request_snapshot LONGTEXT NOT NULL,snapshot_sha256 CHAR(64) NOT NULL,queued_at DATETIME NOT NULL)")
 processor=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=Path(__file__).with_name('test-same-connection-new-approval.php').read_text(),text=True,capture_output=True)
 if processor.returncode:raise RuntimeError('Same-connection new approval failed: '+processor.stdout)
 report['same_connection_new_approval']=json.loads(processor.stdout)
 revision=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=Path(__file__).with_name('test-sale-revision.php').read_text(),text=True,capture_output=True)
 if revision.returncode:raise RuntimeError('Sale revision test failed: '+revision.stdout)
 report['sale_revision']=json.loads(revision.stdout)
 handoff=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=Path(__file__).with_name('test-sale-finance-handoff.php').read_text(),text=True,capture_output=True)
 if handoff.returncode:raise RuntimeError('Finance handoff test failed: '+handoff.stdout)
 report['finance_handoff']=json.loads(handoff.stdout)
 cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+scratch+"' AND TABLE_NAME='units' ORDER BY ORDINAL_POSITION")
 m.target('INSERT INTO '+scratch+'.units SELECT '+','.join('99990900' if c=='id' else m.literal('SYNTHETIC-RACE-UNIT') if c=='code' else '`'+c+'`' for c in cols)+' FROM '+scratch+'.units LIMIT 1')
 handoff_rounds=[]
 payload=Path(__file__).with_name('test-sale-handoff-amendment-worker.php').read_text()
 for round_id in range(5):
  m.target(f"DELETE FROM {scratch}.amendment_test_barrier;DELETE FROM {scratch}.loan_accounts;UPDATE {scratch}.sales SET status='Ordered' WHERE id=(SELECT sale_id FROM {scratch}.approved_deposit_sale_amendments ORDER BY deposit_request_id DESC LIMIT 1)")
  current_unit=m.target(f'SELECT unit_id FROM {scratch}.sale_items WHERE sale_id=(SELECT sale_id FROM {scratch}.approved_deposit_sale_amendments ORDER BY deposit_request_id DESC LIMIT 1)')[0]
  target_unit=m.target(f'SELECT id FROM {scratch}.units WHERE id<>{current_unit} LIMIT 1')[0]
  audit_count=int(m.target(f'SELECT COUNT(*) FROM {scratch}.approved_deposit_sale_amendments')[0])
  sale_count=int(m.target(f'SELECT COUNT(*) FROM {scratch}.sales')[0])
  jobs=[]
  try:
   for worker_id in [1,2]:
    job=subprocess.Popen(['docker','exec','-i','-e','AMENDMENT_WORKER_ID='+str(worker_id),'-e','AMENDMENT_REQUEST_ID='+str(99990500+round_id),'-e','SYNTH_TARGET_UNIT='+str(target_unit),'-e','FORCE_FIRST_WORKER='+str(1 if round_id%2==0 else 2),'bsunits-mobile-local-app-1','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
    jobs.append(job);job.stdin.write(payload);job.stdin.close();job.stdin=None
   outcomes=[]
   for job in jobs:
    out,err=job.communicate(timeout=30)
    if job.returncode:raise RuntimeError('Handoff race worker failed: '+str({k:v for k,v in json.loads(out).items() if k in ['type','database_code']}))
    outcomes.append(json.loads(out)['outcome'])
  finally:
   for job in jobs:
    if job.poll() is None:job.kill();job.communicate()
  if outcomes not in [['amended','denied'],['denied','financed']]:raise RuntimeError('Handoff race inconsistent: '+str(outcomes))
  if outcomes[0]!=('amended' if round_id%2==0 else 'denied'):raise RuntimeError('Forced handoff lock order did not hold')
  financed=outcomes[1]=='financed'
  current_sale=m.target(f'SELECT status FROM {scratch}.sales WHERE id=(SELECT sale_id FROM {scratch}.approved_deposit_sale_amendments ORDER BY deposit_request_id DESC LIMIT 1)')[0]
  if int(m.target(f'SELECT COUNT(*) FROM {scratch}.loan_accounts')[0])!=(1 if financed else 0) or int(m.target(f'SELECT COUNT(*) FROM {scratch}.approved_deposit_sale_amendments')[0])!=audit_count+(0 if financed else 1) or current_sale!=('Accepted' if financed else 'Ordered') or int(m.target(f'SELECT COUNT(*) FROM {scratch}.sales')[0])!=sale_count:raise RuntimeError('Handoff race produced mixed financial state')
  saved_unit=m.target(f'SELECT unit_id FROM {scratch}.sale_items WHERE sale_id=(SELECT sale_id FROM {scratch}.approved_deposit_sale_amendments ORDER BY deposit_request_id DESC LIMIT 1)')[0]
  if saved_unit!=(current_unit if financed else target_unit):raise RuntimeError('Race changed wrong unit')
  handoff_rounds.append({'different_unit_target':True,'winner':'finance' if financed else 'amendment','consistent_sale_account_audit':True})
 report['finance_handoff']['parallel_amendment_rounds']=len(handoff_rounds)
 report['finance_handoff']['race_outcomes']=handoff_rounds
 report['finance_handoff']['scope']='Same-PDO services; five concurrent unit-first handoff/amendment races with synthetic target unit on disposable data; original accounting controller integration pending'
 m.target(f'CREATE VIEW {scratch}.tb_test_loan_account_template AS SELECT * FROM {scratch}.test_loan_account_template;CREATE VIEW {scratch}.tb_sale_order AS SELECT s.*,s.customer_account_id AS client_id FROM {scratch}.sales s;CREATE VIEW {scratch}.tb_sale_items AS SELECT * FROM {scratch}.sale_items;CREATE VIEW {scratch}.tb_client_loan_accounts AS SELECT a.*,a.customer_account_id AS client_id FROM {scratch}.loan_accounts a')
 acceptance=subprocess.run(['docker','exec','-i','beloan-local','php'],input=Path(__file__).with_name('test-original-sale-acceptance.php').read_text(),text=True,capture_output=True)
 if acceptance.returncode:raise RuntimeError('Original sale acceptance test failed: '+acceptance.stdout)
 report['original_sale_acceptance']=json.loads(acceptance.stdout)
 workers=[subprocess.Popen(['docker','exec','-i','-e','CONVERSION_TEST_MODE=concurrent','bsunits-mobile-local-app-1','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True) for _ in range(2)]
 for p in workers:p.stdin.write(script);p.stdin.close();p.stdin=None
 results=[]
 for p in workers:
  out,err=p.communicate(timeout=60)
  if p.returncode:raise RuntimeError(err.strip() or 'Concurrency test failed; output withheld.')
  results.append(json.loads(out))
 count=int(m.target(f"SELECT COUNT(*) FROM {scratch}.approved_deposit_sale_links WHERE deposit_request_id=99990003")[0])
 if count!=1 or sorted(r['replayed'] for r in results)!=[False,True]:raise RuntimeError('Concurrent requests did not produce one sale.')
 report['two_process_concurrency']='PASSED: one new conversion, one replay, one sale link'
 view_test=subprocess.run(['docker','exec','-i','-e','UNIFIED_SALES_TEST_DB='+scratch,'beloan-local','php'],input=Path(__file__).with_name('test-beloan-sales.php').read_text(),text=True,capture_output=True)
 if view_test.returncode:raise RuntimeError(view_test.stderr.strip() or 'Beloan converted-sale view failed.')
 view_report=json.loads(view_test.stdout)
 report['beloan_converted_sale_views']=view_report['status']
 customer_test=subprocess.run(['docker','exec','-i','-e','UNIFIED_SALES_TEST_DB='+scratch,'beloan-local','php'],input=Path(__file__).with_name('test-beloan-customers.php').read_text(),text=True,capture_output=True)
 if customer_test.returncode:raise RuntimeError(customer_test.stderr.strip() or 'Converted customer view failed.')
 customer_report=json.loads(customer_test.stdout);report['beloan_converted_customer_views']=customer_report['status']
 Path('outputs/unified-db/converted-customer-view-test-result.json').write_text(json.dumps(customer_report,indent=2)+'\n')
 Path('outputs/unified-db/converted-sale-beloan-view-test-result.json').write_text(json.dumps(view_report,indent=2)+'\n')
 Path('outputs/unified-db/approved-deposit-conversion-test-result.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
finally:m.target(f"REVOKE ALL PRIVILEGES ON {scratch}.* FROM 'unified_deposit_writer'@'%'; REVOKE ALL PRIVILEGES ON {scratch}.* FROM 'unified_beloan_sales_reader'@'%'; DROP DATABASE IF EXISTS {scratch}")
