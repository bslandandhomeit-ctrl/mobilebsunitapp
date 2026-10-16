<?php
namespace App\Services;
/** Caller holds the current unit lock; finance and request use the same database. */
class UnifiedDepositFinanceGuard {
    private $db;
    public function __construct($db){$this->db=$db;}
    public function isFinanced($requestId){
        if($this->db->transactionLevel()<1)throw new \LogicException('Finance guard requires the request transaction.');
        $ids=$this->db->table('approved_deposit_sale_links')->where('source_system','bsunits')->where('deposit_request_id',$requestId)->pluck('sale_id');
        $ids=$ids->merge($this->db->table('approved_deposit_sale_amendments')->where('deposit_request_id',$requestId)->pluck('sale_id'))->unique()->sort();
        foreach($ids as $id){
            $sale=$this->db->table('sales')->where('id',$id)->lockForUpdate()->first();
            if(!$sale)throw new \LogicException('Deposit sale lineage is missing.');
            if($sale->status==='Accepted'||$this->db->table('loan_accounts')->where('sale_id',$id)->exists())return true;
        }
        return false;
    }
}
