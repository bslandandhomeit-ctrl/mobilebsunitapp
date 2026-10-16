<?php
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
class SaleAccountFixture extends App\Http\Controllers\AccountingController {
 public $mode='none';public $calls=0;
 public function post_add_client_loan_account($client_id=0,$redirect=null){
  $this->calls++;if($this->mode==='none')return null;
  $db=Illuminate\Support\Facades\DB::connection();$row=(array)$db->table('test_loan_account_template')->first();$sale=$db->table('sale_order')->where('id',Illuminate\Support\Facades\Request::input('sale_id'))->first();$item=$db->table('sale_items')->where('sale_id',$sale->id)->first();$row['sale_id']=$sale->id;$row['customer_account_id']=$sale->client_id;$row['unit_id']=$item->unit_id;
  $db->table('client_loan_accounts')->insert(array_diff_key($row,array('client_id'=>1)));
  if($this->mode==='fail')throw new RuntimeException('Synthetic accounting failure');
 }
}
try{
$cfg=config('unified_posting.database');$cfg['host']='host.docker.internal';$cfg['port']=3309;$cfg['database']='unified_deposit_conversion_test';$cfg['username']='unified_deposit_writer';$cfg['password']='local-only-unified-deposit-writer';$cfg['prefix']='tb_';$cfg['strict']=false;config(array('database.connections.acceptance_test'=>$cfg,'database.default'=>'acceptance_test'));
$db=Illuminate\Support\Facades\DB::connection();$db->statement('DELETE FROM loan_accounts');$sale=App\Models\Sale::first();$sale->status='Ordered';$sale->save();$item=App\Models\SaleItem::where('sale_id',$sale->id)->first();
$request=Illuminate\Http\Request::create('/sale/accept/'.$sale->id,'POST',array('sale_id'=>$sale->id,'client_id'=>$sale->client_id,'unit_id'=>$item->unit_id));Illuminate\Support\Facades\Request::swap($request);
$fixture=new SaleAccountFixture;$app->instance('App\Http\Controllers\AccountingController',$fixture);$controller=new App\Http\Controllers\SaleController;
$controller->saleAcceptOrder($sale->id);if($sale->fresh()->status!=='Ordered'||$db->table('client_loan_accounts')->count()!==0||$db->transactionLevel()!==0)throw new RuntimeException('Empty accounting result accepted');
// Template uses a physical table with no prefix.

$fixture->mode='fail';$controller->saleAcceptOrder($sale->id);if($sale->fresh()->status!=='Ordered'||$db->table('client_loan_accounts')->count()!==0||$db->transactionLevel()!==0)throw new RuntimeException('Accounting failure not atomic');
$fixture->mode='success';$request->merge(array('client_id'=>99999999));$calls=$fixture->calls;$controller->saleAcceptOrder($sale->id);if($fixture->calls!==$calls||$sale->fresh()->status!=='Ordered')throw new RuntimeException('Mismatched customer reached account writer');$request->merge(array('client_id'=>$sale->client_id));
$controller->saleAcceptOrder($sale->id);if($sale->fresh()->status!=='Accepted'||$db->table('client_loan_accounts')->count()!==1||$db->transactionLevel()!==0)throw new RuntimeException('Valid acceptance failed');
$calls=$fixture->calls;$controller->saleAcceptOrder($sale->id);if($fixture->calls!==$calls||$db->table('client_loan_accounts')->count()!==1)throw new RuntimeException('Acceptance replay created account');
echo json_encode(array('status'=>'PASSED','actual_original_sale_controller'=>true,'account_validation_failure_keeps_ordered'=>true,'account_exception_rolls_back'=>true,'mismatched_customer_denied_before_writer'=>true,'accepted_only_after_account'=>true,'replay_denied'=>true,'scope'=>'Original sale controller on disposable compatibility aliases; accounting writer fixture substitutes full COA/drawdown/audit controller; complete writer integration pending'));
}catch(Exception $e){echo json_encode(array('status'=>'FAILED','type'=>get_class($e),'diagnostic'=>get_class($e)==='RuntimeException'?$e->getMessage():'withheld','schema_error'=>$e instanceof Illuminate\Database\QueryException?$e->errorInfo[2]:null));exit(1);}
