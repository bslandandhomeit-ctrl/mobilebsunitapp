<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(array('unified_posting.database.password'=>'local-only-unified-posting-reader'));
function postingCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
try {
 $repo=App\Services\UnifiedPostingReport::configured();
 $filters=array('from'=>'1900-01-01','to'=>'2099-12-31');
 $db=Illuminate\Support\Facades\DB::connection('unified_posting');
 $expected=$db->table('transactions_posting')->where('status',1)->where('posting_date','>=',$filters['from'])->where('posting_date','<=',$filters['to'])->count();
 postingCheck($repo->query($filters)->count()===$expected,'Joined report duplicates or drops postings');
 postingCheck($repo->query($filters,999999999)->count()===0,'Branch filter failed');
 $sample=$repo->query($filters)->first();postingCheck((bool)$sample,'No posting fixture');
 $stored=$db->table('transactions_posting')->where('id',$sample->id)->first();
 foreach(array('amount','total_principal','total_interest','total_fee','other_fee','total_penalty') as $field)postingCheck((string)$sample->$field===(string)$stored->$field,'Stored money differs');
 if($sample->accounting_branch_id){$scoped=$repo->query($filters,$sample->accounting_branch_id)->take(20)->get();foreach($scoped as $row)postingCheck((string)$row->accounting_branch_id===(string)$sample->accounting_branch_id,'Branch leakage');}
 $bad=false;try{$repo->query(array('from'=>'2026-02-30','to'=>'2026-03-01'));}catch(InvalidArgumentException $e){$bad=true;}postingCheck($bad,'Invalid date accepted');
 $denied=false;try{$db->statement('UPDATE transactions_posting SET amount=amount WHERE id=0');}catch(Exception $e){$denied=true;}postingCheck($denied,'Financial write allowed');
 postingCheck(count($repo->companies())>0,'Company filter empty');
 Illuminate\Support\Facades\Auth::setUser(App\Models\User::where('username','admin')->firstOrFail());
 config(array('unified_posting.enabled'=>true));
 $html=(new App\Http\Controllers\TellerController)->list_posting_transaction()->render();
 postingCheck(strpos($html,'Select Project')!==false,'Original report did not render');
 $export=view('exports.unified_posting_transactions',array('teller_transaction'=>array($sample),'from_date'=>$filters['from'],'to_date'=>$filters['to']))->render();
 postingCheck(strpos($export,'Posting ID')!==false,'Export view did not render');
 echo json_encode(array('status'=>'PASSED','posting_count'=>$expected,'one_row_per_posting'=>true,'stored_money_preserved'=>true,'branch_scope'=>true,'date_validation'=>true,'writes_denied'=>true,'activation'=>'Local unified report controller enabled; HTML render verified. Actual XLSX/CSV downloads verified separately; middleware and broader accounting reports pending.'),JSON_PRETTY_PRINT)."\n";
}catch(Exception $e){fwrite(fopen('php://stderr','w'),'Posting report test failed: '.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine()."\n");exit(1);}
