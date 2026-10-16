<?php
require '/var/www/html/public/global.php';
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cfg=config('unified_posting.database');$cfg['database']='unified_journal_writer_test';$cfg['username']='unified_journal_test';$cfg['password']='local-only-journal-test';$cfg['prefix']='tb_';$cfg['strict']=false;config(array('database.connections.auto_repayment_rehearsal'=>$cfg,'database.default'=>'auto_repayment_rehearsal','unified_customer_ui.active'=>false));
$db=Illuminate\Support\Facades\DB::connection();
class AutoRepaymentRehearsal extends App\Http\Controllers\RepaymentController {public function __construct(){}}
try{
 $user=new App\Models\User();$user->id=3;$user->username='synthetic';Illuminate\Support\Facades\Auth::setUser($user);
 $db->beginTransaction();$level=$db->transactionLevel();$before=$db->table('system_date')->count();
 ob_start();(new AutoRepaymentRehearsal())->exe_auto_payment(array(),'2026-10-08',null,1,new Illuminate\Support\Collection());$response=json_decode(ob_get_clean(),true);
 if($db->transactionLevel()!==$level)throw new RuntimeException('Empty batch leaked a transaction.');
 if(!is_array($response)||$response['success']!==0)throw new RuntimeException('Empty batch response changed.');
 $db->rollBack();if($db->table('system_date')->count()!==$before)throw new RuntimeException('System date escaped rollback.');
 $db->table('client_loan_accounts')->insert(array('id'=>888900,'balance'=>100,'balance_downpayment'=>50,'currency'=>2,'status'=>GENERAL_LC_STATUS,'coa_id'=>1));
 $db->table('loans')->insert(array('id'=>888900,'loan_account_id'=>888900,'contract_id'=>'SYNTH-AUTO','company_branch_id'=>1,'status'=>3));
 $db->table('drawdown_accounts')->insert(array('id'=>888900,'customer_account_id'=>888900,'balance'=>50,'coa_id'=>2,'currency'=>2,'account_no'=>'SYNTH-AUTO-DD'));
 $db->table('repayment_schedule')->insert(array('id'=>888900,'loan_id'=>888900,'no'=>1,'status'=>0,'type'=>'loan'));
 foreach(array('Inc-Fees on Loans Maintenance','Accounts Payable-Other','Inc-Fine and Penalty on Repayment of Loans and leasing') as $name)$db->table('coa_categories')->insert(array('name'=>$name,'currency'=>2));
 $payment=array('month_idx'=>1,'act_principal'=>12.25,'act_interest'=>0,'act_other_fee'=>0,'act_fee'=>0,'act_penalty'=>0,'act_total'=>12.25,'total'=>12.25,'type'=>'loan','schedule_date'=>'2026-10-08');
 $batch=array(array('l_id'=>888900,'dd_id'=>888900,'name'=>'Synthetic customer','reference'=>'SYNTH-AUTO','type'=>'loan','repay'=>array($payment)));
 $baseline=array();foreach(array('journal_requiry','journal_detail','loan_payments','transactions_requiry','system_date') as $table)$baseline[$table]=$db->table($table)->count();
 if(App\Models\DrawdownAccounts::find(888900)->coa_id!=2||App\Models\Loan::with('client_loan_account')->find(888900)->client_loan_account->coa_id!=1)throw new RuntimeException('Synthetic COA fixtures do not hydrate.');
 $db->beginTransaction();ob_start();(new AutoRepaymentRehearsal())->exe_auto_payment($batch,'2026-10-08',array(888900=>0),1,new Illuminate\Support\Collection());$result=json_decode(ob_get_clean(),true);
 if(!is_array($result)||$db->transactionLevel()!==1)throw new RuntimeException('Populated automatic batch failed.');
 if($db->table('drawdown_accounts')->where('id',888900)->pluck('balance')!=37.75||$db->table('client_loan_accounts')->where('id',888900)->pluck('balance')!=87.75)throw new RuntimeException('Automatic repayment balances disagree.');
 if($db->table('repayment_schedule')->where('id',888900)->pluck('status')!=1)throw new RuntimeException('Automatic repayment schedule not completed.');
 $saved=$db->table('loan_payments')->where('loan_id',888900)->orderBy('id','desc')->first();$journal=$db->table('journal_requiry')->orderBy('id','desc')->first();
 if(!$saved||$saved->invoice_number!==$journal->invoice_number||$saved->paid_principal!=12.25)throw new RuntimeException('Automatic payment invoice/amount mismatch.');
 $db->rollBack();foreach($baseline as $table=>$count)if($db->table($table)->count()!==$count)throw new RuntimeException('Automatic batch escaped rollback: '.$table);
 if($db->table('drawdown_accounts')->where('id',888900)->pluck('balance')!=50||$db->table('repayment_schedule')->where('id',888900)->pluck('status')!=0)throw new RuntimeException('Automatic balance/schedule rollback incomplete.');
 $db->table('client_loan_accounts')->where('id',888900)->update(array('air_id'=>1));
 foreach(array('completed','partial','insufficient_funds') as $scenario){
  $mixed=$payment;$mixed['act_interest']=2;$mixed['act_fee']=0.5;$mixed['act_other_fee']=0.25;$mixed['act_penalty']=1;$mixed['act_total']=16;$mixed['total']=$scenario==='partial'?20:16;
  $mixedBatch=$batch;$mixedBatch[0]['repay']=array($mixed);$db->beginTransaction();
  if($scenario==='insufficient_funds')$db->table('drawdown_accounts')->where('id',888900)->update(array('balance'=>10));
  ob_start();(new AutoRepaymentRehearsal())->exe_auto_payment($mixedBatch,'2026-10-08',array(888900=>0),1,new Illuminate\Support\Collection());$mixedResult=json_decode(ob_get_clean(),true);
  if(!is_array($mixedResult)||$db->transactionLevel()!==1)throw new RuntimeException('Mixed batch failed: '.$scenario);
  if($scenario==='insufficient_funds'){
   if($db->table('loan_payments')->count()!==$baseline['loan_payments']||$db->table('drawdown_accounts')->where('id',888900)->pluck('balance')!=10)throw new RuntimeException('Insufficient funds posted a payment.');
  }else{
   if($db->table('drawdown_accounts')->where('id',888900)->pluck('balance')!=34||$db->table('client_loan_accounts')->where('id',888900)->pluck('balance')!=87.75)throw new RuntimeException('Mixed components changed principal balance incorrectly.');
   $mixedPayment=$db->table('loan_payments')->where('loan_id',888900)->orderBy('id','desc')->first();$mixedJournal=$db->table('journal_requiry')->orderBy('id','desc')->first();
   foreach(array('paid_principal'=>12.25,'paid_interest'=>2,'paid_fee'=>0.5,'paid_other_fee'=>0.25,'penalty_amount'=>1,'repayment_owed'=>$scenario==='partial'?4:0) as $field=>$expected)if($mixedPayment->$field!=$expected)throw new RuntimeException('Mixed payment component differs: '.$field);
   if($mixedPayment->invoice_number!==$mixedJournal->invoice_number)throw new RuntimeException('Mixed invoice link differs.');
   $ledger=$db->table('journal_detail')->where('journal_id',$mixedJournal->id);
   if($ledger->sum('debit')!=16||$ledger->sum('credit')!=16)throw new RuntimeException('Mixed payment ledger is unbalanced.');
   if($db->table('repayment_schedule')->where('id',888900)->pluck('status')!=($scenario==='partial'?0:1))throw new RuntimeException('Partial schedule status differs.');
  }
  $db->rollBack();foreach($baseline as $table=>$count)if($db->table($table)->count()!==$count)throw new RuntimeException('Mixed batch escaped rollback: '.$table);
 }
 // Down-payment repayment is a separate balance, not loan principal.
 $db->beginTransaction();
 $db->table('repayment_schedule')->where('id',888900)->update(array('type'=>'downpayment'));
 $downPayment=$payment;$downPayment['type']='downpayment';$downBatch=$batch;$downBatch[0]['type']='downpayment';$downBatch[0]['repay']=array($downPayment);
 ob_start();(new AutoRepaymentRehearsal())->exe_auto_payment($downBatch,'2026-10-08',array(888900=>0),1,new Illuminate\Support\Collection());$downResult=json_decode(ob_get_clean(),true);
 if(!is_array($downResult)||$db->transactionLevel()!==1)throw new RuntimeException('Down-payment batch failed.');
 $downAccount=$db->table('client_loan_accounts')->where('id',888900)->first();
 if($downAccount->balance!=100||$downAccount->balance_downpayment!=37.75||$db->table('drawdown_accounts')->where('id',888900)->pluck('balance')!=37.75)throw new RuntimeException('Down-payment affected wrong balances.');
 $downSaved=$db->table('loan_payments')->where('loan_id',888900)->orderBy('id','desc')->first();$downJournal=$db->table('journal_requiry')->orderBy('id','desc')->first();
 if($downSaved->loan_repayment_type!=='downpayment'||$downSaved->invoice_number!==$downJournal->invoice_number)throw new RuntimeException('Down-payment type/invoice differs.');
 $downLedger=$db->table('journal_detail')->where('journal_id',$downJournal->id);
 if($downLedger->sum('debit')!=12.25||$downLedger->sum('credit')!=12.25)throw new RuntimeException('Down-payment ledger unbalanced.');
 $db->rollBack();foreach($baseline as $table=>$count)if($db->table($table)->count()!==$count)throw new RuntimeException('Down-payment escaped rollback.');
 $restored=$db->table('client_loan_accounts')->where('id',888900)->first();if($restored->balance!=100||$restored->balance_downpayment!=50||$db->table('repayment_schedule')->where('id',888900)->pluck('type')!=='loan')throw new RuntimeException('Down-payment rollback incomplete.');
 // Final principal payment must preserve the original disabled auto-completion policy.
 $db->beginTransaction();
 $db->table('client_loan_accounts')->where('id',888900)->update(array('balance'=>12.25));
 $db->table('loans')->where('id',888900)->update(array('start_date'=>'2026-09-08','start_payment_date'=>'2026-09-08','transfer_date'=>'0000-00-00','penalty_rate1'=>0,'penalty_rate2'=>0));
 $db->table('repayment_schedule')->where('id',888900)->update(array('schedule_date'=>'2026-10-08','principal'=>12.25,'interest'=>0,'fee'=>0,'other_fee'=>0));
 $db->table('repayment_schedule')->insert(array('id'=>888901,'loan_id'=>888900,'no'=>2,'status'=>0,'type'=>'loan','schedule_date'=>'2026-11-08','principal'=>0,'interest'=>0,'fee'=>0,'other_fee'=>0));
 ob_start();(new AutoRepaymentRehearsal())->exe_auto_payment($batch,'2026-10-08',array(888900=>0),1,new Illuminate\Support\Collection());$finalResult=json_decode(ob_get_clean(),true);
 if(!is_array($finalResult)||$db->transactionLevel()!==1)throw new RuntimeException('Final principal payment failed.');
 if($db->table('client_loan_accounts')->where('id',888900)->pluck('balance')!=0||$db->table('drawdown_accounts')->where('id',888900)->pluck('balance')!=37.75)throw new RuntimeException('Final principal balances differ.');
 if($db->table('repayment_draft')->where('id',888900)->exists())throw new RuntimeException('Manual draft escaped caller rollback.');
 if($db->table('loans')->where('id',888900)->pluck('status')!=3||$db->table('client_loan_accounts')->where('id',888900)->pluck('status')!=GENERAL_LC_STATUS)throw new RuntimeException('Final payment changed original disabled completion policy.');
 $finalJournal=$db->table('journal_requiry')->orderBy('id','desc')->first();$finalLedger=$db->table('journal_detail')->where('journal_id',$finalJournal->id);
 if($finalLedger->sum('debit')!=12.25||$finalLedger->sum('credit')!=12.25)throw new RuntimeException('Final payment ledger differs.');
 if($db->table('repayment_schedule')->where('id',888900)->pluck('status')!=1)throw new RuntimeException('Final schedule not completed.');
 $db->rollBack();foreach($baseline as $table=>$count)if($db->table($table)->count()!==$count)throw new RuntimeException('Final payment escaped rollback.');
 if($db->table('client_loan_accounts')->where('id',888900)->pluck('balance')!=100||$db->table('drawdown_accounts')->where('id',888900)->pluck('balance')!=50||$db->table('repayment_schedule')->where('id',888901)->exists())throw new RuntimeException('Final payment fixture rollback incomplete.');
 $provisionIds=array();foreach(array('expense'=>'Exp-Bad and Doubtful Debt SYNTH-X >','loss'=>'(Less) Specific Loan Loses SYNTH-X >') as $key=>$name)$provisionIds[$key]=$db->table('coa_categories')->insertGetId(array('name'=>$name,'currency'=>2,'type'=>6));
 foreach(array(0,1) as $nplFlag){
  $db->beginTransaction();$db->table('client_loan_accounts')->where('id',888900)->update(array('status'=>3,'acc_key'=>'SYNTH-X>','loan_ref'=>'SYNTH-AUTO'));
  ob_start();(new AutoRepaymentRehearsal())->exe_auto_payment($batch,'2026-10-08',array(888900=>$nplFlag),1,new Illuminate\Support\Collection());$provisionResult=json_decode(ob_get_clean(),true);
  if(!is_array($provisionResult)||$db->transactionLevel()!==1)throw new RuntimeException('Provision automatic batch failed.');
  $provisionJournal=$db->table('journal_requiry')->orderBy('id','desc')->first();$ledger=$db->table('journal_detail')->where('journal_id',$provisionJournal->id);
  if(round($ledger->sum('debit'),4)!=31.025||round($ledger->sum('credit'),4)!=31.025)throw new RuntimeException('Provision ledger totals differ.');
  $lossLines=$db->table('journal_detail')->where('journal_id',$provisionJournal->id)->where('coa_id',$provisionIds['loss']);
  if($lossLines->sum('debit')!=10||$lossLines->sum('credit')!=8.775)throw new RuntimeException('Provision reversal/replacement differs.');
  if($db->table('client_loan_accounts')->where('id',888900)->pluck('balance')!=87.75||$db->table('drawdown_accounts')->where('id',888900)->pluck('balance')!=37.75)throw new RuntimeException('Provision changed customer balances.');
  $db->rollBack();foreach($baseline as $table=>$count)if($db->table($table)->count()!==$count)throw new RuntimeException('Provision escaped rollback.');
 }
 // Rejected manual repayment closes its own transaction, preserving a caller's.
 $db->table('loans')->where('id',888900)->update(array('client_id'=>888900,'drawdown_acc'=>'SYNTH-AUTO-DD'));
 $session=$app->make('session')->driver();$session->start();
 foreach(array(0,1) as $callerLevel){
  if($callerLevel)$db->beginTransaction();
  $request=Illuminate\Http\Request::create('/loans/repayment','POST',array('loan_id'=>888900,'repayment_amount'=>500));$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);
  $rejected=(new AutoRepaymentRehearsal())->postLoanRepay();
  if($db->transactionLevel()!==$callerLevel)throw new RuntimeException('Insufficient manual repayment leaked or closed caller transaction.');
  if($rejected->getStatusCode()!==302||!$session->has('danger'))throw new RuntimeException('Original insufficient balance warning missing.');
  foreach($baseline as $table=>$count)if($db->table($table)->count()!==$count)throw new RuntimeException('Rejected manual repayment persisted business rows.');
  if($db->table('drawdown_accounts')->where('id',888900)->pluck('balance')!=50||$db->table('client_loan_accounts')->where('id',888900)->pluck('balance')!=100)throw new RuntimeException('Rejected manual repayment changed balances.');
  if($callerLevel)$db->rollBack();
 }
 // Successful original manual controller posting, isolated by caller rollback.
 foreach(array(false,true) as $manualFinal){
 $db->beginTransaction();
 if($manualFinal)$db->table('client_loan_accounts')->where('id',888900)->update(array('balance'=>12.25));
 $db->table('loans')->where('id',888900)->update(array('start_date'=>'2026-09-08','start_payment_date'=>'2026-09-08','penalty_rate_type'=>0,'penalty_period1'=>7));
 $db->table('repayment_schedule')->where('id',888900)->update(array('schedule_date'=>'2026-10-08','principal'=>12.25,'interest'=>0,'fee'=>0,'other_fee'=>0,'loan_no'=>1));
 $db->table('repayment_schedule')->insert(array('id'=>888899,'loan_id'=>888900,'no'=>0,'loan_no'=>0,'schedule_date'=>'2026-09-08','principal'=>0,'interest'=>0,'fee'=>0,'other_fee'=>0,'type'=>'loan','status'=>1));
 $db->table('transactions_requiry')->insert(array('loan_id'=>888900,'balance'=>$manualFinal?12.25:100,'balance_downpayment'=>50,'amount'=>0,'trans_type'=>'Synthetic opening','trans_date'=>'2026-09-08'));
 $user->branch_id=1;
 $db->table('repayment_draft')->insert(array('id'=>888900,'loan_id'=>888900,'status'=>0));
 $manualData=array('loan_id'=>888900,'repayment_amount'=>12.25,'act_principal'=>12.25,'act_interest'=>0,'act_fee'=>0,'act_other_fee'=>0,'act_penalty'=>0,'act_total'=>12.25,'note'=>'Synthetic manual repayment','dpDate'=>'2026-10-08','last_paid_date'=>'_','payment_type'=>0,'sch_penalty'=>0,'draft_id'=>888900,'debit'=>array(12.25),'credit'=>array(12.25),'parent_debit'=>array(2),'parent_credit'=>array(1),'d_description'=>array('Synthetic'),'c_description'=>array('Synthetic'),'description'=>array('Synthetic'),'debit_down'=>array(0),'parent_debit_down'=>array(0),'currency'=>2);
 $request=Illuminate\Http\Request::create('/loans/repayment','POST',$manualData);$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);
 $manualResponse=(new AutoRepaymentRehearsal())->postLoanRepay();
 if($manualResponse->getStatusCode()!==302||!$session->has('msg')||$db->transactionLevel()!==1)throw new RuntimeException('Successful manual repayment did not complete.');
 $manualPayment=$db->table('loan_payments')->where('loan_id',888900)->orderBy('id','desc')->first();$manualJournal=$db->table('journal_requiry')->orderBy('id','desc')->first();
 if(!$manualPayment||$manualPayment->paid_principal!=12.25||$manualPayment->invoice_number!==$manualJournal->invoice_number)throw new RuntimeException('Synthetic manual payment differs: '.json_encode(array('payment_principal'=>$manualPayment?$manualPayment->paid_principal:null,'payment_invoice'=>$manualPayment?$manualPayment->invoice_number:null,'journal_invoice'=>$manualJournal?$manualJournal->invoice_number:null)));
 if($db->table('client_loan_accounts')->where('id',888900)->pluck('balance')!=($manualFinal?0:87.75)||$db->table('drawdown_accounts')->where('id',888900)->pluck('balance')!=37.75)throw new RuntimeException('Manual balances differ.');
 if($db->table('repayment_draft')->where('id',888900)->pluck('status')!=1)throw new RuntimeException('Successful manual draft not completed.');
 $manualLedger=$db->table('journal_detail')->where('journal_id',$manualJournal->id);if($manualLedger->sum('debit')!=12.25||$manualLedger->sum('credit')!=12.25)throw new RuntimeException('Manual ledger differs.');
 if($manualFinal){
  $closedLoan=$db->table('loans')->where('id',888900)->first();$closedAccount=$db->table('client_loan_accounts')->where('id',888900)->first();
  if($closedLoan->status!=10||substr($closedLoan->settlement_date,0,10)!=='2026-10-08'||$closedAccount->status!=8)throw new RuntimeException('Original manual final repayment did not complete loan/account.');
  if($db->table('repayment_schedule')->where('id',888900)->pluck('status')!=1)throw new RuntimeException('Final manual repayment schedule not completed.');
 }
 $db->rollBack();foreach($baseline as $table=>$count)if($db->table($table)->count()!==$count)throw new RuntimeException('Manual repayment escaped caller rollback.');
 if($db->table('loans')->where('id',888900)->pluck('status')!=3||$db->table('client_loan_accounts')->where('id',888900)->pluck('balance')!=100||$db->table('client_loan_accounts')->where('id',888900)->pluck('status')!=GENERAL_LC_STATUS)throw new RuntimeException('Manual completion escaped rollback.');
 }
 // Standalone failure after a real ledger insert must roll back all posting writes.
 $db->table('loans')->where('id',888900)->update(array('start_date'=>'2026-09-08','start_payment_date'=>'2026-09-08','penalty_rate_type'=>0,'penalty_period1'=>7));
 $db->table('repayment_schedule')->where('id',888900)->update(array('schedule_date'=>'2026-10-08','principal'=>12.25,'interest'=>0,'fee'=>0,'other_fee'=>0,'loan_no'=>1));
 $db->table('repayment_schedule')->insert(array('id'=>888899,'loan_id'=>888900,'no'=>0,'loan_no'=>0,'schedule_date'=>'2026-09-08','principal'=>0,'interest'=>0,'fee'=>0,'other_fee'=>0,'type'=>'loan','status'=>1));
 $db->table('transactions_requiry')->insert(array('loan_id'=>888900,'balance'=>100,'balance_downpayment'=>50,'amount'=>0,'trans_type'=>'Synthetic opening','trans_date'=>'2026-09-08'));
 $failedBaseline=array();foreach(array('journal_requiry','journal_detail','loan_payments','transactions_requiry','user_activity','audit') as $table)$failedBaseline[$table]=$db->table($table)->count();
 $manualLedgerInterrupted=false;App\Models\JournalDetail::created(function($model)use(&$manualLedgerInterrupted){$manualLedgerInterrupted=true;throw new RuntimeException('Synthetic ledger interruption.');});
 $session->forget('msg');$request=Illuminate\Http\Request::create('/loans/repayment','POST',$manualData);$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);
 $failedResponse=(new AutoRepaymentRehearsal())->postLoanRepay();
 if(!$manualLedgerInterrupted||$failedResponse->getStatusCode()!==302||$session->has('msg')||$db->transactionLevel()!==0)throw new RuntimeException('Manual ledger failure not exercised or cleanup incomplete.');
 foreach($failedBaseline as $table=>$count)if($db->table($table)->count()!==$count)throw new RuntimeException('Manual ledger failure persisted '.$table);
 if($db->table('client_loan_accounts')->where('id',888900)->pluck('balance')!=100||$db->table('drawdown_accounts')->where('id',888900)->pluck('balance')!=50||$db->table('repayment_schedule')->where('id',888900)->pluck('status')!=0)throw new RuntimeException('Manual ledger failure changed balance/schedule.');
 App\Models\SystemDate::saved(function($model){throw new RuntimeException('Synthetic posting interruption.');});
 ob_start();(new AutoRepaymentRehearsal())->exe_auto_payment(array(),'2026-10-09',null,1,new Illuminate\Support\Collection());ob_end_clean();
 if($db->transactionLevel()!==0||$db->table('system_date')->count()!==$before)throw new RuntimeException('Failed run persisted system date or transaction.');
 echo json_encode(array('status'=>'PASSED','empty_batch_transaction_closed'=>true,'system_date_caller_rollback'=>true,'system_date_failure_atomic'=>true,'manual_draft_completion_and_caller_rollback'=>true,'manual_after_ledger_failure_full_rollback'=>true,'manual_final_principal_closes_loan_and_account'=>true,'manual_settlement_date_schedule_and_rollback'=>true,'manual_successful_principal_invoice_balances_and_rollback'=>true,'manual_insufficient_funds_transaction_cleanup'=>true,'manual_rejection_preserves_caller_transaction'=>true,'separate_downpayment_balance_and_rollback'=>true,'zero_principal_original_completion_policy'=>true,'final_payment_balanced_and_rolled_back'=>true,'populated_principal_repayment'=>true,'mixed_interest_fees_penalty'=>true,'partial_payment_schedule'=>true,'insufficient_funds_skip'=>true,'balanced_mixed_ledger'=>true,'nonperforming_provision_reversal_replacement'=>true,'payment_invoice_and_balances'=>true,'schedule_and_balance_rollback'=>true,'scope'=>'Synthetic original empty, principal and mixed-component automatic repayment controller; Final principal zero preserves original disabled automatic loan completion; other classifications and middleware remain pending.'),JSON_PRETTY_PRINT)."\n";
}catch(Exception $e){while($db->transactionLevel())$db->rollBack();fwrite(fopen('php://stderr','w'),'Automatic repayment test failed: '.($e instanceof RuntimeException?$e->getMessage():get_class($e))."\n");exit(1);}
