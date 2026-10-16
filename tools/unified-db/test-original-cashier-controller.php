<?php
require '/var/www/html/public/global.php';
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cfg=config('unified_posting.database');$cfg['database']='unified_journal_writer_test';$cfg['username']='unified_journal_test';$cfg['password']='local-only-journal-test';$cfg['prefix']='tb_';$cfg['strict']=false;
config(array('database.connections.original_cashier_rehearsal'=>$cfg,'database.default'=>'original_cashier_rehearsal','unified_customer_ui.active'=>false));
$db=Illuminate\Support\Facades\DB::connection();
class OriginalCashierRehearsal extends App\Http\Controllers\TellerController {public function __construct(){$this->notification=new App\Models\Notification();}}
function cashierOriginalCheck($ok,$label){if(!$ok)throw new RuntimeException($label);}
try{
 $user=new App\Models\User();$user->id=3;$user->branch_id=1;$user->signature='';$user->is_signature=0;Illuminate\Support\Facades\Auth::setUser($user);
 $db->table('till_account')->insert(array('id'=>999900,'assign_user_id'=>3,'currency_id'=>2,'balance'=>100,'account_no'=>'SYNTH-TILL','account_name'=>'Synthetic teller'));
 $db->table('drawdown_accounts')->insert(array('id'=>999900,'customer_account_id'=>999900,'account_no'=>'SYNTH-DD','account_name'=>'Synthetic customer','currency'=>2,'balance'=>50,'coa_id'=>2,'branch'=>1,'unit_id'=>999900));
 $db->table('client_loan_accounts')->insert(array('id'=>999900,'balance'=>100,'currency'=>2,'status'=>GENERAL_LC_STATUS,'coa_id'=>1));
 $db->table('loans')->insert(array('id'=>999899,'loan_account_id'=>999900,'contract_id'=>'SYNTH-OLD','drawdown_acc'=>'SYNTH-OLD-DD','client_id'=>999899,'unit_id'=>999900,'company_branch_id'=>1,'status'=>6));
 $db->table('loans')->insert(array('id'=>999900,'loan_account_id'=>999900,'contract_id'=>'SYNTH-CURRENT','drawdown_acc'=>'SYNTH-DD','client_id'=>999900,'unit_id'=>999900,'company_branch_id'=>1,'status'=>3));
 $db->table('units')->insert(array('id'=>999900,'code'=>'SYNTH-UNIT','status'=>'deposit'));
 $db->table('coa_categories')->where('id',1)->update(array('name'=>'Cash on Hand-Teller','currency'=>2));
 $db->table('slips')->insert(array('print_cnt'=>0));
 $counts=array();foreach(array('journal_requiry','journal_detail','slips','till_transaction','notification','user_activity','audit','transactions_posting','till_transaction_reject') as $table)$counts[$table]=$db->table($table)->count();
 $request=Illuminate\Http\Request::create('/teller/deposit','POST',array('not_id'=>0,'currency_id'=>2,'till_date'=>'2026-10-08 10:00:00','drawdown_acc_id'=>999900,'client_id'=>999900,'client_name'=>'Synthetic customer','drawdown_acc'=>'SYNTH-DD','types'=>'Cash on Hand-Teller','amount'=>'12.25','description'=>'Synthetic original cashier submission','check_num'=>'','bank_name'=>'','users_id'=>3));
 Illuminate\Support\Facades\Request::swap($request);
 $db->beginTransaction();$response=(new OriginalCashierRehearsal())->postDeposit();
 cashierOriginalCheck(is_array($response)&&!empty($response['ins_notify']),'Original cashier submission did not complete');
 cashierOriginalCheck($db->transactionLevel()===1,'Cashier committed caller transaction');
 $slip=$db->table('slips')->orderBy('id','desc')->first();$header=$db->table('journal_requiry')->where('id',$slip->jr_id)->first();$line=$db->table('journal_detail')->where('id',$slip->jd_id)->first();
 cashierOriginalCheck($header&&$line&&$line->journal_id==$header->id,'Original cashier slip has invalid links');
 cashierOriginalCheck($db->table('journal_detail')->where('journal_id',$header->id)->count()===2,'Original cashier posting missing lines');
 cashierOriginalCheck($db->table('drawdown_accounts')->where('id',999900)->pluck('balance')==50,'Unapproved deposit credited balance');
 $notification=$db->table('notification')->orderBy('id','desc')->first();
 Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array('not_id'=>$notification->id,'flag_button'=>'ok')));
 $user->branch_id=2;$denied=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($denied===null&&$db->table('drawdown_accounts')->where('id',999900)->pluck('balance')==50,'Cross-branch approval changed balance');$user->branch_id=1;
 $user->id=4;$denied=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($denied===null&&$db->table('drawdown_accounts')->where('id',999900)->pluck('balance')==50,'Unassigned approval changed balance');$user->id=3;
 $approval=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($approval==='true','Original approval did not complete');
 cashierOriginalCheck($db->table('drawdown_accounts')->where('id',999900)->pluck('balance')==62.25,'Approved balance incorrect');
 $posted=$db->table('transactions_posting')->orderBy('id','desc')->first();cashierOriginalCheck($posted&&$posted->loan_id==999900&&$posted->drawdown_acc_id==999900&&$posted->amount==12.25,'Deposit posting linked wrong loan or drawdown');
 cashierOriginalCheck($db->table('client_loan_accounts')->where('id',999900)->pluck('balance')==100&&$db->table('loan_payments')->where('loan_id',999900)->count()===0,'Ordinary loan-linked cash deposit changed repayment balance');
 $postCount=$db->table('transactions_posting')->count();
 $again=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($again==='true'&&$db->table('drawdown_accounts')->where('id',999900)->pluck('balance')==62.25,'Approval replay credited twice');cashierOriginalCheck($db->table('transactions_posting')->count()===$postCount,'Approval replay duplicated loan posting');
 Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array('not_id'=>$notification->id,'flag_button'=>'ng')));
 $denied=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($denied===null&&$db->table('journal_requiry')->where('id',$header->id)->exists(),'Approved deposit rejection removed journal');
 Illuminate\Support\Facades\Request::swap($request);$pending=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck(!empty($pending['ins_notify']),'Rejection fixture submission failed');
 $pendingNotification=$db->table('notification')->orderBy('id','desc')->first();$pendingTill=$db->table('till_transaction')->where('id',$pendingNotification->n_source_id)->first();$pendingSlip=$db->table('slips')->where('id',$pendingTill->slips_id)->first();
 $decoy=(array)$db->table('journal_requiry')->where('id',$pendingSlip->jr_id)->first();unset($decoy['id']);$decoyId=$db->table('journal_requiry')->insertGetId($decoy);
 Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array('not_id'=>$pendingNotification->id,'flag_button'=>'ng')));
 $user->branch_id=2;$denied=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($denied===null&&$db->table('till_transaction')->where('id',$pendingTill->id)->exists(),'Cross-branch rejection succeeded');$user->branch_id=1;
 $user->id=4;$denied=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($denied===null&&$db->table('till_transaction')->where('id',$pendingTill->id)->exists(),'Unassigned rejection succeeded');$user->id=3;
 $archives=$db->table('till_transaction_reject')->count();
 $db->table('till_transaction')->where('id',$pendingTill->id)->update(array('slips_id'=>0));
 Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array('not_id'=>$pendingNotification->id,'flag_button'=>'ok')));
 $failedApproval=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($failedApproval===null&&$db->table('drawdown_accounts')->where('id',999900)->pluck('balance')==62.25&&$db->table('till_transaction')->where('id',$pendingTill->id)->pluck('approve_status')==0&&$db->table('transactions_posting')->count()===$postCount,'Missing receipt approval left partial balances or posting');
 Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array('not_id'=>$pendingNotification->id,'flag_button'=>'ng')));
 $failed=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($failed===null&&$db->table('till_transaction_reject')->count()===$archives&&$db->table('journal_requiry')->where('id',$pendingSlip->jr_id)->exists(),'Missing receipt rejection left partial records');
 $db->table('till_transaction')->where('id',$pendingTill->id)->update(array('slips_id'=>$pendingSlip->id));
 $rejected=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($rejected==='true','Original rejection did not complete');
 cashierOriginalCheck(!$db->table('journal_requiry')->where('id',$pendingSlip->jr_id)->exists()&&$db->table('journal_requiry')->where('id',$decoyId)->exists()&&$db->table('journal_requiry')->where('id',$header->id)->exists(),'Rejection removed incorrect journal');
 cashierOriginalCheck($db->table('drawdown_accounts')->where('id',999900)->pluck('balance')==62.25&&$db->table('till_transaction_reject')->where('reject_id',$pendingTill->id)->count()===1,'Rejection changed balance or missed archive');
 $replay=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($replay===null&&$db->table('till_transaction_reject')->where('reject_id',$pendingTill->id)->count()===1,'Rejected deposit replay changed archive');
 // Exercise the original approval's Loan Installment branch with a classified pending receipt.
 $db->table('loans')->where('id',999900)->update(array('start_date'=>'2026-09-08','penalty_rate_type'=>0,'penalty_period1'=>7));
 $db->table('transactions_requiry')->insert(array('loan_id'=>999900,'balance'=>100,'amount'=>0,'trans_type'=>'Synthetic opening','trans_date'=>'2026-09-08'));
 foreach(array('Stand-L-PHL-Indi-RT >1 year','AIR-Stand-L-PHL-Indi-RT<=1 year') as $name)$db->table('coa_categories')->insert(array('name'=>$name,'type'=>6,'currency'=>2));
 $db->table('repayment_schedule')->insert(array('loan_id'=>999900,'no'=>0,'loan_no'=>0,'schedule_date'=>'2026-09-08','principal'=>0,'interest'=>0,'fee'=>0,'other_fee'=>0,'type'=>'loan','status'=>1));
 $db->table('repayment_schedule')->insert(array('loan_id'=>999900,'no'=>1,'loan_no'=>1,'schedule_date'=>'2026-10-08','principal'=>12.25,'interest'=>2,'fee'=>0,'other_fee'=>0,'type'=>'loan','status'=>0));
 $db->statement('SAVEPOINT installment_fixture');
 $instRequest=Illuminate\Http\Request::create('/teller/deposit','POST',array_merge($request->all(),array('amount'=>'14.25')));Illuminate\Support\Facades\Request::swap($instRequest);
 $instSubmit=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck(!empty($instSubmit['ins_notify']),'Installment receipt submission failed');
 $instNotification=$db->table('notification')->orderBy('id','desc')->first();$db->table('till_transaction')->where('id',$instNotification->n_source_id)->update(array('deposit_type'=>'Loan Installment'));
 Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array('not_id'=>$instNotification->id,'flag_button'=>'ok')));
 $instApproval=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($instApproval==='true','Installment approval failed');
 $instPayment=$db->table('loan_payments')->where('loan_id',999900)->where('payment_month',1)->first();
 cashierOriginalCheck($instPayment&&$instPayment->paid_principal==12.25&&$instPayment->paid_interest==2&&$instPayment->repayment_owed==0,'Installment components differ');
 cashierOriginalCheck($db->table('client_loan_accounts')->where('id',999900)->pluck('balance')==87.75&&$db->table('drawdown_accounts')->where('id',999900)->pluck('balance')==62.25,'Installment account balances differ');
 $instPosting=$db->table('transactions_posting')->where('till_transaction_id',$instNotification->n_source_id)->first();cashierOriginalCheck($instPosting&&$instPosting->loan_id==999900&&$instPosting->total_principal==12.25&&$instPosting->total_interest==2,'Installment posting summary differs');
 foreach($db->table('journal_requiry')->where('disburs_loan_id',999900)->get() as $entry){$lines=$db->table('journal_detail')->where('journal_id',$entry->id);cashierOriginalCheck(round($lines->sum('debit'),4)===round($lines->sum('credit'),4),'Installment journal is unbalanced');}
 cashierOriginalCheck($db->table('repayment_schedule')->where('loan_id',999900)->where('no',1)->pluck('status')==1,'Installment schedule not completed');
 $instCount=$db->table('loan_payments')->where('loan_id',999900)->count();$instReplay=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($instReplay==='true'&&$db->table('loan_payments')->where('loan_id',999900)->count()===$instCount,'Installment approval replay duplicated repayments');
 foreach(array(array(5,9.25),array(14,0.25)) as $payments){
  $db->statement('ROLLBACK TO SAVEPOINT installment_fixture');
  $paid=0;
  foreach($payments as $index=>$amount){
   Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array_merge($request->all(),array('amount'=>(string)$amount))));
   $submitted=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck(!empty($submitted['ins_notify']),'Partial installment submission failed');
   $n=$db->table('notification')->orderBy('id','desc')->first();$db->table('till_transaction')->where('id',$n->n_source_id)->update(array('deposit_type'=>'Loan Installment'));
   Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array('not_id'=>$n->id,'flag_button'=>'ok')));
   $approved=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($approved==='true','Partial installment approval failed');
   $paid+=$amount;$principal=max(0,$paid-2);$owed=round(14.25-$paid,2);
   $rows=$db->table('loan_payments')->where('loan_id',999900)->where('payment_month',1);
   cashierOriginalCheck(round($rows->sum('paid_principal'),2)==$principal&&round($rows->sum('paid_interest'),2)==min(2.0,$paid),'Partial installment cumulative amounts differ');
   $actualTransaction=$db->table('transactions_requiry')->where('loan_id',999900)->orderBy('id','desc')->first();cashierOriginalCheck(round($actualTransaction->amount,2)==$amount&&round($actualTransaction->principal+$actualTransaction->interest,2)==$amount,'Partial transaction records full installment instead of actual payment');
   foreach($db->table('journal_requiry')->where('disburs_loan_id',999900)->get() as $entry){$lines=$db->table('journal_detail')->where('journal_id',$entry->id);cashierOriginalCheck(round($lines->sum('debit'),4)===round($lines->sum('credit'),4),'Partial installment journal unbalanced');}
   $latest=$rows->orderBy('id','desc')->first();cashierOriginalCheck(round($latest->repayment_owed,2)==$owed&&$latest->status==($owed>0?0:1),'Partial installment owed/status differs');
   cashierOriginalCheck(round($db->table('client_loan_accounts')->where('id',999900)->pluck('balance'),2)===round(100-$principal,2)&&$db->table('drawdown_accounts')->where('id',999900)->pluck('balance')==62.25,'Partial installment balance differs');
   cashierOriginalCheck($db->table('repayment_schedule')->where('loan_id',999900)->where('no',1)->pluck('status')==($owed>0?0:1),'Partial installment schedule status differs');
  }
 }
 foreach(array(array(0,0,5),array(1,0.05,1),array(1,0.05,0.05),array(1,0.2,1)) as $payoffCase){
  $db->statement('ROLLBACK TO SAVEPOINT installment_fixture');
  $db->table('loans')->where('id',999900)->update(array('interest_rate'=>$payoffCase[0],'disburse_date'=>date('Y-m-d',strtotime('-30 days'))));
  if($payoffCase[1]>0)$db->table('transactions_posting')->insert(array('loan_id'=>999900,'trans_type'=>'Pay-Off','amount'=>$payoffCase[1],'total_interest'=>$payoffCase[1],'total_principal'=>0,'posting_date'=>date('Y-m-d')));
  $beforePayoff=$db->table('transactions_posting')->count();$amount=$payoffCase[2];
  Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array_merge($request->all(),array('amount'=>(string)$amount))));
  $submitted=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck(!empty($submitted['ins_notify']),'Payoff submission failed');
  $n=$db->table('notification')->orderBy('id','desc')->first();$db->table('till_transaction')->where('id',$n->n_source_id)->update(array('deposit_type'=>'Pay-Off'));
  Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array('not_id'=>$n->id,'flag_button'=>'ok')));
  $approved=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($approved==='true','Payoff approval failed');
  $posted=$db->table('transactions_posting')->orderBy('id','desc')->first();$expectedInterest=min($amount,max(0,round(12.25*30*($payoffCase[0]*12/100)/360,4)-$payoffCase[1]));
  cashierOriginalCheck($posted->loan_id==999900&&$posted->drawdown_acc_id==999900&&abs($posted->total_interest-$expectedInterest)<0.00001&&abs($posted->total_principal-($amount-$expectedInterest))<0.00001,'Payoff principal/interest split differs: synthetic case '.json_encode($payoffCase).' actual '.json_encode(array($posted->total_interest,$posted->total_principal)).' expected '. $expectedInterest);
  cashierOriginalCheck($db->table('drawdown_accounts')->where('id',999900)->pluck('balance')==62.25+$amount&&$db->table('client_loan_accounts')->where('id',999900)->pluck('balance')==100&&$db->table('loans')->where('id',999900)->pluck('status')==3,'Payoff deposit incorrectly completed loan or balance');
  $replay=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($replay==='true'&&$db->table('transactions_posting')->count()===$beforePayoff+1,'Payoff replay duplicates posting');
 }
 $db->statement('ROLLBACK TO SAVEPOINT installment_fixture');$db->table('loans')->where('id',999900)->update(array('status'=>6));
 Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array_merge($request->all(),array('amount'=>'1'))));
 $submitted=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck(!empty($submitted['ins_notify']),'Missing loan payoff receipt submission failed');
 $n=$db->table('notification')->orderBy('id','desc')->first();$db->table('till_transaction')->where('id',$n->n_source_id)->update(array('deposit_type'=>'Pay-Off'));$beforePosting=$db->table('transactions_posting')->count();
 Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array('not_id'=>$n->id,'flag_button'=>'ok')));
 $denied=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($denied===null&&$db->table('drawdown_accounts')->where('id',999900)->pluck('balance')==62.25&&$db->table('till_transaction')->where('id',$n->n_source_id)->pluck('approve_status')==0&&$db->table('transactions_posting')->count()===$beforePosting,'Missing loan payoff approval left partial credit or posting');
 $db->statement('ROLLBACK TO SAVEPOINT installment_fixture');
 $db->table('currency')->insert(array('id'=>2,'code'=>'USD'));
 $db->table('loans')->where('id',999900)->update(array('workflow_status'=>'approve','disburse_date'=>'2026-09-08','penalty_rate1'=>0,'penalty_rate2'=>0));
 $db->table('loans')->where('id',999899)->update(array('workflow_status'=>'approve','drawdown_acc'=>'SYNTH-DD'));
 $db->table('repayment_schedule')->where('loan_id',999900)->where('no',1)->update(array('status'=>1));
 $db->table('loan_payments')->insert(array('loan_id'=>999900,'payment_month'=>1,'paid_principal'=>12.25,'paid_interest'=>2,'status'=>1,'condition_id'=>0,'repayment_date'=>'2026-10-08','loan_repayment_type'=>'loan','penalty_amount'=>0));
 $db->table('repayment_schedule')->insert(array('loan_id'=>999900,'no'=>2,'loan_no'=>2,'schedule_date'=>'2026-11-08','principal'=>10,'interest'=>1,'type'=>'loan','status'=>0));
 $db->table('coa_categories')->insert(array('name'=>'Inc-Fine and Penalty on Repayment of Loans and leasing','currency'=>2,'type'=>6));
 Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array_merge($request->all(),array('amount'=>'1'))));
 $submitted=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck(!empty($submitted['ins_notify']),'Penalty receipt submission failed');
 $n=$db->table('notification')->orderBy('id','desc')->first();$db->table('till_transaction')->where('id',$n->n_source_id)->update(array('deposit_type'=>'Penalty Fee'));
 Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array('not_id'=>$n->id,'flag_button'=>'ok')));
 $approved=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($approved==='true','Penalty approval failed');
 $penalties=$db->table('loan_payments')->where('loan_id',999900)->where('note','Penalty Fee');cashierOriginalCheck($penalties->sum('penalty_amount')==1&&$penalties->sum('paid_principal')==0&&$penalties->sum('paid_interest')==0,'Penalty synthetic components: '.json_encode($db->table('loan_payments')->where('loan_id',999900)->get(array('paid_principal','paid_interest','penalty_amount','note','loan_repayment_type'))));
 cashierOriginalCheck($db->table('loan_payments')->where('loan_id',999899)->count()===0&&$db->table('drawdown_accounts')->where('id',999900)->pluck('balance')==62.25&&$db->table('client_loan_accounts')->where('id',999900)->pluck('balance')==100,'Penalty changed historical loan or principal/drawdown balance');
 $penCount=$db->table('loan_payments')->count();$replay=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($replay==='true'&&$db->table('loan_payments')->count()===$penCount,'Penalty replay duplicated repayment');
 $penPayment=$penalties->orderBy('id','desc')->first();$penJournal=$db->table('journal_requiry')->orderBy('id','desc')->first();cashierOriginalCheck($penPayment->invoice_number===$penJournal->invoice_number,'Penalty invoice points to wrong journal');
 $penLines=$db->table('journal_detail')->where('journal_id',$penJournal->id);cashierOriginalCheck($penLines->sum('debit')==1&&$penLines->sum('credit')==1,'Penalty journal is unbalanced');
 cashierOriginalCheck($db->table('repayment_schedule')->where('loan_id',999900)->where('no',2)->pluck('status')==0,'Penalty completed future installment');
 Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array_merge($request->all(),array('amount'=>'1'))));$submitted=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck(!empty($submitted['ins_notify']),'Penalty failure fixture submission failed');
 $n=$db->table('notification')->orderBy('id','desc')->first();$db->table('till_transaction')->where('id',$n->n_source_id)->update(array('deposit_type'=>'Penalty Fee'));
 $db->table('coa_categories')->where('name','Inc-Fine and Penalty on Repayment of Loans and leasing')->update(array('name'=>'Synthetic unavailable penalty account'));$beforePenaltyPayments=$db->table('loan_payments')->count();$beforePenaltyJournals=$db->table('journal_requiry')->count();
 Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/deposit','POST',array('not_id'=>$n->id,'flag_button'=>'ok')));
 $failed=(new OriginalCashierRehearsal())->postDeposit();cashierOriginalCheck($failed===null&&$db->transactionLevel()===1&&$db->table('loan_payments')->count()===$beforePenaltyPayments&&$db->table('journal_requiry')->count()===$beforePenaltyJournals&&$db->table('drawdown_accounts')->where('id',999900)->pluck('balance')==62.25&&$db->table('till_transaction')->where('id',$n->n_source_id)->pluck('approve_status')==0,'Penalty failure escaped cashier rollback');
 $db->rollBack();foreach($counts as $table=>$count)cashierOriginalCheck($db->table($table)->count()===$count,'Cashier rollback incomplete: '.$table);
 cashierOriginalCheck($db->table('client_loan_accounts')->where('id',999900)->pluck('balance')==100&&$db->table('drawdown_accounts')->where('id',999900)->pluck('balance')==50&&$db->table('loan_payments')->where('loan_id',999900)->count()===0&&$db->table('repayment_schedule')->where('loan_id',999900)->count()===0,'Loan installment escaped caller rollback');
 echo json_encode(array('status'=>'PASSED','original_postDeposit_controller'=>true,'self_approval'=>true,'approval_replay_no_double_credit'=>true,'assigned_user_and_branch_scope'=>true,'approved_balance'=>true,'original_activity_and_notification'=>true,'slip_journal_links'=>true,'balance_unchanged_until_approval'=>true,'caller_rollback'=>true,'penalty_missing_account_atomic'=>true,'penalty_invoice_balanced_journal'=>true,'penalty_future_schedule_unchanged'=>true,'penalty_correct_active_loan'=>true,'penalty_components_balances_replay'=>true,'payoff_deposit_allocation'=>true,'payoff_interest_capped_to_deposit'=>true,'payoff_negative_interest_prevented'=>true,'payoff_missing_loan_atomic'=>true,'payoff_replay'=>true,'payoff_preserves_completion_stage'=>true,'partial_installment_then_completion'=>true,'remaining_cents_installment'=>true,'loan_installment_approval'=>true,'installment_balanced_journals'=>true,'installment_schedule_completed'=>true,'installment_caller_rollback'=>true,'installment_components_and_balances'=>true,'installment_replay'=>true,'loan_linked_ordinary_cash_deposit'=>true,'historical_loan_not_selected'=>true,'loan_posting_replay_no_duplicate'=>true,'approval_missing_receipt_atomic'=>true,'pending_rejection'=>true,'rejection_missing_receipt_atomic'=>true,'rejection_user_branch_scope'=>true,'approved_rejection_denied'=>true,'exact_journal_rejection'=>true,'rejection_replay_no_duplicate'=>true,'scope'=>'Synthetic ordinary cash deposit submission and self-approval on one scratch PDO; original constructor/middleware, installment submission through BCash, penalty/payoff deposits and final write cutover remain unverified.'),JSON_PRETTY_PRINT)."\n";
}catch(Exception $e){while($db->transactionLevel())$db->rollBack();fwrite(fopen('php://stderr','w'),'Original cashier controller failed: '.($e instanceof RuntimeException?$e->getMessage():get_class($e)).' at '.basename($e->getFile()).':'.$e->getLine()."\n");exit(1);}
