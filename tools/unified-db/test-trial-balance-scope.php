<?php
date_default_timezone_set("Asia/Phnom_Penh");
require '/var/www/html/vendor/autoload.php';
require '/var/www/html/app/Support/TrialBalanceScope.php';
$pdo = new PDO('sqlite::memory:');
$conn = new Illuminate\Database\SQLiteConnection($pdo);
$pdo->exec('CREATE TABLE journal_detail (journal_id INTEGER, branch_code TEXT, is_audit INTEGER, debit NUMERIC)');
$pdo->exec('CREATE TABLE journal_requiry (id INTEGER, entry_date TEXT)');
$fixtures = array(array('2026-08-31','100',1,10),array('2026-09-01','100',1,20),array('2026-09-30 23:59:59','100',1,30),array('2026-10-01','100',1,40),array('2026-09-15','200',1,50),array('2026-09-15','100',0,60));
foreach ($fixtures as $i=>$row) {
$conn->table('journal_requiry')->insert(array('id'=>$i+1,'entry_date'=>$row[0]));
$conn->table('journal_detail')->insert(array('journal_id'=>$i+1,'branch_code'=>$row[1],'is_audit'=>$row[2],'debit'=>$row[3]));
}
function total($conn,$opening,$role,$branch,$requested) {
$q=$conn->table('journal_detail')->join('journal_requiry','journal_detail.journal_id','=','journal_requiry.id');
App\Support\TrialBalanceScope::apply($q,'2026-09-01','2026-09-30',$opening,$role,$branch,$requested);
return (int)$q->sum('debit');
}
$checks=array('current_period_inclusive_end'=>total($conn,false,1,'100',null)===100,'opening_before_start'=>total($conn,true,1,'100',null)===10,'staff_own_branch'=>total($conn,false,3,'100',null)===50,'staff_cannot_override_branch'=>total($conn,false,3,'100','200')===50,'admin_requested_branch'=>total($conn,false,1,'100','200')===50,'manager_requested_branch'=>total($conn,false,2,'100','200')===50);
foreach(array(array('2026-02-30','2026-09-30',1,'100'),array('2026-10-01','2026-09-30',1,'100'),array('2026-09-01','2026-09-30',3,'')) as $i=>$bad) {
try { App\Support\TrialBalanceScope::apply($conn->table('journal_detail'),$bad[0],$bad[1],false,$bad[2],$bad[3],null);$checks['reject_invalid_'.$i]=false; } catch(InvalidArgumentException $e) {$checks['reject_invalid_'.$i]=true;}
}
foreach($checks as $ok) if(!$ok) throw new Exception('Report scope regression');
echo json_encode(array('status'=>'PASSED','checks'=>$checks,'scope'=>'In-memory ledger; original controller uses this helper. Route middleware and full unified report remain pending.'),JSON_PRETTY_PRINT);
