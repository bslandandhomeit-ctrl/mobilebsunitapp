<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$errors=[];Illuminate\Support\Facades\Log::listen(function($event)use(&$errors){$errors[]=(string)$event->message;});
try {
$cfg=config('unified_catalog.database');$cfg['database']='unified_deposit_decision_test';$cfg['username']='unified_deposit_test';$cfg['password']='local-only-deposit-test';$cfg['prefix']='';
config(['database.connections.deposit_test'=>$cfg,'database.default'=>'deposit_test','unified_catalog.request_writes_enabled'=>true,'unified_catalog.request_unit_table'=>'canonical_units','unified_catalog.action_sync_enabled'=>false,'unified_catalog.deposit_conversion_enabled'=>false,'app.default_system_user_id'=>1]);
Illuminate\Support\Facades\Notification::fake();
$worker=(int)getenv('DEPOSIT_WORKER_ID');$user=App\User::role('agent')->orderBy('id')->skip($worker-1)->firstOrFail();Illuminate\Support\Facades\Auth::setUser($user);
$db=Illuminate\Support\Facades\DB::connection();$worker=(int)getenv('DEPOSIT_WORKER_ID');
$db->table('test_barrier')->insert(['id'=>$worker]);$deadline=microtime(true)+10;
while($db->table('test_barrier')->count()<2){if(microtime(true)>$deadline)throw new RuntimeException('Barrier timeout');usleep(10000);}
Illuminate\Support\Facades\Bus::fake();$unit=$db->table('canonical_units')->first();
$r=Illuminate\Http\Request::create('/api/deposit','POST',['unit_id'=>$unit->id,'other_discount_allowance'=>0,'deposit_amount'=>100,'is_selected_payment_option'=>0,'customer_name'=>'Synthetic Customer','customer_gender'=>1,'customer_phone_number'=>'010000001']);$r->setUserResolver(function()use($user){return $user;});
$response=(new App\Http\Controllers\Api\UnitDepositRequestController)->create($r);
echo json_encode(['status_code'=>$response instanceof App\Http\Resources\UnitDepositRequest?200:$response->getStatusCode()]);
}catch(Throwable $e){echo json_encode(['status'=>'FAILED','type'=>get_class($e)]);exit(1);}
