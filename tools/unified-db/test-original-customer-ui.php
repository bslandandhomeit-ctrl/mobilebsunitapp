<?php
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
function originalCheck($ok,$label){if(!$ok)throw new RuntimeException('Original customer check failed: '.$label);}
try{
 originalCheck(config('unified_customer_ui.enabled'),'original UI adapter enabled');
 Illuminate\Support\Facades\Auth::setUser(App\Models\User::where('username','admin')->firstOrFail());
 $controller=new App\Http\Controllers\ClientController;
 $client=App\Models\Client::with('general','Contact','Identification')->findOrFail(1);
 originalCheck($client->getConnection()->getDatabaseName()==='bsunits_beloan_final','customer database');originalCheck($client->general->first()->getConnection()->getDatabaseName()==='bsunits_beloan_final','general database');originalCheck($client->Contact->first()->getConnection()->getDatabaseName()==='bsunits_beloan_final','contacts database');
 originalCheck($controller->listClient()->name()==='clients.list_new','original list view');originalCheck((new App\Http\Controllers\ClientController)->getDetailN(1)->name()==='clients.detail_new','original detail view');originalCheck((new App\Http\Controllers\ClientController)->getClient(1)->name()==='clients.add','original full edit view');
 $list=(new App\Http\Controllers\ClientController)->listClient()->render();$detail=(new App\Http\Controllers\ClientController)->getDetailN(1)->render();$edit=(new App\Http\Controllers\ClientController)->getClient(1)->render();originalCheck(strpos($list,'card_number')!==false&&strpos($detail,'addressList')!==false&&strpos($edit,'saveDraft')!==false,'full views render');
 echo json_encode(array('status'=>'PASSED','original_list_detail_edit'=>true,'customer_database'=>'bsunits_beloan_final','related_profiles_database'=>'bsunits_beloan_final','full_views_render'=>true,'write_activation'=>(bool)config('unified_customer_ui.writes_enabled')),JSON_PRETTY_PRINT)."\n";
}catch(Exception $e){fwrite(fopen('php://stderr','w'),$e instanceof RuntimeException?$e->getMessage()."\n":'Original customer test failed: '.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine()."\n");exit(1);}
