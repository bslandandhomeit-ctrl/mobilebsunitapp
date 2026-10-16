<?php
namespace App\Services;
/** Same-connection handoff barrier. Callback must write using the supplied connection. */
class UnifiedSaleFinanceHandoff
{
    private $db;
    public function __construct($db) {$this->db=$db;}
    public function run($saleId,$expectedTermsHash,callable $create)
    {
        return $this->db->transaction(function()use($saleId,$expectedTermsHash,$create){
            $preview=$this->db->table('sale_items')->where('sale_id',$saleId)->get();
            if ($preview->count()!==1) throw new \LogicException('Single-unit sale required.');
            $previewUnit=$preview->first()->unit_id;
            $this->db->table('units')->where('id',$previewUnit)->lockForUpdate()->first();
            $sale=$this->db->table('sales')->where('id',$saleId)->lockForUpdate()->first();
            if (!$sale || $sale->status!=='Ordered' || $sale->authorization!=='Authorized') throw new \LogicException('Sale is not eligible for finance handoff.');
            $terms=$this->db->table('approved_deposit_sale_terms')->where('sale_id',$saleId)->lockForUpdate()->first();
            if (!$terms || !is_string($expectedTermsHash) || !hash_equals($expectedTermsHash,hash('sha256',$terms->terms_snapshot))) throw new \LogicException('Sale terms changed; reload before finance handoff.');
            if ($this->db->table('loan_accounts')->where('sale_id',$saleId)->lockForUpdate()->first()) throw new \LogicException('Sale already has a finance account.');
            $items=$this->db->table('sale_items')->where('sale_id',$saleId)->lockForUpdate()->get();
            if($items->count()!==1)throw new \LogicException('Single-unit sale required.');
            $item=$items->first();if ($item->unit_id!=$previewUnit) throw new \LogicException('Sale unit changed; reload finance handoff.');
            $unit=$this->db->table('units')->where('id',$item->unit_id)->first();
            $type=$unit?$this->db->table('unit_types')->where('id',$unit->unit_type_id)->first():null;
            if (!$this->db->table('customer_accounts')->where('id',$sale->customer_account_id)->exists()
                || !$this->db->table('currencies')->where('id',$sale->currency)->exists()
                || !$unit || !$type || $type->id!=$item->unit_type_id || $type->project_id!=$item->project_id
                || !$this->db->table('projects')->where('id',$item->project_id)->exists()
                || !$this->db->table('branches')->where('id',$item->company_branch_id)->exists()
                || !$this->db->table('project_accounting_branch_links')->where('project_id',$item->project_id)->where('branch_id',$item->company_branch_id)->exists()) {
                throw new \LogicException('Sale customer, property, currency or accounting branch references are invalid.');
            }
            $result=$create($this->db,$sale,$items->first());
            $account=$this->db->table('loan_accounts')->where('sale_id',$saleId)->first();
            if (!$account || $account->customer_account_id!=$sale->customer_account_id || $account->unit_id!=$item->unit_id || $account->project_id!=$item->project_id || $account->unit_type_id!=$item->unit_type_id || (string)$account->branch!==(string)$item->company_branch_id || $account->currency!=$sale->currency) throw new \LogicException('Finance account does not match the current sale.');
            $this->db->table('sales')->where('id',$saleId)->update(['status'=>'Accepted','updated_at'=>now()->toDateTimeString()]);
            return $result;
        },5);
    }
}
