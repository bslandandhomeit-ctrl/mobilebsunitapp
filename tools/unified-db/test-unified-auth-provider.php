<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
class UnifiedAuthRehearsalGuard extends Illuminate\Auth\Guard {
 private $testUser; public $loggedOut=false;
 public function __construct($user) {$this->testUser=$user;}
 public function guest() {return false;}
 public function logout() {$this->loggedOut=true;}
 public function user() {return $this->testUser;}
}
try {
$cfg=config('unified_posting.database');$cfg['database']='unified_auth_provider_test';$cfg['username']='unified_auth_test';$cfg['password']='local-only-auth-test';$cfg['prefix']='tb_';
config(array('database.connections.auth_rehearsal'=>$cfg,'database.default'=>'auth_rehearsal'));
$provider=new Illuminate\Auth\EloquentUserProvider($app['hash'],'App\Models\User');
$users=App\Models\User::all();$enabled=0;$disabled=0;$permissionLinks=0;$allowed=0;$missingRoles=0;$enabledMissingRoles=0;$middlewareChecks=0;$ipBlocked=0;$branchChecks=0;$missingBranch=0;$enabledMissingBranch=0;
foreach($users as $user) {
 $found=$provider->retrieveById($user->id);if(!$found||$found->id!=$user->id)throw new Exception('Identity lookup failed');
 $login=$provider->retrieveByCredentials(array('username'=>$user->username,'password'=>'not-used-for-lookup','status'=>1));
 // Duplicate names must retain original provider first-match semantics, not imply unique identity.
 if((int)$user->status!==1) {$disabled++; if($login && (int)$login->status!==1)throw new Exception('Disabled account selected');} else {$enabled++;if(!$login || (int)$login->status!==1)throw new Exception('Enabled lookup failed');}
 if(!$user->role()->first()) {$missingRoles++;if((int)$user->status===1)$enabledMissingRoles++;}
 session()->forget('ROLE_PERMISSION');$perms=$user->permission()->get();$expected=false;
 foreach($perms as $p) if($p->code==='ALL_FUNCTIONS'||in_array('TRAIL_BALANCE',explode(',',$p->action_name),true))$expected=true;
 if($user->checkPermission('trail_balance')!==$expected)throw new Exception('Permission provider mismatch');
 if($expected)$allowed++;$permissionLinks+=count($perms);
 // Precheck the original IP gate: its denial path uses dd(), so count blocked
 // source accounts rather than terminating the test or bypassing that rule.
 $ipAllowed=true;
 if(!in_array((int)$user->role_id,array(1,2),true)) {
  $ipAllowed=Illuminate\Support\Facades\DB::table('accessible')->count()===0 || Illuminate\Support\Facades\DB::table('accessible')->whereRaw('INET_ATON(?) = ip_start_range',array($user->login_ip))->count()>0;
 }
 if(!$ipAllowed) {$ipBlocked++;continue;}
 foreach(array(false,true) as $ajax) {
  session()->forget('ROLE_PERMISSION');
  $request=Illuminate\Http\Request::create('/accounting/trail_balance','GET');
  if($ajax)$request->headers->set('X-Requested-With','XMLHttpRequest');
  $route=new Illuminate\Routing\Route('GET','accounting/trail_balance',array('as'=>'trail_balance','uses'=>'AccountingController@getTrailBalance'));
  $request->setRouteResolver(function()use($route){return $route;});
  $request->setSession($app['session']->driver());
  $guard=new UnifiedAuthRehearsalGuard($user);Illuminate\Support\Facades\Auth::swap($guard);
  $called=false;
  $response=(new App\Http\Middleware\Authenticate($guard))->handle($request,function()use(&$called){$called=true;return new Illuminate\Http\Response('allowed',200);});
  $expectedAccess=$expected && (int)$user->status===1;
  if((int)$user->status!==1 && !$guard->loggedOut)throw new Exception('Disabled middleware account was not logged out');
  if($called!==$expectedAccess || $response->getStatusCode()!==($expectedAccess?200:($ajax?401:302)))throw new Exception('Migrated user middleware access differs');
  $middlewareChecks++;
 }
 if(!in_array((int)$user->role_id,array(1,2),true)) {
  $ownBranch=App\Support\TrialBalanceScope::branchForUser($user);
  if($ownBranch===null || $ownBranch==='') {$missingBranch++;if((int)$user->status===1)$enabledMissingBranch++;continue;}
  $pdo=new PDO('sqlite::memory:');$conn=new Illuminate\Database\SQLiteConnection($pdo);
  $query=$conn->table('journal_detail');
  App\Support\TrialBalanceScope::apply($query,'2026-09-01','2026-09-30',false,$user->role_id,$ownBranch,'foreign-test-branch');
  if(!in_array($ownBranch,$query->getBindings(),true)||in_array('foreign-test-branch',$query->getBindings(),true))throw new Exception('Migrated staff branch override');
  $branchChecks++;
 }

}
// Exercise original provider hash verification without using or logging a real password.
$synthetic=new App\Models\User;$synthetic->password=$app['hash']->make('synthetic-provider-test');
if(!$provider->validateCredentials($synthetic,array('password'=>'synthetic-provider-test'))||$provider->validateCredentials($synthetic,array('password'=>'incorrect')))throw new Exception('Hash verification failed');
$denied=false;try {Illuminate\Support\Facades\DB::table('users')->where('id',-1)->update(array('status'=>0));}catch(Exception $e){$denied=strpos($e->getMessage(),'denied')!==false;}
if(!$denied)throw new Exception('Authentication rehearsal permits writes');
echo json_encode(array('status'=>'PASSED','users'=>count($users),'enabled_accounts'=>$enabled,'disabled_accounts'=>$disabled,'permission_links'=>$permissionLinks,'trial_balance_permitted_users'=>$allowed,'missing_role_accounts'=>$missingRoles,'enabled_missing_role_accounts'=>$enabledMissingRoles,'disabled_middleware_accounts_denied'=>true,'actual_user_middleware_checks'=>$middlewareChecks,'staff_branch_checks'=>$branchChecks,'source_ip_blocked_accounts'=>$ipBlocked,'staff_missing_branch_accounts'=>$missingBranch,'enabled_staff_missing_branch_accounts'=>$enabledMissingBranch,'original_provider_lookup'=>true,'original_hash_verification'=>true,'writes_denied'=>true,'scope'=>'Original Laravel provider/User relations over disposable unified read views. Actual migrated permissions and original middleware exercised for IP-allowed accounts; blocked source accounts counted without bypass. Live login/session/token/password updates not switched.'),JSON_PRETTY_PRINT);

} catch(Exception $e) { echo json_encode(array("status"=>"FAILED","error"=>get_class($e),"location"=>basename($e->getFile()).":".$e->getLine()));exit(1); }
