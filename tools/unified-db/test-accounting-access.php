<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
class AccountingAccessUser extends App\Models\User {
    public $permissionAllowed=false;
    public function checkPermission($name) { if($name!=='trail_balance')throw new Exception('Wrong permission name');return $this->permissionAllowed; }
}
class AccountingAccessGuard extends Illuminate\Auth\Guard {
    public $testUser;
    public $isGuest;
    public function __construct($user,$guest) {$this->testUser=$user;$this->isGuest=$guest;}
    public function guest() {return $this->isGuest;}
    public function user() {return $this->testUser;}
}
$checks=array();
foreach(array(false,true) as $ajax) foreach(array('guest','denied','allowed') as $case) {
$request=Illuminate\Http\Request::create('/accounting/trail_balance','GET');
if($ajax)$request->headers->set('X-Requested-With','XMLHttpRequest');
$route=new Illuminate\Routing\Route('GET','accounting/trail_balance',array('as'=>'trail_balance','uses'=>'AccountingController@getTrailBalance'));
$request->setRouteResolver(function()use($route){return $route;});
$session=new Illuminate\Session\Store('accounting-test',new Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler());$session->start();$request->setSession($session);
$user=new AccountingAccessUser;$user->role_id=1;$user->permissionAllowed=$case==='allowed';
$guard=new AccountingAccessGuard($user,$case==='guest');
Illuminate\Support\Facades\Auth::swap($guard);
$called=false;
$response=(new App\Http\Middleware\Authenticate($guard))->handle($request,function()use(&$called){$called=true;return new Illuminate\Http\Response('allowed',200);});
$expected=$case==='allowed'?200:($ajax?401:302);
$checks[($ajax?'ajax_':'normal_').$case]=$response->getStatusCode()===$expected && $called===($case==='allowed');
if($case==='denied'&&!$ajax)$checks['denied_permission_session_flag']=$session->get('NO_PERMISSION')===true;
}
foreach($checks as $ok)if(!$ok)throw new Exception('Accounting access regression');
echo json_encode(array('status'=>'PASSED','checks'=>$checks,'scope'=>'Actual Authenticate middleware with synthetic guard and report route; no real users or financial writes. Staff IP allowlist, actual role assignments and unified auth connection remain pending.'),JSON_PRETTY_PRINT);
