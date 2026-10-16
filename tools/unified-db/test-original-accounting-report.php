<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$testError=fopen('php://stderr','w');
register_shutdown_function(function() use ($testError){ $last=error_get_last();if($last && in_array($last['type'],array(E_ERROR,E_RECOVERABLE_ERROR,E_PARSE)))fwrite($testError,$last['message'].' at '.basename($last['file']).':'.$last['line']); });
error_reporting(0);
try {
$cfg=config('unified_posting.database');$cfg['database']='unified_accounting_report_test';$cfg['username']='unified_accounting_test';$cfg['password']='local-only-accounting-test';$cfg['prefix']='tb_';$cfg['strict']=false;
config(array('database.connections.accounting_rehearsal'=>$cfg,'database.default'=>'accounting_rehearsal'));
$user=new App\Models\User;$user->id=3;$user->role_id=1;$user->branch_id=1;$user->branch_code='100';Illuminate\Support\Facades\Auth::setUser($user);

Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/accounting/trial_balance','GET',array('dpStart'=>'2026-09-01','dpEnd'=>'2026-09-30','cur'=>2)));

$data=(new App\Http\Controllers\AccountingController)->getTB();$rows=array();
foreach($data['coa_details'] as $id=>$row){$out=array();foreach(array('pre_debit','pre_credit','cur_debit','cur_credit') as $field)$out[$field]=number_format(isset($row[$field])?$row[$field]:0,4,'.','');$rows[(string)$id]=$out;}
ksort($rows);echo json_encode(array('rows'=>$rows));

} catch(Exception $e){echo json_encode(array('error'=>get_class($e),'message'=>$e->getMessage(),'location'=>basename($e->getFile()).':'.$e->getLine()));exit(1);}
