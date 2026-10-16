<?php
require '/var/www/html/public/global.php';require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cfg=config('unified_posting.database');$cfg['database']='unified_journal_writer_test';$cfg['username']='unified_journal_test';$cfg['password']='local-only-journal-test';$cfg['prefix']='tb_';$cfg['strict']=false;config(array('database.connections.login_rehearsal'=>$cfg,'database.default'=>'login_rehearsal','unified_customer_ui.active'=>false));$db=Illuminate\Support\Facades\DB::connection();
$sessionRoot=sys_get_temp_dir().'/unified-login-'.getmypid();mkdir($sessionRoot,0700);config(array('session.files'=>$sessionRoot));$session=$app['session']->driver();$session->start();$session->save();$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
function loginRequest($app,$kernel,$session,$method,$path,$data=array()){
 $session->save();$data['_token']=$session->token();$cookie=$app['encrypter']->encrypt($session->getId());
 $app->instance('auth',new Illuminate\Auth\AuthManager($app));$app->forgetInstance('auth.driver');Illuminate\Support\Facades\Auth::clearResolvedInstance('auth');Illuminate\Support\Facades\Request::clearResolvedInstance('request');
 $request=Illuminate\Http\Request::create('http://localhost'.$path,$method,$data,array($session->getName()=>$cookie),array(),array('HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest','REMOTE_ADDR'=>'127.0.0.1'));
 return $kernel->handle($request);
}
try{
 if(!app()->environment('local'))throw new RuntimeException('Login rehearsal requires local environment.');
 $password='Synthetic#'.bin2hex(openssl_random_pseudo_bytes(12));$secondary='Secondary#'.bin2hex(openssl_random_pseudo_bytes(12));
 $db->table('users')->insert(array('id'=>777950,'username'=>'synthetic_session_user','password'=>$app['hash']->make($password),'status'=>1,'role_id'=>3,'branch_id'=>1,'locale'=>'en','name'=>'Synthetic session user'));
 $db->table('admin_password')->insert(array('admin_pass'=>$secondary));
 $db->table('permission')->insert(array('id'=>777950,'code'=>'SYNTH_SESSION','action_name'=>'LOAN_PAYOFF','action_type'=>1));$db->table('user_permission')->insert(array('user_id'=>777950,'permission_id'=>777950));
 foreach(array(array('wrong',$secondary,1),array($password,'wrong',1),array($password,$secondary,0)) as $case){
  $db->table('users')->where('id',777950)->update(array('status'=>$case[2]));$response=loginRequest($app,$kernel,$session,'POST','/user/login',array('username'=>'synthetic_session_user','password'=>$case[0],'pw'=>$case[1]));$payload=json_decode($response->getContent(),true);
  if($response->getStatusCode()!=200||!is_array($payload)||$payload['status']!==false||Illuminate\Support\Facades\Auth::check())throw new RuntimeException('Wrong credential/disabled login was not denied.');
 }
 $db->table('users')->where('id',777950)->update(array('status'=>1));$response=loginRequest($app,$kernel,$session,'POST','/user/login',array('username'=>'synthetic_session_user','password'=>$password,'pw'=>$secondary));$payload=json_decode($response->getContent(),true);
 if($response->getStatusCode()!=200||$payload['status']!==true||Illuminate\Support\Facades\Auth::id()!=777950)throw new RuntimeException('Original password login failed.');
 $cookies=$response->headers->getCookies();$hasSessionCookie=false;foreach($cookies as $cookie)if($cookie->getName()===$session->getName())$hasSessionCookie=true;if(!$hasSessionCookie)throw new RuntimeException('Login did not issue session cookie.');
 // A new guard must reload the account from its persisted session, not supplied user.
 $before=$db->table('loan_payoff')->count();$session->forget('errors');$session->save();$response=loginRequest($app,$kernel,$session,'POST','/loans/payoff/777900',array());
 if(Illuminate\Support\Facades\Auth::id()!=777950||$response->getStatusCode()!=302||!$session->has('errors')||$db->table('loan_payoff')->count()!==$before)throw new RuntimeException('Fresh session did not reach permitted Pay-Off validation.');
 $financeTables=array('loan_payoff','loan_payments','journal_requiry','journal_detail','transactions_requiry','user_activity','audit');$financeBefore=array();foreach($financeTables as $t)$financeBefore[$t]=$db->table($t)->count();
 $payoff=array('dpDate'=>'2026-10-09','ipInterest'=>0,'iPrincipal'=>12.25,'iInterest'=>0,'iPenalty'=>0,'ipPayOffFee'=>0,'downpayment'=>5,'inputTotalPayOff'=>17.25,'ipNote'=>'Synthetic session payoff','ch_accrual_interest'=>0,'accrual_interest_amount'=>0,'iAIR_deduct'=>0,'debit'=>array(12.25),'credit'=>array(12.25),'parent_debit'=>array(2),'parent_credit'=>array(1),'d_description'=>array('Synthetic'),'c_description'=>array('Synthetic'),'debit_down'=>array(5),'credit_down'=>array(5),'parent_debit_down'=>array(2),'parent_credit_down'=>array(1),'d_description_down'=>array('Synthetic down'),'c_description_down'=>array('Synthetic down'));
 $db->beginTransaction();$response=loginRequest($app,$kernel,$session,'POST','/loans/payoff/777900',$payoff);
 if($response->getStatusCode()!=302||Illuminate\Support\Facades\Auth::id()!=777950||$db->transactionLevel()!=1||$db->table('loan_payoff')->where('loan_id',777900)->count()!=1||$db->table('loan_payments')->where('loan_id',777900)->count()!=2)throw new RuntimeException('Password-session Pay-Off posting failed.');
 $account=$db->table('client_loan_accounts')->where('id',777900)->first();if($account->balance!=0||$account->balance_downpayment!=0||$account->status!=8||$db->table('drawdown_accounts')->where('id',777900)->pluck('balance')!=32.75)throw new RuntimeException('Password-session Pay-Off balances differ.');
 $db->rollBack();foreach($financeBefore as $t=>$count)if($db->table($t)->count()!==$count)throw new RuntimeException('Password-session Pay-Off escaped rollback.');
 $db->table('users')->where('id',777950)->update(array('status'=>0));$response=loginRequest($app,$kernel,$session,'POST','/loans/payoff/777900',$payoff);
 if($response->getStatusCode()!=401||Illuminate\Support\Facades\Auth::check()||$db->table('loan_payoff')->count()!==$before)throw new RuntimeException('Disabled persisted session was not denied.');
 $db->table('users')->where('id',777950)->update(array('status'=>1));$response=loginRequest($app,$kernel,$session,'POST','/user/login',array('username'=>'synthetic_session_user','password'=>$password,'pw'=>$secondary));$payload=json_decode($response->getContent(),true);if($payload['status']!==true)throw new RuntimeException('Reenabled account login failed.');
 $response=loginRequest($app,$kernel,$session,'GET','/user/logout');if($response->getStatusCode()!=302||Illuminate\Support\Facades\Auth::check())throw new RuntimeException('Logout failed.');
 $response=loginRequest($app,$kernel,$session,'POST','/loans/payoff/777900',array());if(Illuminate\Support\Facades\Auth::check()||$response->getStatusCode()!=401||$db->table('loan_payoff')->count()!==$before)throw new RuntimeException('Logged-out session accessed Pay-Off.');
 echo json_encode(array('status'=>'PASSED','real_password_and_secondary_login'=>true,'wrong_password_secondary_and_disabled_denied'=>true,'encrypted_session_cookie_issued'=>true,'fresh_guard_persisted_session_reaches_payoff_permission'=>true,'disabled_existing_session_denied_and_reenabled_login'=>true,'real_login_session_successful_payoff_and_rollback'=>true,'logout_and_fresh_request_denied'=>true,'scope'=>'Synthetic original account/provider in disposable schema; no assigned guard user or real credentials. Successful posting already covered separately; unified identity projection and browser UI cutover pending.'),JSON_PRETTY_PRINT)."\n";
}catch(Exception $e){fwrite(fopen('php://stderr','w'),'Password session test failed: '.($e instanceof RuntimeException?$e->getMessage():get_class($e))."\n");exit(1);}
foreach(glob($sessionRoot.'/*') as $file)if(is_file($file))unlink($file);rmdir($sessionRoot);
