<?php
namespace App\Services;
/** Same-connection handoff barrier. Callback must write using the supplied connection. */
class UnifiedOriginalSaleFinanceHandoff
{
    private $db;
    public function __construct($db) {$this->db=$db;}
    private function canonical($table) {
        $schema=$this->db->getDatabaseName();
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$schema)||!preg_match('/^[A-Za-z0-9_]+$/D',$table))throw new \LogicException('Invalid database identifier.');
        return $this->db->table(new \Illuminate\Database\Query\Expression('`'.$schema.'`.`'.$table.'`'));
    }
    private function actorAllowsBranch($branchId) {
        if(!\Illuminate\Support\Facades\Auth::check())return false;
        $user=\Illuminate\Support\Facades\Auth::user();
        if((int)$user->status!==1)return false;
        if(in_array((int)$user->role_id,array(1,2),true))return true;
        return $user->branch_id!==null && $user->branch_id!=='' && (string)$user->branch_id===(string)$branchId;
    }
    private function decimalKey($value){
        if(!preg_match('/^([0-9]+)(?:\.([0-9]+))?$/D',(string)$value,$m))throw new \LogicException('Invalid deposit decimal.');
        $whole=ltrim($m[1],'0');$fraction=isset($m[2])?rtrim($m[2],'0'):'';return ($whole===''?'0':$whole).($fraction===''?'':'.'.$fraction);
    }
    private function verifyLineage($sale,$terms,$request) {
        $origin=$this->canonical('approved_deposit_sale_links')->where('source_system','bsunits')->where('sale_id',$sale->id)->first();
        if(!$origin||$origin->customer_account_id!=$sale->customer_account_id)throw new \LogicException('Sale conversion lineage is missing.');
        $id=$request['id'];$expected=hash('sha256',$terms->terms_snapshot);$seen=array();
        while((string)$id!==(string)$origin->deposit_request_id||!hash_equals($expected,$origin->snapshot_sha256)){
            $key=(string)$id.':'.$expected;if(isset($seen[$key])||count($seen)>=1000)throw new \LogicException('Invalid sale amendment chain.');$seen[$key]=true;
            $revision=$this->canonical('approved_deposit_sale_revisions')->where('sale_id',$sale->id)->where('after_sha256',$expected)->first();
            if($revision){
                $before=json_decode($revision->before_snapshot,true);$previous=isset($before['terms'])?json_decode($before['terms'],true):null;
                if((string)$revision->deposit_request_id!==(string)$id||!hash_equals($expected,hash('sha256',$revision->after_snapshot))||!is_array($previous)||!isset($previous['id'])||(string)$previous['id']!==(string)$id||!hash_equals($revision->before_sha256,hash('sha256',$before['terms'])))throw new \LogicException('Invalid approved sale revision lineage.');
                $expected=$revision->before_sha256;continue;
            }
            $amend=$this->canonical('approved_deposit_sale_amendments')->where('deposit_request_id',$id)->where('sale_id',$sale->id)->first();
            if(!$amend||!hash_equals($expected,$amend->snapshot_sha256)||!hash_equals($expected,hash('sha256',$amend->after_snapshot)))throw new \LogicException('Sale amendment lineage does not match current terms.');
            $before=json_decode($amend->before_snapshot,true);$previous=isset($before['terms'])?json_decode($before['terms'],true):null;
            if(!is_array($previous)||!isset($previous['id'])||(string)$previous['id']!==(string)$amend->predecessor_request_id)throw new \LogicException('Sale amendment predecessor is invalid.');
            $id=$amend->predecessor_request_id;$expected=hash('sha256',$before['terms']);
        }
        if(!hash_equals($expected,$origin->snapshot_sha256)||!hash_equals($expected,hash('sha256',$origin->request_snapshot)))throw new \LogicException('Original approved sale snapshot does not match.');
    }
    public function dropdown($saleId,$kind,$parentId) {
        $empty=array('option'=>'<option value=""> - </option>');
        if(!\Illuminate\Support\Facades\Auth::check())return $empty;
        $sale=$this->canonical('sales')->where('id',$saleId)->first();
        if(!$sale||$sale->status!=='Ordered')return $empty;
        $items=$this->canonical('sale_items')->where('sale_id',$saleId)->get();if(count($items)!==1)return $empty;$item=$items[0];if(!$this->actorAllowsBranch($item->company_branch_id))return $empty;
        if($kind==='project'){
            if((string)$parentId!==(string)$item->company_branch_id||!$this->canonical('project_accounting_branch_links')->where('project_id',$item->project_id)->where('branch_id',$item->company_branch_id)->exists())return $empty;
            $row=$this->canonical('projects')->where('id',$item->project_id)->first();$label=$row?$row->name_en:null;
        }elseif($kind==='type'){
            if((string)$parentId!==(string)$item->project_id)return $empty;
            $row=$this->canonical('unit_types')->where('id',$item->unit_type_id)->where('project_id',$item->project_id)->first();$label=$row?$row->code:null;
        }elseif($kind==='unit'){
            if((string)$parentId!==(string)$item->unit_type_id)return $empty;
            $row=$this->canonical('units')->where('id',$item->unit_id)->where('unit_type_id',$item->unit_type_id)->first();$label=$row?$row->code:null;
        }else{return $empty;}
        if(!$row)return $empty;
        return array('option'=>$empty['option'].'<option value="'.(int)$row->id.'">'.htmlspecialchars((string)$label,ENT_QUOTES,'UTF-8').'</option>');
    }
    public function termsHash($saleId) {
        $items=$this->canonical('sale_items')->where('sale_id',$saleId)->get();
        if(count($items)!==1||!$this->actorAllowsBranch($items[0]->company_branch_id))throw new \LogicException('Sale is outside the active user accounting branch.');
        $terms=$this->canonical('approved_deposit_sale_terms')->where('sale_id',$saleId)->first();
        if(!$terms)throw new \LogicException('Approved sale terms are missing.');
        return hash('sha256',$terms->terms_snapshot);
    }
    public function accept($saleId,$expectedTermsHash) {
        if(\Illuminate\Support\Facades\DB::connection()!==$this->db)throw new \LogicException('Original accounting writer must use the handoff connection.');
        return $this->run($saleId,$expectedTermsHash,function($db,$sale,$item){
            foreach(array('sale_id'=>$sale->id,'client_id'=>$sale->customer_account_id,'unit_id'=>$item->unit_id,'project_id'=>$item->project_id,'unit_type_id'=>$item->unit_type_id,'currency'=>$sale->currency) as $key=>$value){
                if((string)\Illuminate\Support\Facades\Request::input($key)!==(string)$value)throw new \LogicException('Submitted account references do not match the current sale.');
            }
            $branch=$this->canonical('branches')->where('id',$item->company_branch_id)->first();
            $request=\Illuminate\Support\Facades\Request::getFacadeRoot();$original=$request->all();
            if(!$branch || (string)$request->input('branch')!==(string)$branch->code)throw new \LogicException('Submitted branch code does not match the sale accounting branch.');
            $payload=$original;$payload['branch']=$branch->id;$payload['account_branch_code']=$branch->code;$request->replace($payload);
            $previous=config('unified_posting.in_canonical_handoff',false);config(array('unified_posting.in_canonical_handoff'=>true));
            try{return app('App\\Http\\Controllers\\AccountingController')->post_add_client_loan_account($sale->customer_account_id,'/sale/list');}
            finally{$request->replace($original);config(array('unified_posting.in_canonical_handoff'=>$previous));}
        });
    }
    private function run($saleId,$expectedTermsHash,callable $create)
    {
        return $this->db->transaction(function()use($saleId,$expectedTermsHash,$create){
            $preview=$this->canonical('sale_items')->where('sale_id',$saleId)->get();
            if(count($preview)!==1)throw new \LogicException('Single-unit sale required.');
            $unit=$this->canonical('units')->where('id',$preview[0]->unit_id)->lockForUpdate()->first();
            $previewTerms=$this->canonical('approved_deposit_sale_terms')->where('sale_id',$saleId)->first();
            $previewRequest=$previewTerms?json_decode($previewTerms->terms_snapshot,true):null;
            if(!is_array($previewRequest)||empty($previewRequest['id']))throw new \LogicException('Approved sale request is missing.');
            $currentDeposit=$this->canonical('unit_deposit_requests')->where('id',$previewRequest['id'])->lockForUpdate()->first();
            $sale=$this->canonical('sales')->where('id',$saleId)->lockForUpdate()->first();
            if (!$sale || $sale->status!=='Ordered' || $sale->authorization!=='Authorized') throw new \LogicException('Sale is not eligible for finance handoff.');
            $terms=$this->canonical('approved_deposit_sale_terms')->where('sale_id',$saleId)->lockForUpdate()->first();
            if (!$terms || !is_string($expectedTermsHash) || !hash_equals($expectedTermsHash,hash('sha256',$terms->terms_snapshot))) throw new \LogicException('Sale terms changed; reload before finance handoff.');
            if ($this->canonical('loan_accounts')->where('sale_id',$saleId)->lockForUpdate()->first()) throw new \LogicException('Sale already has a finance account.');
            $items=$this->canonical('sale_items')->where('sale_id',$saleId)->lockForUpdate()->get();
            if(count($items)!==1)throw new \LogicException('Single-unit sale required.');
            $item=$items[0];
            if(!$this->actorAllowsBranch($item->company_branch_id))throw new \LogicException('Sale is outside the active user accounting branch.');
            if(!$unit||$unit->id!=$item->unit_id)throw new \LogicException('Sale unit changed; reload before finance handoff.');
            $type=$unit?$this->canonical('unit_types')->where('id',$unit->unit_type_id)->first():null;
            if (!$this->canonical('customer_accounts')->where('id',$sale->customer_account_id)->exists()
                || !$this->canonical('currencies')->where('id',$sale->currency)->exists()
                || !$unit || $unit->catalog_state!=='CURRENT' || $unit->deleted_at!==null || !$unit->is_saleable || in_array($unit->availability_state,array('UNAVAILABLE','UNVERIFIED'),true) || !$type || $type->id!=$item->unit_type_id || $type->project_id!=$item->project_id
                || !$this->canonical('projects')->where('id',$item->project_id)->exists()
                || !$this->canonical('branches')->where('id',$item->company_branch_id)->exists()
                || !$this->canonical('project_accounting_branch_links')->where('project_id',$item->project_id)->where('branch_id',$item->company_branch_id)->exists()) {
                throw new \LogicException('Sale customer, property, currency or accounting branch references are invalid.');
            }
            $approved=json_decode($terms->terms_snapshot,true);
            $action=$unit->current_action_id?$this->canonical('unit_actions')->where('id',$unit->current_action_id)->lockForUpdate()->first():null;
            $owned=false;
            if(is_array($approved)&&!empty($approved['id'])&&isset($approved['unit_id'])&&$approved['unit_id']==$unit->id&&$action&&$action->unit_id==$unit->id){
                if($action->action==='DEPOSIT'&&$unit->availability_state==='RESERVED'&&$action->status_action==='APPROVED'&&$action->actionable_type==='App\UnitDepositRequest'&&(string)$action->actionable_id===(string)$approved['id'])$owned=true;
                elseif($action->action==='CONTRACT'&&$action->actionable_type==='App\UnitContractRequest'){
                    $contract=$this->canonical('unit_contract_requests')->where('id',$action->actionable_id)->lockForUpdate()->first();
                    $state=$action->status_action==='PENDING'?'RESERVED':($action->status_action==='APPROVED'?'SOLD':null);
                    $owned=$contract&&$contract->deleted_at===null&&$contract->unit_id==$unit->id&&(string)$contract->unit_deposit_request_id===(string)$approved['id']&&$contract->status===$action->status_action&&$state!==null&&$unit->availability_state===$state;
                }
            }
            if(!$owned)throw new \LogicException('Current unit reservation does not belong to the approved sale deposit.');
            if(!$currentDeposit||(string)$currentDeposit->id!==(string)$approved['id']||$currentDeposit->status!=='APPROVED'||$currentDeposit->unit_controller_status!=='APPROVED'||!in_array($currentDeposit->sale_manager_status,array('APPROVED','NOT_REQUIRED'),true)||$currentDeposit->deleted_at!==null||$currentDeposit->to_unit_deposit_request_id!==null)throw new \LogicException('Current deposit is not fully approved.');
            $numbers=array('unit_sale_price','discount_promotion','other_discount_allowance','deposit_amount','special_discount','loan_duration','interest','first_payment_duration','first_payment_percentage','first_payment_amount');
            $fields=array_merge($numbers,array('unit_id','customer_name','customer_gender','customer2_name','customer2_gender','customer_phone_number','customer_phone_number2','is_selected_payment_option','payment_option_id','is_first_payment','remark'));
            foreach($fields as $field){
                if(!array_key_exists($field,$approved)||!property_exists($currentDeposit,$field))throw new \LogicException('Approved deposit snapshot is incomplete.');
                $before=$approved[$field];$current=$currentDeposit->$field;
                if(in_array($field,array('is_selected_payment_option','is_first_payment'),true)){if($before!==null)$before=(int)(bool)$before;if($current!==null)$current=(int)(bool)$current;}
                if(in_array($field,$numbers,true)&&$before!==null&&$current!==null){$before=$this->decimalKey($before);$current=$this->decimalKey($current);}
                if(($before===null)!==($current===null)||(string)$before!==(string)$current)throw new \LogicException('Deposit terms changed; approve and synchronize the sale before financing.');
            }
            $this->verifyLineage($sale,$terms,$approved);
            $result=$create($this->db,$sale,$items[0]);
            $account=$this->canonical('loan_accounts')->where('sale_id',$saleId)->first();
            if (!$account || $account->customer_account_id!=$sale->customer_account_id || $account->unit_id!=$item->unit_id || $account->project_id!=$item->project_id || $account->unit_type_id!=$item->unit_type_id || (string)$account->branch!==(string)$item->company_branch_id || $account->currency!=$sale->currency) throw new \LogicException('Finance account does not match the current sale.');
            $this->canonical('sales')->where('id',$saleId)->update(['status'=>'Accepted','updated_at'=>date('Y-m-d H:i:s')]);
            return $result;
        });
    }
}
