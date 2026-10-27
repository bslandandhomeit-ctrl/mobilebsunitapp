<?php
namespace App\Services;
/** Explicit revision of the same approved request; no finance or customer writes. */
class UnifiedDepositSaleRevision {
 private $db;
 public function __construct($db){$this->db=$db;}
 public function revise(array $request,$expectedTermsHash){
  $prices=(new ApprovedDepositSalePricing)->calculate($request);
  $snapshot=json_encode($request,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$hash=hash('sha256',$snapshot);
  return $this->db->transaction(function()use($request,$prices,$snapshot,$hash,$expectedTermsHash){
   $unit=$this->db->table('units')->where('id',$request['unit_id'])->lockForUpdate()->first();
   if(!$unit||$unit->catalog_state!=='CURRENT'||!$unit->is_saleable||$unit->deleted_at!==null)throw new \LogicException('Current unit required.');
   $link=$this->db->table('approved_deposit_sale_links')->where('source_system','bsunits')->where('deposit_request_id',$request['id'])->first();
   if(!$link){$amend=$this->db->table('approved_deposit_sale_amendments')->where('deposit_request_id',$request['id'])->first();if($amend)$link=(object)['sale_id'=>$amend->sale_id];}
   if(!$link)throw new \LogicException('Converted sale required.');
   $sale=$this->db->table('sales')->where('id',$link->sale_id)->lockForUpdate()->first();
   $terms=$this->db->table('approved_deposit_sale_terms')->where('sale_id',$link->sale_id)->lockForUpdate()->first();
   if(!$sale||!$terms)throw new \LogicException('Sale terms missing.');
   $existing=$this->db->table('approved_deposit_sale_revisions')->where('sale_id',$sale->id)->where('after_sha256',$hash)->first();
   if($existing)return ['sale_id'=>$sale->id,'replayed'=>true];
   if(!is_string($expectedTermsHash)||!hash_equals($expectedTermsHash,hash('sha256',$terms->terms_snapshot)))throw new \LogicException('Sale version changed.');
   $old=json_decode($terms->terms_snapshot,true,512,JSON_THROW_ON_ERROR);
   if(($old['id']??null)!=$request['id']||($old['unit_id']??null)!=$request['unit_id'])throw new \LogicException('Same request and unit required.');
            $oldAdditional = $old['additional_customers'] ?? [];
            $newAdditional = $request['additional_customers'] ?? [];
            if (is_string($oldAdditional)) $oldAdditional = json_decode($oldAdditional, true, 512, JSON_THROW_ON_ERROR);
            if (is_string($newAdditional)) $newAdditional = json_decode($newAdditional, true, 512, JSON_THROW_ON_ERROR);
            if ($oldAdditional != $newAdditional) throw new \LogicException('Buyer changes require identity review.');
   foreach(['customer_name','customer_gender','customer2_name','customer2_gender','customer_phone_number','customer_phone_number2'] as $field)if((string)($old[$field]??'')!==(string)($request[$field]??''))throw new \LogicException('Buyer changes require identity review.');
   if($sale->status!=='Ordered'||$sale->authorization!=='Authorized'||$sale->payment_status!=='Unpaid'||$sale->invoice_status!=='First Created'||(float)$sale->clearance_amount!=0||(float)$sale->vat!=0||(float)($request['receiving_amount']??0)!=0||$this->db->table('loan_accounts')->where('sale_id',$sale->id)->exists())throw new \LogicException('Financed or paid sale requires finance review.');
   $items=$this->db->table('sale_items')->where('sale_id',$sale->id)->lockForUpdate()->get();if($items->count()!==1||$items->first()->unit_id!=$unit->id)throw new \LogicException('Same single-unit sale required.');
   $actor=$request['unit_controller_id']??null;if(($request['sale_manager_status']??'')==='APPROVED'&&($request['sale_manager_actioned_at']??'')>($request['unit_controller_actioned_at']??''))$actor=$request['sale_manager_id']??null;
   $actor=$this->db->table('bsunits_actor_aliases')->where('source_user_id',$actor)->value('actor_id');if(!$actor)throw new \LogicException('Approval actor missing.');
   $before=json_encode(['sale'=>(array)$sale,'items'=>$items->toArray(),'terms'=>$terms->terms_snapshot],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
   $now=now()->toDateTimeString();$saleFields=array_intersect_key($prices,array_flip(['discount_promotion','discount_other','discount_payment_option','amount_discount_payment_option','price_after_discount','final_price','diposit_amount']));
   $this->db->table('sales')->where('id',$sale->id)->update($saleFields+['updated_at'=>$now]);
   $itemFields=array_intersect_key($prices,array_flip(['discount_promotion','discount_other','discount_payment_option','amount_discount_payment_option','unit_sale_price']));$this->db->table('sale_items')->where('id',$items->first()->id)->update($itemFields+['updated_at'=>$now]);
   $this->db->table('approved_deposit_sale_terms')->where('sale_id',$sale->id)->update(['terms_snapshot'=>$snapshot]);
   $this->db->table('approved_deposit_sale_revisions')->insert(['sale_id'=>$sale->id,'deposit_request_id'=>$request['id'],'before_snapshot'=>$before,'after_snapshot'=>$snapshot,'before_sha256'=>$expectedTermsHash,'after_sha256'=>$hash,'approved_actor_id'=>$actor,'created_at'=>$now]);
   return ['sale_id'=>$sale->id,'replayed'=>false];
  },5);
 }
}
