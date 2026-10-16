<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
$source=config('database.default');$cfg=config('unified_catalog.database');$cfg['database']='unified_request_read_test';$cfg['username']='unified_request_test';$cfg['password']='local-only-request-test';$cfg['prefix']='';config(array('database.connections.role_rehearsal'=>$cfg,'cache.default'=>'array'));
$expected=array();$counts=array();$checked=0;$loginCounts=array();$statusChecks=0;
foreach(array($source,'role_rehearsal') as $connection) {
 config(array('database.default'=>$connection));$users=App\User::with('roles')->get();$results=array();$roles=array();$login=array();$status=array();
 foreach($users as $user) {
  $names=$user->roles->pluck('name')->sort()->values()->all();$roles[$user->id]=$names;
  try {$passport=(new App\User)->findForPassport($user->phone_number);$login[$user->id]=$passport?'allowed:'.$passport->id:'not_found';}catch(League\OAuth2\Server\Exception\OAuthServerException $e){$login[$user->id]=$e->getErrorType();}
  $statusRequest=Illuminate\Http\Request::create('/api/whoami','GET');$statusRequest->setUserResolver(function()use($user){return $user;});
  $reached=false;$statusResponse=(new App\Http\Middleware\EnsureApiAccountEnabled)->handle($statusRequest,function()use(&$reached){$reached=true;return response()->json(['ok'=>true]);});
  $enabled=(bool)$user->active && (bool)$user->verified;
  if($reached!==$enabled||$statusResponse->getStatusCode()!==($enabled?200:401))throw new Exception('Account status API check mismatch');
  $status[$user->id]=$enabled;
  Illuminate\Support\Facades\Auth::setUser($user);
  foreach(array('hold_create'=>'sale_team_leader|agent','unit_controller'=>'unit_controller','sale_manager'=>'sale_manager') as $feature=>$required) {
   $called=false;$allowed=false;
   try {(new Spatie\Permission\Middlewares\RoleMiddleware)->handle(Illuminate\Http\Request::create('/api/test','GET'),function()use(&$called){$called=true;return true;},$required);$allowed=true;}catch(Spatie\Permission\Exceptions\UnauthorizedException $e){if($e->getStatusCode()!==403)throw new Exception('Unexpected role denial status');}
   $match=count(array_intersect($names,explode('|',$required)))>0;if($allowed!==$match || $called!==$match)throw new Exception('Original role middleware differs from assigned roles');
   $results[$user->id][$feature]=$allowed;
  }
 }
 ksort($roles);ksort($results);$snapshot=hash('sha256',json_encode(array($roles,$results,$login,$status)));
 if($connection===$source)$expected=$snapshot;else {if($snapshot!==$expected)throw new Exception('Source/unified role behavior differs');$checked=count($users)*3;$statusChecks=count($users);foreach($login as $result){$type=strpos($result,'allowed:')===0?'allowed':$result;$loginCounts[$type]=($loginCounts[$type]??0)+1;}foreach($results as $features)foreach($features as $feature=>$allowed)if($allowed)$counts[$feature]=($counts[$feature]??0)+1;}
}
$apiRoute=$app['router']->getRoutes()->getByName('api.user.authenticated');
if(!$apiRoute||!in_array('apiAccountEnabled',$apiRoute->gatherMiddleware(),true)||!in_array('auth:api',$apiRoute->gatherMiddleware(),true))throw new Exception('API account-status middleware not wired');
$guestRequest=Illuminate\Http\Request::create('/api/whoami','GET');$guestRequest->setUserResolver(function(){return null;});
$guestStatus=(new App\Http\Middleware\EnsureApiAccountEnabled)->handle($guestRequest,function(){throw new Exception('Unauthenticated account check reached handler');});if($guestStatus->getStatusCode()!==401)throw new Exception('Unauthenticated API account check failed');
Illuminate\Support\Facades\Auth::swap(new class {public function guest(){return true;}});
$guestDenied=false;try{(new Spatie\Permission\Middlewares\RoleMiddleware)->handle(Illuminate\Http\Request::create('/api/test','GET'),function(){throw new Exception('Guest reached handler');},'agent');}catch(Spatie\Permission\Exceptions\UnauthorizedException $e){$guestDenied=$e->getStatusCode()===403;}
if(!$guestDenied)throw new Exception('Guest middleware guard failed');
echo json_encode(array('status'=>'PASSED','users'=>count($users),'role_access_checks'=>$checked,'source_unified_behavior_parity'=>true,'permitted_counts'=>$counts,'guest_denied'=>true,'passport_login_status_counts'=>$loginCounts,'api_account_status_checks'=>$statusChecks,'api_route_middleware_wired'=>true,'unauthenticated_api_denied'=>true,'scope'=>'Actual Spatie middleware with original User roles from source and unified views. Auth identity set in-process: Original Passport login eligibility and API active/verified enforcement compared; signed token issuance/validation and live unified login/session not covered.'),JSON_PRETTY_PRINT);
}catch(Exception $e){echo json_encode(array('status'=>'FAILED','error'=>get_class($e),'location'=>basename($e->getFile()).':'.$e->getLine(),'sql_error'=>($e instanceof Illuminate\Database\QueryException)?$e->errorInfo[2]:null));exit(1);}
