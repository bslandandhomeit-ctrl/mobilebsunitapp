<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$checks=array();
foreach(array(false,true) as $ajax) {
$request=Illuminate\Http\Request::create('/accounting/trail_balance','GET');
if($ajax) $request->headers->set('X-Requested-With','XMLHttpRequest');
Illuminate\Support\Facades\Request::swap($request);
$middleware=(new App\Http\Controllers\AccountingController)->getMiddleware();
$names=array_keys($middleware);
$checks[$ajax?'ajax_auth_registered':'normal_auth_registered']=in_array('auth',$names,true);
}
foreach($checks as $pass) if(!$pass) throw new Exception('Accounting authentication registration missing');
echo json_encode(array('status'=>'PASSED','checks'=>$checks,'scope'=>'Controller middleware registration. Full session/login/IP/role database UAT still pending.'),JSON_PRETTY_PRINT);
