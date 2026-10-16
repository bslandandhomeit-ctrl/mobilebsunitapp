<?php
namespace App\Services;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Expression;
class UnifiedSalesRepository
{
    private $db;
    public function __construct(Connection $db) {
        if ($db->getTablePrefix()!=='') throw new \LogicException('Unified sales connection must have no prefix.');
        $this->db=$db;
    }
    public static function configured() {
        if (!config('unified_sales.enabled')) throw new \LogicException('Unified sale reads disabled.');
        config(array('database.connections.unified_sales'=>config('unified_sales.database')));
        return new static(DB::connection('unified_sales'));
    }
    private function query() {
        return $this->db->table('sales as s')
            ->leftJoin(new Expression('(SELECT sale_id, MIN(id) AS item_id FROM sale_items GROUP BY sale_id) AS first_item'),'first_item.sale_id','=','s.id')
            ->leftJoin('sale_items as i','i.id','=','first_item.item_id')
            ->leftJoin('customer_accounts as c','c.id','=','s.customer_account_id')
            ->leftJoin('units as u','u.id','=','i.unit_id')->leftJoin('unit_types as t','t.id','=','i.unit_type_id')
            ->leftJoin('projects as p','p.id','=','i.project_id')->leftJoin('branches as b','b.id','=','i.company_branch_id')
            ->leftJoin('currencies as currency','currency.id','=','s.currency')
            ->leftJoin('approved_deposit_sale_links as link','link.sale_id','=','s.id')
            ->select('s.*','c.display_name as customer_name','c.account_number','c.account_type','u.code as unit_code',
                't.code as unit_type_code','p.name_en as project_name','p.name_km as project_name_km','p.id as project_id',
                'b.name as branch_name','b.id as branch_id','currency.code as currency_code','i.unit_sale_price',
                'link.deposit_request_id');
    }
    public function listing(array $filters=array()) {
        $query=$this->query();
        foreach(array('customer_id'=>'s.customer_account_id','company'=>'i.company_branch_id','project_id'=>'i.project_id','status'=>'s.status') as $key=>$column) {
            if(isset($filters[$key]) && $filters[$key]!=='') $query->where($column,$filters[$key]);
        }
        if(!empty($filters['t_from']))$query->where('s.created_on','>=',$filters['t_from'].' 00:00:00');
        if(!empty($filters['t_to']))$query->where('s.created_on','<=',$filters['t_to'].' 23:59:59');
        if(!empty($filters['search'])) {
            $term='%'.$filters['search'].'%';
            $query->where(function($q)use($term){
                $q->where('s.order_no','like',$term)->orWhere('c.display_name','like',$term)->orWhere('u.code','like',$term)
                    ->orWhere('p.name_en','like',$term)->orWhere('p.name_km','like',$term);
            });
        }
        $size=isset($filters['offset'])?max(1,min(100,(int)$filters['offset'])):50;
        return $query->orderBy('s.id','desc')->paginate($size);
    }
    public function detail($id) {
        $sale=$this->query()->where('s.id',$id)->first();
        if(!$sale)return null;
        $terms=$this->db->table('approved_deposit_sale_terms')->where('sale_id',$id)->pluck('terms_snapshot');
        return array('sale'=>$sale,'terms'=>$terms?json_decode($terms,true):array(),
            'items'=>$this->db->table('sale_items as i')->leftJoin('units as u','u.id','=','i.unit_id')->where('i.sale_id',$id)->select('i.*','u.code as unit_code')->get(),
            'members'=>$this->db->table('customer_account_members as m')->join('persons as p','p.id','=','m.person_id')->where('m.account_id',$sale->customer_account_id)->select('p.name_en','p.name_km','p.gender','p.identity_review_state')->get());
    }
    public function branches() {return $this->db->table('branches')->orderBy('name')->get(array('id','name'));}
    public static function money($value) {
        if($value===null)return 'N/A';$parts=explode('.',(string)$value,2);
        return preg_replace('/\B(?=(\d{3})+(?!\d))/',',',$parts[0]).'.'.str_pad(isset($parts[1])?substr($parts[1],0,2):'',2,'0');
    }
}
