<?php
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
function customerCheck($ok,$label){if(!$ok)throw new RuntimeException('Customer check failed: '.$label);}
try{
 config(array('unified_sales.customers_enabled'=>true,'unified_sales.database.host'=>'host.docker.internal','unified_sales.database.port'=>3309,'unified_sales.database.password'=>'local-only-unified-sales-reader','unified_sales.database.database'=>getenv('UNIFIED_SALES_TEST_DB')?getenv('UNIFIED_SALES_TEST_DB'):'bsunits_beloan_final'));
 $repo=App\Services\UnifiedCustomersRepository::configured();$page=$repo->listing(array('sel_offset'=>3));customerCheck(count($page->items())===3,'pagination');
 $first=$page->items()[0];$detail=$repo->detail($first->id);customerCheck($detail['account']->id==$first->id,'account detail');customerCheck($repo->detail(999999999)===null,'missing account');
 $filtered=$repo->listing(array('customer_id'=>$first->account_number,'name'=>'unlikely-no-customer-with-this-name'));customerCheck($filtered->total()===0,'combined filters do not broaden search');
 if(getenv('UNIFIED_SALES_TEST_DB'))customerCheck(count($detail['contacts'])>0&&count($detail['members'])>0&&count($detail['orders'])===1,'converted account contacts buyers and order visible');
 Illuminate\Support\Facades\Auth::setUser(App\Models\User::where('username','admin')->firstOrFail());
 $controller=new App\Http\Controllers\ClientController;$list=$controller->listClient()->render();$html=$controller->getDetailN($first->id)->render();
 customerCheck(strpos($list,'Customer accounts')!==false&&strpos($html,'Phone numbers')!==false,'views render');
 echo json_encode(array('status'=>'PASSED','pagination'=>true,'detail'=>true,'missing_account'=>true,'combined_search_scoped'=>true,'views_render'=>true,'converted_account_test'=>(bool)getenv('UNIFIED_SALES_TEST_DB'),'scope'=>'Read only; admin guard injected.'),JSON_PRETTY_PRINT)."\n";
}catch(Exception $e){fwrite(fopen('php://stderr','w'),$e instanceof RuntimeException?$e->getMessage()."\n":'Customer test failed: '.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine()."\n");exit(1);}
