<?php
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
function saleCheck($ok,$label){if(!$ok)throw new RuntimeException('Sales check failed: '.$label);}
try{
 config(array('unified_sales.enabled'=>true,'unified_sales.database.host'=>'host.docker.internal','unified_sales.database.port'=>3309,'unified_sales.database.password'=>'local-only-unified-sales-reader','unified_sales.database.database'=>getenv('UNIFIED_SALES_TEST_DB')?getenv('UNIFIED_SALES_TEST_DB'):'bsunits_beloan_final'));
 $repo=App\Services\UnifiedSalesRepository::configured();$page=$repo->listing(array('offset'=>3));saleCheck(count($page->items())===3,'pagination');
 $first=$page->items()[0];$detail=$repo->detail($first->id);saleCheck($detail!==null && $detail['sale']->final_price===$first->final_price,'detail prices');
 if(getenv('UNIFIED_SALES_TEST_DB'))saleCheck(!empty($detail['terms'])&&!empty($detail['members']),'approved customer and payment terms visible');
 saleCheck($repo->detail(999999999)===null,'missing sale');
 $filtered=$repo->listing(array('company'=>$first->branch_id,'search'=>$first->order_no));foreach($filtered->items()as$row)saleCheck($row->branch_id==$first->branch_id,'search stays scoped');
 saleCheck(App\Services\UnifiedSalesRepository::money('999999999999999999.99')==='999,999,999,999,999,999.99','large exact display');
 Illuminate\Support\Facades\Auth::setUser(App\Models\User::where('username','admin')->firstOrFail());
 $controller=new App\Http\Controllers\SaleController;
 $list=$controller->listSale()->render();$html=$controller->getDetail($first->id)->render();
 saleCheck(strpos($list,'Customer orders')!==false&&strpos($html,'Sale summary')!==false,'existing screen views render');
 $db=Illuminate\Support\Facades\DB::connection('unified_sales');$blocked=false;try{$db->update('UPDATE sales SET status=status WHERE id=?',array($first->id));}catch(Exception $e){$blocked=true;}saleCheck($blocked,'write denied');
 $blocked=false;try{$db->table('login_identities')->first();}catch(Exception $e){$blocked=true;}saleCheck($blocked,'login data denied');
 echo json_encode(array('status'=>'PASSED','pagination'=>true,'detail_agreed_prices'=>true,'missing_sale'=>true,'scoped_search'=>true,'exact_large_amount_display'=>true,'views_render'=>true,'write_denied'=>true,'login_data_denied'=>true,'runtime'=>PHP_VERSION,'authentication'=>'Injected existing local admin; no password/session test.'),JSON_PRETTY_PRINT)."\n";
}catch(Exception $e){fwrite(fopen('php://stderr','w'),$e instanceof RuntimeException&&strpos($e->getMessage(),'Sales check failed:')===0?$e->getMessage()."\n":'Beloan sales test failed: '.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine()."\n");exit(1);}
