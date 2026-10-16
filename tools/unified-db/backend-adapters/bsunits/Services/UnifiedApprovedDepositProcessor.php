<?php
namespace App\Services;
/** Canonical same-connection outbox consumption; linked-unit replacements stay explicit. */
class UnifiedApprovedDepositProcessor {
 private $db;
 public function __construct($db){if($db->getTablePrefix()!=='')throw new \LogicException('Canonical approval processor requires no table prefix.');$this->db=$db;}
 public function drain($limit=100){
  if($this->db->transactionLevel()!==0)throw new \LogicException('Approval processing requires committed input.');
  $ids=$this->db->table('unified_approved_deposit_outbox')->whereExists(function($q){$q->selectRaw('1')->from('unit_deposit_requests')->whereColumn('unit_deposit_requests.id','unified_approved_deposit_outbox.deposit_request_id')->whereNull('unit_deposit_requests.from_unit_deposit_request_id');})->orderBy('deposit_request_id')->limit($limit)->pluck('deposit_request_id');$count=0;
  foreach($ids as $id)$count+=$this->db->transaction(function()use($id){
   $preview=$this->db->table('unit_deposit_requests')->where('id',$id)->first();if(!$preview)throw new \LogicException('Approval request missing.');
   $this->db->table('units')->where('id',$preview->unit_id)->lockForUpdate()->first();
   $current=$this->db->table('unit_deposit_requests')->where('id',$id)->lockForUpdate()->first();
   if(!$current||$current->unit_id!=$preview->unit_id||$current->from_unit_deposit_request_id!==null)throw new \LogicException('Approval ownership changed.');
   $event=$this->db->table('unified_approved_deposit_outbox')->where('deposit_request_id',$id)->lockForUpdate()->first();if(!$event)return 0;
   $query=$this->db->table('unit_deposit_requests')->where('id',$id)->select('*');foreach(['unit_sale_price','discount_promotion','other_discount_allowance','deposit_amount','special_discount'] as $field)$query->selectRaw('CAST(`'.$field.'` AS DECIMAL(20,2)) AS `'.$field.'`');
   $request=(array)$query->first();(new ApprovedDepositSalePricing)->calculate($request);$payload=json_encode($request,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
   if(!hash_equals($event->snapshot_sha256,hash('sha256',$event->request_snapshot))||!hash_equals($event->snapshot_sha256,hash('sha256',$payload)))throw new \LogicException('Approval event is stale or invalid.');
   $link=$this->db->table('approved_deposit_sale_links')->where('source_system','bsunits')->where('deposit_request_id',$id)->first();
   if(!$link)(new UnifiedApprovedDepositConverter($this->db))->convert($request);
   else{$terms=$this->db->table('approved_deposit_sale_terms')->where('sale_id',$link->sale_id)->first();if(!$terms)throw new \LogicException('Converted sale terms missing.');$hash=hash('sha256',$terms->terms_snapshot);if(!hash_equals($hash,$event->snapshot_sha256))(new UnifiedDepositSaleRevision($this->db))->revise($request,$hash);}
   $this->db->table('unified_approved_deposit_outbox')->where('deposit_request_id',$id)->delete();return 1;
  },5);
  return $count;
 }
}
