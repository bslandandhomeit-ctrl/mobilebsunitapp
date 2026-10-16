<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try{
$cfg=config('unified_catalog.database');$cfg['database']='unified_deposit_decision_test';$cfg['username']='unified_deposit_test';$cfg['password']='local-only-deposit-test';$cfg['prefix']='';config(['database.connections.deposit_test'=>$cfg,'database.default'=>'deposit_test','unified_catalog.request_writes_enabled'=>true,'unified_catalog.request_unit_table'=>'canonical_units','unified_catalog.action_sync_enabled'=>false,'unified_catalog.deposit_conversion_enabled'=>false,'app.default_system_user_id'=>1]);
Illuminate\Support\Facades\Notification::fake();Illuminate\Support\Facades\Bus::fake();
$user=App\User::role('agent')->firstOrFail();Illuminate\Support\Facades\Auth::setUser($user);$db=Illuminate\Support\Facades\DB::connection();$unit=$db->table('canonical_units')->first();
function availableForCreate($unit){App\UnitAction::create(['user_id'=>1,'unit_id'=>$unit->id,'action'=>'AVAILABLE','status_action'=>'','actionable_type'=>'','actionable_id'=>0]);}
function createReq($unit,$user){$r=Illuminate\Http\Request::create('/api/deposit','POST',['unit_id'=>$unit->id,'other_discount_allowance'=>0,'deposit_amount'=>100,'is_selected_payment_option'=>0,'customer_name'=>'Synthetic Customer','customer_gender'=>1,'customer_phone_number'=>'010000001']);$r->setUserResolver(function()use($user){return $user;});return $r;}
$c=new App\Http\Controllers\Api\UnitDepositRequestController;availableForCreate($unit);$before=App\UnitDepositRequest::count();$r=$c->create(createReq($unit,$user));
if(!($r instanceof App\Http\Resources\UnitDepositRequest)||App\UnitDepositRequest::count()!==$before+1||$db->table('canonical_units')->value('availability_state')!=='RESERVED'||$r->resource->unit_sale_price!=$unit->list_price)throw new RuntimeException('Creation failed');
$created=$r->resource;$savedPrice=$created->unit_sale_price;
$edit=createReq($unit,$user);$edit->merge(['remark'=>'Synthetic updated remark']);
$edited=$c->update($edit,$created->id);if(!($edited instanceof App\Http\Resources\UnitDepositRequest)||$created->fresh()->remark!=='Synthetic updated remark'||$created->fresh()->unit_sale_price!=$savedPrice)throw new RuntimeException('Owner edit failed');
$other=App\User::role('agent')->where('id','<>',$user->id)->firstOrFail();
if($c->update(createReq($unit,$other),$created->id)->getStatusCode()!==403)throw new RuntimeException('Foreign edit accepted');
$invalid=createReq($unit,$user);$invalid->merge(['deposit_amount'=>-1,'remark'=>'Invalid edit']);
if($c->update($invalid,$created->id)->getStatusCode()!==422||$created->fresh()->remark!=='Synthetic updated remark'||$db->transactionLevel()!==0)throw new RuntimeException('Invalid edit changed data');
$db->table('unit_deposit_requests')->where('id',$created->id)->update(['status'=>'APPROVED']);
if($c->update($edit,$created->id)->getStatusCode()!==422)throw new RuntimeException('Approved edit accepted');
$db->table('unit_deposit_requests')->where('id',$created->id)->update(['status'=>'PENDING']);
$ownedPointer=$db->table('canonical_units')->value('current_action_id');
App\UnitAction::create(['user_id'=>1,'unit_id'=>$unit->id,'action'=>'CONTRACT','status_action'=>'PENDING','actionable_type'=>'App\\UnitContractRequest','actionable_id'=>999]);$newPointer=$db->table('canonical_units')->value('current_action_id');
if($c->update($edit,$created->id)->getStatusCode()!==422||$db->table('canonical_units')->value('current_action_id')!=$newPointer)throw new RuntimeException('Stale edit accepted');
$db->table('canonical_units')->where('id',$unit->id)->update(['current_action_id'=>$ownedPointer]);
if($c->create(createReq($unit,$user))->getStatusCode()!==422||App\UnitDepositRequest::count()!==$before+1)throw new RuntimeException('Duplicate creation accepted');
availableForCreate($unit);$pointer=$db->table('canonical_units')->value('current_action_id');$n=App\UnitAction::count();
Illuminate\Support\Facades\Notification::swap(new class {public function send($users,$notification){throw new RuntimeException('Synthetic notification failure');}});
$r=$c->create(createReq($unit,$user));if($r->getStatusCode()!==500||App\UnitDepositRequest::count()!==$before+1||App\UnitAction::count()!==$n||$db->table('canonical_units')->value('current_action_id')!=$pointer||$db->transactionLevel()!==0)throw new RuntimeException('Creation failure not atomic');
Illuminate\Support\Facades\Notification::fake();
function heldForCreate($unit,$user,$status='APPROVED',$expired=false){
 $hold=App\UnitHoldRequest::create(['user_id'=>$user->id,'unit_id'=>$unit->id,'status'=>$status,'hold_day'=>1,'release_date'=>$expired?now()->subMinute():now()->addDay()]);
 App\UnitAction::create(['user_id'=>$user->id,'unit_id'=>$unit->id,'action'=>'HOLD','status_action'=>$status,'actionable_type'=>$hold->getMorphClass(),'actionable_id'=>$hold->id]);return $hold;
}
$hold=heldForCreate($unit,$user);$pointer=$db->table('canonical_units')->value('current_action_id');
$other=App\User::role('agent')->where('id','<>',$user->id)->firstOrFail();Illuminate\Support\Facades\Auth::setUser($other);
$r=createReq($unit,$other);$r->merge(['unit_hold_request_id'=>$hold->id]);if($c->create($r)->getStatusCode()!==422||$hold->fresh()->status!=='APPROVED')throw new RuntimeException('Foreign hold converted');
Illuminate\Support\Facades\Auth::setUser($user);$r=createReq($unit,$user);$r->merge(['unit_hold_request_id'=>$hold->id]);
$n=App\UnitDepositRequest::count();$actions=App\UnitAction::count();
Illuminate\Support\Facades\Notification::swap(new class {public function send($users,$notification){throw new RuntimeException('Synthetic notification failure');}});
if($c->create($r)->getStatusCode()!==500||$hold->fresh()->status!=='APPROVED'||App\UnitDepositRequest::count()!==$n||App\UnitAction::count()!==$actions||$db->table('canonical_units')->value('current_action_id')!=$pointer)throw new RuntimeException('Hold conversion rollback failed');
Illuminate\Support\Facades\Notification::fake();$converted=$c->create($r);
if(!($converted instanceof App\Http\Resources\UnitDepositRequest)||$hold->fresh()->status!=='RELEASE'||$db->table('canonical_units')->value('availability_state')!=='RESERVED')throw new RuntimeException('Hold conversion failed');
if($c->create($r)->getStatusCode()!==422)throw new RuntimeException('Hold conversion replay accepted');
foreach([['PENDING',false],['APPROVED',true]] as $case){$h=heldForCreate($unit,$user,$case[0],$case[1]);$r=createReq($unit,$user);$r->merge(['unit_hold_request_id'=>$h->id]);if($c->create($r)->getStatusCode()!==422)throw new RuntimeException('Pending or expired hold converted');}
$old=heldForCreate($unit,$user);$new=heldForCreate($unit,$user);$r=createReq($unit,$user);$r->merge(['unit_hold_request_id'=>$old->id]);if($c->create($r)->getStatusCode()!==422||$new->fresh()->status!=='APPROVED')throw new RuntimeException('Stale hold converted');
echo json_encode(['status'=>'PASSED','owner_edit_preserves_saved_price'=>true,'foreign_invalid_approved_stale_edits_denied'=>true,'original_controller_creation'=>true,'latest_canonical_price'=>true,'reservation'=>true,'duplicate_denied'=>true,'failure_atomic_rollback'=>true,'hold_conversion'=>true,'foreign_pending_expired_stale_hold_denied'=>true,'hold_conversion_replay_denied'=>true,'hold_conversion_failure_rollback'=>true,'scope'=>'Disposable database; notifications and job dispatch faked; parallel submissions tested separately']);
}catch(Throwable $e){echo json_encode(['status'=>'FAILED','type'=>get_class($e),'diagnostic'=>$e instanceof RuntimeException?$e->getMessage():'withheld']);exit(1);}
