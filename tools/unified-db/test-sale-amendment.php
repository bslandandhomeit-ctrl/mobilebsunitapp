<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
function amendmentCheck($ok,$label){if(!$ok)throw new RuntimeException($label);}
class AmendmentFaultConnection extends Illuminate\Database\MySqlConnection {public $calls=0;public function table($table){if($table==='approved_deposit_sale_amendments' && ++$this->calls===2)throw new RuntimeException('Synthetic audit insert failure');return parent::table($table);}}
try{
$pdo=new PDO('mysql:host=host.docker.internal;port=3309;dbname=unified_deposit_conversion_test;charset=utf8mb4','unified_deposit_writer','local-only-unified-deposit-writer',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=new Illuminate\Database\MySqlConnection($pdo,'unified_deposit_conversion_test','');
$link=$db->table('approved_deposit_sale_links')->where('deposit_request_id',99990001)->first();$request=json_decode($link->request_snapshot,true);$request['id']=99990200;$request['from_unit_deposit_request_id']=99990001;$request['unit_sale_price']='100000.00';$request['receiving_amount']='0.00';
$service=new App\Services\UnifiedDepositSaleAmendment($db);
function snapshotAmendment($db){$result=[];foreach(['sales','sale_items','approved_deposit_sale_terms','approved_deposit_sale_links','approved_deposit_sale_amendments','approved_deposit_conversion_keys','customer_accounts','persons','unified_id_allocators','loan_accounts'] as $t){$result[$t]=hash('sha256',$db->table($t)->get()->toJson());}return $result;}
$db->table('loan_accounts')->update(['sale_id'=>$link->sale_id]);$financedBefore=snapshotAmendment($db);$blocked=false;
try{$service->amend($request);}catch(LogicException $e){$blocked=true;}
amendmentCheck($blocked&&snapshotAmendment($db)===$financedBefore,'financed amendment changed financial data');$db->table('loan_accounts')->delete();
$before=snapshotAmendment($db);$saleCount=$db->table('sales')->count();
$paid=$request;$paid['receiving_amount']='1.00';$blocked=false;try{$service->amend($paid);}catch(LogicException $e){$blocked=true;}amendmentCheck($blocked&&snapshotAmendment($db)===$before,'paid amendment not atomic');
$buyer=$request;$buyer['customer_name']='Different Buyer';$blocked=false;try{$service->amend($buyer);}catch(LogicException $e){$blocked=true;}amendmentCheck($blocked&&snapshotAmendment($db)===$before,'buyer change not blocked');
// Fault after sale/item/link/terms writes: the audit insert must roll back everything.
$fault=new AmendmentFaultConnection($pdo,'unified_deposit_conversion_test','');$failed=false;try{(new App\Services\UnifiedDepositSaleAmendment($fault))->amend($request);}catch(RuntimeException $e){$failed=true;}amendmentCheck($failed&&snapshotAmendment($db)===$before&&$fault->transactionLevel()===0,'audit failure partial writes');
$one=$service->amend($request);$two=$service->amend($request);amendmentCheck($one['sale_id']==$link->sale_id&&$one['customer_account_id']==$link->customer_account_id&&!$one['replayed']&&$two['replayed'],'same sale/customer or replay mismatch');
amendmentCheck($db->table('sales')->count()===$saleCount,'sale count unexpected');
$audit=$db->table('approved_deposit_sale_amendments')->where('deposit_request_id',$request['id'])->first();$original=json_decode($audit->before_snapshot,true);amendmentCheck($original['sale']['final_price']==='81742.59'&&$db->table('sale_items')->where('sale_id',$link->sale_id)->value('unit_sale_price')==='100000.00','audit or amended saved price mismatch');
$changed=$request;$changed['deposit_amount']='300.00';$blocked=false;try{$service->amend($changed);}catch(LogicException $e){$blocked=true;}amendmentCheck($blocked,'changed amendment replay accepted');
$stale=$request;$stale['id']=99990201;$blocked=false;try{$service->amend($stale);}catch(LogicException $e){$blocked=true;}amendmentCheck($blocked,'stale predecessor accepted');
$chained=$request;$chained['id']=99990201;$chained['from_unit_deposit_request_id']=$request['id'];$chained['unit_sale_price']='120000.00';$chain=$service->amend($chained);
amendmentCheck($chain['sale_id']===$one['sale_id']&&$db->table('sales')->count()===$saleCount&&$db->table('approved_deposit_sale_amendments')->count()===2,'amendment chain duplicated sale');
$chainBefore=snapshotAmendment($db);$replay=$service->amend($request);amendmentCheck($replay['replayed']&&snapshotAmendment($db)===$chainBefore,'old replay reverted newer amendment');
echo json_encode(['status'=>'PASSED','financed_guard_preserves_account_snapshot'=>true,'chained_amendments_reuse_sale'=>true,'old_replay_cannot_revert_new_version'=>true,'same_sale_and_customer'=>true,'audit_retains_old_prices'=>true,'saved_approved_replacement_price'=>true,'idempotent_retry'=>true,'buyer_paid_and_stale_changes_denied'=>true,'audit_insert_failure_full_rollback'=>true,'scope'=>'Explicit service on disposable target; automatic queue integration disabled; finance-linked and concurrent amendments pending']);
}catch(Throwable $e){echo json_encode(['status'=>'FAILED','type'=>get_class($e),'diagnostic'=>get_class($e)==='RuntimeException'?$e->getMessage():'withheld']);exit(1);}
