<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$errors=[];Illuminate\Support\Facades\Log::listen(function($event)use(&$errors){$errors[]=(string)$event->message;});
try {
$cfg=config('unified_catalog.database');$cfg['database']='unified_deposit_decision_test';$cfg['username']='unified_deposit_test';$cfg['password']='local-only-deposit-test';$cfg['prefix']='';
config(['database.connections.deposit_test'=>$cfg,'database.default'=>'deposit_test','unified_catalog.request_writes_enabled'=>true,'unified_catalog.request_unit_table'=>'canonical_units','unified_catalog.action_sync_enabled'=>false,'unified_catalog.deposit_conversion_enabled'=>false,'app.default_system_user_id'=>1]);
Illuminate\Support\Facades\Notification::fake();
$user=App\User::role('unit_controller')->firstOrFail();Illuminate\Support\Facades\Auth::setUser($user);
$db=Illuminate\Support\Facades\DB::connection();$deposit=App\UnitDepositRequest::first();$price=$db->table('canonical_units')->value('list_price');$c=new App\Http\Controllers\Api\UnitDepositRequestController;
function decisionReq($user){$r=Illuminate\Http\Request::create('/api/deposit','POST',['action_reason'=>'Synthetic cancel']);$r->setUserResolver(function()use($user){return $user;});return $r;}
function resetDeposit($deposit){$deposit->refresh();$deposit->status='PENDING';$deposit->unit_controller_status='PENDING';$deposit->sale_manager_status='NOT_REQUIRED';$deposit->save();App\UnitAction::create(['user_id'=>1,'unit_id'=>$deposit->unit_id,'action'=>'DEPOSIT','status_action'=>'PENDING','actionable_type'=>$deposit->getMorphClass(),'actionable_id'=>$deposit->id]);}
resetDeposit($deposit);$deposit->status='APPROVED';$deposit->unit_controller_status='APPROVED';$deposit->save();
$saleRow=(array)$db->table('test_sale_template')->first();$saleRow['id']=999907;$saleRow['order_no']='SYNTH-FINANCE-GUARD';$saleRow['customer_account_id']=1;$saleRow['status']='Ordered';$saleRow['authorization']='Authorized';$db->table('sales')->insert($saleRow);$db->table('approved_deposit_sale_links')->insert(['source_system'=>'bsunits','deposit_request_id'=>$deposit->id,'customer_account_id'=>1,'sale_id'=>999907,'approved_actor_id'=>1,'request_snapshot'=>'{}','snapshot_sha256'=>hash('sha256','{}'),'created_at'=>date('Y-m-d H:i:s')]);
$row=(array)$db->table('test_loan_account_template')->first();unset($row['id']);$row['sale_id']=999907;$db->table('loan_accounts')->insert($row);$n=App\UnitAction::count();$pointer=$db->table('canonical_units')->value('current_action_id');
if($c->cancel(decisionReq($user),$deposit->id)->getStatusCode()!==422||App\UnitAction::count()!==$n||$db->table('canonical_units')->value('current_action_id')!=$pointer||$deposit->fresh()->status!=='APPROVED')throw new RuntimeException('Financed deposit cancelled');
$db->table('loan_accounts')->delete();$db->table('sales')->where('id',999907)->update(['status'=>'Accepted']);if($c->cancel(decisionReq($user),$deposit->id)->getStatusCode()!==422)throw new RuntimeException('Accepted sale cancelled');
$db->table('approved_deposit_sale_links')->delete();$db->table('sales')->delete();

resetDeposit($deposit);
$r=$c->approve(decisionReq($user),$deposit->id);if(!($r instanceof App\Http\Resources\UnitDepositRequest)||$deposit->fresh()->status!=='APPROVED')throw new RuntimeException('Approval failed');
$n=App\UnitAction::count();if($c->approve(decisionReq($user),$deposit->id)->getStatusCode()!==422||App\UnitAction::count()!==$n)throw new RuntimeException('Approval replay');
resetDeposit($deposit);$r=$c->reject(decisionReq($user),$deposit->id);if(!($r instanceof App\Http\Resources\UnitDepositRequest)||$deposit->fresh()->status!=='REJECTED'||$db->table('canonical_units')->value('availability_state')!=='AVAILABLE')throw new RuntimeException('Reject failed');
resetDeposit($deposit);App\UnitAction::create(['user_id'=>1,'unit_id'=>$deposit->unit_id,'action'=>'CONTRACT','status_action'=>'PENDING','actionable_type'=>'App\\UnitContractRequest','actionable_id'=>999]);$pointer=$db->table('canonical_units')->value('current_action_id');
foreach(['approve','reject','cancel'] as $decision){if($c->$decision(decisionReq($user),$deposit->id)->getStatusCode()!==422||$db->table('canonical_units')->value('current_action_id')!=$pointer)throw new RuntimeException('Stale decision accepted');}
$deposit->is_selected_payment_option=1;$deposit->other_discount_allowance=1;$deposit->save();
$manager=App\User::role('sale_manager')->where('id','<>',$user->id)->firstOrFail();
$user->syncRoles(['unit_controller']);$user->unsetRelation('roles');$manager->syncRoles(['sale_manager']);$manager->unsetRelation('roles');
foreach([[$user,$manager],[$manager,$user]] as $order){
 resetDeposit($deposit);$deposit->sale_manager_status='PENDING';$deposit->save();
 $r=$c->approve(decisionReq($order[0]),$deposit->id);
 if(!($r instanceof App\Http\Resources\UnitDepositRequest)||$deposit->fresh()->status!=='PENDING')throw new RuntimeException('First stage result '.get_class($r).' status '.$deposit->fresh()->status.' controller '.$deposit->fresh()->unit_controller_status.' manager '.$deposit->fresh()->sale_manager_status);
 $n=App\UnitAction::count();if($c->approve(decisionReq($order[0]),$deposit->id)->getStatusCode()!==422||App\UnitAction::count()!==$n)throw new RuntimeException('Partial stage replay accepted');
 $r=$c->approve(decisionReq($order[1]),$deposit->id);if(!($r instanceof App\Http\Resources\UnitDepositRequest)||$deposit->fresh()->status!=='APPROVED')throw new RuntimeException('Second stage failed');
}
// Add the second role only to the disposable user-role table.
$user->assignRole('sale_manager');$user->unsetRelation('roles');
foreach(['NOT_REQUIRED','PENDING','APPROVED'] as $managerStatus){
 resetDeposit($deposit);$deposit->sale_manager_status=$managerStatus;$deposit->save();
 $r=$c->approve(decisionReq($user),$deposit->id);if(!($r instanceof App\Http\Resources\UnitDepositRequest)||$deposit->fresh()->status!=='APPROVED')throw new RuntimeException('Dual role approval failed');
}
resetDeposit($deposit);$deposit->sale_manager_status='PENDING';$deposit->unit_controller_status='APPROVED';$deposit->save();
$r=$c->approve(decisionReq($user),$deposit->id);if(!($r instanceof App\Http\Resources\UnitDepositRequest)||$deposit->fresh()->status!=='APPROVED')throw new RuntimeException('Dual role remaining stage failed');
resetDeposit($deposit);$deposit->sale_manager_status='PENDING';$deposit->unit_controller_status='APPROVED';$deposit->save();
$r=$c->reject(decisionReq($user),$deposit->id);if(!($r instanceof App\Http\Resources\UnitDepositRequest)||$deposit->fresh()->status!=='REJECTED')throw new RuntimeException('Dual role rejection failed');
$user->removeRole('sale_manager');$user->unsetRelation('roles');
resetDeposit($deposit);$n=App\UnitAction::count();$pointer=$db->table('canonical_units')->value('current_action_id');
Illuminate\Support\Facades\Notification::swap(new class {public function send($users,$notification){throw new RuntimeException('Synthetic notification failure');}});
$r=$c->approve(decisionReq($user),$deposit->id);if($r->getStatusCode()!==500||$deposit->fresh()->status!=='PENDING'||App\UnitAction::count()!==$n||$db->table('canonical_units')->value('current_action_id')!=$pointer||$db->transactionLevel()!==0)throw new RuntimeException('Failure not atomic');
if($db->table('canonical_units')->value('list_price')!=$price)throw new RuntimeException('Price changed');
echo json_encode(['status'=>'PASSED','approval'=>true,'financed_and_accepted_sale_cancellation_denied'=>true,'approval_replay_denied'=>true,'rejection_releases_owned_unit'=>true,'stale_approval_rejection_cancel_denied'=>true,'notification_failure_atomic_rollback'=>true,'price_preserved'=>true,'both_stage_orders'=>true,'partial_stage_replay_denied'=>true,'dual_role_pending_completed_not_required_stages'=>true,'dual_role_remaining_stage_rejection'=>true,'scope'=>'Disposable tables, original controller and real local role relationships; notifications faked; parallel decisions and live cutover not tested'],JSON_PRETTY_PRINT);
}catch(Throwable $e){echo json_encode(['status'=>'FAILED','type'=>get_class($e),'diagnostic'=>$e instanceof RuntimeException?$e->getMessage():'withheld']);exit(1);}
