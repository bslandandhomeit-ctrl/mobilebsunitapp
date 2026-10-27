<?php
namespace App\Services;
/** Explicit, gated amendment of an unfinanced converted order. Never writes loans. */
class UnifiedDepositSaleAmendment
{
    private $db;
    public function __construct($db) { $this->db=$db; }
    public function amend(array $request)
    {
        $prices=(new ApprovedDepositSalePricing)->calculate($request);
        $parent=$request['from_unit_deposit_request_id'] ?? null;
        if (!$parent || empty($request['id']) || $parent==$request['id']) throw new \LogicException('A distinct predecessor is required.');
        $snapshot=json_encode($request,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $hash=hash('sha256',$snapshot);
        return $this->db->transaction(function()use($request,$parent,$prices,$snapshot,$hash){
            // Preview ownership, then lock both property rows before sale/account locks.
            $previewLink=$this->db->table('approved_deposit_sale_links')->where('source_system','bsunits')->where('deposit_request_id',$parent)->first();
            if (!$previewLink) $previewLink=$this->db->table('approved_deposit_sale_amendments')->where('deposit_request_id',$parent)->first();
            if (!$previewLink) throw new \LogicException('Predecessor sale must be converted first.');
            $previewItems=$this->db->table('sale_items')->where('sale_id',$previewLink->sale_id)->get();
            if ($previewItems->count()!==1) throw new \LogicException('Single-unit sale required.');
            $previewUnit=$previewItems->first()->unit_id;
            $this->db->table('units')->whereIn('id',array_unique([$previewUnit,$request['unit_id']]))->orderBy('id')->lockForUpdate()->get();
            // Lock source requests by ID in the same sequence for concurrent amendments.
            foreach (array_unique([min($parent,$request['id']),max($parent,$request['id'])]) as $id) {
                $key=['source_system'=>'bsunits','deposit_request_id'=>$id];
                $this->db->table('approved_deposit_conversion_keys')->insertOrIgnore($key);
                $this->db->table('approved_deposit_conversion_keys')->where($key)->lockForUpdate()->first();
            }
            $link=$this->db->table('approved_deposit_sale_links')->where('source_system','bsunits')->where('deposit_request_id',$parent)->lockForUpdate()->first();
            if (!$link) {
                $parentAmendment=$this->db->table('approved_deposit_sale_amendments')->where('deposit_request_id',$parent)->lockForUpdate()->first();
                if ($parentAmendment) {
                    $parentSale=$this->db->table('sales')->where('id',$parentAmendment->sale_id)->lockForUpdate()->first();
                    if ($parentSale) $link=(object)['sale_id'=>$parentSale->id,'customer_account_id'=>$parentSale->customer_account_id,
                        'request_snapshot'=>$parentAmendment->after_snapshot,'snapshot_sha256'=>$parentAmendment->snapshot_sha256];
                }
            }
            if (!$link) throw new \LogicException('Predecessor sale must be converted first.');
            $sale=$this->db->table('sales')->where('id',$link->sale_id)->lockForUpdate()->first();
            $existing=$this->db->table('approved_deposit_sale_amendments')->where('deposit_request_id',$request['id'])->lockForUpdate()->first();
            if ($existing) {
                if (!hash_equals($existing->snapshot_sha256,$hash)) throw new \LogicException('Amendment changed after application.');
                return ['sale_id'=>$link->sale_id,'customer_account_id'=>$link->customer_account_id,'replayed'=>true];
            }
            if ($this->db->table('approved_deposit_sale_links')->where('source_system','bsunits')->where('deposit_request_id',$request['id'])->exists()) throw new \LogicException('Replacement already has a conversion.');
            $terms=$this->db->table('approved_deposit_sale_terms')->where('sale_id',$link->sale_id)->lockForUpdate()->first();
            if (!$terms) throw new \LogicException('Predecessor sale terms missing.');
            $currentHash=hash('sha256',$terms->terms_snapshot);$traceHash=$currentHash;$seen=[];
            while (!hash_equals($link->snapshot_sha256,$traceHash)) {
                if (isset($seen[$traceHash]) || count($seen)>=1000) throw new \LogicException('Invalid predecessor revision chain.');
                $seen[$traceHash]=true;
                $revision=$this->db->table('approved_deposit_sale_revisions')->where('sale_id',$sale->id)->where('deposit_request_id',$parent)->where('after_sha256',$traceHash)->first();
                if (!$revision || !hash_equals($traceHash,hash('sha256',$revision->after_snapshot))) throw new \LogicException('Predecessor is no longer the current sale version.');
                $beforeRevision=json_decode($revision->before_snapshot,true,512,JSON_THROW_ON_ERROR);
                $beforeTerms=$beforeRevision['terms'] ?? '';
                $beforeRequest=json_decode($beforeTerms,true,512,JSON_THROW_ON_ERROR);
                if (($beforeRequest['id'] ?? null)!=$parent || !hash_equals($revision->before_sha256,hash('sha256',$beforeTerms))) throw new \LogicException('Invalid predecessor revision audit.');
                $traceHash=$revision->before_sha256;
            }
            if (!hash_equals($link->snapshot_sha256,hash('sha256',$link->request_snapshot))) throw new \LogicException('Invalid predecessor approval snapshot.');
            $old=json_decode($terms->terms_snapshot,true,512,JSON_THROW_ON_ERROR);
            if (($old['id'] ?? null)!=$parent) throw new \LogicException('Predecessor is no longer the current sale request.');
            $oldAdditional = $old['additional_customers'] ?? [];
            $newAdditional = $request['additional_customers'] ?? [];
            if (is_string($oldAdditional)) $oldAdditional = json_decode($oldAdditional, true, 512, JSON_THROW_ON_ERROR);
            if (is_string($newAdditional)) $newAdditional = json_decode($newAdditional, true, 512, JSON_THROW_ON_ERROR);
            if ($oldAdditional != $newAdditional) throw new \LogicException('Buyer changes require identity review.');
            foreach(['customer_name','customer_gender','customer2_name','customer2_gender','customer_phone_number','customer_phone_number2'] as $field) {
                if ((string)($old[$field] ?? '')!==(string)($request[$field] ?? '')) throw new \LogicException('Buyer changes require separate identity review.');
            }
            if (!$sale || $sale->status!=='Ordered' || $sale->payment_status!=='Unpaid' || $sale->invoice_status!=='First Created'
                || (float)$sale->clearance_amount!=0 || (float)$sale->vat!=0 || (float)($request['receiving_amount'] ?? 0)!=0) throw new \LogicException('Posted or paid sale requires finance amendment.');
            if ($this->db->table('loan_accounts')->where('sale_id',$sale->id)->lockForUpdate()->first()) throw new \LogicException('Financed sale requires original loan-change workflow.');
            $items=$this->db->table('sale_items')->where('sale_id',$sale->id)->lockForUpdate()->get();
            if ($items->count()!==1) throw new \LogicException('Single-unit sale required.');
            $item=$items->first();if ($item->unit_id!=$previewUnit) throw new \LogicException('Sale unit changed; retry amendment.');$unit=$this->db->table('units')->where('id',$request['unit_id'])->lockForUpdate()->first();
            if (!$unit || $unit->catalog_state!=='CURRENT' || !$unit->is_saleable || $unit->deleted_at!==null) throw new \LogicException('Current saleable unit required.');
            $type=$this->db->table('unit_types')->where('id',$unit->unit_type_id)->first();
            $branches=$this->db->table('project_accounting_branch_links')->where('project_id',$type->project_id)->pluck('branch_id')->unique();
            if ($branches->count()!==1 || $branches->first()!=$item->company_branch_id) throw new \LogicException('Cross-branch amendment requires finance review.');
            $approver=$request['unit_controller_id'] ?? null;
            if (($request['sale_manager_status'] ?? '')==='APPROVED' && ($request['sale_manager_actioned_at'] ?? '')>($request['unit_controller_actioned_at'] ?? '')) $approver=$request['sale_manager_id'] ?? null;
            $actor=$this->db->table('bsunits_actor_aliases')->where('source_user_id',$approver)->value('actor_id');
            if (!$actor) throw new \LogicException('Approval actor mapping is missing.');
            $before=json_encode(['approved_actor_id'=>$actor,'sale'=>(array)$sale,'items'=>$items->toArray(),'terms'=>$terms->terms_snapshot],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $now=now()->toDateTimeString();
            $saleFields=array_intersect_key($prices,array_flip(['discount_promotion','discount_other','discount_payment_option','amount_discount_payment_option','price_after_discount','final_price','diposit_amount']));
            $this->db->table('sales')->where('id',$sale->id)->update($saleFields+['updated_at'=>$now]);
            $itemFields=array_intersect_key($prices,array_flip(['discount_promotion','discount_other','discount_payment_option','amount_discount_payment_option','unit_sale_price']));
            $this->db->table('sale_items')->where('id',$item->id)->update($itemFields+['project_id'=>$type->project_id,'unit_type_id'=>$unit->unit_type_id,'unit_id'=>$unit->id,'updated_at'=>$now]);
            $this->db->table('approved_deposit_sale_terms')->where('sale_id',$sale->id)->update(['terms_snapshot'=>$snapshot]);
            $this->db->table('approved_deposit_sale_amendments')->insert(['deposit_request_id'=>$request['id'],'predecessor_request_id'=>$parent,'sale_id'=>$sale->id,'before_snapshot'=>$before,'after_snapshot'=>$snapshot,'snapshot_sha256'=>$hash,'created_at'=>$now]);
            return ['sale_id'=>$sale->id,'customer_account_id'=>$link->customer_account_id,'replayed'=>false];
        }, 5);
    }
}
