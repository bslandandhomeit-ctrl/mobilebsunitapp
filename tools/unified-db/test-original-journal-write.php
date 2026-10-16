<?php
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cfg=config('unified_posting.database');$cfg['database']='unified_journal_writer_test';$cfg['username']='unified_journal_test';$cfg['password']='local-only-journal-test';$cfg['prefix']='tb_';$cfg['strict']=false;
config(array('database.connections.original_finance_rehearsal'=>$cfg,'database.default'=>'original_finance_rehearsal','unified_customer_ui.active'=>false));
$db=Illuminate\Support\Facades\DB::connection();
function originalCheck($ok,$label){if(!$ok)throw new RuntimeException($label);}
try {
 $headers=$db->table('journal_requiry')->count();$details=$db->table('journal_detail')->count();
 $rows=array(array(1,'12.25','Synthetic debit',2,'12.25','Synthetic credit','Synthetic original journal','Cash Deposit'));
 $db->beginTransaction();record_journal_no_trans(null,'2026-10-08 02:00:00',$rows,1,3,0,null,1);
 originalCheck($db->table('journal_requiry')->count()===$headers+1&&$db->table('journal_detail')->count()===$details+2,'Original helper did not write all rows');
 $journal=$db->table('journal_requiry')->orderBy('id','desc')->first();$lines=$db->table('journal_detail')->where('journal_id',$journal->id)->get();
 originalCheck(count($lines)===2,'Original helper linked wrong journal');originalCheck($lines[0]->debit==='12.2500'&&$lines[1]->credit==='12.2500','Original stored amounts differ');
 originalCheck(strpos($journal->receipt_no,'RP10026')===0,'Original receipt format changed');
 $db->rollBack();originalCheck($db->table('journal_requiry')->count()===$headers&&$db->table('journal_detail')->count()===$details,'Original posting rollback incomplete');
 config(array('database.connections.interleaved_post'=>$cfg));$other=Illuminate\Support\Facades\DB::connection('interleaved_post');$injected=false;
 $db->listen(function($query,$bindings,$time,$connection)use($other,&$injected){if(!$injected&&$connection==='original_finance_rehearsal'&&stripos($query,'insert into `tb_journal_requiry`')===0){$injected=true;$other->table('journal_requiry')->insert(array('tran_id'=>0,'entry_date'=>'2026-10-08 03:00:00','description'=>'Other synthetic post','user_id'=>3,'trans_type'=>'Cash Deposit','is_audit'=>'0'));}});
 $db->beginTransaction();$posted=record_journal_no_trans(null,'2026-10-08 03:00:00',$rows,1,3,0,null,0);$own=$db->table('journal_requiry')->where('description','Synthetic original journal')->orderBy('id','desc')->first();$foreign=$db->table('journal_requiry')->where('description','Other synthetic post')->orderBy('id','desc')->first();
 originalCheck($injected&&$own&&$foreign,'Interleaved write did not occur');originalCheck($posted['journal_id']==$own->id&&$db->table('journal_detail')->where('id',$posted['journal_detail_id'])->pluck('journal_id')==$own->id,'Returned cashier references captured foreign journal');$db->table('slips')->insert(array('jr_id'=>$posted['journal_id'],'jd_id'=>$posted['journal_detail_id']));$slip=$db->table('slips')->orderBy('id','desc')->first();originalCheck($slip->jr_id==$own->id&&$slip->jd_id==$posted['journal_detail_id'],'Slip references changed');$safe=$db->table('journal_detail')->where('journal_id',$own->id)->count()===2&&$db->table('journal_detail')->where('journal_id',$foreign->id)->count()===0;$db->rollBack();originalCheck($safe,'Concurrent header captured original journal lines');
 $beforeHeaders=$db->table('journal_requiry')->count();$db->beginTransaction();record_journal_no_trans(null,'2026-10-08 04:00:00',$rows,1,3,0,$foreign->id,0);originalCheck($db->table('journal_requiry')->count()===$beforeHeaders&&$db->table('journal_detail')->where('journal_id',$foreign->id)->count()===2,'Explicit existing journal link changed');$db->rollBack();
 $db->beginTransaction();
 $first=new App\Models\JournalRequiry();$first->tran_id=0;$first->entry_date='2026-10-08 05:00:00';$first->invoice_number=null;$first->description='Synthetic database allocated header';$first->user_id=3;$first->save();
 $second=new App\Models\JournalRequiry();$second->tran_id=0;$second->entry_date='2026-10-08 05:00:00';$second->invoice_number='00001234';$second->description='Synthetic external invoice header';$second->user_id=3;$second->save();
 originalCheck($first->id>0&&$second->id>$first->id,'Database generated journal IDs missing');
 originalCheck($db->table('journal_requiry')->where('id',$first->id)->pluck('invoice_number')===null&&$db->table('journal_requiry')->where('id',$second->id)->pluck('invoice_number')==='00001234','Invoice references changed');
 $db->rollBack();
 $beforeGenerated=$db->table('journal_requiry')->count();$db->beginTransaction();
 $generated=new App\Models\JournalRequiry();$generated->tran_id=0;$generated->entry_date='2026-10-08 06:00:00';$generated->description='Synthetic generated invoice';$generated->user_id=3;
 originalCheck($generated->saveWithGeneratedInvoice()===true,'Generated invoice header failed');
 originalCheck($db->table('journal_requiry')->where('id',$generated->id)->pluck('invoice_number')===str_pad($generated->id,8,'0',STR_PAD_LEFT),'Eight-digit generated invoice changed');
 originalCheck($db->transactionLevel()===1,'Generated invoice committed caller transaction');$db->rollBack();
 originalCheck($db->table('journal_requiry')->count()===$beforeGenerated,'Generated invoice escaped rollback');
 App\Models\JournalRequiry::saving(function($model){if($model->description==='Synthetic invoice failure'&&$model->exists)return false;});
 $failed=new App\Models\JournalRequiry();$failed->tran_id=0;$failed->entry_date='2026-10-08 06:00:00';$failed->description='Synthetic invoice failure';$failed->user_id=3;$rejectedInvoice=false;
 try{$failed->saveWithGeneratedInvoice();}catch(RuntimeException $e){$rejectedInvoice=true;}
 originalCheck($rejectedInvoice&&$db->table('journal_requiry')->count()===$beforeGenerated,'Failed invoice left orphan header');
 $beforeTransactions=$db->table('transactions_requiry')->count();$db->beginTransaction();
 $transactionIds=array();
 foreach(array('Disbursement','Loan Repayment') as $type){
  $transaction=new App\Models\TransactionsRequiry();$transaction->loan_id=1;$transaction->trans_date='2026-10-08 07:00:00';$transaction->trans_type=$type;$transaction->amount='12.2500';$transaction->principal='12.2500';$transaction->user_id=3;
  originalCheck($transaction->save()&&$transaction->id>0,'Transaction ID not generated');$transactionIds[]=$transaction->id;
  $linked=new App\Models\JournalRequiry();$linked->tran_id=$transaction->id;$linked->entry_date='2026-10-08 07:00:00';$linked->description='Synthetic transaction link';$linked->user_id=3;$linked->saveWithGeneratedInvoice();
  originalCheck($db->table('journal_requiry')->where('id',$linked->id)->pluck('tran_id')==$transaction->id,'Journal linked incorrect transaction');
  originalCheck($db->table('transactions_requiry')->where('id',$transaction->id)->pluck('amount')==12.25,'Transaction stored amount changed');
 }
 originalCheck($transactionIds[0]!==$transactionIds[1],'Transaction IDs collide');$db->rollBack();
 originalCheck($db->table('transactions_requiry')->count()===$beforeTransactions&&$db->table('journal_requiry')->count()===$beforeGenerated,'Linked transaction rollback incomplete');
 $loan=(object)array('id'=>1,'contract_id'=>'SYNTHETIC-CONTRACT','client_loan_account'=>(object)array('balance'=>100,'balance_downpayment'=>50));
 $repaymentData=array('act_interest'=>0,'act_principal'=>12.25,'act_fee'=>0,'act_other_fee'=>0,'act_penalty'=>0,'act_total'=>12.25,'note'=>'Synthetic repayment');
 foreach(array('WriteOff Payment','Auto Accrued Interest','Register Fixed Asset','Loan Repayment') as $operation){
  $db->beginTransaction();$beforeLines=$db->table('journal_detail')->count();$beforeOps=$db->table('journal_requiry')->count();
  record_journal($loan,'2026-10-08 08:00:00',$operation,$repaymentData,$rows,1,3,null,'loan');
  originalCheck($db->table('journal_requiry')->count()===$beforeOps+1&&$db->table('journal_detail')->count()===$beforeLines+2,'Original operation missing rows: '.$operation);
  $operationHeader=$db->table('journal_requiry')->orderBy('id','desc')->first();$operationLines=$db->table('journal_detail')->where('journal_id',$operationHeader->id)->get();
  originalCheck(count($operationLines)===2&&$operationLines[0]->debit==='12.2500'&&$operationLines[1]->credit==='12.2500','Original operation unbalanced: '.$operation);
  originalCheck((float)$operationLines[0]->b_debit===(float)$operationLines[0]->p_debit+12.25,'Original operation running debit incorrect: '.$operation);
  if($operation==='Loan Repayment'){$operationTran=$db->table('transactions_requiry')->where('id',$operationHeader->tran_id)->first();originalCheck($operationTran&&$operationTran->principal==12.25&&$operationTran->balance==87.75,'Repayment amount/balance differs');}
  $db->rollBack();
 }
 $db->beginTransaction();$groupRows=array_merge($rows,$rows);$groupData=$repaymentData;$groupData['act_principal']=24.5;$groupData['act_total']=24.5;
 record_journal($loan,'2026-10-08 09:00:00','Loan Repayment',$groupData,$groupRows,1,3,null,'loan');
 $groupHeader=$db->table('journal_requiry')->orderBy('id','desc')->first();$groupDebit=$db->table('journal_detail')->where('journal_id',$groupHeader->id)->where('coa_id',1)->first();
 originalCheck($db->table('journal_detail')->where('journal_id',$groupHeader->id)->count()===3&&$groupDebit->debit==='24.5000'&&(float)$groupDebit->b_debit===(float)$groupDebit->p_debit+24.5,'Grouped repayment running debit incorrect');
 $db->rollBack();
 $db->beginTransaction();
 $payment=new App\Models\LoanPayments();$payment->loan_id=1;$payment->loan_repayment_type='loan';$payment->invoice_number='00000000';$payment->repayment_date='2026-10-08';$payment->paid_principal=12.25;$payment->save();
 $postedRepayment=record_journal($loan,'2026-10-08 11:00:00','Loan Repayment',$repaymentData,$rows,1,3,null,'loan');
 App\Services\OriginalRepaymentInvoiceLinker::attach($postedRepayment,array($payment->id),1,'loan');
 originalCheck($db->table('loan_payments')->where('id',$payment->id)->pluck('invoice_number')===$postedRepayment['invoice_number'],'Payment did not receive actual invoice');
 $historical=new App\Models\LoanPayments();$historical->loan_id=1;$historical->loan_repayment_type='loan';$historical->invoice_number='HISTORICAL';$historical->repayment_date='2026-10-08';$historical->save();$deniedHistory=false;
 try{App\Services\OriginalRepaymentInvoiceLinker::attach($postedRepayment,array($historical->id),1,'loan');}catch(RuntimeException $e){$deniedHistory=true;}
 originalCheck($deniedHistory&&$db->table('loan_payments')->where('id',$historical->id)->pluck('invoice_number')==='HISTORICAL','Historical payment invoice changed');
 $wrongOwner=new App\Models\LoanPayments();$wrongOwner->loan_id=2;$wrongOwner->loan_repayment_type='loan';$wrongOwner->invoice_number='00000000';$wrongOwner->repayment_date='2026-10-08';$wrongOwner->save();$deniedOwner=false;
 try{App\Services\OriginalRepaymentInvoiceLinker::attach($postedRepayment,array($wrongOwner->id),1,'loan');}catch(RuntimeException $e){$deniedOwner=true;}
 originalCheck($deniedOwner,'Foreign loan invoice linked');$db->rollBack();originalCheck($db->table('loan_payments')->count()===0,'Invoice link escaped caller rollback');
 echo json_encode(array('status'=>'PASSED','original_helper_unmodified'=>false,'targeted_inserted_header_id_fix'=>true,'database_allocated_header_ids'=>true,'generated_transaction_ids_and_journal_links'=>true,'generated_invoice_format'=>true,'generated_invoice_failure_atomic'=>true,'null_and_external_invoice_preserved'=>true,'interleaved_header_isolation'=>true,'cashier_slip_actual_journal_links'=>true,'original_helper_operations'=>array('WriteOff Payment','Auto Accrued Interest','Register Fixed Asset','Loan Repayment'),'grouped_repayment_running_debit'=>true,'actual_repayment_invoice_links'=>true,'historical_and_foreign_payment_guards'=>true,'original_table_names_via_views'=>true,'balanced_saved_amounts'=>true,'receipt_format'=>true,'caller_transaction_rollback'=>true,'scope'=>'Synthetic journal and interleaved-header regression in disposable schema. Original receipt numbering and complete cashier/repayment workflows remain pending.'),JSON_PRETTY_PRINT)."\n";
}catch(Exception $e){if($db->transactionLevel())$db->rollBack();fwrite(fopen('php://stderr','w'),'Original journal test failed: '.($e instanceof RuntimeException?$e->getMessage():get_class($e)).' at '.basename($e->getFile()).':'.$e->getLine()."\n");exit(1);}
