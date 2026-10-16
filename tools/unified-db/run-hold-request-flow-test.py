import importlib.util,json,subprocess
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
DB='unified_hold_flow_test';USER='unified_hold_test';created=False;user_created=False
if m.target('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='+m.literal(DB)):raise RuntimeError('Scratch exists')
try:
 m.target('CREATE DATABASE '+DB);created=True
 for table in ['unit_deposit_requests','unit_hold_requests']:m.target('CREATE TABLE '+DB+'.'+table+' LIKE bsunits_beloan_final.'+table)
 m.target('CREATE TABLE '+DB+'.unit_actions LIKE bsunits_migration_stage.unit_actions;CREATE TABLE '+DB+'.canonical_units LIKE bsunits_beloan_final.units')
 m.target('INSERT INTO '+DB+'.unit_deposit_requests SELECT * FROM bsunits_beloan_final.unit_deposit_requests LIMIT 1;UPDATE '+DB+".unit_deposit_requests SET user_id=1,status='APPROVED',is_selected_payment_option=1")
 m.target('INSERT INTO '+DB+'.canonical_units SELECT u.* FROM bsunits_beloan_final.units u JOIN '+DB+'.unit_deposit_requests d ON d.unit_id=u.id;UPDATE '+DB+'.canonical_units SET current_action_id=NULL;CREATE VIEW '+DB+'.units AS SELECT id,code,current_action_id AS unit_action_id,deleted_at FROM '+DB+'.canonical_units')
 for table in ['unit_types','projects','contract_templates']:m.target('CREATE TABLE '+DB+'.'+table+' LIKE bsunits_migration_stage.'+table)
 m.target('CREATE TABLE '+DB+'.users LIKE bsunits_migration_stage.users;INSERT INTO '+DB+'.users SELECT * FROM bsunits_migration_stage.users WHERE id=1')
 m.target("CREATE USER '"+USER+"'@'%' IDENTIFIED BY 'local-only-hold-test'");user_created=True
 m.target('GRANT SELECT,INSERT,UPDATE,DELETE ON '+DB+".* TO '"+USER+"'@'%'")
 r=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-hold-request-flow.php').read_bytes(),capture_output=True)
 try:d=json.loads(r.stdout)
 except ValueError:raise RuntimeError('Contract controller test failed; sensitive diagnostics withheld')
 if r.returncode or d.get('status')!='PASSED':raise RuntimeError('Contract controller test failed: '+json.dumps(d))
 m.target('CREATE TABLE '+DB+'.test_barrier(id INT PRIMARY KEY)')
 concurrency=[]
 for round_id in range(5):
  m.target('DELETE FROM '+DB+'.unit_hold_requests;DELETE FROM '+DB+'.unit_actions;DELETE FROM '+DB+'.test_barrier;INSERT INTO '+DB+".unit_actions(user_id,unit_id,action,status_action,actionable_type,actionable_id,created_at,updated_at) SELECT 1,id,'AVAILABLE','','App\\\\UnitHoldRequest',0,NOW(),NOW() FROM "+DB+'.canonical_units;UPDATE '+DB+".canonical_units SET current_action_id=LAST_INSERT_ID(),availability_state='AVAILABLE'")
  workers=[subprocess.Popen(['docker','exec','-i','-e','HOLD_WORKER_ID='+str(i),'bsunits-mobile-local-app-1','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE) for i in [1,2]]
  payload=(HERE/'test-hold-concurrency-worker.php').read_bytes()
  for worker in workers:worker.stdin.write(payload);worker.stdin.close();worker.stdin=None
  codes=[]
  for worker in workers:
   out,err=worker.communicate(timeout=30)
   if worker.returncode:raise RuntimeError('Hold concurrency worker failed; diagnostics withheld')
   codes.append(json.loads(out)['status_code'])
  if sorted(codes)!=[200,422]:raise RuntimeError('Competing holds not serialized: '+str(codes))
  approved=int(m.target('SELECT COUNT(*) FROM '+DB+".unit_hold_requests WHERE status='APPROVED'")[0])
  if approved!=1:raise RuntimeError('More than one hold approved')
  concurrency.append({'approved_requests':approved,'one_success_one_denial':True})
 approval_rounds=0
 for round_id in range(5):
  m.target('DELETE FROM '+DB+'.unit_hold_requests;DELETE FROM '+DB+'.unit_actions;DELETE FROM '+DB+'.test_barrier;INSERT INTO '+DB+".unit_hold_requests(user_id,unit_id,status,hold_day,release_date,created_at,updated_at) SELECT 1,id,'PENDING',2,DATE_ADD(NOW(),INTERVAL 2 DAY),NOW(),NOW() FROM "+DB+'.canonical_units;INSERT INTO '+DB+".unit_hold_requests(user_id,unit_id,status,hold_day,release_date,created_at,updated_at) SELECT 1,id,'PENDING',2,DATE_ADD(NOW(),INTERVAL 2 DAY),NOW(),NOW() FROM "+DB+'.canonical_units')
  m.target('INSERT INTO '+DB+".unit_actions(user_id,unit_id,action,status_action,actionable_type,actionable_id,created_at,updated_at) SELECT 1,unit_id,'HOLD','PENDING','App\\\\UnitHoldRequest',id,NOW(),NOW() FROM "+DB+'.unit_hold_requests;UPDATE '+DB+".canonical_units SET current_action_id=(SELECT MAX(id) FROM "+DB+".unit_actions),availability_state='RESERVED'")
  workers=[subprocess.Popen(['docker','exec','-i','-e','HOLD_WORKER_ID='+str(i),'-e','HOLD_APPROVAL_RACE=1','bsunits-mobile-local-app-1','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE) for i in [1,2]]
  for worker in workers:worker.stdin.write(payload);worker.stdin.close();worker.stdin=None
  codes=[]
  for worker in workers:
   out,err=worker.communicate(timeout=30)
   if worker.returncode:raise RuntimeError('Approval race worker failed; diagnostics withheld')
   codes.append(json.loads(out)['status_code'])
  if sorted(codes)!=[200,422]:raise RuntimeError('Competing approvals not serialized: '+str(codes))
  counts=m.target('SELECT status,COUNT(*) FROM '+DB+'.unit_hold_requests GROUP BY status')
  if sorted(counts)!=['APPROVED\t1','REJECTED\t1']:raise RuntimeError('Competing approvals status mismatch')
  approval_rounds+=1
 cancel_expiry_rounds=0
 for round_id in range(5):
  m.target('DELETE FROM '+DB+'.unit_hold_requests;DELETE FROM '+DB+'.unit_actions;DELETE FROM '+DB+'.test_barrier;INSERT INTO '+DB+".unit_hold_requests(user_id,unit_id,status,hold_day,release_date,created_at,updated_at) SELECT 1,id,'APPROVED',1,DATE_SUB(NOW(),INTERVAL 1 DAY),NOW(),NOW() FROM "+DB+'.canonical_units;INSERT INTO '+DB+".unit_actions(user_id,unit_id,action,status_action,actionable_type,actionable_id,created_at,updated_at) SELECT 1,unit_id,'HOLD','APPROVED','App\\\\UnitHoldRequest',id,NOW(),NOW() FROM "+DB+'.unit_hold_requests;UPDATE '+DB+".canonical_units SET current_action_id=(SELECT MAX(id) FROM "+DB+".unit_actions),availability_state='RESERVED'")
  workers=[subprocess.Popen(['docker','exec','-i','-e','HOLD_WORKER_ID='+str(i),'-e','HOLD_CANCEL_EXPIRY_RACE=1','bsunits-mobile-local-app-1','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE) for i in [1,2]]
  for worker in workers:worker.stdin.write(payload);worker.stdin.close();worker.stdin=None
  codes=[]
  for worker in workers:
   out,err=worker.communicate(timeout=30)
   if worker.returncode:raise RuntimeError('Cancel/expiry worker failed; diagnostics withheld')
   codes.append(json.loads(out)['status_code'])
  if codes[0] not in [200,409,422] or codes[1]!=200:raise RuntimeError('Cancel/expiry race unexpected response: '+str(codes))
  state=m.target('SELECT status FROM '+DB+'.unit_hold_requests')[0]
  releases=int(m.target('SELECT COUNT(*) FROM '+DB+".unit_actions WHERE action='AVAILABLE'")[0])
  availability=m.target('SELECT availability_state FROM '+DB+'.canonical_units')[0]
  if state not in ['CANCELLED','OVERDUE'] or releases!=1 or availability!='AVAILABLE':raise RuntimeError('Cancellation/expiry double release or invalid status')
  cancel_expiry_rounds+=1
 m.target('CREATE TABLE '+DB+'.jobs(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,queue VARCHAR(255) NOT NULL,payload LONGTEXT NOT NULL,attempts TINYINT UNSIGNED NOT NULL,reserved_at INT UNSIGNED NULL,available_at INT UNSIGNED NOT NULL,created_at INT UNSIGNED NOT NULL,INDEX queue_index(queue))')
 queue_evidence={}
 for phase in ['produce','consume']:
  queued=subprocess.run(['docker','exec','-i','-e','HOLD_QUEUE_PHASE='+phase,'bsunits-mobile-local-app-1','php'],input=(HERE/'test-hold-queue-worker.php').read_bytes(),capture_output=True)
  try:evidence=json.loads(queued.stdout)
  except ValueError:raise RuntimeError('Real queue test failed; sensitive diagnostics withheld')
  if queued.returncode or evidence.get('status')!='PASSED':raise RuntimeError('Real queue test failed: '+json.dumps(evidence))
  queue_evidence[phase]=evidence
 d['real_database_queue']=queue_evidence
 d['cancellation_expiry_race_rounds']=cancel_expiry_rounds;d['exactly_one_release_per_race']=True
 d['competing_approval_rounds']=approval_rounds;d['one_approved_one_rejected_per_round']=True
 d['concurrent_hold_rounds']=len(concurrency);d['independent_workers']=2;d['exactly_one_auto_approved_per_round']=True
 d['scope']='Original Hold controller/expiry job with disposable canonical units; five two-connection submission races and five competing approval and five cancellation/expiry races passed. Real database queue serialization/delay/worker verified in separate processes; external notifications isolated and live worker configuration/role middleware pending.'
 (HERE.parents[1]/'outputs/unified-db/hold-request-flow-test-result.json').write_text(json.dumps(d,indent=2)+'\n');print(json.dumps(d,indent=2))
finally:
 if created:m.target('DROP DATABASE '+DB)
 if user_created:m.target("DROP USER '"+USER+"'@'%'")
