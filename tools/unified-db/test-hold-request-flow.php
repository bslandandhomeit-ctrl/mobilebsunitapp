<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
class HoldFlowUser extends App\User { public $controllerRole=false; public function hasRole($roles):bool {return $this->controllerRole && $roles===App\Helpers\UserRole::UNIT_CONTROLLER;} }
try {
$cfg=config('unified_catalog.database');$cfg['database']='unified_hold_flow_test';$cfg['username']='unified_hold_test';$cfg['password']='local-only-hold-test';$cfg['prefix']='';
config(array('database.connections.hold_test'=>$cfg,'database.default'=>'hold_test','unified_catalog.request_writes_enabled'=>true,'unified_catalog.request_unit_table'=>'canonical_units','unified_catalog.action_sync_enabled'=>false,'app.default_system_user_id'=>1));
Illuminate\Support\Facades\Bus::fake();Illuminate\Support\Facades\Notification::fake();
$user=new HoldFlowUser;$user->id=1;Illuminate\Support\Facades\Auth::setUser($user);$unit=Illuminate\Support\Facades\DB::table('canonical_units')->first();
App\UnitAction::create(array('user_id'=>1,'unit_id'=>$unit->id,'action'=>'AVAILABLE','status_action'=>'','actionable_type'=>'App\\UnitHoldRequest','actionable_id'=>0));
function holdReq($data,$user){$r=Illuminate\Http\Request::create('/api/unit_hold_requests','POST',$data);$r->setUserResolver(function()use($user){return $user;});return $r;}
$c=new App\Http\Controllers\Api\UnitHoldController;$created=$c->hold(holdReq(array('unit_id'=>$unit->id,'hold_day'=>1,'remark'=>'Synthetic hold'),$user));
if(!($created instanceof App\Http\Resources\UnitHoldRequest))throw new Exception('Hold creation failed');$hold=$created->resource;
if($hold->status!=='APPROVED'||Illuminate\Support\Facades\DB::table('canonical_units')->value('availability_state')!=='RESERVED')throw new Exception('Hold reservation failed');
$duplicate=$c->hold(holdReq(array('unit_id'=>$unit->id,'hold_day'=>1),$user));if($duplicate->getStatusCode()!==422)throw new Exception('Duplicate reserved unit hold allowed');
$other=new HoldFlowUser;$other->id=2;$denied=$c->cancel(holdReq(array('action_reason'=>'foreign'),$other),$hold->id);if($denied->getStatusCode()!==403||$hold->fresh()->status!=='APPROVED')throw new Exception('Foreign hold cancellation allowed');
$cancelled=$c->cancel(holdReq(array('action_reason'=>'Synthetic cancel'),$user),$hold->id);if(!($cancelled instanceof App\Http\Resources\UnitHoldRequest)||$hold->fresh()->status!=='CANCELLED')throw new Exception('Owner cancellation failed');
if(Illuminate\Support\Facades\DB::table('canonical_units')->value('availability_state')!=='AVAILABLE')throw new Exception('Cancellation did not release unit');
$second=$c->hold(holdReq(array('unit_id'=>$unit->id,'hold_day'=>1),$user));
if(!($second instanceof App\Http\Resources\UnitHoldRequest))throw new Exception('Second hold failed');
$controllerUser=new HoldFlowUser;$controllerUser->id=3;$controllerUser->controllerRole=true;
$controllerCancel=$c->cancel(holdReq(array(),$controllerUser),$second->resource->id);
if(!($controllerCancel instanceof App\Http\Resources\UnitHoldRequest)||$second->resource->fresh()->status!=='CANCELLED')throw new Exception('Authorized controller cancellation failed');
$pending=App\UnitHoldRequest::create(array('user_id'=>1,'unit_id'=>$unit->id,'status'=>'PENDING','hold_day'=>2,'release_date'=>now()->addDays(2)));
App\UnitAction::create(array('user_id'=>1,'unit_id'=>$unit->id,'action'=>'HOLD','status_action'=>'PENDING','actionable_type'=>'App\UnitHoldRequest','actionable_id'=>$pending->id));
Illuminate\Support\Facades\Auth::setUser($controllerUser);
$approved=$c->approve(holdReq(array(),$controllerUser),$pending->id);
if(!($approved instanceof App\Http\Resources\UnitHoldRequest)||$pending->fresh()->status!=='APPROVED')throw new Exception('Multi-day approval failed');
$job=new App\Jobs\ProcessUnitHold($pending);
$job->handle();if($pending->fresh()->status!=='APPROVED')throw new Exception('Early expiry processed');
$pending->release_date=now()->subMinute();$pending->save();$job->handle();
if($pending->fresh()->status!=='OVERDUE'||Illuminate\Support\Facades\DB::table('canonical_units')->value('availability_state')!=='AVAILABLE')throw new Exception('Expiry release failed');
$count=App\UnitAction::count();$job->handle();if(App\UnitAction::count()!==$count)throw new Exception('Expiry replay duplicated actions');
$stale=App\UnitHoldRequest::create(array('user_id'=>1,'unit_id'=>$unit->id,'status'=>'APPROVED','hold_day'=>1,'release_date'=>now()->subMinute()));
App\UnitAction::create(array('user_id'=>1,'unit_id'=>$unit->id,'action'=>'CONTRACT','status_action'=>'PENDING','actionable_type'=>'App\UnitContractRequest','actionable_id'=>999));
$pointer=Illuminate\Support\Facades\DB::table('canonical_units')->value('current_action_id');(new App\Jobs\ProcessUnitHold($stale))->handle();
if($stale->fresh()->status!=='OVERDUE'||Illuminate\Support\Facades\DB::table('canonical_units')->value('current_action_id')!==$pointer||Illuminate\Support\Facades\DB::table('canonical_units')->value('availability_state')!=='RESERVED')throw new Exception('Stale hold released newer contract');
$stalePending=App\UnitHoldRequest::create(array('user_id'=>1,'unit_id'=>$unit->id,'status'=>'PENDING','hold_day'=>2,'release_date'=>now()->addDays(2)));
$staleApproval=$c->approve(holdReq(array(),$controllerUser),$stalePending->id);
if($staleApproval->getStatusCode()!==422||$stalePending->fresh()->status!=='PENDING'||Illuminate\Support\Facades\DB::table('canonical_units')->value('current_action_id')!==$pointer)throw new Exception('Stale hold approval replaced contract');
$staleCancelHold=App\UnitHoldRequest::create(array('user_id'=>1,'unit_id'=>$unit->id,'status'=>'APPROVED','hold_day'=>1,'release_date'=>now()->addDay()));
$staleCancel=$c->cancel(holdReq(array(),$controllerUser),$staleCancelHold->id);
if(!($staleCancel instanceof App\Http\Resources\UnitHoldRequest)||$staleCancelHold->fresh()->status!=='CANCELLED'||Illuminate\Support\Facades\DB::table('canonical_units')->value('current_action_id')!==$pointer||Illuminate\Support\Facades\DB::table('canonical_units')->value('availability_state')!=='RESERVED')throw new Exception('Old cancellation replaced newer contract');
$failureHold=App\UnitHoldRequest::create(array('user_id'=>1,'unit_id'=>$unit->id,'status'=>'APPROVED','hold_day'=>1,'release_date'=>now()->subMinute()));
App\UnitAction::create(array('user_id'=>1,'unit_id'=>$unit->id,'action'=>'HOLD','status_action'=>'APPROVED','actionable_type'=>'App\UnitHoldRequest','actionable_id'=>$failureHold->id));
$beforeCount=App\UnitAction::count();$beforePointer=Illuminate\Support\Facades\DB::table('canonical_units')->value('current_action_id');
$notificationFake=Illuminate\Support\Facades\Notification::getFacadeRoot();
Illuminate\Support\Facades\Notification::swap(new class {public function send($users,$notification){throw new RuntimeException('Synthetic notification failure');}});
$failed=false;try {(new App\Jobs\ProcessUnitHold($failureHold))->handle();}catch(Exception $e){$failed=$e->getMessage()==='Synthetic notification failure';}
Illuminate\Support\Facades\Notification::swap($notificationFake);
if(!$failed||$failureHold->fresh()->status!=='APPROVED'||App\UnitAction::count()!==$beforeCount||Illuminate\Support\Facades\DB::table('canonical_units')->value('current_action_id')!==$beforePointer)throw new Exception('Expiry failure did not roll back for retry');
echo json_encode(array('status'=>'PASSED','one_day_auto_approval'=>true,'canonical_reservation'=>true,'duplicate_hold_denied'=>true,'foreign_cancellation_denied'=>true,'owner_cancel_response'=>true,'authorized_controller_cancel'=>true,'canonical_release'=>true,'multi_day_approval'=>true,'early_expiry_denied'=>true,'expiry_release'=>true,'expiry_replay'=>true,'newer_contract_preserved'=>true,'stale_approval_denied'=>true,'old_cancellation_preserves_new_contract'=>true,'expiry_failure_atomic_retry'=>true,'scope'=>'Original controller with disposable canonical units/request tables; queue and notifications faked. Actual approval controller and expiry job exercised directly; real role middleware, concurrent holds and queued delivery remain pending.'),JSON_PRETTY_PRINT);
}catch(Exception $e){echo json_encode(array('status'=>'FAILED','error'=>get_class($e),'location'=>basename($e->getFile()).':'.$e->getLine(),'sql_error'=>($e instanceof Illuminate\Database\QueryException)?$e->errorInfo[2]:null,'diagnostic'=>get_class($e)==='Exception'?$e->getMessage():'withheld'));exit(1);}
