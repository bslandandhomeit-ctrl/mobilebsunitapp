<?php
namespace App\Services;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
class UnifiedLoansRepository {
 private $db;
 public function __construct(Connection $db){if($db->getTablePrefix()!=='')throw new \LogicException('Loan reader requires no prefix.');$this->db=$db;}
 public static function configured(){if(!config('unified_sales.loans_enabled'))throw new \LogicException('Loan reads disabled.');config(array('database.connections.unified_loans'=>config('unified_sales.loan_database')));return new static(DB::connection('unified_loans'));}
 private function query($branch){$q=$this->db->table('loans as l')->leftJoin('customer_accounts as c','c.id','=','l.customer_account_id')->leftJoin('branches as b','b.id','=','l.company_branch_id')->leftJoin('units as u','u.id','=','l.unit_id')->leftJoin('projects as p','p.id','=','l.project_id')->select('l.*','c.display_name as customer_name','b.name as branch_name','p.name_en as project_name','u.code as catalog_unit_code');if($branch!==null)$q->where('l.company_branch_id',$branch);return $q;}
 public function listing(array $filters=array(),$branch=null){$q=$this->query($branch)->whereIn('l.status',array(3,8));if(!empty($filters['contract_id']))$q->where('l.legacy_contract_number','like','%'.$filters['contract_id'].'%');if(!empty($filters['customer_name']))$q->where('c.display_name','like','%'.$filters['customer_name'].'%');$size=isset($filters['offset'])?max(1,min(100,(int)$filters['offset'])):25;return $q->orderBy('l.id','desc')->paginate($size);}
 public function detail($id,$branch=null,array $pages=array()){$loan=$this->query($branch)->where('l.id',$id)->first();if(!$loan)return null;return array('loan'=>$loan,'schedules'=>$this->history($this->db->table('repayment_schedule')->where('loan_id',$id)->orderBy('no')->orderBy('id'),'schedule_page',$pages),'payments'=>$this->history($this->db->table('loan_payments')->where('loan_id',$id)->orderBy('repayment_date','desc')->orderBy('id','desc'),'payment_page',$pages));}
 private function history($query,$key,array $pages){$page=isset($pages[$key])?max(1,(int)$pages[$key]):1;$countQuery=clone $query;$total=$countQuery->count();$page=min($page,max(1,(int)ceil($total/25)));return new \Illuminate\Pagination\LengthAwarePaginator($query->forPage($page,25)->get(),$total,25,$page,array('path'=>\Illuminate\Pagination\Paginator::resolveCurrentPath(),'pageName'=>$key));}
 public static function date($value){return !$value || substr($value,0,10)==='0000-00-00'?'N/A':$value;}
 public static function number($value){if($value===null)return 'N/A';$p=explode('.',(string)$value,2);return preg_replace('/\B(?=(\d{3})+(?!\d))/',',',$p[0]).(isset($p[1])?'.'.$p[1]:'');}
}
