<?php
namespace App\Services;
use Illuminate\Database\Connection;
/** Internal ordinary-deposit approval; caller supplies a verified legacy Beloan user context. */
class UnifiedCashDepositApproval {
 private $db;
 public function __construct(Connection $db){if($db->getTablePrefix()!=='')throw new \LogicException('Unified connection required.');$this->db=$db;}
 public function pendingNotifications($userId,$branchId){
  return $this->db->table('notification as n')->join('till_transaction as t','t.id','=','n.n_source_id')->where('n.n_user_id',$userId)->where('n.n_activity_type','Cash Deposit')->where('t.branch_id',$branchId)->where('t.approve_status',0)->select('n.*')->orderBy('n.id')->get();
 }
 public function reject($notificationId,$userId,$branchId,$reason){
  if($this->db->transactionLevel()<1)throw new \LogicException('Cash rejection requires an outer transaction.');
  if(!$userId||!$branchId||!is_string($reason)||trim($reason)===''||strlen($reason)>1000)throw new \InvalidArgumentException('Verified user, branch and rejection reason required.');
  $n=$this->db->table('notification')->where('id',$notificationId)->lockForUpdate()->first();
  if(!$n||$n->n_activity_type!=='Cash Deposit'||(int)$n->n_user_id!==(int)$userId)throw new \LogicException('Deposit rejection is not assigned to this user.');
  $t=$this->db->table('till_transaction')->where('id',$n->n_source_id)->lockForUpdate()->first();
  if(!$t||$t->type!=='Cash Deposit'||(int)$t->branch_id!==(int)$branchId)throw new \LogicException('Deposit branch mismatch.');
  if($t->deposit_type!==null&&$t->deposit_type!==''&&$t->deposit_type!=='Deposit')throw new \LogicException('Repayment deposits require their dedicated adapter.');
  $done=$this->db->table('unified_cashier_rejection_audit')->where('till_transaction_id',$t->id)->first();
  if($done){if((int)$done->rejector_id!==(int)$userId||$done->reason!==$reason||(int)$t->approve_status!==2)throw new \LogicException('Inconsistent rejection replay.');return (int)$t->id;}
  if((int)$t->approve_status!==0||$this->db->table('unified_cashier_approval_audit')->where('till_transaction_id',$t->id)->exists())throw new \LogicException('Processed deposit cannot be rejected.');
  $slip=$this->db->table('slips')->where('id',$t->slips_id)->lockForUpdate()->first();
  if(!$slip||(int)$slip->draw_acc_id!==(int)$t->drawdown_acc_id)throw new \LogicException('Receipt reference mismatch.');
  $journal=$this->db->table('journal_requiry')->where('id',$slip->jr_id)->lockForUpdate()->first();
  if(!$journal||(int)$journal->is_audit!==0||$this->db->table('journal_detail')->where('journal_id',$journal->id)->where('is_audit','1')->exists())throw new \LogicException('Authorized journal cannot be rejected.');
  // Preserve pending receipt/journal evidence. No balance or audited ledger mutation.
  $this->db->table('till_transaction')->where('id',$t->id)->update(array('approve_status'=>2));
  $this->db->table('unified_cashier_rejection_audit')->insert(array('till_transaction_id'=>$t->id,'notification_id'=>$n->id,'journal_id'=>$journal->id,'rejector_id'=>$userId,'branch_id'=>$branchId,'reason'=>$reason,'created_at'=>date('Y-m-d H:i:s')));
  return (int)$t->id;
 }
 public function approve($notificationId,$userId,$branchId){
  if($this->db->transactionLevel()<1)throw new \LogicException('Cash approval requires an outer transaction.');
  if(!$userId||!$branchId)throw new \InvalidArgumentException('Verified user and branch required.');
  $n=$this->db->table('notification')->where('id',$notificationId)->lockForUpdate()->first();
  if(!$n||$n->n_activity_type!=='Cash Deposit'||(int)$n->n_user_id!==(int)$userId)throw new \LogicException('Deposit approval is not assigned to this user.');
  $t=$this->db->table('till_transaction')->where('id',$n->n_source_id)->lockForUpdate()->first();
  if(!$t||$t->type!=='Cash Deposit'||(int)$t->branch_id!==(int)$branchId)throw new \LogicException('Deposit branch mismatch.');
  if($t->deposit_type!==null&&$t->deposit_type!==''&&$t->deposit_type!=='Deposit')throw new \LogicException('Repayment deposits require their dedicated adapter.');
  $done=$this->db->table('unified_cashier_approval_audit')->where('till_transaction_id',$t->id)->first();
  if($done){if((int)$done->approver_id!==(int)$userId||(int)$t->approve_status!==1)throw new \LogicException('Inconsistent approval replay.');return (int)$t->id;}
  if((int)$t->approve_status!==0)throw new \LogicException('Deposit is already processed.');
  $till=$this->db->table('till_account')->where('id',$t->till_account_id)->lockForUpdate()->first();
  $draw=$this->db->table('drawdown_accounts')->where('id',$t->drawdown_acc_id)->lockForUpdate()->first();
  $slip=$this->db->table('slips')->where('id',$t->slips_id)->lockForUpdate()->first();
  if(!$till||!$draw||!$slip||(int)$till->branch_id!==(int)$branchId||(string)$draw->branch!==(string)$branchId||(int)$till->currency_id!==(int)$t->tran_currency_id||(int)$draw->currency!==(int)$t->tran_currency_id||(int)$slip->draw_acc_id!==(int)$draw->id||(int)$slip->currency_id!==(int)$t->tran_currency_id)throw new \LogicException('Receipt/account scope mismatch.');
  $journal=$this->db->table('journal_requiry')->where('id',$slip->jr_id)->lockForUpdate()->first();
  if(!$journal||(int)$journal->is_audit!==0)throw new \LogicException('Pending receipt journal required.');
  $money=$this->db->select('SELECT CAST(? AS DECIMAL(18,4)) amount, (CAST(? AS DECIMAL(18,4))=CAST(? AS DECIMAL(18,4)) AND CAST(? AS DECIMAL(18,4))=CAST(? AS DECIMAL(18,4))) matches',array($t->cash_in,$t->cash_in,$n->n_amount,$t->cash_in,$slip->amount))[0];
  if(!$money->matches||!preg_match('/^[0-9]{1,12}\.[0-9]{4}$/D',$money->amount)||$money->amount==='0.0000')throw new \LogicException('Deposit amount mismatch.');
  $totals=$this->db->table('journal_detail')->where('journal_id',$journal->id)->selectRaw('COUNT(*) line_count,SUM(debit) debit,SUM(credit) credit')->first();
  if($totals->line_count<2||$totals->debit!==$totals->credit||$totals->debit!==$money->amount)throw new \LogicException('Receipt journal is not balanced to deposit.');
  $this->db->table('drawdown_accounts')->where('id',$draw->id)->update(array('balance'=>$this->db->raw('balance+CAST('.$money->amount.' AS DECIMAL(18,4))')));
  if($t->methode==='Cash on Hand-Teller')$this->db->table('till_account')->where('id',$till->id)->update(array('balance'=>$this->db->raw('balance+CAST('.$money->amount.' AS DECIMAL(18,4))')));
  $balance=$this->db->table('till_account')->where('id',$till->id)->pluck('balance');
  $this->db->table('till_transaction')->where('id',$t->id)->update(array('approve_status'=>1,'balance'=>$balance));
  $this->db->table('journal_requiry')->where('id',$journal->id)->update(array('is_audit'=>'1'));
  $this->db->table('journal_detail')->where('journal_id',$journal->id)->update(array('is_audit'=>'1'));
  $this->db->table('slips')->where('id',$slip->id)->update(array('authorized_by'=>$userId));
  $this->db->table('unified_cashier_approval_audit')->insert(array('till_transaction_id'=>$t->id,'notification_id'=>$n->id,'journal_id'=>$journal->id,'approver_id'=>$userId,'branch_id'=>$branchId,'amount'=>$money->amount,'created_at'=>date('Y-m-d H:i:s')));
  return (int)$t->id;
 }
}
