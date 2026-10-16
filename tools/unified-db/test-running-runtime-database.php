<?php
$root=is_file('/app/bootstrap/app.php')?'/app':'/var/www/html';require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try{
 $default=Illuminate\Support\Facades\DB::connection();$actual=$default->select('SELECT DATABASE() AS current_database');$database=$actual[0]->current_database;$expected='bsunits_beloan_final';
 $classes=$root==='/app'?array('App\User','App\Unit','App\UnitHoldRequest','App\UnitDepositRequest','App\UnitContractRequest'):array('App\Models\User','App\Models\Loan','App\Models\ClientLoanAccounts','App\Models\DrawdownAccounts','App\Models\JournalRequiry');$checks=array();
 foreach($classes as $class){$model=new $class;$checks[]=array('model'=>$class,'configured_database'=>$model->getConnection()->getDatabaseName(),'final_database_selected'=>$model->getConnection()->getDatabaseName()===$expected);}
 $all=$database===$expected;foreach($checks as $check)$all=$all&&$check['final_database_selected'];
 echo json_encode(array('status'=>$all?'FINAL_DATABASE_SELECTED':'NOT_FULLY_SWITCHED','app'=>$root==='/app'?'bsunits':'beloan','actual_default_database'=>$database,'expected_database'=>$expected,'core_model_checks'=>$checks,'scope'=>'Read-only running-container/default-PDO check. Selected repository adapters can use final DB while these default models remain source-backed. No connection switch or business writes.'),JSON_PRETTY_PRINT)."\n";
}catch(Exception $e){fwrite(fopen('php://stderr','w'),'Runtime database audit failed: '.get_class($e)."\n");exit(1);}
