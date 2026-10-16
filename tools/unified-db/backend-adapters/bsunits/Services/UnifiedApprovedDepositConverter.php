<?php
namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class UnifiedApprovedDepositConverter
{
    private $db;
    public function __construct(Connection $db) { $this->db=$db; }
    public static function configured() {
        if (!config('unified_catalog.deposit_conversion_enabled')) throw new \LogicException('Deposit conversion is disabled.');
        config(['database.connections.unified_deposits'=>config('unified_catalog.deposit_database')]);
        return new static(DB::connection('unified_deposits'));
    }
    private function allocate($entity) {
        $row=$this->db->table('unified_id_allocators')->where('entity',$entity)->lockForUpdate()->first();
        if (!$row) throw new \LogicException('Missing customer identity allocator.');
        $this->db->table('unified_id_allocators')->where('entity',$entity)->update(['next_id'=>$row->next_id+1]);
        return $row->next_id;
    }
    public function convert(array $request) {
        $prices=(new ApprovedDepositSalePricing)->calculate($request);
        if (empty($request['id']) || empty($request['unit_id']) || trim($request['customer_name'] ?? '')==='') throw new \InvalidArgumentException('Incomplete approved request.');
        $snapshot=json_encode($request,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $hash=hash('sha256',$snapshot);
        return $this->db->transaction(function() use($request,$prices,$snapshot,$hash) {
            // A linked replacement must amend the predecessor sale through an explicit workflow.
            // Do not silently create another customer/sale for the same deposit history.
            if (!empty($request['from_unit_deposit_request_id'])) {
                throw new \LogicException('Linked deposit replacement requires an explicit sale amendment.');
            }
            $key=['source_system'=>'bsunits','deposit_request_id'=>$request['id']];
            $this->db->table('approved_deposit_conversion_keys')->insertOrIgnore($key);
            $this->db->table('approved_deposit_conversion_keys')->where($key)->lockForUpdate()->first();
            $existing=$this->db->table('approved_deposit_sale_links')->where($key)->lockForUpdate()->first();
            if ($existing) {
                if (!hash_equals($existing->snapshot_sha256,$hash)) throw new \LogicException('Approved request changed after conversion; explicit amendment required.');
                return ['sale_id'=>$existing->sale_id,'customer_account_id'=>$existing->customer_account_id,'replayed'=>true];
            }
            $unit=$this->db->table('units')->where('id',$request['unit_id'])->lockForUpdate()->first();
            if (!$unit || $unit->catalog_state!=='CURRENT') throw new \LogicException('Current canonical unit is missing.');
            $type=$this->db->table('unit_types')->where('id',$unit->unit_type_id)->first();
            $branches=$this->db->table('project_accounting_branch_links')->where('project_id',$type->project_id)->pluck('branch_id')->unique();
            if ($branches->count()!==1) throw new \LogicException('Accounting branch requires an explicit project mapping.');
            $currencies=$this->db->table('currencies')->where('code','USD')->pluck('id');
            if ($currencies->count()!==1) throw new \LogicException('USD currency mapping is ambiguous.');
            $approver=$request['unit_controller_id'] ?? null;
            if (($request['sale_manager_status'] ?? '')==='APPROVED' && ($request['sale_manager_actioned_at'] ?? '')>($request['unit_controller_actioned_at'] ?? '')) $approver=$request['sale_manager_id'] ?? null;
            $actor=$this->db->table('bsunits_actor_aliases')->where('source_user_id',$approver)->value('actor_id');
            if (!$actor) throw new \LogicException('Approval actor mapping is missing.');
            $account=$this->allocate('customer_accounts');
            $joint=trim($request['customer2_name'] ?? '')!=='';
            $this->db->table('customer_accounts')->insert([
                'id'=>$account,'account_number'=>'BS-DP-'.$request['id'],'display_name'=>$request['customer_name'],
                'account_type'=>$joint?'JOINT':'SINGLE','identity_review_state'=>'PENDING','source_system'=>'bsunits_deposit','source_id'=>$request['id'],
            ]);
            foreach ([1,2] as $number) {
                if ($number===2 && !$joint) continue;
                $name=$request[$number===1?'customer_name':'customer2_name'];
                $gender=(int)($request[$number===1?'customer_gender':'customer2_gender'] ?? 0);
                if (!in_array($gender,[1,2],true)) throw new \InvalidArgumentException('Invalid approved customer gender.');
                $person=$this->allocate('persons');
                $khmer=preg_match('/\p{Khmer}/u',$name)===1;
                $this->db->table('persons')->insert(['id'=>$person,'name_km'=>$khmer?$name:null,'name_en'=>$khmer?null:$name,'birthdate'=>null,
                    'gender'=>$gender===1?'Male':'Female','identity_review_state'=>'PENDING','source_system'=>'bsunits_deposit_buyer'.$number,'source_profile_id'=>$request['id']]);
                $this->db->table('customer_account_members')->insert(['account_id'=>$account,'person_id'=>$person,'source_applicant_type'=>'CUSTOMER'.$number]);
            }
            $contact=json_encode(['phone_number'=>$request['customer_phone_number'] ?? '', 'phone_number2'=>$request['customer_phone_number2'] ?? ''],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $this->db->table('customer_account_evidence')->insert(['source_table'=>'bsunits_deposit_contact','source_id'=>$request['id'],'account_id'=>$account,'evidence_kind'=>'CONTACT','ownership_state'=>'ACCOUNT_ONLY','source_payload'=>$contact,'source_checksum'=>hash('sha256',$contact)]);
            (new UnifiedBeloanCustomerProfile($this->db))->create($account,$request,$joint);
            $now=now()->toDateTimeString();
            $sale=$this->db->table('sales')->insertGetId([
                'order_no'=>'BS-DP-'.$request['id'],'user_id'=>$actor,'customer_account_id'=>$account,'currency'=>$currencies->first(),
                'created_on'=>$now,'clearance_amount'=>'0.00','discount_promotion'=>$prices['discount_promotion'],'discount_other'=>$prices['discount_other'],
                'discount_payment_option'=>$prices['discount_payment_option'],'amount_discount_payment_option'=>$prices['amount_discount_payment_option'],
                'price_after_discount'=>$prices['price_after_discount'],'vat'=>'0.00','diposit_amount'=>$prices['diposit_amount'],'final_price'=>$prices['final_price'],
                'status'=>'Ordered','authorization'=>'Authorized','payment_status'=>'Unpaid','invoice_status'=>'First Created',
                // BS and Beloan payment-option identifiers are not interchangeable.
                'payment_option'=>null,'payment_option_type'=>null,'remark'=>null,'created_at'=>$now,'updated_at'=>$now,
            ]);
            $this->db->table('sale_items')->insert(['sale_id'=>$sale,'company_branch_id'=>$branches->first(),'project_id'=>$type->project_id,'unit_type_id'=>$unit->unit_type_id,'unit_id'=>$unit->id,
                'discount_promotion'=>$prices['discount_promotion'],'discount_other'=>$prices['discount_other'],'discount_payment_option'=>$prices['discount_payment_option'],
                'amount_discount_payment_option'=>$prices['amount_discount_payment_option'],'qty'=>1,'unit_sale_price'=>$prices['unit_sale_price'],'created_at'=>$now,'updated_at'=>$now]);
            $this->db->table('approved_deposit_sale_terms')->insert(['sale_id'=>$sale,'terms_snapshot'=>$snapshot]);
            $this->db->table('approved_deposit_sale_links')->insert($key+['customer_account_id'=>$account,'sale_id'=>$sale,'approved_actor_id'=>$actor,'request_snapshot'=>$snapshot,'snapshot_sha256'=>$hash,'created_at'=>$now]);
            return ['sale_id'=>$sale,'customer_account_id'=>$account,'replayed'=>false];
        },3);
    }
}
