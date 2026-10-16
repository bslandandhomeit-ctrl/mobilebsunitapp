import importlib.util,subprocess,json
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
DB='unified_account_creation_test';USER='unified_account_creation_test';created=False;user=False
if m.target("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='"+DB+"'"):raise RuntimeError('Scratch exists')
try:
 m.target('CREATE DATABASE '+DB);created=True
 canonical={'coa_categories':'coa_categories','client_loan_accounts':'loan_accounts','drawdown_account':'drawdown_accounts','sale_order':'sales','sale_items':'sale_items'}
 for t in ['coa_categories','client_loan_accounts','drawdown_account','user_activity','audit','units','sale_order','sale_items']:
  if t not in canonical:
   m.target('CREATE TABLE '+DB+'.tb_'+t+' LIKE beloan_migration_stage.tb_'+t)
   continue
  target=canonical[t]
  m.target('CREATE TABLE '+DB+'.'+target+' LIKE bsunits_beloan_final.'+target)
  cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='beloan_migration_stage' AND TABLE_NAME='tb_"+t+"' ORDER BY ORDINAL_POSITION")
  projection=[]
  for col in cols:
   actual='customer_account_id' if col=='client_id' and t in ['client_loan_accounts','drawdown_account','sale_order'] else col
   projection.append('`'+actual+'` AS `'+col+'`')
  m.target('CREATE ALGORITHM=MERGE SQL SECURITY INVOKER VIEW '+DB+'.tb_'+t+' AS SELECT '+','.join(projection)+' FROM '+DB+'.'+target)
 for table in ['customer_accounts','currencies','units','unit_types','projects','branches','project_accounting_branch_links','approved_deposit_sale_terms','unit_actions','approved_deposit_sale_links','approved_deposit_sale_amendments','unit_contract_requests','approved_deposit_sale_revisions']:
  m.target('CREATE TABLE '+DB+'.'+table+' LIKE bsunits_beloan_final.'+table)
 for table in ['users','roles','model_has_roles','permissions','model_has_permissions','role_has_permissions']:
  m.target('CREATE TABLE '+DB+'.'+table+' LIKE bsunits_migration_stage.'+table)
  if table in ['users','roles','model_has_roles']:m.target('INSERT INTO '+DB+'.'+table+' SELECT * FROM bsunits_migration_stage.'+table)
 m.target('CREATE TABLE '+DB+'.unit_deposit_requests LIKE bsunits_beloan_final.unit_deposit_requests;CREATE TABLE '+DB+'.test_deposit_template LIKE bsunits_beloan_final.unit_deposit_requests;INSERT INTO '+DB+'.test_deposit_template SELECT * FROM bsunits_beloan_final.unit_deposit_requests LIMIT 1')
 for table in ['discount_promotions','discount_promotion_items','contract_templates']:
  m.target('CREATE TABLE '+DB+'.'+table+' LIKE bsunits_migration_stage.'+table)
  if table!='contract_templates':m.target('INSERT INTO '+DB+'.'+table+' SELECT * FROM bsunits_beloan_final.'+table)
 m.target('CREATE TABLE '+DB+'.bsunits_actor_aliases AS SELECT * FROM bsunits_beloan_final.bsunits_actor_aliases LIMIT 1')
 m.target('CREATE TABLE '+DB+'.unified_request_notifications LIKE bsunits_beloan_final.unified_request_notifications;CREATE SQL SECURITY DEFINER VIEW '+DB+'.unified_overdue_recipients AS SELECT * FROM bsunits_beloan_final.unified_overdue_recipients')
 m.target("GRANT SELECT,UPDATE(read_at,updated_at) ON "+DB+".unified_request_notifications TO 'unified_notification_reader'@'%'")
 m.target('CREATE TABLE '+DB+'.jobs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,queue VARCHAR(255) NOT NULL,payload LONGTEXT NOT NULL,attempts TINYINT UNSIGNED NOT NULL,reserved_at INT UNSIGNED NULL,available_at INT UNSIGNED NOT NULL,created_at INT UNSIGNED NOT NULL,INDEX queue_idx(queue)) ENGINE=InnoDB')
 m.target('CREATE TABLE '+DB+'.unified_approved_deposit_outbox (deposit_request_id BIGINT UNSIGNED PRIMARY KEY,request_snapshot LONGTEXT NOT NULL,snapshot_sha256 CHAR(64) NOT NULL,queued_at DATETIME NOT NULL) ENGINE=InnoDB')
 m.target('INSERT INTO '+DB+'.unit_contract_requests SELECT * FROM bsunits_beloan_final.unit_contract_requests LIMIT 1')
 m.target('INSERT INTO '+DB+'.customer_accounts SELECT * FROM bsunits_beloan_final.customer_accounts LIMIT 1')
 m.target('INSERT INTO '+DB+'.currencies SELECT * FROM bsunits_beloan_final.currencies WHERE id=2')
 m.target("INSERT INTO "+DB+".units SELECT u.* FROM bsunits_beloan_final.units u JOIN bsunits_beloan_final.unit_types t ON t.id=u.unit_type_id JOIN bsunits_beloan_final.project_accounting_branch_links l ON l.project_id=t.project_id WHERE u.catalog_state='CURRENT' AND u.deleted_at IS NULL AND u.is_saleable=1 AND u.availability_state IN ('AVAILABLE','RESERVED','SOLD') LIMIT 1")
 m.target('INSERT INTO '+DB+'.unit_types SELECT t.* FROM bsunits_beloan_final.unit_types t JOIN '+DB+'.units u ON u.unit_type_id=t.id')
 m.target('INSERT INTO '+DB+'.projects SELECT p.* FROM bsunits_beloan_final.projects p JOIN '+DB+'.unit_types t ON t.project_id=p.id')
 m.target('INSERT INTO '+DB+'.project_accounting_branch_links SELECT l.* FROM bsunits_beloan_final.project_accounting_branch_links l JOIN '+DB+'.projects p ON p.id=l.project_id')
 m.target('INSERT INTO '+DB+'.branches SELECT b.* FROM bsunits_beloan_final.branches b JOIN '+DB+'.project_accounting_branch_links l ON l.branch_id=b.id')
 m.target('INSERT INTO '+DB+'.tb_coa_categories SELECT * FROM beloan_migration_stage.tb_coa_categories LIMIT 1')
 m.target("CREATE USER '"+USER+"'@'%' IDENTIFIED BY 'local-only-account-creation-test'");user=True
 m.target('GRANT SELECT,INSERT,UPDATE,DELETE ON '+DB+".* TO '"+USER+"'@'%'")
 r=subprocess.run(['docker','exec','-i','beloan-local','php'],input=(HERE/'test-original-account-creation.php').read_bytes(),capture_output=True)
 try:d=json.loads(r.stdout)
 except ValueError:raise RuntimeError('Original account test failed; diagnostics withheld')
 if r.returncode or d.get('status')!='PASSED':raise RuntimeError(json.dumps(d))
 workers=[]
 try:
  code=(HERE/'test-concurrent-account-coa.php').read_text()
  for number in ['10026000003','10026000004']:
   workers.append(subprocess.Popen(['docker','exec','-i','-e','SYNTH_ACCOUNT='+number,'beloan-local','php','-r',code],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True))
  for w in workers:
   if w.stdout.readline().strip()!='READY':raise RuntimeError('Concurrent ledger barrier failed; diagnostics withheld')
  for w in workers:w.stdin.write('GO\n');w.stdin.flush()
  results=[]
  for w in workers:
   line=w.stdout.readline();w.wait(timeout=30)
   if w.returncode:raise RuntimeError('Concurrent ledger creation failed; diagnostics withheld')
   results.append(json.loads(line))
  if len({x['id'] for x in results})!=2 or any(x['new']!=1 for x in results):raise RuntimeError('Ledger IDs collided')
  d['concurrent_real_ledger_helper_distinct_generated_ids']=True
 finally:
  for w in workers:
   if w.poll() is None:w.kill();w.wait()
 workers=[]
 try:
  code=(HERE/'test-concurrent-account-numbers.php').read_text()
  for sale in ['999902','999903']:
   workers.append(subprocess.Popen(['docker','exec','-i','-e','SYNTH_SALE='+sale,'beloan-local','php','-r',code],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True))
  for w in workers:
   if w.stdout.readline().strip()!='READY':raise RuntimeError('Concurrent account barrier failed; diagnostics withheld')
  for w in workers:w.stdin.write('GO\n');w.stdin.flush()
  results=[]
  for w in workers:
   line=w.stdout.readline();w.wait(timeout=30)
   if w.returncode:raise RuntimeError('Concurrent account creation failed; diagnostics withheld')
   results.append(json.loads(line))
  if len({x['account'] for x in results})!=2 or len({x['drawdown'] for x in results})!=2:raise RuntimeError('Account numbers collided')
  d['concurrent_original_account_and_drawdown_numbers_distinct']=True
  d['canonical_loan_drawdown_and_ledger_tables_through_merge_views']=True
  d['canonical_sales_and_sale_items_same_transaction']=True
  d['scope']='Disposable canonical sales, sale_items, loan_accounts, drawdown_accounts and coa_categories with original-column MERGE views, one PDO; audit and unit fixtures still original schema; automatic authorizations disabled; runtime identity, authorization and unit-state integration pending'
 finally:
  for w in workers:
   if w.poll() is None:w.kill();w.wait()
 r=subprocess.run(['docker','exec','-i','beloan-local','php'],input=(HERE/'test-original-canonical-handoff.php').read_bytes(),capture_output=True)
 try:bridge=json.loads(r.stdout)
 except ValueError:raise RuntimeError('Original canonical bridge failed; diagnostics withheld')
 if r.returncode or bridge.get('status')!='PASSED':raise RuntimeError(json.dumps(bridge))
 d['original_canonical_handoff_bridge']=bridge
 race_results=[]
 for round_id,modes in enumerate([['finance','finance'],['release','finance'],['finance','cancel'],['cancel','finance'],['finance','change'],['change','finance']]):
  sale=999907+round_id;request=99990700+round_id;action=99990710+round_id*100
  # Synthetic templates produced by the original bridge regression; no source records changed.
  m.target(f"INSERT INTO {DB}.sales SELECT * FROM {DB}.sales WHERE 1=0")
  cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+DB+"' AND TABLE_NAME='sales' ORDER BY ORDINAL_POSITION")
  expr=[str(sale) if c=='id' else "'Ordered'" if c=='status' else "'Unpaid'" if c=='payment_status' else "'First Created'" if c=='invoice_status' else '0' if c in ['clearance_amount','vat'] else '`'+c+'`' for c in cols]
  m.target('INSERT INTO '+DB+'.sales SELECT '+','.join(expr)+' FROM '+DB+'.sales WHERE id=999905')
  cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+DB+"' AND TABLE_NAME='sale_items' AND COLUMN_NAME<>'id' ORDER BY ORDINAL_POSITION")
  m.target('INSERT INTO '+DB+'.sale_items ('+','.join('`'+c+'`' for c in cols)+') SELECT '+','.join(str(sale) if c=='sale_id' else '`'+c+'`' for c in cols)+' FROM '+DB+'.sale_items WHERE sale_id=999905')
  unit=m.target('SELECT unit_id FROM '+DB+'.sale_items WHERE sale_id='+str(sale))[0]
  cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+DB+"' AND TABLE_NAME='test_deposit_template' ORDER BY ORDINAL_POSITION")
  expr=[str(request) if c=='id' else str(unit) if c=='unit_id' else "'APPROVED'" if c in ['status','unit_controller_status'] else "'NOT_REQUIRED'" if c=='sale_manager_status' else '0' if c=='receiving_amount' else 'NULL' if c in ['from_unit_deposit_request_id','to_unit_deposit_request_id'] else '`'+c+'`' for c in cols]
  m.target('INSERT INTO '+DB+'.unit_deposit_requests SELECT '+','.join(expr)+' FROM '+DB+'.test_deposit_template')
  snapshot=m.target(f"SELECT JSON_OBJECT("+','.join(m.literal(c)+',`'+c+'`' for c in cols)+f") FROM {DB}.unit_deposit_requests WHERE id={request}")[0]
  import hashlib
  sha=hashlib.sha256(snapshot.encode()).hexdigest()
  m.target(f"INSERT INTO {DB}.approved_deposit_sale_terms VALUES ({sale},"+m.literal(snapshot)+')')
  m.target(f"INSERT INTO {DB}.approved_deposit_sale_links SELECT source_system,{request},customer_account_id,{sale},approved_actor_id,"+m.literal(snapshot)+','+m.literal(sha)+f",created_at FROM {DB}.approved_deposit_sale_links WHERE sale_id=999905")
  m.target(f"INSERT INTO {DB}.unit_actions (id,user_id,unit_id,action,status_action,actionable_type,actionable_id) VALUES ({action},3,{unit},'DEPOSIT','APPROVED',"+m.literal('App\\UnitDepositRequest')+f",{request});UPDATE {DB}.units SET availability_state='RESERVED',current_action_id={action} WHERE id={unit}")
  deposit_before=m.target(f"SELECT SHA2(JSON_OBJECT('remark',remark,'discount',other_discount_allowance,'price',unit_sale_price,'payment',is_selected_payment_option),256) FROM {DB}.unit_deposit_requests WHERE id={request}") if round_id==4 else None
  workers=[]
  try:
   code=(HERE/'test-original-handoff-race-worker.php').read_text()
   for index,mode in enumerate(modes):
    code=(HERE/('test-cancel-finance-race-worker.php' if mode in ['cancel','change'] else 'test-original-handoff-race-worker.php')).read_text()
    container='bsunits-mobile-local-app-1' if mode in ['cancel','change'] else 'beloan-local'
    w=subprocess.Popen(['docker','exec','-i','-e','SYNTH_SALE='+str(sale),'-e','SYNTH_MODE='+mode,'-e','SYNTH_REQUEST='+str(request),'-e','FORCE_UNIT_LOCK='+('1' if round_id>=2 and index==0 else '0'),container,'php','-r',code],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True);workers.append(w)
    if w.stdout.readline().strip()!='READY':raise RuntimeError('Actual handoff race barrier failed')
   for w in workers:w.stdin.write('GO\n');w.stdin.flush()
   outcomes=[]
   for w in workers:
    row=json.loads(w.stdout.readline());w.wait(timeout=30)
    if w.returncode or row.get('status')!='PASSED' or not row.get('transaction_closed'):raise RuntimeError('Actual handoff race failed; diagnostics withheld')
    outcomes.append(row['outcome'])
   expected=['denied','financed'] if round_id in [0,2,4] else ['denied','released'] if round_id==1 else ['denied','edited'] if round_id==5 else ['cancelled','denied']
   if sorted(outcomes)!=expected:raise RuntimeError('Actual handoff race inconsistent')
   counts=m.target(f"SELECT (SELECT COUNT(*) FROM {DB}.loan_accounts WHERE sale_id={sale}),(SELECT COUNT(*) FROM {DB}.drawdown_accounts WHERE sale_id={sale}),(SELECT status FROM {DB}.sales WHERE id={sale})")[0].split('\t')
   if counts!=(['1','1','Accepted'] if round_id in [0,2,4] else ['0','0','Ordered']):raise RuntimeError('Actual handoff race left partial finance')
   if round_id>=2:
    state=m.target(f"SELECT (SELECT status FROM {DB}.unit_deposit_requests WHERE id={request}),(SELECT availability_state FROM {DB}.units WHERE id={unit})")[0].split('\t')
    if state!=(['APPROVED','RESERVED'] if round_id in [2,4,5] else ['CANCELLED','AVAILABLE']):raise RuntimeError('Controller race left inconsistent deposit or availability')
   if round_id==4 and m.target(f"SELECT SHA2(JSON_OBJECT('remark',remark,'discount',other_discount_allowance,'price',unit_sale_price,'payment',is_selected_payment_option),256) FROM {DB}.unit_deposit_requests WHERE id={request}")!=deposit_before:raise RuntimeError('Financed deposit terms changed')
   race_results.append({'outcomes':outcomes,'consistent_account_drawdown_sale':True,'actual_bs_cancel_controller':round_id in [2,3],'actual_bs_change_controller':round_id in [4,5]})
  finally:
   for w in workers:
    if w.poll() is None:w.kill();w.wait()
 d['actual_original_handoff_races']=race_results
 m.target(f"UPDATE {DB}.unit_deposit_requests SET unit_controller_id=(SELECT source_user_id FROM {DB}.bsunits_actor_aliases LIMIT 1),unit_controller_actioned_at=NOW() WHERE id={request}")
 revision=subprocess.run(['docker','exec','-i','-e','SYNTH_SALE='+str(sale),'-e','SYNTH_REQUEST='+str(request),'bsunits-mobile-local-app-1','php'],input=(HERE/'test-original-sale-revision-worker.php').read_bytes(),capture_output=True)
 try:revision_result=json.loads(revision.stdout)
 except ValueError:raise RuntimeError('Original sale revision bridge failed; diagnostics withheld')
 if revision.returncode or revision_result.get('status')!='PASSED':raise RuntimeError(json.dumps(revision_result))
 w=subprocess.Popen(['docker','exec','-i','-e','SYNTH_SALE='+str(sale),'-e','SYNTH_MODE=finance','beloan-local','php','-r',(HERE/'test-original-handoff-race-worker.php').read_text()],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
 try:
  if w.stdout.readline().strip()!='READY':raise RuntimeError('Revised finance barrier failed')
  w.stdin.write('GO\n');w.stdin.flush();row=json.loads(w.stdout.readline());w.wait(timeout=30)
  if w.returncode or row.get('outcome')!='financed':raise RuntimeError('Revised approval did not reach original finance writer')
 finally:
  if w.poll() is None:w.kill();w.wait()
 d['edited_approval_revision_then_original_finance']={'status':'PASSED','same_sale':revision_result['same_sale'],'revision_audit_rows':revision_result['revision_audit_rows'],'original_accounting_writer_accepted':True,'automatic_queue_enabled':False,'worker_verification':revision_result}
 transfer_results=[]
 for round_id,modes in enumerate([['finance','transfer'],['transfer','finance']],start=6):
  sale=999907+round_id;request=99990700+round_id;action=99990710+round_id*100
  # Synthetic templates produced by the original bridge regression; no source records changed.
  m.target(f"INSERT INTO {DB}.sales SELECT * FROM {DB}.sales WHERE 1=0")
  cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+DB+"' AND TABLE_NAME='sales' ORDER BY ORDINAL_POSITION")
  expr=[str(sale) if c=='id' else "'Ordered'" if c=='status' else "'Unpaid'" if c=='payment_status' else "'First Created'" if c=='invoice_status' else '0' if c in ['clearance_amount','vat'] else '`'+c+'`' for c in cols]
  m.target('INSERT INTO '+DB+'.sales SELECT '+','.join(expr)+' FROM '+DB+'.sales WHERE id=999905')
  cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+DB+"' AND TABLE_NAME='sale_items' AND COLUMN_NAME<>'id' ORDER BY ORDINAL_POSITION")
  m.target('INSERT INTO '+DB+'.sale_items ('+','.join('`'+c+'`' for c in cols)+') SELECT '+','.join(str(sale) if c=='sale_id' else '`'+c+'`' for c in cols)+' FROM '+DB+'.sale_items WHERE sale_id=999905')
  unit=m.target('SELECT unit_id FROM '+DB+'.sale_items WHERE sale_id='+str(sale))[0]
  cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+DB+"' AND TABLE_NAME='test_deposit_template' ORDER BY ORDINAL_POSITION")
  expr=[str(request) if c=='id' else str(unit) if c=='unit_id' else "'APPROVED'" if c in ['status','unit_controller_status'] else "'NOT_REQUIRED'" if c=='sale_manager_status' else '0' if c=='receiving_amount' else 'NULL' if c in ['from_unit_deposit_request_id','to_unit_deposit_request_id'] else '`'+c+'`' for c in cols]
  m.target('INSERT INTO '+DB+'.unit_deposit_requests SELECT '+','.join(expr)+' FROM '+DB+'.test_deposit_template')
  snapshot=m.target(f"SELECT JSON_OBJECT("+','.join(m.literal(c)+',`'+c+'`' for c in cols)+f") FROM {DB}.unit_deposit_requests WHERE id={request}")[0]
  import hashlib
  sha=hashlib.sha256(snapshot.encode()).hexdigest()
  m.target(f"INSERT INTO {DB}.approved_deposit_sale_terms VALUES ({sale},"+m.literal(snapshot)+')')
  m.target(f"INSERT INTO {DB}.approved_deposit_sale_links SELECT source_system,{request},customer_account_id,{sale},approved_actor_id,"+m.literal(snapshot)+','+m.literal(sha)+f",created_at FROM {DB}.approved_deposit_sale_links WHERE sale_id=999905")
  m.target(f"INSERT INTO {DB}.unit_actions (id,user_id,unit_id,action,status_action,actionable_type,actionable_id) VALUES ({action},3,{unit},'DEPOSIT','APPROVED',"+m.literal('App\\UnitDepositRequest')+f",{request});UPDATE {DB}.units SET availability_state='RESERVED',current_action_id={action} WHERE id={unit}")
  target_unit=99994000+round_id;target_action=99995000+round_id
  unitcols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+DB+"' AND TABLE_NAME='units' ORDER BY ORDINAL_POSITION")
  m.target('INSERT INTO '+DB+'.units SELECT '+','.join(str(target_unit) if c=='id' else m.literal('SYNTHETIC-TRANSFER-'+str(round_id)) if c=='code' else 'NULL' if c=='current_action_id' else "'AVAILABLE'" if c=='availability_state' else '`'+c+'`' for c in unitcols)+' FROM '+DB+'.units WHERE id='+str(unit))
  m.target(f"INSERT INTO {DB}.unit_actions (id,user_id,unit_id,action,status_action,actionable_type,actionable_id) VALUES ({target_action},3,{target_unit},'AVAILABLE','','',0);UPDATE {DB}.units SET current_action_id={target_action} WHERE id={target_unit}")
  workers=[]
  try:
   code=(HERE/'test-original-handoff-race-worker.php').read_text()
   for index,mode in enumerate(modes):
    code=(HERE/('test-cancel-finance-race-worker.php' if mode in ['cancel','change','transfer'] else 'test-original-handoff-race-worker.php')).read_text()
    container='bsunits-mobile-local-app-1' if mode in ['cancel','change','transfer'] else 'beloan-local'
    w=subprocess.Popen(['docker','exec','-i','-e','SYNTH_SALE='+str(sale),'-e','SYNTH_MODE='+mode,'-e','SYNTH_REQUEST='+str(request),'-e','SYNTH_TARGET_UNIT='+str(target_unit),'-e','FORCE_UNIT_LOCK='+('1' if round_id>=2 and index==0 else '0'),container,'php','-r',code],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True);workers.append(w)
    if w.stdout.readline().strip()!='READY':raise RuntimeError('Actual handoff race barrier failed')
   for w in workers:w.stdin.write('GO\n');w.stdin.flush()
   outcomes=[];transfer_diagnostics=[]
   for w in workers:
    row=json.loads(w.stdout.readline());w.wait(timeout=30)
    if w.returncode or row.get('status')!='PASSED' or not row.get('transaction_closed'):raise RuntimeError('Actual handoff race failed; diagnostics withheld')
    outcomes.append(row['outcome']);transfer_diagnostics.extend(row.get('diagnostics',[]))
   expected=['denied','financed'] if round_id==6 else ['denied','transferred']
   if sorted(outcomes)!=expected:raise RuntimeError('Actual transfer/finance race inconsistent: '+str(outcomes)+' '+str(transfer_diagnostics))
   counts=m.target(f"SELECT (SELECT COUNT(*) FROM {DB}.loan_accounts WHERE sale_id={sale}),(SELECT COUNT(*) FROM {DB}.drawdown_accounts WHERE sale_id={sale}),(SELECT status FROM {DB}.sales WHERE id={sale})")[0].split('\t')
   if counts!=(['1','1','Accepted'] if round_id==6 else ['0','0','Ordered']):raise RuntimeError('Transfer race partial finance')
   state=m.target(f"SELECT (SELECT status FROM {DB}.unit_deposit_requests WHERE id={request}),(SELECT availability_state FROM {DB}.units WHERE id={unit}),(SELECT availability_state FROM {DB}.units WHERE id={target_unit})")[0].split('\t')
   if state!=(['APPROVED','RESERVED','AVAILABLE'] if round_id==6 else ['CHANGED','AVAILABLE','RESERVED']):raise RuntimeError('Transfer race inconsistent reservation: '+str(state))
   replacements=int(m.target(f"SELECT COUNT(*) FROM {DB}.unit_deposit_requests WHERE from_unit_deposit_request_id={request} AND unit_id={target_unit}")[0])
   if replacements!=(0 if round_id==6 else 1):raise RuntimeError('Transfer race duplicate or missing replacement')
   if round_id==7 and m.target(f'SELECT COUNT(*) FROM {DB}.unit_deposit_requests d JOIN {DB}.units u ON u.id=d.unit_id WHERE d.from_unit_deposit_request_id={request} AND d.unit_sale_price=u.list_price')[0]!='1':raise RuntimeError('Transfer missed canonical target price')
   transfer_results.append({'outcomes':outcomes,'actual_bs_transfer_controller':True,'original_beloan_accounting_writer':True,'consistent_sale_accounts_replacement_and_reservations':True})
  finally:
   for w in workers:
    if w.poll() is None:w.kill();w.wait()
 overdue_races=[]
 for round_id,modes in enumerate([['finance','overdue'],['overdue','finance']],start=8):
  sale=999907+round_id;request=99998000+round_id;action=99990710+round_id*100
  # Synthetic templates produced by the original bridge regression; no source records changed.
  m.target(f"INSERT INTO {DB}.sales SELECT * FROM {DB}.sales WHERE 1=0")
  cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+DB+"' AND TABLE_NAME='sales' ORDER BY ORDINAL_POSITION")
  expr=[str(sale) if c=='id' else "'Ordered'" if c=='status' else "'Unpaid'" if c=='payment_status' else "'First Created'" if c=='invoice_status' else '0' if c in ['clearance_amount','vat'] else '`'+c+'`' for c in cols]
  m.target('INSERT INTO '+DB+'.sales SELECT '+','.join(expr)+' FROM '+DB+'.sales WHERE id=999905')
  cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+DB+"' AND TABLE_NAME='sale_items' AND COLUMN_NAME<>'id' ORDER BY ORDINAL_POSITION")
  m.target('INSERT INTO '+DB+'.sale_items ('+','.join('`'+c+'`' for c in cols)+') SELECT '+','.join(str(sale) if c=='sale_id' else '`'+c+'`' for c in cols)+' FROM '+DB+'.sale_items WHERE sale_id=999905')
  unit=m.target('SELECT unit_id FROM '+DB+'.sale_items WHERE sale_id='+str(sale))[0]
  cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+DB+"' AND TABLE_NAME='test_deposit_template' ORDER BY ORDINAL_POSITION")
  expr=[str(request) if c=='id' else str(unit) if c=='unit_id' else "'APPROVED'" if c in ['status','unit_controller_status'] else "'NOT_REQUIRED'" if c=='sale_manager_status' else m.literal('2020-01-01 00:00:00') if c=='created_at' else '0' if c=='receiving_amount' else 'NULL' if c in ['from_unit_deposit_request_id','to_unit_deposit_request_id'] else '`'+c+'`' for c in cols]
  m.target('INSERT INTO '+DB+'.unit_deposit_requests SELECT '+','.join(expr)+' FROM '+DB+'.test_deposit_template')
  snapshot=m.target(f"SELECT JSON_OBJECT("+','.join(m.literal(c)+',`'+c+'`' for c in cols)+f") FROM {DB}.unit_deposit_requests WHERE id={request}")[0]
  import hashlib
  sha=hashlib.sha256(snapshot.encode()).hexdigest()
  m.target(f"INSERT INTO {DB}.approved_deposit_sale_terms VALUES ({sale},"+m.literal(snapshot)+')')
  m.target(f"INSERT INTO {DB}.approved_deposit_sale_links SELECT source_system,{request},customer_account_id,{sale},approved_actor_id,"+m.literal(snapshot)+','+m.literal(sha)+f",created_at FROM {DB}.approved_deposit_sale_links WHERE sale_id=999905")
  m.target(f"INSERT INTO {DB}.unit_actions (id,user_id,unit_id,action,status_action,actionable_type,actionable_id) VALUES ({action},3,{unit},'DEPOSIT','APPROVED',"+m.literal('App\\UnitDepositRequest')+f",{request});UPDATE {DB}.units SET availability_state='RESERVED',current_action_id={action} WHERE id={unit}")
  workers=[]
  try:
   code=(HERE/'test-original-handoff-race-worker.php').read_text()
   for index,mode in enumerate(modes):
    code=(HERE/('test-overdue-finance-race-worker.php' if mode=='overdue' else 'test-original-handoff-race-worker.php')).read_text()
    container='bsunits-mobile-local-app-1' if mode=='overdue' else 'beloan-local'
    w=subprocess.Popen(['docker','exec','-i','-e','SYNTH_SALE='+str(sale),'-e','SYNTH_MODE='+mode,'-e','SYNTH_REQUEST='+str(request),'-e','FORCE_UNIT_LOCK='+('1' if round_id>=2 and index==0 else '0'),container,'php','-r',code],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True);workers.append(w)
    if w.stdout.readline().strip()!='READY':raise RuntimeError('Actual handoff race barrier failed')
   for w in workers:w.stdin.write('GO\n');w.stdin.flush()
   outcomes=[]
   for w in workers:
    row=json.loads(w.stdout.readline());w.wait(timeout=30)
    if w.returncode or row.get('status')!='PASSED' or not row.get('transaction_closed'):raise RuntimeError('Actual handoff race failed; diagnostics withheld')
    outcomes.append(row['outcome'])
   expected=['financed','skipped'] if round_id==8 else ['denied','overdue']
   if sorted(outcomes)!=expected:raise RuntimeError('Queued overdue/finance race inconsistent: '+str(outcomes))
   counts=m.target(f"SELECT (SELECT COUNT(*) FROM {DB}.loan_accounts WHERE sale_id={sale}),(SELECT COUNT(*) FROM {DB}.drawdown_accounts WHERE sale_id={sale}),(SELECT status FROM {DB}.sales WHERE id={sale})")[0].split('\t')
   if counts!=(['1','1','Accepted'] if round_id==8 else ['0','0','Ordered']):raise RuntimeError('Queued overdue race partial finance')
   state=m.target(f"SELECT (SELECT status FROM {DB}.unit_deposit_requests WHERE id={request}),(SELECT availability_state FROM {DB}.units WHERE id={unit})")[0].split('\t')
   if state!=(['APPROVED','RESERVED'] if round_id==8 else ['OVERDUE','RESERVED']):raise RuntimeError('Queued overdue race changed reservation')
   overdue_races.append({'outcomes':outcomes,'actual_database_queue_worker':True,'serialized_model_restored':True,'original_accounting_writer':True,'consistent_finance_and_reservation':True})
  finally:
   for w in workers:
    if w.poll() is None:w.kill();w.wait()
 d['database_queued_overdue_finance_races']=overdue_races
 overdue_transfer_races=[]
 for round_id,modes in enumerate([['transfer','overdue'],['overdue','transfer']],start=10):
  sale=999970+round_id;request=99999000+round_id*100;action=99990710+round_id*100
  # Synthetic templates produced by the original bridge regression; no source records changed.
  m.target(f"INSERT INTO {DB}.sales SELECT * FROM {DB}.sales WHERE 1=0")
  cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+DB+"' AND TABLE_NAME='sales' ORDER BY ORDINAL_POSITION")
  expr=[str(sale) if c=='id' else "'Ordered'" if c=='status' else "'Unpaid'" if c=='payment_status' else "'First Created'" if c=='invoice_status' else '0' if c in ['clearance_amount','vat'] else '`'+c+'`' for c in cols]
  m.target('INSERT INTO '+DB+'.sales SELECT '+','.join(expr)+' FROM '+DB+'.sales WHERE id=999905')
  cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+DB+"' AND TABLE_NAME='sale_items' AND COLUMN_NAME<>'id' ORDER BY ORDINAL_POSITION")
  m.target('INSERT INTO '+DB+'.sale_items ('+','.join('`'+c+'`' for c in cols)+') SELECT '+','.join(str(sale) if c=='sale_id' else '`'+c+'`' for c in cols)+' FROM '+DB+'.sale_items WHERE sale_id=999905')
  unit=m.target('SELECT unit_id FROM '+DB+'.sale_items WHERE sale_id='+str(sale))[0]
  cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+DB+"' AND TABLE_NAME='test_deposit_template' ORDER BY ORDINAL_POSITION")
  expr=[str(request) if c=='id' else str(unit) if c=='unit_id' else "'APPROVED'" if c in ['status','unit_controller_status'] else "'NOT_REQUIRED'" if c=='sale_manager_status' else m.literal('2020-01-01 00:00:00') if c=='created_at' else '0' if c=='receiving_amount' else 'NULL' if c in ['from_unit_deposit_request_id','to_unit_deposit_request_id'] else '`'+c+'`' for c in cols]
  m.target('INSERT INTO '+DB+'.unit_deposit_requests SELECT '+','.join(expr)+' FROM '+DB+'.test_deposit_template')
  snapshot=m.target(f"SELECT JSON_OBJECT("+','.join(m.literal(c)+',`'+c+'`' for c in cols)+f") FROM {DB}.unit_deposit_requests WHERE id={request}")[0]
  import hashlib
  sha=hashlib.sha256(snapshot.encode()).hexdigest()
  m.target(f"INSERT INTO {DB}.approved_deposit_sale_terms VALUES ({sale},"+m.literal(snapshot)+')')
  m.target(f"INSERT INTO {DB}.approved_deposit_sale_links SELECT source_system,{request},customer_account_id,{sale},approved_actor_id,"+m.literal(snapshot)+','+m.literal(sha)+f",created_at FROM {DB}.approved_deposit_sale_links WHERE sale_id=999905")
  m.target(f"INSERT INTO {DB}.unit_actions (id,user_id,unit_id,action,status_action,actionable_type,actionable_id) VALUES ({action},3,{unit},'DEPOSIT','APPROVED',"+m.literal('App\\UnitDepositRequest')+f",{request});UPDATE {DB}.units SET availability_state='RESERVED',current_action_id={action} WHERE id={unit}")
  target_unit=99996000+round_id;target_action=99997000+round_id*100
  unitcols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='"+DB+"' AND TABLE_NAME='units' ORDER BY ORDINAL_POSITION")
  m.target('INSERT INTO '+DB+'.units SELECT '+','.join(str(target_unit) if c=='id' else m.literal('SYNTHETIC-OVERDUE-TRANSFER-'+str(round_id)) if c=='code' else 'NULL' if c=='current_action_id' else "'AVAILABLE'" if c=='availability_state' else '`'+c+'`' for c in unitcols)+' FROM '+DB+'.units WHERE id='+str(unit))
  m.target(f"INSERT INTO {DB}.unit_actions (id,user_id,unit_id,action,status_action,actionable_type,actionable_id) VALUES ({target_action},3,{target_unit},'AVAILABLE','','',0);UPDATE {DB}.units SET current_action_id={target_action} WHERE id={target_unit}")
  workers=[]
  try:
   code=(HERE/'test-original-handoff-race-worker.php').read_text()
   for index,mode in enumerate(modes):
    code=(HERE/('test-overdue-finance-race-worker.php' if mode=='overdue' else 'test-cancel-finance-race-worker.php')).read_text()
    container='bsunits-mobile-local-app-1' if mode in ['overdue','transfer'] else 'beloan-local'
    w=subprocess.Popen(['docker','exec','-i','-e','SYNTH_SALE='+str(sale),'-e','SYNTH_MODE='+mode,'-e','SYNTH_REQUEST='+str(request),'-e','SYNTH_TARGET_UNIT='+str(target_unit),'-e','FORCE_UNIT_LOCK='+('1' if round_id>=2 and index==0 else '0'),container,'php','-r',code],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True);workers.append(w)
    if w.stdout.readline().strip()!='READY':raise RuntimeError('Actual handoff race barrier failed')
   for w in workers:w.stdin.write('GO\n');w.stdin.flush()
   outcomes=[]
   for w in workers:
    row=json.loads(w.stdout.readline());w.wait(timeout=30)
    if w.returncode or row.get('status')!='PASSED' or not row.get('transaction_closed'):raise RuntimeError('Actual handoff race failed; diagnostics withheld')
    outcomes.append(row['outcome'])
   expected=['skipped','transferred'] if round_id==10 else ['overdue','transferred']
   if sorted(outcomes)!=expected:raise RuntimeError('Actual queued overdue/transfer race inconsistent: '+str(outcomes))
   counts=m.target(f"SELECT (SELECT COUNT(*) FROM {DB}.loan_accounts WHERE sale_id={sale}),(SELECT status FROM {DB}.sales WHERE id={sale})")[0].split('\t')
   if counts!=['0','Ordered']:raise RuntimeError('Overdue transfer changed finance')
   state=m.target(f"SELECT (SELECT status FROM {DB}.unit_deposit_requests WHERE id={request}),(SELECT availability_state FROM {DB}.units WHERE id={unit}),(SELECT availability_state FROM {DB}.units WHERE id={target_unit})")[0].split('\t')
   if state!=['CHANGED','AVAILABLE','RESERVED']:raise RuntimeError('Overdue transfer reservation mismatch')
   replacements=int(m.target(f"SELECT COUNT(*) FROM {DB}.unit_deposit_requests WHERE from_unit_deposit_request_id={request} AND unit_id={target_unit}")[0])
   if replacements!=1:raise RuntimeError('Overdue transfer replacement mismatch')
   overdue_transfer_races.append({'outcomes':outcomes,'actual_database_queue_worker':True,'actual_bs_transfer_controller':True,'consistent_replacement_finance_and_reservations':True})
  finally:
   for w in workers:
    if w.poll() is None:w.kill();w.wait()
 d['database_queued_overdue_transfer_races']=overdue_transfer_races
 overdue_test=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-deposit-overdue-canonical.php').read_bytes(),capture_output=True)
 if overdue_test.returncode:raise RuntimeError('Canonical overdue job test failed: '+overdue_test.stdout.decode())
 d['canonical_deposit_overdue_job']=json.loads(overdue_test.stdout)
 retry_test=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-deposit-overdue-queue-retry.php').read_bytes(),capture_output=True)
 if retry_test.returncode:raise RuntimeError('Canonical overdue queue retry failed: '+retry_test.stdout.decode())
 d['canonical_overdue_queue_retry']=json.loads(retry_test.stdout)
 notification_test=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-overdue-database-notification.php').read_bytes(),capture_output=True)
 if notification_test.returncode:raise RuntimeError('Overdue database notification test failed: '+notification_test.stdout.decode())
 d['canonical_overdue_database_notification']=json.loads(notification_test.stdout)
 notification_ui_test=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-unified-notification-ui.php').read_bytes(),capture_output=True)
 if notification_ui_test.returncode:raise RuntimeError('Unified notification UI test failed: '+notification_ui_test.stdout.decode())
 d['unified_notification_ui']=json.loads(notification_ui_test.stdout)
 model_test=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=(HERE/'test-request-property-model-reads.php').read_bytes(),capture_output=True)
 if model_test.returncode:raise RuntimeError('Property model read mapping regression failed')
 d['request_property_model_reads']=json.loads(model_test.stdout)
 d['actual_unit_transfer_finance_scope']='Actual BS changeUnit and original Beloan accounting with implemented default-off property mapping; disposable canonical tables and preserved populated BS promotion fixtures; notifications/Bus faked. Promotion and file-copy job integration pending.'
 d['actual_unit_transfer_finance_races']=transfer_results
 (HERE.parents[1]/'outputs/unified-db/original-account-creation-test-result.json').write_text(json.dumps(d,indent=2)+'\n');print(json.dumps(d,indent=2))
finally:
 if created:m.target('DROP DATABASE '+DB)
 if user:m.target("DROP USER '"+USER+"'@'%'")
