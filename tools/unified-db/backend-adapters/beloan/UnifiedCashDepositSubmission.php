<?php
namespace App\Services;
use Illuminate\Database\Connection;
/** Internal ordinary-deposit submission. Controller must verify role and approver eligibility. */
class UnifiedCashDepositSubmission {
 private $db;
 public function __construct(Connection $db){if($db->getTablePrefix()!=='')throw new \LogicException('Unified connection required.');$this->db=$db;}
 public function resolvePaymentMethod($label,$currencyId,array $allowedMethods){
  $ids=array();foreach($allowedMethods as $id=>$value)if((string)$value===(string)$label&&(int)$id>0)$ids[]=(int)$id;
  if(count($ids)!==1)throw new \InvalidArgumentException('Active payment method is missing or ambiguous.');
  $accounts=$this->db->table('coa_categories')->where('currency',$currencyId)->where('name',$label)->get();
  if(count($accounts)===0){$accounts=$this->db->table('coa_categories')->where('currency',$currencyId)->where('name','like','%'.str_replace(array('\\','%','_'),array('\\\\','\\%','\\_'),$label).'%')->get();}
  if(count($accounts)!==1)throw new \LogicException('Payment method requires one explicit currency accounting account.');
  return array('method'=>$label,'payment_method_id'=>$ids[0],'payment_coa_id'=>$accounts[0]->id);
 }
 public function submit(array $data,$requestKey){
  if($this->db->transactionLevel()<1)throw new \LogicException('Deposit submission requires the outer unified transaction.');
  foreach(array('user_id','approver_id','branch_id','currency_id','till_account_id','drawdown_account_id','payment_coa_id','payment_method_id','amount','date','method') as $field)if(!isset($data[$field]))throw new \InvalidArgumentException('Missing deposit field.');
  if(!preg_match('/^[A-Za-z0-9:_.-]{1,112}$/D',$requestKey)||!is_string($data['amount'])||!preg_match('/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/D',$data['amount'])||!preg_match('/[1-9]/',$data['amount']))throw new \InvalidArgumentException('Invalid deposit key or amount.');
  if(!is_string($data['method'])||$data['method']===''||strlen($data['method'])>250||!filter_var($data['payment_method_id'],FILTER_VALIDATE_INT,array('options'=>array('min_range'=>1))))throw new \InvalidArgumentException('Unsupported deposit method.');
  $payload=hash('sha256',json_encode($data));
  $this->db->statement('INSERT IGNORE INTO unified_cashier_submission_keys (request_key,payload_hash,till_transaction_id,notification_id) VALUES (?,?,NULL,NULL)',array($requestKey,$payload));
  $key=$this->db->table('unified_cashier_submission_keys')->where('request_key',$requestKey)->lockForUpdate()->first();
  if($key->payload_hash!==$payload)throw new \LogicException('Submission payload changed.');
  if($key->till_transaction_id)return array('transaction_id'=>(int)$key->till_transaction_id,'notification_id'=>(int)$key->notification_id);
  $till=$this->db->table('till_account')->where('id',$data['till_account_id'])->lockForUpdate()->first();
  $draw=$this->db->table('drawdown_accounts')->where('id',$data['drawdown_account_id'])->lockForUpdate()->first();
  if(!$till||!$draw||(int)$till->assign_user_id!==(int)$data['user_id']||(int)$till->branch_id!==(int)$data['branch_id']||(string)$draw->branch!==(string)$data['branch_id']||(int)$till->currency_id!==(int)$data['currency_id']||(int)$draw->currency!==(int)$data['currency_id']||(int)$till->status!==1||(int)$draw->status!==1)throw new \LogicException('Active deposit account scope mismatch.');
  $coa=$this->db->table('coa_categories')->where('id',$data['payment_coa_id'])->first();
  $credit=$this->db->table('coa_categories')->where('id',$draw->coa_id)->first();
  if(!$coa||!$credit||(int)$coa->currency!==(int)$data['currency_id']||(int)$credit->currency!==(int)$data['currency_id'])throw new \LogicException('Accounting currency mismatch.');
  $receipt=(new UnifiedReceiptAllocator($this->db))->next($data['branch_id'],$data['date']);
  $branch=$this->db->table('branches')->where('id',$data['branch_id'])->first();$note=isset($data['description'])?$data['description']:'Cash Deposit';
  $header=array_merge($receipt,array('tran_id'=>0,'entry_date'=>$data['date'],'description'=>$note,'user_id'=>$data['user_id'],'trans_type'=>'Cash Deposit','is_audit'=>'0'));
  $journal=(new UnifiedJournalWriter($this->db))->post($header,array(array('coa_id'=>$coa->id,'branch_code'=>$branch->code,'debit'=>$data['amount'],'credit'=>'0','description'=>$note,'is_audit'=>'0'),array('coa_id'=>$draw->coa_id,'branch_code'=>$branch->code,'debit'=>'0','credit'=>$data['amount'],'description'=>$note,'is_audit'=>'0')),'cashier:'.$requestKey);
  $this->db->statement('INSERT IGNORE INTO unified_receipt_sequences (receipt_year,next_number) SELECT 0,COALESCE(MAX(print_cnt),0)+1 FROM slips');
  $print=$this->db->table('unified_receipt_sequences')->where('receipt_year',0)->lockForUpdate()->first();$this->db->table('unified_receipt_sequences')->where('receipt_year',0)->update(array('next_number'=>$print->next_number+1));
  $detail=$this->db->table('journal_detail')->where('journal_id',$journal)->orderBy('id','desc')->first();
  $slip=$this->db->table('slips')->insertGetId(array('draw_acc_id'=>$draw->id,'jr_id'=>$journal,'jd_id'=>$detail->id,'client_id'=>$draw->customer_account_id,'user_id'=>$data['user_id'],'create_date'=>$data['date'],'currency_id'=>$data['currency_id'],'types'=>$data['payment_method_id'],'amount'=>$data['amount'],'trans_type'=>'Cash Deposit','check_num'=>'','bank_name'=>'','description'=>$note,'prepared_by'=>$data['user_id'],'checked_by'=>0,'authorized_by'=>0,'print_cnt'=>$print->next_number,'is_signature'=>0,'created_at'=>$data['date'],'updated_at'=>$data['date']));
  $tx=$this->db->table('till_transaction')->insertGetId(array('till_account_id'=>$till->id,'drawdown_acc_id'=>$draw->id,'from_account'=>substr($draw->account_name,0,50),'to_account'=>substr($till->account_no.' / '.$till->account_name,0,50),'drawdown_acc'=>$draw->account_no,'till_user_id'=>$data['user_id'],'branch_id'=>$data['branch_id'],'operate_by'=>$data['user_id'],'tran_currency_id'=>$data['currency_id'],'slips_id'=>$slip,'tranx_time'=>$data['date'],'type'=>'Cash Deposit','methode'=>$data['method'],'cash_in'=>$data['amount'],'cash_out'=>0,'balance'=>$till->balance,'description'=>$note,'status'=>0,'approve_status'=>0,'deposit_type'=>'Deposit','receipt_no'=>$receipt['receipt_no']));
  $notification=$this->db->table('notification')->insertGetId(array('n_user_id'=>$data['approver_id'],'n_group_code_id'=>'0','n_source_id'=>$tx,'n_amount'=>$data['amount'],'chief_till_account_id'=>0,'n_activity_type'=>'Cash Deposit','n_create_times'=>$data['date'],'n_description'=>substr($note,0,160),'teller_till_account_id'=>$till->id,'created_at'=>$data['date'],'updated_at'=>$data['date']));
  $this->db->table('unified_cashier_submission_keys')->where('request_key',$requestKey)->update(array('till_transaction_id'=>$tx,'notification_id'=>$notification));
  return array('transaction_id'=>(int)$tx,'notification_id'=>(int)$notification);
 }
}
