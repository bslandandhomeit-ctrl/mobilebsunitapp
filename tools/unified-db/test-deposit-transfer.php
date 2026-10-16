<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try{
$cfg=config('unified_catalog.database');$cfg['database']='unified_deposit_decision_test';$cfg['username']='unified_deposit_test';$cfg['password']='local-only-deposit-test';$cfg['prefix']='';config(['database.connections.deposit_test'=>$cfg,'database.default'=>'deposit_test','unified_catalog.request_writes_enabled'=>true,'unified_catalog.request_unit_table'=>'canonical_units','unified_catalog.action_sync_enabled'=>false,'unified_catalog.deposit_conversion_enabled'=>false,'app.default_system_user_id'=>1]);
Illuminate\Support\Facades\Notification::fake();Illuminate\Support\Facades\Bus::fake();
$user=App\User::role('agent')->firstOrFail();Illuminate\Support\Facades\Auth::setUser($user);$db=Illuminate\Support\Facades\DB::connection();
$deposit=App\UnitDepositRequest::first();$user=App\User::findOrFail($deposit->user_id);Illuminate\Support\Facades\Auth::setUser($user);$source=$db->table('canonical_units')->where('id',$deposit->unit_id)->first();$target=$db->table('canonical_units')->where('id','<>',$source->id)->first();if(!$target)throw new RuntimeException('Two-unit fixture missing');$db->table('canonical_units')->where('id',$target->id)->update(['current_action_id'=>null]);
function transferReq($target,$user){$r=Illuminate\Http\Request::create('/api/deposit','POST',['unit_id'=>$target->id,'other_discount_allowance'=>0,'deposit_amount'=>100,'is_selected_payment_option'=>0,'customer_name'=>'Synthetic Transfer','customer_gender'=>1,'customer_phone_number'=>'010000001']);$r->setUserResolver(function()use($user){return $user;});return $r;}
function targetAction($unit,$action,$status=''){return App\UnitAction::create(['user_id'=>1,'unit_id'=>$unit->id,'action'=>$action,'status_action'=>$status,'actionable_type'=>'App\\UnitContractRequest','actionable_id'=>999]);}
$c=new App\Http\Controllers\Api\UnitDepositRequestController;
// A reserved destination must leave both units and the saved request untouched.
targetAction($target,'CONTRACT','PENDING');$before=$deposit->getAttributes();$pointers=$db->table('canonical_units')->orderBy('id')->pluck('current_action_id')->all();$n=App\UnitAction::count();
if($c->update(transferReq($target,$user),$deposit->id)->getStatusCode()!==422||$deposit->fresh()->getAttributes()!=$before||App\UnitAction::count()!==$n||$db->table('canonical_units')->orderBy('id')->pluck('current_action_id')->all()!=$pointers)throw new RuntimeException('Reserved destination altered data');
targetAction($target,'AVAILABLE');$db->table('canonical_units')->where('id',$target->id)->update(['list_price'=>'123456.78']);
// Force a missing promotion table after locks but before release; verify no partial move.
$db->statement('RENAME TABLE discount_promotions TO test_hidden_promotions');$pointers=$db->table('canonical_units')->orderBy('id')->pluck('current_action_id')->all();$n=App\UnitAction::count();
try{$r=$c->update(transferReq($target,$user),$deposit->id);if($r->getStatusCode()!==500||$deposit->fresh()->unit_id!=$source->id||App\UnitAction::count()!==$n||$db->table('canonical_units')->orderBy('id')->pluck('current_action_id')->all()!=$pointers||$db->transactionLevel()!==0)throw new RuntimeException('Failed move status '.$r->getStatusCode().' tx '.$db->transactionLevel().' actions '.App\UnitAction::count().' expected '.$n.' response '.json_encode($r->getData()));}finally{$db->statement('RENAME TABLE test_hidden_promotions TO discount_promotions');}
$injectFailure=true;$releaseObserved=false;
App\UnitDepositRequest::updating(function($model)use(&$injectFailure,&$releaseObserved,$target,$source,$db){
 if($injectFailure && (int)$model->unit_id===(int)$target->id){
  $releaseObserved=$db->table('canonical_units')->where('id',$source->id)->value('availability_state')==='AVAILABLE';
  throw new RuntimeException('Synthetic failure after old unit release');
 }
});
$pointers=$db->table('canonical_units')->orderBy('id')->pluck('current_action_id')->all();$n=App\UnitAction::count();$before=$deposit->fresh()->getAttributes();
$r=$c->update(transferReq($target,$user),$deposit->id);
if(!$releaseObserved||$r->getStatusCode()!==500||$deposit->fresh()->getAttributes()!=$before||App\UnitAction::count()!==$n||$db->table('canonical_units')->orderBy('id')->pluck('current_action_id')->all()!=$pointers||$db->transactionLevel()!==0)throw new RuntimeException('Post-release failure not atomic');
$injectFailure=false;
$r=$c->update(transferReq($target,$user),$deposit->id);
if(!($r instanceof App\Http\Resources\UnitDepositRequest)||$deposit->fresh()->unit_id!=$target->id||$deposit->fresh()->unit_sale_price!='123456.78')throw new RuntimeException('Transfer price or request failed');
$old=$db->table('canonical_units')->where('id',$source->id)->first();$new=$db->table('canonical_units')->where('id',$target->id)->first();$action=App\UnitAction::find($new->current_action_id);
if($old->availability_state!=='AVAILABLE'||$new->availability_state!=='RESERVED'||$action->action!=='DEPOSIT'||$action->actionable_id!=$deposit->id)throw new RuntimeException('Transfer availability failed');
if($old->list_price!=$source->list_price||$new->list_price!='123456.78')throw new RuntimeException('Transfer modified catalog prices');
$deposit=$deposit->fresh();$db->table('unit_deposit_requests')->where('id',$deposit->id)->update(['status'=>'APPROVED','unit_controller_status'=>'APPROVED','unit_controller_id'=>1,'receiving_amount'=>25]);$deposit=$deposit->fresh();
$other=App\User::role('agent')->where('id','<>',$user->id)->firstOrFail();
if($c->changeUnit(transferReq($source,$other),$deposit->id)->getStatusCode()!==403)throw new RuntimeException('Foreign approved change accepted');
$before=$deposit->getAttributes();$pointers=$db->table('canonical_units')->orderBy('id')->pluck('current_action_id')->all();$n=App\UnitAction::count();$count=App\UnitDepositRequest::count();
Illuminate\Support\Facades\Notification::swap(new class {public function send($users,$notification){throw new RuntimeException('Synthetic linked change failure');}});
$r=$c->changeUnit(transferReq($source,$user),$deposit->id);
if($r->getStatusCode()!==500||$deposit->fresh()->getAttributes()!=$before||App\UnitDepositRequest::count()!==$count||App\UnitAction::count()!==$n||$db->table('canonical_units')->orderBy('id')->pluck('current_action_id')->all()!=$pointers||$db->transactionLevel()!==0)throw new RuntimeException('Linked change failure not atomic');
Illuminate\Support\Facades\Notification::fake();$r=$c->changeUnit(transferReq($source,$user),$deposit->id);
if(!($r instanceof App\Http\Resources\UnitDepositRequest))throw new RuntimeException('Approved linked change failed');$replacement=$r->resource;$old=$deposit->fresh();
if($old->status!=='CHANGED'||$old->to_unit_deposit_request_id!=$replacement->id||$replacement->from_unit_deposit_request_id!=$old->id||$replacement->unit_id!=$source->id||$replacement->status!=='PENDING'||$replacement->receiving_amount!=25||$replacement->deposit_amount!=$old->deposit_amount||$replacement->unit_sale_price!=$source->list_price||$old->unit_sale_price!=$before['unit_sale_price'])throw new RuntimeException('Linked history or money changed');
if($db->table('canonical_units')->where('id',$target->id)->value('availability_state')!=='AVAILABLE'||$db->table('canonical_units')->where('id',$source->id)->value('availability_state')!=='RESERVED')throw new RuntimeException('Approved change availability failed');
$count=App\UnitDepositRequest::count();if($c->changeUnit(transferReq($source,$user),$deposit->id)->getStatusCode()!==422||App\UnitDepositRequest::count()!==$count)throw new RuntimeException('Linked change replay accepted');
$db->table('unit_deposit_requests')->where('id',$replacement->id)->update(['status'=>'APPROVED','unit_controller_status'=>'APPROVED','unit_controller_id'=>1]);$replacement=$replacement->fresh();$savedPrice=$replacement->unit_sale_price;$received=$replacement->receiving_amount;
$terms=transferReq($source,$user);$terms->merge(['is_selected_payment_option'=>1,'other_discount_allowance'=>100,'loan_duration'=>120,'interest'=>5,'special_discount'=>2,'is_first_payment'=>0,'first_payment_duration'=>0,'first_payment_percentage'=>0,'remark'=>'Synthetic custom terms']);
$before=$replacement->getAttributes();$pointers=$db->table('canonical_units')->orderBy('id')->pluck('current_action_id')->all();$n=App\UnitAction::count();
Illuminate\Support\Facades\Notification::swap(new class {public function send($users,$notification){throw new RuntimeException('Synthetic terms failure');}});
$r=$c->changeUnit($terms,$replacement->id);if($r->getStatusCode()!==500||$replacement->fresh()->getAttributes()!=$before||App\UnitAction::count()!==$n||$db->table('canonical_units')->orderBy('id')->pluck('current_action_id')->all()!=$pointers||$db->transactionLevel()!==0)throw new RuntimeException('Terms failure not atomic');
Illuminate\Support\Facades\Notification::fake();$r=$c->changeUnit($terms,$replacement->id);$pending=$replacement->fresh();
if(!($r instanceof App\Http\Resources\UnitDepositRequest)||$pending->status!=='PENDING'||$pending->sale_manager_status!=='PENDING'||$pending->unit_controller_status!=='APPROVED'||$pending->unit_sale_price!=$savedPrice||$pending->receiving_amount!=$received||$pending->loan_duration!=120||$pending->interest!=5)throw new RuntimeException('Terms reapproval or history failed');
$manager=App\User::role('sale_manager')->firstOrFail();$manager->syncRoles(['sale_manager']);$manager->unsetRelation('roles');$approval=transferReq($source,$manager);$r=$c->approve($approval,$replacement->id);
if(!($r instanceof App\Http\Resources\UnitDepositRequest)||$replacement->fresh()->status!=='APPROVED')throw new RuntimeException('Terms manager reapproval failed');
$none=transferReq($source,$user);$none->merge(['remark'=>'Synthetic no selected option']);$r=$c->changeUnit($none,$replacement->id);
if(!($r instanceof App\Http\Resources\UnitDepositRequest)||$replacement->fresh()->status!=='APPROVED'||$replacement->fresh()->sale_manager_status!=='NOT_REQUIRED'||$replacement->fresh()->unit_sale_price!=$savedPrice||$replacement->fresh()->receiving_amount!=$received)throw new RuntimeException('No-option terms changed financial history');
echo json_encode(['status'=>'PASSED','reserved_destination_denied_without_changes'=>true,'failed_transfer_rolls_back'=>true,'post_release_failure_restores_request_and_both_unit_pointers'=>true,'old_unit_released'=>true,'new_unit_reserved_by_same_request'=>true,'latest_target_price_saved'=>true,'catalog_prices_preserved'=>true,'custom_terms_require_manager_reapproval'=>true,'same_unit_terms_failure_atomic'=>true,'same_unit_terms_preserve_price_and_received_amount'=>true,'no_selected_option_preserves_original_approval_rules'=>true,'approved_linked_change'=>true,'approved_change_failure_atomic'=>true,'deposit_received_amount_and_historical_price_preserved'=>true,'foreign_and_replay_approved_changes_denied'=>true,'scope'=>'Disposable tables; real original pending update; notifications/jobs faked; parallel approved changes and conversion outbox integration pending']);
}catch(Throwable $e){echo json_encode(['status'=>'FAILED','type'=>get_class($e),'diagnostic'=>$e instanceof RuntimeException?$e->getMessage():'withheld']);exit(1);}
