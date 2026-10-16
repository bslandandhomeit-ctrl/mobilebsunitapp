<?php
namespace App\Services;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
/** Stored posting report. No balance calculations or mutation methods. */
class UnifiedPostingReport {
 private $db;
 public function __construct(Connection $db){if($db->getTablePrefix()!=='')throw new \LogicException('Posting reader requires no prefix.');$this->db=$db;}
 public static function configured(){config(array('database.connections.unified_posting'=>config('unified_posting.database')));return new static(DB::connection('unified_posting'));}
 public function query(array $filters,$branch=null){
  foreach(array('from','to') as $key){if(empty($filters[$key])||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$filters[$key]))throw new \InvalidArgumentException('Valid report dates required.');$parts=explode('-',$filters[$key]);if(!checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0]))throw new \InvalidArgumentException('Invalid report date.');}
  if($filters['from']>$filters['to'])throw new \InvalidArgumentException('Report dates are reversed.');
  // Join the explicit posting loan ID: account-number joins can duplicate receipts.
  $q=$this->db->table('transactions_posting as t')
   ->leftJoin('drawdown_accounts as d','d.id','=','t.drawdown_acc_id')
   ->leftJoin('loans as l','l.id','=','t.loan_id')
   ->leftJoin('customer_accounts as c','c.id','=','d.customer_account_id')
   ->leftJoin('projects as p','p.id','=','d.project_id')
   ->leftJoin('units as u','u.id','=','d.unit_id')
   ->leftJoin('unit_types as ut','ut.id','=','d.unit_type_id')
   ->select('t.*','c.display_name as client_name','c.account_number as cus_acc','p.name_km as dealer','p.name_en as dealer_en','p.code as project_name','u.code as unit_code','ut.name_km as name','ut.code as short_code','l.legacy_contract_number as contract_id','l.id as linked_loan_id','d.branch as accounting_branch_id')
   ->where('t.status',1)->where('t.posting_date','>=',$filters['from'])->where('t.posting_date','<=',$filters['to']);
  if($branch!==null)$q->where('d.branch',(string)$branch);
  foreach(array('customer_id'=>'d.customer_account_id','project_id'=>'d.project_id','company'=>'p.organisation_id','teller_id'=>'t.user_id') as $key=>$column){if(isset($filters[$key])&&$filters[$key]!==''){$id=filter_var($filters[$key],FILTER_VALIDATE_INT,array('options'=>array('min_range'=>1)));if($id===false)throw new \InvalidArgumentException('Invalid report filter.');$q->where($column,$id);}}
  return $q->orderBy('t.posting_date')->orderBy('t.id');
 }
 public function companies(){return $this->db->table('organisations')->select('id','name_en as name')->orderBy('name_en')->get();}
 public function projects(){return $this->db->table('projects')->select('id','code as short_code')->orderBy('code')->get();}
}
