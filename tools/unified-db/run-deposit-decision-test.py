import importlib.util,json,subprocess
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
DB='unified_deposit_decision_test';USER='unified_deposit_test';created=False;user_created=False
if m.target('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='+m.literal(DB)):raise RuntimeError('Scratch exists')
try:
 m.target('CREATE DATABASE '+DB);created=True
 for table in ['unit_deposit_requests','sales','loan_accounts','approved_deposit_sale_links','approved_deposit_sale_amendments']:m.target('CREATE TABLE '+DB+'.'+table+' LIKE bsunits_beloan_final.'+table)
 m.target('CREATE TABLE '+DB+'.test_sale_template LIKE bsunits_beloan_final.sales;INSERT INTO '+DB+'.test_sale_template SELECT * FROM bsunits_beloan_final.sales LIMIT 1')
 m.target('CREATE TABLE '+DB+'.test_loan_account_template LIKE bsunits_beloan_final.loan_accounts;INSERT INTO '+DB+'.test_loan_account_template SELECT * FROM bsunits_beloan_final.loan_accounts LIMIT 1')
 for table in ['discount_promotions','discount_promotion_items','unit_hold_requests','unit_types','projects','contract_templates','unit_actions','users','roles','model_has_roles','permissions','model_has_permissions','role_has_permissions']:m.target('CREATE TABLE '+DB+'.'+table+' LIKE bsunits_migration_stage.'+table)
 for table in ['users','roles','model_has_roles']:m.target('INSERT INTO '+DB+'.'+table+' SELECT * FROM bsunits_migration_stage.'+table)
 m.target('CREATE TABLE '+DB+'.canonical_units LIKE bsunits_beloan_final.units;INSERT INTO '+DB+'.unit_deposit_requests SELECT * FROM bsunits_beloan_final.unit_deposit_requests LIMIT 1')
 m.target('INSERT INTO '+DB+'.canonical_units SELECT u.* FROM bsunits_beloan_final.units u JOIN '+DB+'.unit_deposit_requests d ON d.unit_id=u.id;UPDATE '+DB+'.canonical_units SET current_action_id=NULL')
 m.target('CREATE TABLE '+DB+'.legacy_units LIKE bsunits_migration_stage.units;INSERT INTO '+DB+'.legacy_units SELECT u.* FROM bsunits_migration_stage.units u JOIN '+DB+'.canonical_units c ON c.id=u.id;INSERT INTO '+DB+'.unit_types SELECT t.* FROM bsunits_migration_stage.unit_types t JOIN '+DB+'.legacy_units u ON u.unit_type_id=t.id')
 m.target('CREATE VIEW '+DB+'.units AS SELECT c.id,c.code,c.current_action_id AS unit_action_id,c.deleted_at,c.list_price AS price,u.unit_type_id FROM '+DB+'.canonical_units c JOIN '+DB+'.legacy_units u ON u.id=c.id')
 m.target("CREATE USER '"+USER+"'@'%' IDENTIFIED BY 'local-only-deposit-test'");user_created=True
 m.target('GRANT SELECT,INSERT,UPDATE,DELETE,ALTER,DROP,CREATE ON '+DB+".* TO '"+USER+"'@'%'")
 r=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-deposit-decision.php').read_bytes(),capture_output=True)
 try:d=json.loads(r.stdout)
 except ValueError:raise RuntimeError('Deposit decision test failed; sensitive diagnostics withheld')
 if r.returncode or d.get('status')!='PASSED':raise RuntimeError('Deposit decision test failed: '+json.dumps(d))
 r=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-deposit-create.php').read_bytes(),capture_output=True)
 try:creation=json.loads(r.stdout)
 except ValueError:raise RuntimeError('Creation test failed; diagnostics withheld')
 if r.returncode or creation.get('status')!='PASSED':raise RuntimeError('Creation test failed: '+json.dumps(creation))
 d['creation']=creation
 m.target('DELETE FROM '+DB+'.unit_deposit_requests WHERE id<>(SELECT keep_id FROM (SELECT MIN(id) keep_id FROM '+DB+'.unit_deposit_requests) k)')
 m.target('CREATE TABLE '+DB+'.test_barrier(id INT PRIMARY KEY)')
 rounds=[]
 payload=(HERE/'test-deposit-concurrency-worker.php').read_bytes()
 for decisions in [('approve','approve'),('approve','reject'),('cancel','cancel'),('approve','cancel')]:
  for round_id in range(5):
   state='APPROVED' if decisions==('cancel','cancel') else 'PENDING'
   m.target('DELETE FROM '+DB+'.unit_actions;DELETE FROM '+DB+'.test_barrier;UPDATE '+DB+".unit_deposit_requests SET status='"+state+"',unit_controller_status='"+state+"',sale_manager_status='NOT_REQUIRED';INSERT INTO "+DB+".unit_actions(user_id,unit_id,action,status_action,actionable_type,actionable_id,created_at,updated_at) SELECT 1,unit_id,'DEPOSIT','"+state+"',"+m.literal('App\\UnitDepositRequest')+",id,NOW(),NOW() FROM "+DB+'.unit_deposit_requests;UPDATE '+DB+".canonical_units SET current_action_id=(SELECT MAX(id) FROM "+DB+".unit_actions),availability_state='RESERVED'")
   workers=[]
   try:
    for i,decision in enumerate(decisions,1):
     worker=subprocess.Popen(['docker','exec','-i','-e','DEPOSIT_WORKER_ID='+str(i),'-e','DEPOSIT_DECISION='+decision,'bsunits-mobile-local-app-1','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
     workers.append(worker)
     worker.stdin.write(payload);worker.stdin.close();worker.stdin=None
    codes=[]
    for worker in workers:
     out,err=worker.communicate(timeout=30)
     if worker.returncode:raise RuntimeError('Deposit race worker failed; diagnostics withheld')
     codes.append(json.loads(out)['status_code'])
   finally:
    for worker in workers:
     if worker.poll() is None:worker.kill();worker.communicate()
   status=m.target('SELECT status FROM '+DB+'.unit_deposit_requests')[0]
   availability=m.target('SELECT availability_state FROM '+DB+'.canonical_units')[0]
   releases=int(m.target('SELECT COUNT(*) FROM '+DB+".unit_actions WHERE action='AVAILABLE'")[0])
   if decisions==('approve','cancel'):
    if codes[0]!=200 or codes[1] not in [200,422] or status!=('CANCELLED' if codes[1]==200 else 'APPROVED'):raise RuntimeError('Approval/cancel serialization failed')
   elif sorted(codes)!=[200,422]:raise RuntimeError('Competing decisions not serialized: '+str(codes))
   expected='AVAILABLE' if status in ['REJECTED','CANCELLED'] else 'RESERVED'
   if availability!=expected or releases!=(1 if expected=='AVAILABLE' else 0):raise RuntimeError('Decision state or release mismatch')
   rounds.append({'decisions':list(decisions),'status_codes':codes,'final_status':status,'consistent_availability':True,'single_release':True})
 creation_rounds=0
 payload=(HERE/'test-deposit-create-concurrency-worker.php').read_bytes()
 for round_id in range(5):
  m.target('DELETE FROM '+DB+'.unit_deposit_requests;DELETE FROM '+DB+'.unit_actions;DELETE FROM '+DB+'.test_barrier;INSERT INTO '+DB+".unit_actions(user_id,unit_id,action,status_action,actionable_type,actionable_id,created_at,updated_at) SELECT 1,id,'AVAILABLE','', '',0,NOW(),NOW() FROM "+DB+'.canonical_units;UPDATE '+DB+".canonical_units SET current_action_id=(SELECT MAX(id) FROM "+DB+".unit_actions),availability_state='AVAILABLE'")
  workers=[]
  try:
   for i in [1,2]:
    worker=subprocess.Popen(['docker','exec','-i','-e','DEPOSIT_WORKER_ID='+str(i),'bsunits-mobile-local-app-1','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    workers.append(worker);worker.stdin.write(payload);worker.stdin.close();worker.stdin=None
   codes=[]
   for worker in workers:
    out,err=worker.communicate(timeout=30)
    if worker.returncode:raise RuntimeError('Creation race worker failed; diagnostics withheld')
    codes.append(json.loads(out)['status_code'])
  finally:
   for worker in workers:
    if worker.poll() is None:worker.kill();worker.communicate()
  if sorted(codes)!=[200,422]:raise RuntimeError('Competing creations not serialized: '+str(codes))
  count=int(m.target('SELECT COUNT(*) FROM '+DB+'.unit_deposit_requests')[0])
  owned=int(m.target('SELECT COUNT(*) FROM '+DB+'.canonical_units u JOIN '+DB+'.unit_actions a ON a.id=u.current_action_id JOIN '+DB+".unit_deposit_requests d ON d.id=a.actionable_id AND d.unit_id=u.id WHERE a.action='DEPOSIT' AND d.status='PENDING' AND u.availability_state='RESERVED'")[0])
  if count!=1 or owned!=1:raise RuntimeError('Duplicate creation or incorrect canonical reservation')
  creation_rounds+=1
 m.target('INSERT INTO '+DB+'.canonical_units SELECT c.* FROM bsunits_beloan_final.units c JOIN bsunits_migration_stage.units u ON u.id=c.id WHERE c.id NOT IN (SELECT id FROM '+DB+".canonical_units) AND c.catalog_state='CURRENT' AND c.is_saleable=1 AND c.deleted_at IS NULL AND u.unit_type_id=(SELECT unit_type_id FROM "+DB+'.legacy_units LIMIT 1) LIMIT 1;INSERT INTO '+DB+'.legacy_units SELECT u.* FROM bsunits_migration_stage.units u JOIN '+DB+'.canonical_units c ON c.id=u.id WHERE u.id NOT IN (SELECT id FROM '+DB+'.legacy_units)')
 r=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-deposit-transfer.php').read_bytes(),capture_output=True)
 try:transfer=json.loads(r.stdout)
 except ValueError:raise RuntimeError('Transfer test failed; diagnostics withheld')
 if r.returncode or transfer.get('status')!='PASSED':raise RuntimeError('Transfer test failed: '+json.dumps(transfer))
 d['transfer']=transfer
 m.target('INSERT INTO '+DB+'.canonical_units SELECT c.* FROM bsunits_beloan_final.units c JOIN bsunits_migration_stage.units u ON u.id=c.id WHERE c.id NOT IN (SELECT id FROM '+DB+".canonical_units) AND c.catalog_state='CURRENT' AND c.is_saleable=1 AND c.deleted_at IS NULL AND u.unit_type_id=(SELECT unit_type_id FROM "+DB+'.legacy_units LIMIT 1) LIMIT 1;INSERT INTO '+DB+'.legacy_units SELECT u.* FROM bsunits_migration_stage.units u JOIN '+DB+'.canonical_units c ON c.id=u.id WHERE u.id NOT IN (SELECT id FROM '+DB+'.legacy_units)')
 transfer_rounds=0
 payload=(HERE/'test-deposit-transfer-concurrency-worker.php').read_bytes()
 for round_id in range(5):
  setup=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-deposit-transfer-race-setup.php').read_bytes(),capture_output=True)
  if setup.returncode or json.loads(setup.stdout).get('status')!='PASSED':raise RuntimeError('Transfer race setup failed')
  workers=[]
  try:
   for i in [1,2]:
    worker=subprocess.Popen(['docker','exec','-i','-e','DEPOSIT_WORKER_ID='+str(i),'bsunits-mobile-local-app-1','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    workers.append(worker);worker.stdin.write(payload);worker.stdin.close();worker.stdin=None
   codes=[]
   for worker in workers:
    out,err=worker.communicate(timeout=30)
    if worker.returncode:raise RuntimeError('Transfer race worker failed; diagnostics withheld')
    codes.append(json.loads(out)['status_code'])
  finally:
   for worker in workers:
    if worker.poll() is None:worker.kill();worker.communicate()
  if sorted(codes)!=[200,422]:raise RuntimeError('Competing transfers not serialized: '+str(codes))
  target_owners=int(m.target('SELECT COUNT(*) FROM '+DB+'.unit_deposit_requests WHERE unit_id=(SELECT MAX(id) FROM '+DB+'.canonical_units)')[0])
  owned=int(m.target('SELECT COUNT(*) FROM '+DB+'.canonical_units u JOIN '+DB+'.unit_actions a ON a.id=u.current_action_id JOIN '+DB+".unit_deposit_requests d ON d.id=a.actionable_id AND d.unit_id=u.id WHERE a.action='DEPOSIT' AND d.status='PENDING' AND u.availability_state='RESERVED'")[0])
  available=int(m.target('SELECT COUNT(*) FROM '+DB+".canonical_units WHERE availability_state='AVAILABLE'")[0])
  if target_owners!=1 or owned!=2 or available!=1:raise RuntimeError('Transfer race damaged winner or losing reservation')
  transfer_rounds+=1
 edit_approval_rounds=0
 payload=(HERE/'test-deposit-edit-approval-worker.php').read_bytes()
 for round_id in range(5):
  setup=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-deposit-transfer-race-setup.php').read_bytes(),capture_output=True)
  if setup.returncode or json.loads(setup.stdout).get('status')!='PASSED':raise RuntimeError('Edit/approval setup failed')
  before=m.target('SELECT unit_sale_price,deposit_amount,receiving_amount FROM '+DB+'.unit_deposit_requests ORDER BY id LIMIT 1')[0]
  workers=[]
  try:
   for i in [1,2]:
    worker=subprocess.Popen(['docker','exec','-i','-e','DEPOSIT_WORKER_ID='+str(i),'bsunits-mobile-local-app-1','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    workers.append(worker);worker.stdin.write(payload);worker.stdin.close();worker.stdin=None
   codes=[]
   for worker in workers:
    out,err=worker.communicate(timeout=30)
    if worker.returncode:raise RuntimeError('Edit/approval worker failed; diagnostics withheld')
    codes.append(json.loads(out)['status_code'])
  finally:
   for worker in workers:
    if worker.poll() is None:worker.kill();worker.communicate()
  if codes[0] not in [200,422] or codes[1]!=200:raise RuntimeError('Edit/approval race inconsistent: '+str(codes))
  after=m.target('SELECT unit_sale_price,deposit_amount,receiving_amount FROM '+DB+'.unit_deposit_requests ORDER BY id LIMIT 1')[0]
  state=m.target('SELECT status,unit_controller_status FROM '+DB+'.unit_deposit_requests ORDER BY id LIMIT 1')[0]
  edited=int(m.target('SELECT COUNT(*) FROM (SELECT remark FROM '+DB+".unit_deposit_requests ORDER BY id LIMIT 1) d WHERE remark='Synthetic concurrent edit'")[0])
  if before!=after or state!='APPROVED\tAPPROVED' or edited!=(1 if codes[0]==200 else 0):raise RuntimeError('Approved request was changed after approval or financial history changed')
  edit_approval_rounds+=1
 d['parallel_edit_approval_rounds']=edit_approval_rounds
 d['parallel_transfer_rounds']=transfer_rounds
 d['parallel_creation_rounds']=creation_rounds
 d['parallel_decision_rounds']=len(rounds);d['parallel_decisions']=rounds
 d['scope']='Disposable tables, original controller and real role relationships; 20 decision races, five submission races and five transfer races and five edit/approval races with two independent PHP database connections; notifications faked; live cutover not tested'
 (HERE.parents[1]/'outputs/unified-db/deposit-decision-test-result.json').write_text(json.dumps(d,indent=2)+'\n');print(json.dumps(d,indent=2))
finally:
 if created:m.target('DROP DATABASE '+DB)
 if user_created:m.target("DROP USER '"+USER+"'@'%'")
