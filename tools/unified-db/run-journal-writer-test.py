"""Run journal component checks only against an automatically removed scratch DB."""
import importlib.util
import json
import subprocess
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging', HERE / 'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)
DB = 'unified_journal_writer_test'
if s.target("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='" + DB + "';"):
    raise RuntimeError('Scratch already exists; refusing to overwrite')
try:
    s.target('CREATE DATABASE ' + DB + ';' + ''.join('CREATE TABLE ' + DB + '.' + t + ' LIKE bsunits_beloan_final.' + t + ';' for t in ['coa_categories', 'journal_requiry', 'journal_detail']) +
             'CREATE TABLE ' + DB + '.branches (id BIGINT PRIMARY KEY,code VARCHAR(5)); INSERT INTO ' + DB + ".branches VALUES (1,'100'),(2,'200');" +
             'CREATE TABLE ' + DB + '.unified_receipt_sequences (receipt_year INT PRIMARY KEY,next_number BIGINT NOT NULL) ENGINE=InnoDB;' +
             'CREATE TABLE ' + DB + '.unified_journal_posting_keys (request_key VARCHAR(120) PRIMARY KEY,payload_hash CHAR(64) NOT NULL,journal_id BIGINT NULL) ENGINE=InnoDB;' +
             ''.join('CREATE TABLE ' + DB + '.' + t + ' LIKE beloan_migration_stage.tb_' + t + ';' for t in ['notification','slips','till_account','till_transaction']) +
             'CREATE TABLE ' + DB + '.drawdown_accounts LIKE bsunits_beloan_final.drawdown_accounts;' +
             'CREATE TABLE ' + DB + '.transactions_requiry LIKE beloan_migration_stage.tb_transactions_requiry; CREATE VIEW ' + DB + '.tb_transactions_requiry AS SELECT * FROM ' + DB + '.transactions_requiry;' +
             'CREATE TABLE ' + DB + '.unified_cashier_approval_audit (till_transaction_id BIGINT PRIMARY KEY,notification_id BIGINT NOT NULL,journal_id BIGINT NOT NULL,approver_id BIGINT NOT NULL,branch_id BIGINT NOT NULL,amount DECIMAL(18,4) NOT NULL,created_at DATETIME NOT NULL) ENGINE=InnoDB;' +
             'CREATE TABLE ' + DB + '.unified_cashier_rejection_audit (till_transaction_id BIGINT PRIMARY KEY,notification_id BIGINT NOT NULL,journal_id BIGINT NOT NULL,rejector_id BIGINT NOT NULL,branch_id BIGINT NOT NULL,reason TEXT NOT NULL,created_at DATETIME NOT NULL) ENGINE=InnoDB;' +
             'CREATE TABLE ' + DB + '.unified_cashier_submission_keys (request_key VARCHAR(120) PRIMARY KEY,payload_hash CHAR(64) NOT NULL,till_transaction_id BIGINT NULL,notification_id BIGINT NULL) ENGINE=InnoDB;' +
             'INSERT INTO ' + DB + '.coa_categories SELECT * FROM bsunits_beloan_final.coa_categories WHERE id IN (1,2);' +
             ''.join('CREATE VIEW ' + DB + '.tb_' + t + ' AS SELECT * FROM ' + DB + '.' + t + ';' for t in ['journal_requiry','journal_detail','coa_categories']) +
             'CREATE VIEW ' + DB + '.tb_company_branch AS SELECT id,code AS branch_code FROM ' + DB + '.branches;' +
             'CREATE VIEW ' + DB + '.tb_slips AS SELECT * FROM ' + DB + '.slips;' +
             'CREATE TABLE ' + DB + '.user_activity LIKE beloan_migration_stage.tb_user_activity;' +
             ''.join('CREATE TABLE ' + DB + '.' + table + ' LIKE beloan_migration_stage.tb_' + table + '; CREATE VIEW ' + DB + '.tb_' + table + ' AS SELECT * FROM ' + DB + '.' + table + ';' for table in ['fee_charges','schedule_fee','admin_password','products','product_brand','users','permission','user_permission','accessible','audit','loans','units','transactions_posting','loan_payments','repayment_draft','system_date','client_loan_accounts','repayment_schedule','till_transaction_reject','clients','projects','unit_types','currency','loan_approval','loan_payoff','penalty_record','holiday']) +
             ''.join('CREATE VIEW ' + DB + '.tb_' + table + ' AS SELECT * FROM ' + DB + '.' + table + ';' for table in ['notification','till_account','till_transaction','user_activity']) +
             'CREATE VIEW ' + DB + '.tb_drawdown_accounts AS SELECT d.*,customer_account_id AS client_id FROM ' + DB + '.drawdown_accounts d;' +
             'CREATE VIEW ' + DB + '.tb_drawdown_account AS SELECT d.*,customer_account_id AS client_id FROM ' + DB + '.drawdown_accounts d;' +
             'CREATE VIEW ' + DB + '.tb_last_repay_schedule AS SELECT loan_id,MAX(id) AS id FROM ' + DB + '.repayment_schedule GROUP BY loan_id;' +
             "CREATE USER 'unified_journal_test'@'%' IDENTIFIED BY 'local-only-journal-test';" +
             "GRANT SELECT,INSERT,UPDATE ON " + DB + ".* TO 'unified_journal_test'@'%';" +
             "GRANT DELETE ON " + DB + ".* TO 'unified_journal_test'@'%';")
    with (HERE / 'test-journal-writer.php').open('rb') as script:
        result = subprocess.run(['docker', 'exec', '-i', 'beloan-local', 'php'], stdin=script, capture_output=True)
    if result.returncode:
        raise RuntimeError(result.stderr.decode(errors='replace'))
    payload = (HERE / 'test-journal-writer.php').read_bytes()
    workers = [subprocess.Popen(['docker', 'exec', '-i', '-e', 'JOURNAL_CONCURRENCY_TEST=1', 'beloan-local', 'php'], stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE) for _ in range(2)]
    for worker in workers:
        worker.stdin.write(payload)
        worker.stdin.close()
        worker.stdin = None
    ids = []
    for worker in workers:
        output, errors = worker.communicate(timeout=30)
        if worker.returncode:
            raise RuntimeError('Concurrent journal worker failed')
        ids.append(json.loads(output)['journal_id'])
    counts = s.target('SELECT (SELECT COUNT(*) FROM ' + DB + '.journal_requiry),(SELECT COUNT(*) FROM ' + DB + '.journal_detail),(SELECT COUNT(*) FROM ' + DB + '.unified_journal_posting_keys);')[0]
    if ids[0] != ids[1] or counts != '3\t6\t3':
        raise RuntimeError('Concurrent replay duplicated a journal')
    with (HERE / 'test-cashier-approval.php').open('rb') as script:
        cashier = subprocess.run(['docker','exec','-i','beloan-local','php'], stdin=script, capture_output=True)
    if cashier.returncode:
        raise RuntimeError(cashier.stderr.decode(errors='replace'))
    (s.ROOT / 'outputs/unified-db/cashier-approval-test-result.json').write_bytes(cashier.stdout)
    with (HERE / 'test-original-journal-write.php').open('rb') as script:
        original = subprocess.run(['docker','exec','-i','beloan-local','php'], stdin=script, capture_output=True)
    if original.returncode:
        raise RuntimeError(original.stderr.decode(errors='replace'))
    (s.ROOT / 'outputs/unified-db/original-journal-write-test-result.json').write_bytes(original.stdout)
    with (HERE / 'test-original-cashier-controller.php').open('rb') as script:
        controller = subprocess.run(['docker','exec','-i','beloan-local','php'],stdin=script,capture_output=True)
    if controller.returncode:
        raise RuntimeError(controller.stderr.decode(errors='replace'))
    (s.ROOT / 'outputs/unified-db/original-cashier-controller-test-result.json').write_bytes(controller.stdout)
    with (HERE / 'test-original-payoff.php').open('rb') as script:
        payoff = subprocess.run(['docker','exec','-i','beloan-local','php'],stdin=script,capture_output=True)
    if payoff.returncode:
        raise RuntimeError(payoff.stderr.decode(errors='replace'))
    (s.ROOT / 'outputs/unified-db/original-payoff-test-result.json').write_bytes(payoff.stdout)
    payoff_payload = (HERE / 'test-original-payoff.php').read_bytes()
    setup = subprocess.run(['docker','exec','-i','-e','PAYOFF_RACE_MODE=setup','beloan-local','php'],input=payoff_payload,capture_output=True)
    if setup.returncode:
        raise RuntimeError('Payoff race fixture failed: '+setup.stderr.decode(errors='replace'))
    first = subprocess.Popen(['docker','exec','-i','-e','PAYOFF_RACE_MODE=worker','-e','PAYOFF_RACE_HOLD=1','beloan-local','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    first.stdin.write(payoff_payload);first.stdin.close();first.stdin=None
    if first.stdout.readline().strip() != b'LOCKED':
        raise RuntimeError('Payoff race did not reach held loan lock')
    second = subprocess.Popen(['docker','exec','-i','-e','PAYOFF_RACE_MODE=worker','beloan-local','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    second_out,second_err=second.communicate(input=payoff_payload,timeout=30)
    first_out,first_err=first.communicate(timeout=30)
    if first.returncode or second.returncode or json.loads(second_out)['elapsed'] < 0.5:
        raise RuntimeError('Concurrent payoff failed: '+str((first.returncode,second.returncode))+'; '+first_err.decode(errors='replace')+'; '+second_err.decode(errors='replace')+'; '+second_out.decode(errors='replace'))
    counts=s.target('SELECT (SELECT COUNT(*) FROM '+DB+'.loan_payoff WHERE loan_id=777901),(SELECT COUNT(*) FROM '+DB+'.loan_payments WHERE loan_id=777901),(SELECT COUNT(*) FROM '+DB+'.transactions_requiry WHERE loan_id=777901 AND trans_type=\'Pay-Off\'),(SELECT balance FROM '+DB+'.drawdown_accounts WHERE id=777901),(SELECT balance FROM '+DB+'.client_loan_accounts WHERE id=777901),(SELECT status FROM '+DB+'.loans WHERE id=777901);')[0]
    from decimal import Decimal
    if [Decimal(v) for v in counts.split('\t')]!=[Decimal(v) for v in ['1','1','1','37.75','0','9']]:
        raise RuntimeError('Concurrent payoff aggregate differs: '+counts)
    (s.ROOT / 'outputs/unified-db/payoff-concurrency-test-result.json').write_text(json.dumps({'status':'PASSED','independent_controller_processes':2,'second_waited_for_first_loan_lock':True,'single_payoff_payment_transaction':True,'single_drawdown_deduction':True,'scope':'Same-loan original Pay-Off concurrency in disposable schema; competing other finance paths not covered.'},indent=2)+'\n')
    with (HERE / 'test-original-password-session.php').open('rb') as script:
        password_session=subprocess.run(['docker','exec','-i','beloan-local','php'],stdin=script,capture_output=True)
    if password_session.returncode:
        raise RuntimeError(password_session.stderr.decode(errors='replace'))
    (s.ROOT / 'outputs/unified-db/original-password-session-test-result.json').write_bytes(password_session.stdout)
    with (HERE / 'test-original-disbursement.php').open('rb') as script:
        disbursement=subprocess.run(['docker','exec','-i','beloan-local','php'],stdin=script,capture_output=True)
    if disbursement.returncode:
        raise RuntimeError(disbursement.stderr.decode(errors='replace'))
    (s.ROOT / 'outputs/unified-db/original-disbursement-test-result.json').write_bytes(disbursement.stdout)
    race_payload=(HERE/'test-original-disbursement.php').read_bytes()
    race_cases=[]
    for first_op in ['disburse','payoff']:
        setup=subprocess.run(['docker','exec','-i','-e','DISBURSE_RACE_MODE=setup','beloan-local','php'],input=race_payload,capture_output=True)
        if setup.returncode:raise RuntimeError('Disbursement race setup failed')
        second_op='payoff' if first_op=='disburse' else 'disburse'
        first=subprocess.Popen(['docker','exec','-i','-e','DISBURSE_RACE_MODE=worker','-e','DISBURSE_RACE_HOLD=1','-e','DISBURSE_RACE_OPERATION='+first_op,'beloan-local','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
        first.stdin.write(race_payload);first.stdin.close();first.stdin=None
        if first.stdout.readline().strip()!=b'LOCKED':raise RuntimeError('Competing race lock not reached')
        second=subprocess.Popen(['docker','exec','-i','-e','DISBURSE_RACE_MODE=worker','-e','DISBURSE_RACE_OPERATION='+second_op,'beloan-local','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
        second_out,second_err=second.communicate(input=race_payload,timeout=30);first_out,first_err=first.communicate(timeout=30)
        if first.returncode or second.returncode:raise RuntimeError('Competing controller failed: '+first_err.decode(errors='replace')+second_err.decode(errors='replace'))
        a=json.loads(first_out);b=json.loads(second_out)
        if b['elapsed']<0.5 or a['denied']!=(first_op=='payoff') or b['denied']:raise RuntimeError('Competing finance ordering differs')
        values=s.target('SELECT (SELECT status FROM '+DB+'.loans WHERE id=666901),(SELECT balance FROM '+DB+'.client_loan_accounts WHERE id=666901),(SELECT balance_downpayment FROM '+DB+'.client_loan_accounts WHERE id=666901),(SELECT balance FROM '+DB+'.drawdown_accounts WHERE id=666901),(SELECT COUNT(*) FROM '+DB+'.loan_payoff WHERE loan_id=666901),(SELECT COUNT(*) FROM '+DB+".transactions_requiry WHERE loan_id=666901 AND trans_type='Disbursement');")[0].split('\t')
        expected=['9','0','0','0','1','1'] if first_op=='disburse' else ['3','100','10','110','0','1']
        if [Decimal(v) for v in values]!=[Decimal(v) for v in expected]:raise RuntimeError('Competing finance final state differs')
        race_cases.append({'first':first_op,'second':second_op,'second_waited':True,'state_and_single_disbursement_verified':True})
        s.target('DELETE d FROM '+DB+'.journal_detail d JOIN '+DB+'.journal_requiry j ON j.id=d.journal_id JOIN '+DB+'.transactions_requiry t ON t.id=j.tran_id WHERE t.loan_id=666901;DELETE j FROM '+DB+'.journal_requiry j JOIN '+DB+'.transactions_requiry t ON t.id=j.tran_id WHERE t.loan_id=666901;')
        s.target('DELETE FROM '+DB+'.loan_payoff WHERE loan_id=666901;DELETE FROM '+DB+'.loan_payments WHERE loan_id=666901;DELETE FROM '+DB+'.repayment_schedule WHERE loan_id=666901;DELETE FROM '+DB+'.transactions_requiry WHERE loan_id=666901;DELETE FROM '+DB+'.drawdown_accounts WHERE id=666901;DELETE FROM '+DB+'.loans WHERE id=666901;DELETE FROM '+DB+'.client_loan_accounts WHERE id=666901;')
    (s.ROOT/'outputs/unified-db/disbursement-payoff-race-result.json').write_text(json.dumps({'status':'PASSED','cases':race_cases,'scope':'Actual original controllers, both forced loan-lock orders, disposable fixtures. Other competing finance operations and invoice marking remain pending.'},indent=2)+'\n')
    with (HERE / 'test-original-auto-repayment.php').open('rb') as script:
        automatic = subprocess.run(['docker','exec','-i','beloan-local','php'],stdin=script,capture_output=True)
    if automatic.returncode:
        raise RuntimeError(automatic.stderr.decode(errors='replace'))
    (s.ROOT / 'outputs/unified-db/original-auto-repayment-test-result.json').write_bytes(automatic.stdout)
    receipt_workers = [subprocess.Popen(['docker','exec','-i','-e','RECEIPT_CONCURRENCY_TEST=1','beloan-local','php'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE) for _ in range(2)]
    for worker in receipt_workers:
        worker.stdin.write(payload)
        worker.stdin.close()
        worker.stdin = None
    receipts = []
    for worker in receipt_workers:
        output, errors = worker.communicate(timeout=30)
        if worker.returncode:
            raise RuntimeError('Concurrent receipt allocation worker failed: '+errors.decode(errors='replace'))
        receipts.append(json.loads(output))
    if receipts[0]['receipt_no'] == receipts[1]['receipt_no'] or abs(receipts[0]['sequence']-receipts[1]['sequence']) != 1:
        raise RuntimeError('Concurrent receipt allocation duplicated or skipped a sequence')
    (s.ROOT / 'outputs/unified-db/receipt-concurrency-test-result.json').write_text(json.dumps({'status':'PASSED','independent_connections':2,'distinct_receipt_numbers':True,'consecutive_sequence':True,'scope':'Unified allocator in disposable schema only. Original helper receipt allocation is still unchanged and not proven concurrent-safe.'},indent=2)+'\n')
    report = json.loads(result.stdout)
    report['concurrent_replay_exactly_once'] = True
    report['scope'] = 'Disposable schema only; full cashier integration pending'
    (s.ROOT / 'outputs/unified-db/journal-writer-test-result.json').write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps(report, indent=2))
finally:
    s.target('DROP DATABASE IF EXISTS ' + DB + "; DROP USER IF EXISTS 'unified_journal_test'@'%';")
