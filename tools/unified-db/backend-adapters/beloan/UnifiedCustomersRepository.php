<?php
namespace App\Services;
class UnifiedCustomersRepository
{
    private $db;
    public function __construct(\Illuminate\Database\Connection $db){
        if($db->getTablePrefix()!=='')throw new \LogicException('Canonical customer connection requires no prefix.');$this->db=$db;
    }
    public static function configured(){
        if(!config('unified_sales.customers_enabled'))throw new \LogicException('Canonical customer reads disabled.');
        config(array('database.connections.unified_sales'=>config('unified_sales.database')));
        return new static(\Illuminate\Support\Facades\DB::connection('unified_sales'));
    }
    public function listing(array $filters=array()){
        $q=$this->db->table('customer_accounts as a')->select('a.*');
        if(!empty($filters['name'])){
            $term='%'.$filters['name'].'%';$q->where(function($w)use($term){
                $w->where('a.display_name','like',$term)->orWhereExists(function($sub)use($term){
                    $sub->selectRaw('1')->from('customer_account_members as m')->join('persons as p','p.id','=','m.person_id')
                        ->whereRaw('m.account_id=a.id')->where(function($names)use($term){$names->where('p.name_en','like',$term)->orWhere('p.name_km','like',$term);});
                });
            });
        }
        if(!empty($filters['customer_id']))$q->where('a.account_number','like','%'.$filters['customer_id'].'%');
        if(!empty($filters['phone'])){
            $term='%'.str_replace('-','',$filters['phone']).'%';
            $q->whereExists(function($sub)use($term){
                $sub->selectRaw('1')->from('customer_account_evidence as e')->whereRaw('e.account_id=a.id')->where('e.evidence_kind','CONTACT')->whereRaw("(e.source_table='unified_contact_override' OR NOT EXISTS (SELECT 1 FROM customer_account_evidence override_contact WHERE override_contact.account_id=a.id AND override_contact.source_table='unified_contact_override'))")
                    ->where(function($phones)use($term){
                        foreach(array('phone_number','phone_number2','contact_number_number','phone_numbers')as$key){
                            $phones->orWhereRaw("REPLACE(JSON_UNQUOTE(JSON_EXTRACT(e.source_payload, '$.".$key."')), '-', '') LIKE ?",array($term));
                        }
                    });
            });
        }
        $size=isset($filters['sel_offset'])?max(1,min(100,(int)$filters['sel_offset'])):25;
        return $q->orderBy('a.id','desc')->paginate($size);
    }
    public function detail($id){
        $account=$this->db->table('customer_accounts')->where('id',$id)->first();if(!$account)return null;
        $contacts=array();
        foreach($this->db->table('customer_account_evidence')->where('account_id',$id)->where('evidence_kind','CONTACT')->orderByRaw("source_table='unified_contact_override' DESC")->get()as$row){
            $payload=json_decode($row->source_payload,true);
            if($row->source_table==='unified_contact_override'){ $contacts=isset($payload['phone_numbers'])?$payload['phone_numbers']:array();break; }
            foreach(array('phone_number','phone_number2','contact_number_number')as$key){if(!empty($payload[$key]))$contacts[]=(string)$payload[$key];}
        }
        return array('account'=>$account,'contacts'=>array_values(array_unique($contacts)),
            'members'=>$this->db->table('customer_account_members as m')->join('persons as p','p.id','=','m.person_id')->where('m.account_id',$id)->select('p.name_en','p.name_km','p.gender','p.birthdate','p.identity_review_state')->get(),
            'orders'=>$this->db->table('sales')->where('customer_account_id',$id)->orderBy('id','desc')->get(array('id','order_no','final_price','status')));
    }
}
