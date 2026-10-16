<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try{
$cfg=config('unified_catalog.database');$cfg['database']='unified_deposit_decision_test';$cfg['username']='unified_deposit_test';$cfg['password']='local-only-deposit-test';$cfg['prefix']='';config(['database.connections.deposit_test'=>$cfg,'database.default'=>'deposit_test','unified_catalog.request_writes_enabled'=>true,'unified_catalog.request_unit_table'=>'canonical_units','unified_catalog.action_sync_enabled'=>false,'unified_catalog.deposit_conversion_enabled'=>false,'app.default_system_user_id'=>1]);
Illuminate\Support\Facades\Notification::fake();Illuminate\Support\Facades\Bus::fake();
$db=Illuminate\Support\Facades\DB::connection();$worker=(int)getenv('DEPOSIT_WORKER_ID');$deposit=App\UnitDepositRequest::orderBy('id')->firstOrFail();$user=App\User::findOrFail($deposit->user_id);Illuminate\Support\Facades\Auth::setUser($user);$target=$db->table('canonical_units')->where('id',$deposit->unit_id)->first();
if($worker===2){$user=App\User::role('unit_controller')->firstOrFail();Illuminate\Support\Facades\Auth::setUser($user);}
$db->table('test_barrier')->insert(['id'=>$worker]);$deadline=microtime(true)+10;while($db->table('test_barrier')->count()<2){if(microtime(true)>$deadline)throw new RuntimeException('Barrier timeout');usleep(10000);}
$r=Illuminate\Http\Request::create('/api/deposit','POST',['unit_id'=>$target->id,'other_discount_allowance'=>0,'deposit_amount'=>100,'is_selected_payment_option'=>0,'customer_name'=>'Synthetic Transfer','customer_gender'=>1,'customer_phone_number'=>'010000001','remark'=>'Synthetic concurrent edit']);$r->setUserResolver(function()use($user){return $user;});
$controller=new App\Http\Controllers\Api\UnitDepositRequestController;$response=$worker===1?$controller->update($r,$deposit->id):$controller->approve($r,$deposit->id);echo json_encode(['status_code'=>$response instanceof App\Http\Resources\UnitDepositRequest?200:$response->getStatusCode()]);
}catch(Throwable $e){echo json_encode(['status'=>'FAILED','type'=>get_class($e)]);exit(1);}
