"""Local restricted overdue-worker identity; does not start a worker or enable flags."""
import importlib.util,json,subprocess
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
USER='unified_overdue_worker';PASSWORD='local-only-unified-overdue-worker'
read=['units','unit_actions','unit_deposit_requests','approved_deposit_sale_links','approved_deposit_sale_amendments','sales','loan_accounts']
m.target('CREATE TABLE IF NOT EXISTS unified_deposit_overdue_jobs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,queue VARCHAR(255) NOT NULL,payload LONGTEXT NOT NULL,attempts TINYINT UNSIGNED NOT NULL,reserved_at INT UNSIGNED NULL,available_at INT UNSIGNED NOT NULL,created_at INT UNSIGNED NOT NULL,INDEX queue_idx(queue)) ENGINE=InnoDB')
m.target("CREATE OR REPLACE ALGORITHM=MERGE SQL SECURITY DEFINER VIEW unified_overdue_recipients AS SELECT source_user_id AS id,display_name AS name FROM actors WHERE source_system='bsunits';CREATE TABLE IF NOT EXISTS unified_request_notifications LIKE bsunits_migration_stage.notifications")
exists=m.target("SELECT User FROM mysql.user WHERE User='"+USER+"'")
if not exists:m.target("CREATE USER '"+USER+"'@'%' IDENTIFIED BY '"+PASSWORD+"'")
for t in read:m.target('GRANT SELECT ON bsunits_beloan_final.'+t+" TO '"+USER+"'@'%'")
m.target("GRANT UPDATE (status,updated_at,customer_phone_number2,customer2_name,customer2_gender) ON bsunits_beloan_final.unit_deposit_requests TO '"+USER+"'@'%';GRANT UPDATE (current_action_id,availability_state) ON bsunits_beloan_final.units TO '"+USER+"'@'%';GRANT INSERT ON bsunits_beloan_final.unit_actions TO '"+USER+"'@'%';GRANT SELECT,INSERT,UPDATE,DELETE ON bsunits_beloan_final.unified_deposit_overdue_jobs TO '"+USER+"'@'%'")
m.target("GRANT SELECT ON bsunits_beloan_final.unified_overdue_recipients TO '"+USER+"'@'%';GRANT SELECT,INSERT ON bsunits_beloan_final.unified_request_notifications TO '"+USER+"'@'%'")
script=r'''<?php
try{$pdo=new PDO('mysql:host=host.docker.internal;port=3309;dbname=bsunits_beloan_final;charset=utf8mb4','unified_overdue_worker','local-only-unified-overdue-worker',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();$pdo->exec("INSERT INTO unified_deposit_overdue_jobs(queue,payload,attempts,available_at,created_at) VALUES ('synthetic-permission-check','{}',0,0,0)");$id=$pdo->lastInsertId();$pdo->exec('UPDATE unified_deposit_overdue_jobs SET attempts=1 WHERE id='.(int)$id);$pdo->exec('DELETE FROM unified_deposit_overdue_jobs WHERE id='.(int)$id);$pdo->exec('UPDATE unit_deposit_requests SET status=status WHERE 1=0');$pdo->exec('UPDATE units SET current_action_id=current_action_id WHERE 1=0');$pdo->exec("INSERT INTO unified_request_notifications(id,type,notifiable_type,notifiable_id,data,created_at,updated_at) VALUES ('00000000-0000-0000-0000-000000000001','synthetic','App\\\\User',0,'{}',NOW(),NOW())");$pdo->query('SELECT id FROM unified_overdue_recipients LIMIT 1')->fetchColumn();$pdo->rollBack();
$checks=['UPDATE units SET list_price=list_price WHERE 1=0','UPDATE unit_deposit_requests SET deposit_amount=deposit_amount WHERE 1=0','DELETE FROM unit_deposit_requests WHERE 1=0','UPDATE sales SET status=status WHERE 1=0','UPDATE loan_accounts SET sale_id=sale_id WHERE 1=0','CREATE TABLE synthetic_forbidden_worker_table(id INT)','SELECT password_hash FROM login_identities LIMIT 1','SELECT profile FROM actors LIMIT 1'];foreach($checks as $sql){$denied=false;try{$pdo->exec($sql);}catch(PDOException $e){$denied=true;}if(!$denied)throw new RuntimeException('Worker privileges exceed scope');}echo json_encode(['status'=>'PASSED','queue_dml_and_scoped_updates_allowed'=>true,'price_deposit_amount_request_delete_sale_loan_and_ddl_denied'=>count($checks),'business_mutations'=>false]);
}catch(Throwable $e){echo json_encode(['status'=>'FAILED','type'=>get_class($e)]);exit(1);}
'''
r=subprocess.run(['docker','exec','-i','bsunits-mobile-local-app-1','php'],input=script,text=True,capture_output=True)
if r.returncode:raise RuntimeError('Restricted worker permission verification failed; details withheld')
d=json.loads(r.stdout);d.update({'local_only':True,'worker_started':False,'application_flags_enabled':False,'pending_dependencies':['Final worker startup and rollback rehearsal','Worker profile/environment routing and final rollback rehearsal']})
(HERE.parents[1]/'outputs/unified-db/overdue-worker-permission-result.json').write_text(json.dumps(d,indent=2)+'\n');print(json.dumps(d,indent=2))
