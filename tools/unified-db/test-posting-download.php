<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(array('unified_posting.database.password'=>'local-only-unified-posting-reader','unified_posting.enabled'=>true));
$repo=App\Services\UnifiedPostingReport::configured();
$db=Illuminate\Support\Facades\DB::connection('unified_posting');
$day=$db->table('transactions_posting as t')->join('drawdown_accounts as d','d.id','=','t.drawdown_acc_id')->where('t.status',1)->where('t.posting_date','>=','2000-01-01')->where('t.posting_date','<=','2099-12-31')->select('t.posting_date',Illuminate\Support\Facades\DB::raw('COUNT(*) AS row_count'))->groupBy('t.posting_date')->havingRaw('COUNT(DISTINCT d.branch)>1')->orderBy('row_count','desc')->first();
if(!$day)throw new RuntimeException('No multi-branch export fixture.');
$date=substr($day->posting_date,0,10);$fixture=$repo->query(array('from'=>$date,'to'=>$date))->first();
if(!$fixture)throw new RuntimeException('No report export fixture.');$branch=getenv('POSTING_BRANCH_SCOPE')?$fixture->accounting_branch_id:null;
$user=App\Models\User::where('username','admin')->firstOrFail();
if(getenv('POSTING_BRANCH_SCOPE')){$user->role_id=3;$user->branch_id=$branch;}
Illuminate\Support\Facades\Auth::setUser($user);
$format=getenv('POSTING_DOWNLOAD_FORMAT');
if($format==='metadata'){
 $rows=$repo->query(array('from'=>$date,'to'=>$date),$branch)->get();$expected=array();
 foreach($rows as $r)$expected[]=array((string)$r->id,(string)$r->amount,(string)$r->total_principal,(string)$r->total_interest);
 echo json_encode(array('rows'=>$expected,'branch_scope'=>(bool)getenv('POSTING_BRANCH_SCOPE')));exit;
}
if(!in_array($format,array('xlsx','csv'),true))throw new RuntimeException('Invalid test export format.');
Illuminate\Support\Facades\Request::swap(Illuminate\Http\Request::create('/teller/posting_transaction','GET',array('t_from'=>$date,'t_to'=>$date,'is_excel'=>$format==='xlsx'?1:0,'is_csv'=>$format==='csv'?1:0)));
(new App\Http\Controllers\TellerController)->list_posting_transaction();
throw new RuntimeException('Original download did not finish.');
