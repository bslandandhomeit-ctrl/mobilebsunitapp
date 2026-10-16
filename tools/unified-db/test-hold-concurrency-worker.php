<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
class ApprovalRaceUser extends App\User {public function hasRole($roles):bool{return $roles===App\Helpers\UserRole::UNIT_CONTROLLER;}}
try {
$cfg=config('unified_catalog.database');$cfg['database']='unified_hold_flow_test';$cfg['username']='unified_hold_test';$cfg['password']='local-only-hold-test';$cfg['prefix']='';config(array('database.connections.hold_test'=>$cfg,'database.default'=>'hold_test','unified_catalog.request_writes_enabled'=>true,'unified_catalog.request_unit_table'=>'canonical_units','unified_catalog.action_sync_enabled'=>false,'app.default_system_user_id'=>1));
Illuminate\Support\Facades\Bus::fake();Illuminate\Support\Facades\Notification::fake();
$id=(int)getenv('HOLD_WORKER_ID');$user=getenv('HOLD_APPROVAL_RACE')?new ApprovalRaceUser:new App\User;$user->id=$id;Illuminate\Support\Facades\Auth::setUser($user);
$unit=Illuminate\Support\Facades\DB::table('canonical_units')->first();
Illuminate\Support\Facades\DB::table('test_barrier')->insert(array('id'=>$id));
$deadline=microtime(true)+10;while(Illuminate\Support\Facades\DB::table('test_barrier')->count()<2){if(microtime(true)>$deadline)throw new Exception('Barrier timeout');usleep(10000);}
$r=Illuminate\Http\Request::create('/api/unit_hold_requests','POST',array('unit_id'=>$unit->id,'hold_day'=>1));$r->setUserResolver(function()use($user){return $user;});
if(getenv('HOLD_CANCEL_EXPIRY_RACE')) { $hold=App\UnitHoldRequest::first();if($id===1){$user->id=1;$response=(new App\Http\Controllers\Api\UnitHoldController)->cancel($r,$hold->id);}else{(new App\Jobs\ProcessUnitHold($hold))->handle();echo json_encode(array('status_code'=>200));exit(0);}} elseif(getenv('HOLD_APPROVAL_RACE')) {$ids=App\UnitHoldRequest::orderBy('id')->pluck('id')->sort()->values();$response=(new App\Http\Controllers\Api\UnitHoldController)->approve($r,$ids[$id-1]);} else {$response=(new App\Http\Controllers\Api\UnitHoldController)->hold($r);}
echo json_encode(array('status_code'=>$response instanceof App\Http\Resources\UnitHoldRequest?200:$response->getStatusCode()));
}catch(Exception $e){echo json_encode(array('error'=>get_class($e),'location'=>basename($e->getFile()).':'.$e->getLine()));exit(1);}
