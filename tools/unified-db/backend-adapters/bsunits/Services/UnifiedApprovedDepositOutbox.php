<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class UnifiedApprovedDepositOutbox
{
    public static function enqueue($model) {
        $db=$model->getConnection();
        $query=$db->table($model->getTable())->where('id',$model->id)->select('*');
        foreach(['unit_sale_price','discount_promotion','other_discount_allowance','deposit_amount','special_discount'] as $field) {
            $query->selectRaw('CAST(`'.$field.'` AS DECIMAL(20,2)) AS `'.$field.'`');
        }
        $request=(array)$query->first();
        (new ApprovedDepositSalePricing)->calculate($request);
        $payload=json_encode($request,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $db->table('unified_approved_deposit_outbox')->updateOrInsert(['deposit_request_id'=>$model->id],[
            'request_snapshot'=>$payload,'snapshot_sha256'=>hash('sha256',$payload),'queued_at'=>now()->toDateTimeString(),
        ]);
    }
    public function drain($limit=100) {
        if (!config('unified_catalog.deposit_conversion_enabled')) return 0;
        $source=DB::connection();
        if(config('unified_catalog.same_connection_approval_processing',false)) return (new UnifiedApprovedDepositProcessor($source))->drain($limit);
        if($source->transactionLevel()!==0) throw new \LogicException('Cannot convert uncommitted approvals.');
        // Linked replacements require the amendment workflow. Keep their durable events,
        // but exclude them before the batch limit so they cannot starve normal approvals.
        $ids=$source->table('unified_approved_deposit_outbox')
            ->whereExists(function($query) {
                $query->selectRaw('1')->from('unit_deposit_requests')
                    ->whereColumn('unit_deposit_requests.id','unified_approved_deposit_outbox.deposit_request_id')
                    ->whereNull('unit_deposit_requests.from_unit_deposit_request_id');
            })->orderBy('deposit_request_id')->limit($limit)->pluck('deposit_request_id');
        $converter=UnifiedApprovedDepositConverter::configured();$count=0;
        foreach($ids as $id){
            $count+=$source->transaction(function()use($source,$converter,$id){
                $event=$source->table('unified_approved_deposit_outbox')->where('deposit_request_id',$id)->lockForUpdate()->first();
                if(!$event)return 0;
                $current=$source->table('unit_deposit_requests')->where('id',$id)->first();
                if (!$current || $current->status!=='APPROVED' || $current->deleted_at!==null || $current->to_unit_deposit_request_id!==null) throw new \LogicException('Source approval is no longer current.');
                if(!hash_equals($event->snapshot_sha256,hash('sha256',$event->request_snapshot)))throw new \LogicException('Approval snapshot integrity failure.');
                $converter->convert(json_decode($event->request_snapshot,true,512,JSON_THROW_ON_ERROR));
                $source->table('unified_approved_deposit_outbox')->where('deposit_request_id',$id)->delete();
                return 1;
            });
        }
        return $count;
    }
}
