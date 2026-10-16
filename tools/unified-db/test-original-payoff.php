<?php
require '/var/www/html/public/global.php';require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cfg=config('unified_posting.database');$cfg['database']='unified_journal_writer_test';$cfg['username']='unified_journal_test';$cfg['password']='local-only-journal-test';$cfg['prefix']='tb_';$cfg['strict']=false;config(array('database.connections.payoff_rehearsal'=>$cfg,'database.default'=>'payoff_rehearsal','unified_customer_ui.active'=>false));$db=Illuminate\Support\Facades\DB::connection();
class PayoffRehearsal extends App\Http\Controllers\LoanController{public function __construct(){}}
class PayoffFormDataRehearsal extends App\Http\Controllers\LoanController{public function __construct(){}protected function view($view=null,$data=array(),$mergeData=array()){return array('view'=>$view,'data'=>$data);}}
class PayoffKernelExceptionRecorder extends App\Exceptions\Handler{public $seen=array();public function report(Exception $e){$this->seen[]=get_class($e);}}
function createPayoffImageFixture($path){
 $image=imagecreatetruecolor(8,6);imagefill($image,0,0,imagecolorallocate($image,40,120,200));imagepng($image,$path);imagedestroy($image);
}
function cleanupPayoffReceiptFixture($root){
 foreach(array($root.'/data/loans/documents/*',$root.'/*') as $pattern)foreach(glob($pattern) as $file)if(is_file($file))unlink($file);
 foreach(array('/data/loans/documents','/data/loans','/data','') as $part)if(is_dir($root.$part))rmdir($root.$part);
}
try{
 $user=new App\Models\User();$user->id=3;$user->branch_id=1;$user->role_id=3;$user->status=1;Illuminate\Support\Facades\Auth::setUser($user);$session=$app->make('session')->driver();$session->start();
 $payoffMode=getenv('PAYOFF_RACE_MODE');$fixtureId=$payoffMode?777901:777900;
 if($payoffMode!=='worker'){
 $db->table('coa_categories')->whereIn('id',array(1,2))->update(array('currency'=>2));
 $db->table('client_loan_accounts')->insert(array('id'=>$fixtureId,'balance'=>12.25,'balance_downpayment'=>0,'currency'=>2,'status'=>GENERAL_LC_STATUS,'coa_id'=>1,'air_id'=>1,'int_inc_id'=>1));
 $db->table('drawdown_accounts')->insert(array('id'=>$fixtureId,'customer_account_id'=>$fixtureId,'account_no'=>'SYNTH-PAYOFF-DD','balance'=>50,'currency'=>2,'coa_id'=>2));
 $db->table('loans')->insert(array('id'=>$fixtureId,'client_id'=>$fixtureId,'loan_account_id'=>$fixtureId,'contract_id'=>'SYNTH-PAYOFF','drawdown_acc'=>'SYNTH-PAYOFF-DD','company_branch_id'=>1,'status'=>3));
 $db->table('transactions_requiry')->insert(array('loan_id'=>$fixtureId,'balance'=>12.25,'amount'=>0,'trans_type'=>'Synthetic opening','trans_date'=>'2026-10-08'));
 }
 $data=array('dpDate'=>'2026-10-09','ipInterest'=>0,'iPrincipal'=>12.25,'iInterest'=>0,'iPenalty'=>0,'ipPayOffFee'=>0,'downpayment'=>0,'inputTotalPayOff'=>12.25,'ipNote'=>'Synthetic payoff','ch_accrual_interest'=>0,'accrual_interest_amount'=>0,'iAIR_deduct'=>0,'invoice_number'=>'SYNTH-PAYOFF-INPUT','debit'=>array(12.25),'credit'=>array(12.25),'parent_debit'=>array(2),'parent_credit'=>array(1),'d_description'=>array('Synthetic'),'c_description'=>array('Synthetic'));
 if($payoffMode==='setup'){echo json_encode(array('status'=>'READY'))."\n";exit;}
 if($payoffMode==='worker'){
  if(getenv('PAYOFF_RACE_HOLD'))App\Models\LoanPayOff::created(function($model){echo "LOCKED\n";flush();usleep(3000000);});
  $request=Illuminate\Http\Request::create('/loans/payoff','POST',$data);$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);$start=microtime(true);
  $response=(new PayoffRehearsal())->postPayOff($fixtureId);
  if($response->getStatusCode()!=302||$db->transactionLevel()!=0)throw new RuntimeException('Concurrent payoff response/cleanup failed.');
 echo json_encode(array('status'=>'PASSED','elapsed'=>microtime(true)-$start))."\n";exit;
 }
 $tables=array('loan_payoff','journal_requiry','journal_detail','loan_payments','transactions_requiry','user_activity','audit');$before=array();foreach($tables as $t)$before[$t]=$db->table($t)->count();
 foreach(array(array('inputTotalPayOff'=>1),array('iPrincipal'=>-1),array('iInterest'=>'invalid'),array('credit'=>array(10)),array('debit'=>array(1),'credit'=>array(1)),array('debit'=>array(-12.25)),array('parent_debit'=>array(99999999)),array('credit'=>array()),array('debit'=>'invalid'),array('downpayment'=>1,'inputTotalPayOff'=>13.25),array('ch_waive_panalty'=>1,'waive_panalty_amount'=>-1),array('ch_waive_panalty'=>2),array('iAIR_deduct'=>'invalid')) as $invalid){
  $session->forget('danger');$session->forget('errors');$request=Illuminate\Http\Request::create('/loans/payoff','POST',array_merge($data,$invalid));$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);
  $denied=(new PayoffRehearsal())->postPayOff($fixtureId);
  if($denied->getStatusCode()!=302||$db->transactionLevel()!=0||(!$session->has('danger')&&!$session->has('errors')))throw new RuntimeException('Invalid payoff did not warn/clean transaction.');
  foreach($before as $t=>$count)if($db->table($t)->count()!=$count)throw new RuntimeException('Invalid payoff wrote business rows.');
  if($db->table('drawdown_accounts')->where('id',$fixtureId)->pluck('balance')!=50||$db->table('loans')->where('id',$fixtureId)->pluck('status')!=3)throw new RuntimeException('Invalid payoff changed balance/status.');
 }
 $receiptRoot=sys_get_temp_dir().'/unified-payoff-files-'.getmypid();mkdir($receiptRoot.'/data/loans/documents',0700,true);$originalPublicPath=$app->make('path.public');$app->instance('path.public',$receiptRoot);
 foreach(array(2,null) as $deniedBranch){
  $user->branch_id=$deniedBranch;$request=Illuminate\Http\Request::create('/loans/payoff','POST',$data);$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);
  foreach(array('post','get') as $verb){$denied=false;try{if($verb==='post')(new PayoffRehearsal())->postPayOff($fixtureId);else(new PayoffRehearsal())->getPayOff($fixtureId);}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){$denied=$e->getStatusCode()===403;}
   if(!$denied||$db->transactionLevel()!=0)throw new RuntimeException('Payoff branch scope/transaction cleanup failed.');
   foreach($before as $t=>$count)if($db->table($t)->count()!=$count)throw new RuntimeException('Branch-denied payoff wrote rows.');
  }
 }
 $user->branch_id=1;
 // Actual original authentication middleware resolves the real named Pay-Off route.
 $user->login_ip='127.0.0.1';$session->forget('ROLE_PERMISSION');
 foreach(array(true,false) as $ajax){
  $request=Illuminate\Http\Request::create('/loans/payoff/'.$fixtureId,'POST',$data);$request->setSession($session);if($ajax)$request->headers->set('X-Requested-With','XMLHttpRequest');
  $route=$app['router']->getRoutes()->match($request);$request->setRouteResolver(function()use($route){return $route;});
  if($route->getName()!=='loan_payoff')throw new RuntimeException('Original payoff route did not match.');
  $reached=false;$denied=(new App\Http\Middleware\Authenticate($app['auth']->driver()))->handle($request,function()use(&$reached){$reached=true;return response('unexpected',200);});
  if($reached||$denied->getStatusCode()!=($ajax?401:302))throw new RuntimeException('Missing Pay-Off permission not denied.');
  foreach($before as $t=>$count)if($db->table($t)->count()!=$count)throw new RuntimeException('Permission denial wrote financial rows.');
 }
 $guestGuard=new Illuminate\Auth\Guard(new Illuminate\Auth\EloquentUserProvider($app['hash'],'App\Models\User'),$session);$guestGuard->setRequest($request);
 $request->headers->set('X-Requested-With','XMLHttpRequest');$reached=false;$guestDenied=(new App\Http\Middleware\Authenticate($guestGuard))->handle($request,function()use(&$reached){$reached=true;return response('unexpected',200);});if($reached||$guestDenied->getStatusCode()!=401)throw new RuntimeException('Anonymous Pay-Off not denied.');
 $db->table('permission')->insert(array('id'=>777900,'code'=>'SYNTH_PAYOFF','action_name'=>'LOAN_PAYOFF','action_type'=>1));$db->table('user_permission')->insert(array('user_id'=>3,'permission_id'=>777900));$session->forget('ROLE_PERMISSION');
 $db->beginTransaction();Illuminate\Support\Facades\Request::swap($request);
 $allowed=(new App\Http\Middleware\Authenticate($app['auth']->driver()))->handle($request,function(){return (new App\Http\Controllers\LoanController())->postPayOff(777900);});
 if($allowed->getStatusCode()!=302||$db->transactionLevel()!=1||$db->table('loan_payoff')->where('loan_id',$fixtureId)->count()!=1)throw new RuntimeException('Permitted original middleware/controller posting failed.');
 $db->rollBack();foreach($before as $t=>$count)if($db->table($t)->count()!=$count)throw new RuntimeException('Middleware posting escaped caller rollback.');
 // Full original HTTP kernel: encrypted session cookie and real CSRF middleware.
 $app->instance('path.public',$originalPublicPath);$session->save();$cookieName=$session->getName();$cookieValue=$app['encrypter']->encrypt($session->getId());$csrf=$session->token();
 $originalHandler=$app->make(Illuminate\Contracts\Debug\ExceptionHandler::class);$kernelRecorder=new PayoffKernelExceptionRecorder($app['log']);$app->instance(Illuminate\Contracts\Debug\ExceptionHandler::class,$kernelRecorder);$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
 foreach(array('invalid_csrf','missing_permission','valid') as $httpScenario){
  $validCsrf=$httpScenario!=='invalid_csrf';
  $session->forget('ROLE_PERMISSION');$session->save();$kernelData=$data;$kernelData['_token']=$validCsrf?$csrf:'synthetic-invalid-token';
  $http=Illuminate\Http\Request::create('http://localhost/loans/payoff/'.$fixtureId,'POST',$kernelData,array($cookieName=>$cookieValue),array(),array('HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest'));
  Illuminate\Support\Facades\Request::clearResolvedInstance('request');$db->beginTransaction();if($httpScenario==='missing_permission')$db->table('user_permission')->where('user_id',3)->where('permission_id',777900)->delete();$response=$kernel->handle($http);
  if($httpScenario==='missing_permission'){if($response->getStatusCode()!=401||$db->table('loan_payoff')->where('loan_id',$fixtureId)->count()!=0||$db->transactionLevel()!=1)throw new RuntimeException('Full kernel missing permission not denied.');}
  elseif($validCsrf){if($response->getStatusCode()!=302||$db->table('loan_payoff')->where('loan_id',$fixtureId)->count()!=1||$db->transactionLevel()!=1)throw new RuntimeException('Full HTTP kernel valid-CSRF posting failed.');}
  else{if(!in_array('Illuminate\Session\TokenMismatchException',$kernelRecorder->seen)||$db->table('loan_payoff')->where('loan_id',$fixtureId)->count()!=0||$db->transactionLevel()!=1)throw new RuntimeException('Full HTTP kernel invalid-CSRF not denied.');}
  $db->rollBack();foreach($before as $t=>$count)if($db->table($t)->count()!=$count)throw new RuntimeException('HTTP kernel posting escaped rollback.');
 }
 $app->instance(Illuminate\Contracts\Debug\ExceptionHandler::class,$originalHandler);$app->instance('path.public',$receiptRoot);
 $db->table('repayment_draft')->insert(array('id'=>777910,'loan_id'=>$fixtureId+1,'user_id'=>3,'status'=>0));
 $db->table('repayment_draft')->insert(array('id'=>777911,'loan_id'=>$fixtureId,'user_id'=>99,'status'=>0));
 $db->table('repayment_draft')->insert(array('id'=>777912,'loan_id'=>$fixtureId,'user_id'=>3,'status'=>1));
 foreach(array(777910=>403,777911=>302,777912=>302,777913=>404) as $draftId=>$expected){
  $request=Illuminate\Http\Request::create('/loans/payoff/'.$fixtureId,'GET',array('draft_id'=>$draftId));$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);
  $actual=null;try{$actual=(new PayoffRehearsal())->getPayOff($fixtureId)->getStatusCode();}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){$actual=$e->getStatusCode();}catch(Illuminate\Database\Eloquent\ModelNotFoundException $e){$actual=404;}
  if($actual!==$expected)throw new RuntimeException('Payoff draft access response differs.');foreach($before as $t=>$count)if($db->table($t)->count()!=$count)throw new RuntimeException('Draft-denied GET changed financial rows.');
 }
 $db->beginTransaction();$db->table('loans')->where('id',$fixtureId)->update(array('status'=>9));$request=Illuminate\Http\Request::create('/loans/payoff/'.$fixtureId,'GET');$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);$denied=false;try{(new PayoffRehearsal())->getPayOff($fixtureId);}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){$denied=$e->getStatusCode()===404;}if(!$denied)throw new RuntimeException('Completed payoff loan GET not rejected.');$db->rollBack();
 $db->beginTransaction();
 $db->table('clients')->insert(array('id'=>$fixtureId,'client_name'=>'Synthetic form customer'));
 $db->table('loans')->where('id',$fixtureId)->update(array('start_date'=>'2026-09-09','disburse_date'=>'2026-09-09','start_payment_date'=>'2026-09-09','interest_rate'=>1,'loan_duration'=>1,'transfer_date'=>'0000-00-00','penalty_rate_type'=>0,'penalty_period1'=>7));
 foreach(array(0,1) as $month)$db->table('repayment_schedule')->insert(array('loan_id'=>$fixtureId,'no'=>$month,'loan_no'=>$month,'schedule_date'=>$month?'2026-10-09':'2026-09-09','principal'=>$month?12.25:0,'interest'=>$month?2:0,'fee'=>0,'other_fee'=>0,'date_num'=>30,'type'=>'loan','status'=>$month?0:1));
 foreach(array('Inc-Fine and Penalty on Repayment of Loans and leasing','Inc-Other Fees and Commissions from Loans and Leasing') as $name)$db->table('coa_categories')->insert(array('name'=>$name,'currency'=>2,'type'=>6));
 $request=Illuminate\Http\Request::create('/loans/payoff/'.$fixtureId,'GET',array('dpDate'=>'2026-10-09'));$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);$form=(new PayoffFormDataRehearsal())->getPayOff($fixtureId);
 if($form['view']!=='loans.payoff'||$form['data']['draft_id']!=0||$form['data']['draft']->exists||$form['data']['draft']->ipInterest!=1||$form['data']['draft']->ipStartDate!=='2026-09-09'||$form['data']['draft']->ipTenure!=0)throw new RuntimeException('No-draft Pay-Off defaults differ.');
 if($form['data']['loan']->id!=$fixtureId||!is_array($form['data']['sch_repay_arr'])||!is_numeric($form['data']['interest']))throw new RuntimeException('Successful Pay-Off form calculations missing.');
 mkdir($receiptRoot.'/views',0700,true);file_put_contents($receiptRoot.'/views/shell.blade.php',"<!doctype html><html><head>@yield('css')</head><body>@yield('content')@yield('js')</body></html>");
 $template=file_get_contents('/var/www/html/resources/views/loans/payoff.blade.php');file_put_contents($receiptRoot.'/views/form.blade.php',str_replace("@extends('layouts.app')","@extends('payoff-fixture::shell')",$template));$app['view']->addNamespace('payoff-fixture',$receiptRoot.'/views');
 $renderData=$form['data'];$renderData['errors']=new Illuminate\Support\ViewErrorBag();$renderData['secure']=false;$html=$app['view']->make('payoff-fixture::form',$renderData)->render();
 if(strpos($html,'id="frmPayoff"')===false||!preg_match('/name="ipInterest"[^>]*value="1"/',$html)||strpos($html,'name="draft_id" value="0"')===false)throw new RuntimeException('Original Pay-Off form markup/defaults not rendered.');
 unlink($receiptRoot.'/views/form.blade.php');unlink($receiptRoot.'/views/shell.blade.php');rmdir($receiptRoot.'/views');
 $db->rollBack();foreach($before as $t=>$count)if($db->table($t)->count()!=$count)throw new RuntimeException('Form GET changed financial rows.');
 $baseData=$data;
 foreach(array(0,1,2,3,4,5,6,7,8,9) as $mixedPayoff){
 $data=$baseData;$total=12.25;$user->role_id=$mixedPayoff===9?1:3;$user->branch_id=$mixedPayoff===9?2:1;
 if($mixedPayoff){$total=15.75;$data['iInterest']=2;$data['iPenalty']=0.5;$data['ipPayOffFee']=1;$data['inputTotalPayOff']=$total;
  $data['debit']=array(12.25,2,0.5,1);$data['credit']=$data['debit'];$data['parent_debit']=array(2,2,2,2);$data['parent_credit']=array(1,1,1,1);$data['d_description']=array('Principal','Interest','Penalty','Fee');$data['c_description']=$data['d_description'];}
 if(($mixedPayoff===3||$mixedPayoff===8||$mixedPayoff===9)){$data['ch_waive_panalty']=1;$data['waive_panalty_amount']=2;}
 if($mixedPayoff===2){$total+=5;$data['downpayment']=5;$data['inputTotalPayOff']=$total;foreach(array('debit','credit') as $key)$data[$key.'_down']=array(5);$data['parent_debit_down']=array(2);$data['parent_credit_down']=array(1);$data['d_description_down']=array('Synthetic downpayment');$data['c_description_down']=array('Synthetic downpayment');}
 if(($mixedPayoff>=4&&$mixedPayoff<=7)){$data['iAIR_deduct']=($mixedPayoff===4||$mixedPayoff===6)?1.25:-1.25;$data['ch_accrual_interest']=1;$data['accrual_interest_amount']=1.25;}
 $db->beginTransaction();if($mixedPayoff===2)$db->table('client_loan_accounts')->where('id',$fixtureId)->update(array('balance_downpayment'=>5));if(($mixedPayoff>=4&&$mixedPayoff<=7)){$airAccount=$db->table('coa_categories')->insertGetId(array('name'=>'Synthetic AIR','currency'=>2,'type'=>6));$incomeAccount=$db->table('coa_categories')->insertGetId(array('name'=>'Synthetic interest income','currency'=>2,'type'=>6));$db->table('client_loan_accounts')->where('id',$fixtureId)->update(array('air_id'=>$airAccount,'int_inc_id'=>$incomeAccount));}
 if(($mixedPayoff>=6&&$mixedPayoff<=7)){
  $suspendedAccount=$db->table('coa_categories')->insertGetId(array('name'=>'Synthetic suspended interest','currency'=>2,'type'=>6));
  $expenseAccount=$db->table('coa_categories')->insertGetId(array('name'=>'Exp-Bad and Doubtful Debt SYNTH-P >','currency'=>2,'type'=>6));
  $lossAccount=$db->table('coa_categories')->insertGetId(array('name'=>'(Less) Specific Loan Loses SYNTH-P >','currency'=>2,'type'=>6));
  $db->table('client_loan_accounts')->where('id',$fixtureId)->update(array('status'=>3,'sus_id'=>$suspendedAccount,'acc_key'=>'SYNTH-P>','loan_ref'=>'SYNTH-PAYOFF'));
 }
 $receiptFiles=array();if($mixedPayoff===8){$uploadSource=$receiptRoot.'/synthetic-receipt.txt';file_put_contents($uploadSource,'Synthetic waiver receipt');$receiptFiles['waive_panalty_receipt']=new Symfony\Component\HttpFoundation\File\UploadedFile($uploadSource,'synthetic-receipt.txt','text/plain',null,0,true);}
 if($mixedPayoff===9){$uploadSource=$receiptRoot.'/synthetic-receipt.png';createPayoffImageFixture($uploadSource);$receiptFiles['waive_panalty_receipt']=new Symfony\Component\HttpFoundation\File\UploadedFile($uploadSource,'synthetic-receipt.png','image/png',null,0,true);}
 $request=Illuminate\Http\Request::create('/loans/payoff','POST',$data,array(),$receiptFiles);$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);$response=(new PayoffRehearsal())->postPayOff($fixtureId);
 if($response->getStatusCode()!=302||$db->transactionLevel()!=1)throw new RuntimeException('Payoff success/transaction ownership differs.');
 $loan=$db->table('loans')->where('id',$fixtureId)->first();$account=$db->table('client_loan_accounts')->where('id',$fixtureId)->first();
 if($loan->status!=9||substr($loan->settlement_date,0,10)!=='2026-10-09'||$account->balance!=0||$account->status!=8||$db->table('drawdown_accounts')->where('id',$fixtureId)->pluck('balance')!=(50-$total))throw new RuntimeException('Payoff closure/balances differ.');
 $journal=$db->table('journal_requiry')->orderBy('id','desc')->first();$ledger=$db->table('journal_detail')->where('journal_id',$journal->id);if($ledger->sum('debit')!=($total+(($mixedPayoff>=4&&$mixedPayoff<=7)?1.25:0)+(($mixedPayoff>=6&&$mixedPayoff<=7)?3.225:0))||$ledger->sum('credit')!=($total+(($mixedPayoff>=4&&$mixedPayoff<=7)?1.25:0)+(($mixedPayoff>=6&&$mixedPayoff<=7)?3.225:0)))throw new RuntimeException('Payoff ledger unbalanced.');
 if($db->table('loan_payoff')->where('loan_id',$fixtureId)->count()!=1||$db->table('loan_payments')->where('loan_id',$fixtureId)->count()!=($mixedPayoff===2?2:1))throw new RuntimeException('Payoff/payment missing.');
 $payment=$db->table('loan_payments')->where('loan_id',$fixtureId)->first();$transaction=$db->table('transactions_requiry')->where('id',$journal->tran_id)->first();
 if($payment->invoice_number!==$journal->invoice_number||$transaction->invoice_number!==$journal->invoice_number||$payment->invoice_number==='SYNTH-PAYOFF-INPUT')throw new RuntimeException('Payoff invoice linkage differs.');
 if($payment->paid_principal!=12.25||$payment->paid_interest!=($mixedPayoff?2:0)||$payment->penalty_amount!=($mixedPayoff?0.5:0)||$transaction->fee!=($mixedPayoff?1:0)||$transaction->amount!=($mixedPayoff===2?$total-5:$total))throw new RuntimeException('Mixed payoff components differ.');
 if($mixedPayoff===2){$downSaved=$db->table('loan_payments')->where('loan_id',$fixtureId)->where('loan_repayment_type','downpayment')->first();if(!$downSaved||$downSaved->paid_principal!=5||$downSaved->invoice_number!==$journal->invoice_number||$transaction->downpayment!=5||$account->balance_downpayment!=0)throw new RuntimeException('Payoff downpayment ledger/payment/account differ.');}
 if($payment->waived_penalty!=(($mixedPayoff===3||$mixedPayoff===8||$mixedPayoff===9)?2:0))throw new RuntimeException('Payoff waived penalty record differs.');
 if(($mixedPayoff>=4&&$mixedPayoff<=7)&&$mixedPayoff<6){
  $airRows=$db->table('journal_detail')->where('journal_id',$journal->id)->where('coa_id',$airAccount);$incomeRows=$db->table('journal_detail')->where('journal_id',$journal->id)->where('coa_id',$incomeAccount);
  if($airRows->sum('debit')!=($mixedPayoff===5?1.25:0)||$airRows->sum('credit')!=($mixedPayoff===4?1.25:0)||$incomeRows->sum('debit')!=($mixedPayoff===4?1.25:0)||$incomeRows->sum('credit')!=($mixedPayoff===5?1.25:0))throw new RuntimeException('Accrued interest adjustment direction differs.');
  $savedPayoff=$db->table('loan_payoff')->where('loan_id',$fixtureId)->first();if($savedPayoff->ch_accrual_interest!=1||$savedPayoff->accrual_interest_amount!=1.25)throw new RuntimeException('Accrued interest settings differ.');
 }
 if(($mixedPayoff>=6&&$mixedPayoff<=7)){
  $airRows=$db->table('journal_detail')->where('journal_id',$journal->id)->where('coa_id',$airAccount);$suspendedRows=$db->table('journal_detail')->where('journal_id',$journal->id)->where('coa_id',$suspendedAccount);$incomeRows=$db->table('journal_detail')->where('journal_id',$journal->id)->where('coa_id',$incomeAccount);
  if($airRows->sum('debit')!=($mixedPayoff===7?1.25:0)||$airRows->sum('credit')!=($mixedPayoff===6?1.25:0)||$suspendedRows->sum('debit')!=($mixedPayoff===6?3.25:2)||$suspendedRows->sum('credit')!=($mixedPayoff===7?1.25:0)||$incomeRows->sum('credit')!=2||$incomeRows->sum('debit')!=0)throw new RuntimeException('NPL suspended interest direction differs.');
  $lossRows=$db->table('journal_detail')->where('journal_id',$journal->id)->where('coa_id',$lossAccount);$expenseRows=$db->table('journal_detail')->where('journal_id',$journal->id)->where('coa_id',$expenseAccount);
  if($lossRows->sum('debit')!=1.225||$expenseRows->sum('credit')!=1.225)throw new RuntimeException('NPL full principal provision reversal differs.');
 }
 if($mixedPayoff===8){$savedReceipt=$receiptRoot.'/data/loans/documents/'.$transaction->waive_panalty_receipt;if(!is_file($savedReceipt)||file_get_contents($savedReceipt)!=='Synthetic waiver receipt')throw new RuntimeException('Waiver receipt link/content missing.');}
 if($mixedPayoff===9){$savedReceipt=$receiptRoot.'/data/loans/documents/'.$transaction->waive_panalty_receipt;$imageInfo=is_file($savedReceipt)?getimagesize($savedReceipt):false;if(!$imageInfo||$imageInfo[0]!=8||$imageInfo[1]!=6||$imageInfo['mime']!=='image/jpeg'||pathinfo($savedReceipt,PATHINFO_EXTENSION)!=='jpg')throw new RuntimeException('Processed waiver image link/type/dimensions differ.');}
 $postedCounts=array();foreach($tables as $t)$postedCounts[$t]=$db->table($t)->count();
 $session->forget('danger');
 $replay=(new PayoffRehearsal())->postPayOff($fixtureId);if($replay->getStatusCode()!=302||$db->transactionLevel()!=1||$session->has('danger'))throw new RuntimeException('Payoff replay response/transaction differs.');
 foreach($postedCounts as $t=>$count)if($db->table($t)->count()!=$count)throw new RuntimeException('Payoff replay duplicated rows.');
 if($db->table('drawdown_accounts')->where('id',$fixtureId)->pluck('balance')!=(50-$total))throw new RuntimeException('Payoff replay deducted balance twice.');
 $session->forget('danger');$changed=$data;$changed['iPrincipal']=11.25;$changed['inputTotalPayOff']=$total-1;$request=Illuminate\Http\Request::create('/loans/payoff','POST',$changed);$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);
 (new PayoffRehearsal())->postPayOff($fixtureId);if(!$session->has('danger')||$db->transactionLevel()!=1)throw new RuntimeException('Changed payoff replay missing warning.');
 foreach($postedCounts as $t=>$count)if($db->table($t)->count()!=$count)throw new RuntimeException('Changed payoff replay modified rows.');
 foreach(array(array('downpayment'=>1),array('ch_waive_panalty'=>1,'waive_panalty_amount'=>3),array('ch_accrual_interest'=>1,'accrual_interest_amount'=>1)) as $changedTerms){
  $session->forget('danger');$request=Illuminate\Http\Request::create('/loans/payoff','POST',array_merge($data,$changedTerms));$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);
  (new PayoffRehearsal())->postPayOff($fixtureId);if(!$session->has('danger')||$db->transactionLevel()!=1)throw new RuntimeException('Changed downpayment/waiver/accrual replay missing warning.');
  foreach($postedCounts as $t=>$count)if($db->table($t)->count()!=$count)throw new RuntimeException('Changed additional payoff terms modified rows.');
 }
 if($mixedPayoff===8||$mixedPayoff===9)unlink($savedReceipt);
 $db->rollBack();foreach($before as $t=>$count)if($db->table($t)->count()!=$count)throw new RuntimeException('Payoff escaped caller rollback.');
 }
 $user->role_id=3;$user->branch_id=1;
 $data=$baseData;$data['ch_waive_panalty']=1;$data['waive_panalty_amount']=2;$uploadSource=$receiptRoot.'/failure-receipt.txt';file_put_contents($uploadSource,'Synthetic failed receipt');$upload=new Symfony\Component\HttpFoundation\File\UploadedFile($uploadSource,'failed-receipt.txt','text/plain',null,0,true);$request=Illuminate\Http\Request::create('/loans/payoff','POST',$data,array(),array('waive_panalty_receipt'=>$upload));$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);
 $interrupted=false;App\Models\JournalDetail::created(function($model)use(&$interrupted){$interrupted=true;throw new RuntimeException('Synthetic payoff interruption.');});
 $failed=false;try{(new PayoffRehearsal())->postPayOff($fixtureId);}catch(RuntimeException $e){$failed=true;}
 if(glob($receiptRoot.'/data/loans/documents/*'))throw new RuntimeException('Failed payoff retained new receipt file.');
 if(!$failed||!$interrupted||$db->transactionLevel()!=0)throw new RuntimeException('Payoff ledger interruption not exercised.');
 foreach($before as $t=>$count)if($db->table($t)->count()!=$count)throw new RuntimeException('Payoff failure persisted rows.');
 if($db->table('loans')->where('id',$fixtureId)->pluck('status')!=3||$db->table('client_loan_accounts')->where('id',$fixtureId)->pluck('balance')!=12.25||$db->table('drawdown_accounts')->where('id',$fixtureId)->pluck('balance')!=50)throw new RuntimeException('Payoff failure persisted status/balances.');
 $uploadSource=$receiptRoot.'/failure-image.png';createPayoffImageFixture($uploadSource);$upload=new Symfony\Component\HttpFoundation\File\UploadedFile($uploadSource,'failure-image.png','image/png',null,0,true);$request=Illuminate\Http\Request::create('/loans/payoff','POST',$data,array(),array('waive_panalty_receipt'=>$upload));$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);
 $failed=false;$interrupted=false;try{(new PayoffRehearsal())->postPayOff($fixtureId);}catch(RuntimeException $e){$failed=true;}
 if(!$failed||!$interrupted||$db->transactionLevel()!=0||glob($receiptRoot.'/data/loans/documents/*'))throw new RuntimeException('Failed image payoff did not remove processed receipt.');
 foreach($before as $t=>$count)if($db->table($t)->count()!=$count)throw new RuntimeException('Image receipt failure persisted rows.');
  App\Models\JournalDetail::flushEventListeners();
 $db->table('client_loan_accounts')->where('id',$fixtureId)->update(array('balance_downpayment'=>5));
 $data=$baseData;$data['downpayment']=5;$data['inputTotalPayOff']=17.25;$data['debit_down']=array(5);$data['credit_down']=array(5);$data['parent_debit_down']=array(2);$data['parent_credit_down']=array(1);$data['d_description_down']=array('Synthetic downpayment');$data['c_description_down']=array('Synthetic downpayment');
 $request=Illuminate\Http\Request::create('/loans/payoff','POST',$data);$request->setSession($session);Illuminate\Support\Facades\Request::swap($request);
 $lateInterrupted=false;App\Models\LoanPayments::created(function($model)use(&$lateInterrupted){if($model->loan_repayment_type==='downpayment'){$lateInterrupted=true;throw new RuntimeException('Synthetic late downpayment interruption.');}});
 $failed=false;try{(new PayoffRehearsal())->postPayOff($fixtureId);}catch(RuntimeException $e){$failed=true;}
 if(!$failed||!$lateInterrupted||$db->transactionLevel()!=0)throw new RuntimeException('Late payoff payment interruption not exercised.');
 foreach($before as $t=>$count)if($db->table($t)->count()!=$count)throw new RuntimeException('Late payoff failure persisted rows.');
 $restored=$db->table('client_loan_accounts')->where('id',$fixtureId)->first();if($restored->balance!=12.25||$restored->balance_downpayment!=5||$restored->status!=GENERAL_LC_STATUS||$db->table('drawdown_accounts')->where('id',$fixtureId)->pluck('balance')!=50||$db->table('loans')->where('id',$fixtureId)->pluck('status')!=3)throw new RuntimeException('Late payoff failure persisted closure/balances.');
 $app->instance('path.public',$originalPublicPath);cleanupPayoffReceiptFixture($receiptRoot);if(is_dir($receiptRoot))throw new RuntimeException('Receipt fixture cleanup incomplete.');
 echo json_encode(array('status'=>'PASSED','full_http_kernel_permission_denial'=>true,'full_http_kernel_encrypted_session_csrf_denial_and_valid_posting'=>true,'original_named_route_middleware_guest_and_missing_permission_denied'=>true,'original_permission_grant_reaches_real_controller_and_rollback'=>true,'branch_scoped_get_post_and_missing_branch_denied'=>true,'same_branch_and_admin_cross_branch_posting'=>true,'original_form_template_rendered_in_isolated_shell'=>true,'successful_no_draft_get_calculations_and_original_defaults'=>true,'get_draft_wrong_loan_owner_completed_missing_and_inactive_loan_checked'=>true,'original_dedicated_payoff_controller'=>true,'downpayment_separate_payment_ledger_balance_replay_and_rollback'=>true,'npl_signed_air_suspended_interest_and_provision_reversal'=>true,'general_air_positive_negative_balanced_directions_and_rollback'=>true,'nonnumeric_air_adjustment_rejected'=>true,'saved_accrual_flags_and_amount'=>true,'png_receipt_processed_jpeg_link_and_failure_cleanup'=>true,'waiver_receipt_file_link_and_failed_posting_cleanup'=>true,'waived_penalty_record_and_replay'=>true,'changed_downpayment_waiver_accrual_replay_warned'=>true,'negative_waiver_invalid_flag_rejected'=>true,'downpayment_missing_ledger_rejected'=>true,'loan_account_closure_and_settlement'=>true,'balanced_ledger'=>true,'payment_and_payoff_records'=>true,'unbalanced_wrong_total_negative_missing_account_and_malformed_ledger_rejected'=>true,'invalid_negative_nonnumeric_and_total_rejected'=>true,'changed_amount_duplicate_warning_no_writes'=>true,'interest_penalty_fee_components_and_total'=>true,'generated_invoice_shared_payment_journal_transaction'=>true,'replay_no_duplicate_or_double_debit'=>true,'caller_rollback'=>true,'standalone_late_downpayment_insert_failure_atomic'=>true,'standalone_ledger_interruption_atomic'=>true,'scope'=>'Synthetic principal-only dedicated Pay-Off controller; Mixed interest/penalty/fee amounts and sequential replay tested; waivers, concurrent submissions and route permissions remain pending.'),JSON_PRETTY_PRINT)."\n";
}catch(Exception $e){if(isset($receiptRoot))cleanupPayoffReceiptFixture($receiptRoot);while($db->transactionLevel())$db->rollBack();fwrite(fopen('php://stderr','w'),'Payoff test failed: '.($e instanceof RuntimeException?$e->getMessage():get_class($e))."\n");exit(1);}

