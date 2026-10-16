<?php
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(array('unified_posting.database.password'=>'local-only-unified-posting-reader'));App\Services\UnifiedPostingReport::configured();
function masterCheck($ok,$label){if(!$ok)throw new RuntimeException($label);}
try{
 $db=Illuminate\Support\Facades\DB::connection('unified_posting');
 foreach(array('App\Models\Project'=>'projects','App\Models\UnitType'=>'unit_types','App\Models\Unit'=>'units') as $class=>$table){$model=new $class;$model->setConnection('unified_posting');$model->setTable('beloan_compat_'.$table);$record=$model->newQuery()->first();masterCheck((bool)$record,'Original model cannot read projection');}
 $projects=$db->table('beloan_compat_projects')->whereNotNull('accounting_branch_id')->get();masterCheck(count($projects)===20,'Explicit branch projection lost projects');
 foreach($projects as $project)masterCheck($db->table('project_accounting_branch_links')->where('project_id',$project->id)->where('branch_id',$project->accounting_branch_id)->exists(),'Branch projection is not explicit');
 $pm=new App\Models\Project;$pm->setConnection('unified_posting');$pm->setTable('beloan_compat_projects');
 foreach($pm->newQuery()->whereNotNull('source_project_id')->with('Banks','Representative')->get() as $p){
  foreach(array('Banks'=>array('banks','bank_account'),'Representative'=>array('representatives','sale_representative_id')) as $name=>$reference){
   $expected=$db->table('beloan_compat_'.$reference[0])->where('id',$p->{$reference[1]})->first();$related=$p->$name;
   masterCheck((bool)$related===(bool)$expected,'Historical project reference existence differs');
   if($related){masterCheck($related->id==$p->{$reference[1]}&&$related->getConnectionName()==='unified_posting','Historical project reference identity or connection differs');$blocked=false;try{$related->save();}catch(LogicException $e){$blocked=true;}masterCheck($blocked,'Project reference write allowed');}
  }
 }
 foreach($pm->newQuery()->whereNotNull('source_project_id')->with('projectBank','loans','product')->get() as $p){
  foreach(array('projectBank'=>'dealer_banks','loans'=>'loan_dealers','product'=>'products') as $name=>$table){$expected=$db->table('beloan_compat_'.$table)->where('dealer_id',$p->source_project_id)->count();masterCheck(count($p->$name)===$expected&&$p->$name()->count()===$expected,'Historical project relationship count changed');foreach($p->$name as $child)masterCheck($child->getConnectionName()==='unified_posting','Historical project child escaped unified DB');}
 }
 foreach($pm->newQuery()->with('bank')->get() as $p){
  $expected=$p->source_project_id?$db->table('beloan_compat_dealer_banks')->where('dealer_id',$p->source_project_id)->count():0;
  masterCheck(count($p->bank)===$expected&&$p->bank()->count()===$expected,'Project bank pivot count changed');
  foreach($p->bank as $bank)masterCheck($bank->getConnectionName()==='unified_posting'&&$bank->pivot->project_id==$p->id,'Project bank pivot identity or connection differs');
 }
 $originalProject=new App\Models\Project;masterCheck($originalProject->Banks()->getRelated()->getTable()==='banks'&&$originalProject->Representative()->getRelated()->getTable()==='representatives','Default original project relations changed');
 $unit=new App\Models\Unit;$unit->setConnection('unified_posting');$unit->setTable('beloan_compat_units');$record=$unit->newQuery()->with('UnitType.Projects.Company')->whereHas('UnitType',function($q){$q->whereHas('Projects',function($p){$p->whereNotNull('accounting_branch_id');});})->first();
 masterCheck((bool)$record&&$record->UnitType&&$record->UnitType->Projects&&$record->UnitType->Projects->Company,'Nested original relations failed');
 masterCheck($record->UnitType->getConnectionName()==='unified_posting'&&$record->UnitType->Projects->Company->getConnectionName()==='unified_posting','Relation escaped unified connection');
 masterCheck((int)$record->UnitType->Projects->Company->id===(int)$record->UnitType->Projects->accounting_branch_id,'Company relation used organisation ID');
 masterCheck($record->getTable()==='beloan_compat_units','Loaded model lost read-view identity');
 masterCheck($record->UnitType()->first()->Projects()->first()->Company()->first()->getConnectionName()==='unified_posting','Lazy relationship escaped unified connection');
 masterCheck($record->UnitType->units()->count()>0,'Reverse unit relationship failed');
 $original=new App\Models\Unit;masterCheck($original->getTable()==='units'&&get_class($original->newQuery())==='Illuminate\Database\Eloquent\Builder','Original default behavior changed');
 foreach(array('App\Models\Loan'=>'loans','App\Models\DrawdownAccounts'=>'drawdown_account','App\Models\ClientLoanAccounts'=>'client_loan_accounts') as $class=>$table){$model=new $class;$model->setConnection('unified_posting');$model->setTable('beloan_compat_'.$table);$customer=$model->newQuery()->where('client_id','>',0)->with('client')->first();masterCheck((bool)$customer&&$customer->client&&$customer->client->getConnectionName()==='unified_posting','Finance customer relationship escaped unified DB');}
 $model=new App\Models\Loan;$model->setConnection('unified_posting');$model->setTable('beloan_compat_loans');$loan=$model->newQuery()->whereHas('units',function($q){})->whereHas('projects',function($q){})->whereHas('branch',function($q){})->with('units','projects','branch')->first();masterCheck((bool)$loan&&$loan->units&&$loan->projects&&$loan->branch,'Finance shared references did not resolve');
 foreach(array('sale_persons'=>'sale_person','sale_commission_persons'=>'saleperson_id','SaleRepresentative'=>'sale_person','sale_person_parent_lavel1'=>'sale_person_parent_l1','sale_person_parent_lavel2'=>'sale_person_parent_l2','dealer'=>'dealer_id') as $name=>$key){
  if(!Illuminate\Support\Facades\Schema::connection('unified_posting')->hasColumn('beloan_compat_loans',$key)){masterCheck(in_array($key,array('saleperson_id','sale_person_parent_l1','sale_person_parent_l2')),'Unexpected missing loan reference field');continue;}
  $linked=$model->newQuery()->where($key,'>',0)->with($name)->first();
  if(!$linked)continue;
  $table=$name==='dealer'?'dealers':'saleperson';$expected=$db->table('beloan_compat_'.$table)->where('id',$linked->$key)->first();$related=$linked->$name;
  masterCheck((bool)$related===(bool)$expected,'Loan historical reference existence differs');
  if($related){masterCheck($related->id==$linked->$key&&$related->getConnectionName()==='unified_posting','Loan historical reference identity changed');masterCheck($linked->$name()->count()===1,'Loan lazy reference differs');}
 }
 $cl=new App\Models\LoanCollateral;$cl->setConnection('unified_posting');$cl->setTable('beloan_compat_collateral');
 $collateral=$cl->newQuery()->whereHas('loan',function($q){})->with('loan.collateral')->first();
 masterCheck((bool)$collateral||$db->table('beloan_compat_collateral')->count()===0,'Collateral has no resolved loan fixture');
 if($collateral){masterCheck($collateral->loan->getConnectionName()==='unified_posting'&&count($collateral->loan->collateral)===$db->table('beloan_compat_collateral')->where('loan_id',$collateral->loan_id)->count(),'Loan collateral count or reverse connection differs');$blocked=false;try{$collateral->save();}catch(LogicException $e){$blocked=true;}masterCheck($blocked,'Collateral read adapter permits writes');}
 $sp=new App\Models\SalePerson;$sp->setConnection('unified_posting');$sp->setTable('beloan_compat_saleperson');
 foreach($sp->newQuery()->with('parent','SaleTeam')->get() as $person){if($person->parent)masterCheck($person->parent->id==$person->parent_id&&$person->parent->getConnectionName()==='unified_posting','Sales-person parent identity changed');if($person->SaleTeam)masterCheck($person->SaleTeam->id==$person->sale_team_id&&$person->SaleTeam->source_system==='beloan','Sales team actor source changed');}
 $stored=$db->table('loans')->where('id',$loan->id)->first();masterCheck((string)$loan->final_price===(string)$stored->final_price,'Loan amount changed through projection');
 $sample=$db->table('loan_payments')->where('loan_id','>',0)->first();$history=$model->newQuery()->with('payment','schedule')->find($sample->loan_id);masterCheck((bool)$history,'Payment loan missing');
 masterCheck(count($history->payment)===$db->table('loan_payments')->where('loan_id',$history->id)->count(),'Saved payment count differs');masterCheck(count($history->schedule)===$db->table('repayment_schedule')->where('loan_id',$history->id)->count(),'Saved schedule count differs');
 foreach($history->payment as $payment){masterCheck($payment->getTable()==='beloan_compat_loan_payments'&&$payment->loan->id===$history->id,'Payment reverse relation escaped');$raw=$db->table('loan_payments')->where('id',$payment->id)->first();masterCheck((string)$payment->paid_principal===(string)$raw->paid_principal,'Payment amount changed');}
 if(count($history->schedule)){ $schedule=$history->schedule[0];masterCheck($schedule->getTable()==='beloan_compat_repayment_schedule','Schedule escaped unified read');masterCheck($schedule->payment()->count()===$db->table('loan_payments')->where('loan_id',$history->id)->where('payment_month',$schedule->no)->count(),'Schedule installment match changed');}
 $draw=new App\Models\DrawdownAccounts;$draw->setConnection('unified_posting');$draw->setTable('beloan_compat_drawdown_account');$account=$draw->newQuery()->whereHas('coa',function($q){})->whereHas('currency_tbl',function($q){})->whereHas('company_branch',function($q){})->with('coa.currency_i','currency_tbl','company_branch')->first();masterCheck((bool)$account,'Drawdown fixture absent');masterCheck((bool)$account->coa,'COA eager link failed');masterCheck((bool)$account->currency_tbl,'Currency eager link failed');masterCheck((bool)$account->company_branch,'Branch eager link failed');
 masterCheck($account->coa->getConnectionName()==='unified_posting'&&$account->currency_tbl->getConnectionName()==='unified_posting','Accounting relation escaped unified DB');
 $raw=$db->table('drawdown_accounts')->where('id',$account->id)->first();masterCheck((string)$account->balance===(string)$raw->balance,'Drawdown balance changed');masterCheck((string)$account->company_branch->id===(string)$account->branch,'Drawdown branch used wrong identity');
 $acc=new App\Models\ClientLoanAccounts;$acc->setConnection('unified_posting');$acc->setTable('beloan_compat_client_loan_accounts');$acc=$acc->newQuery()->whereHas('currencies',function($q){})->whereHas('get_branch',function($q){})->with('currencies','get_branch')->first();masterCheck((bool)$acc&&$acc->currencies&&$acc->get_branch,'Loan account currency/branch failed');
 foreach(array('transaction','transaction_req','transaction_reqLastBalance','transaction_req_last') as $method){
  $relation=$history->$method();masterCheck($relation->getRelated()->getConnectionName()==='unified_posting'&&$relation->getRelated()->getTable()==='beloan_compat_transactions_requiry','Loan transaction relation escaped: '.$method);
  masterCheck($relation->count()===$db->table('transactions_requiry')->where('loan_id',$history->id)->count(),'Loan transaction history differs: '.$method);
 }
 foreach(array(array($history,'accrued_journal_detail','air_id'),array($history,'coa_journal_detail','coa_id'),array($account,'journal_detail','coa_id'),array($acc,'journal_detail_coa','coa_id'),array($acc,'journal_detail_air','air_id'),array($acc,'journal_detail_int_inc','int_inc_id'),array($acc,'journal_detail_sus','sus_id')) as $check){
  $owner=$check[0];$method=$check[1];$key=$check[2];$relation=$owner->$method();
  masterCheck($relation->getRelated()->getConnectionName()==='unified_posting'&&$relation->getRelated()->getTable()==='beloan_compat_journal_detail','Journal relation escaped: '.$method);
  $expected=$owner->$key===null?0:$db->table('journal_detail')->where('coa_id',$owner->$key)->count();masterCheck($relation->count()===$expected,'Account journal history differs: '.$method);
 }
 $legacyRelation=(new App\Models\Loan())->transaction();masterCheck($legacyRelation->getRelated()->getTable()==='transactions_requiry'&&$legacyRelation->getRelated()->getConnectionName()!=='unified_posting','Original default transaction behavior changed');
 foreach(array('user'=>'user_id','co_user'=>'co','disburse_user'=>'disburse_byuserid') as $method=>$key){
  $relation=$history->$method();masterCheck($relation->getRelated()->getConnectionName()==='unified_posting'&&$relation->getRelated()->getTable()==='beloan_compat_actor_reads','Loan actor escaped: '.$method);
  $actor=$relation->first();$rawActor=$db->table('beloan_compat_actor_reads')->where('id',$history->$key)->first();masterCheck((bool)$actor===(bool)$rawActor,'Loan actor identity differs');
  if($actor){masterCheck((string)$actor->id===(string)$history->$key&&$actor->source_system==='beloan','Loan actor confused source identities');masterCheck(!array_key_exists('password',$actor->getAttributes())&&!array_key_exists('remember_token',$actor->getAttributes()),'Actor projection exposed credentials');}
 }
 masterCheck($history->penalty_record()->getRelated()->getTable()==='beloan_compat_penalty_record'&&$history->penalty_record()->getRelated()->getConnectionName()==='unified_posting','Penalty history escaped');
 masterCheck($history->penalty_record()->count()===$db->table('penalty_record')->where('loan_id',$history->id)->count(),'Penalty history count differs');
 masterCheck(get_class((new App\Models\Loan())->user()->getRelated())==='App\Models\User','Original user/auth relation changed');
 $projectRelation=$acc->projects();masterCheck($projectRelation->getRelated()->getTable()==='beloan_compat_projects'&&$projectRelation->getRelated()->getConnectionName()==='unified_posting','Loan account project escaped');
 $accountProject=$projectRelation->first();$canonicalProject=$db->table('projects')->where('id',$acc->project_id)->first();masterCheck((bool)$accountProject===(bool)$canonicalProject,'Loan account project existence differs');if($accountProject)masterCheck($accountProject->id==$acc->project_id,'Loan account project identity differs');
 foreach(array('loanDealer'=>'loan_dealers','commission_withdrawal_transaction'=>'commission_withdrawal_transaction') as $method=>$table){
  $relation=$history->$method();masterCheck($relation->getRelated()->getConnectionName()==='unified_posting'&&$relation->getRelated()->getTable()==='beloan_compat_'.$table,'Historical auxiliary relation escaped');
  if($method==='loanDealer'){$dealer=$relation->first();$rawDealer=$db->table($table)->where('loan_id',$history->id)->first();masterCheck((bool)$dealer===(bool)$rawDealer,'Historical dealer link differs');if($dealer)masterCheck($dealer->loan&&$dealer->loan->id==$history->id&&$dealer->loan->getConnectionName()==='unified_posting','Dealer reverse loan escaped');}
  else masterCheck($relation->count()===$db->table($table)->where('loan_id',$history->id)->count(),'Historical commission count differs');
 }
 $bankLink=new App\Models\DealerBanks();$bankLink->setConnection('unified_posting');$bankLink->setTable('beloan_compat_dealer_banks');
 foreach($bankLink->newQuery()->with('bank','dealer')->get() as $link){
  foreach(array('bank'=>'bank_id','dealer'=>'dealer_id') as $method=>$key){$related=$link->$method;$table=$method==='bank'?'banks':'dealers';$rawRelated=$db->table('beloan_reference_'.$table)->where('id',$link->$key)->first();masterCheck((bool)$related===(bool)$rawRelated,'Bank/dealer reference differs');if($related)masterCheck($related->id==$link->$key&&$related->getConnectionName()==='unified_posting'&&$related->getTable()==='beloan_compat_'.$table,'Bank/dealer link escaped');}
 }
 $loanDealer=new App\Models\LoanDealer();$loanDealer->setConnection('unified_posting');$loanDealer->setTable('beloan_compat_loan_dealers');
 foreach(array('bank'=>'banks','dealer'=>'dealers') as $method=>$table)masterCheck($loanDealer->$method()->getRelated()->getTable()==='beloan_compat_'.$table&&$loanDealer->$method()->getRelated()->getConnectionName()==='unified_posting','Loan dealer counterpart escaped');
 foreach(array('product'=>'products','product_type'=>'product_type','PaymentOptions'=>'payment_options') as $method=>$table){$relation=$history->$method();masterCheck($relation->getRelated()->getTable()==='beloan_compat_'.$table&&$relation->getRelated()->getConnectionName()==='unified_posting','Loan lookup escaped: '.$method);}
 $product=new App\Models\Product();$product->setConnection('unified_posting');$product->setTable('beloan_compat_products');
 foreach(array('category'=>'product_category','brand'=>'product_brand','dealer'=>'dealers','prod_prod_type'=>'product_products_type','product_types'=>'product_type') as $method=>$table)masterCheck($product->$method()->getRelated()->getTable()==='beloan_compat_'.$table&&$product->$method()->getRelated()->getConnectionName()==='unified_posting','Product classification escaped');
 masterCheck($product->newQuery()->count()===$db->table('beloan_reference_products')->count(),'Original product count differs');
 $option=new App\Models\PaymentOption();$option->setConnection('unified_posting');$option->setTable('beloan_compat_payment_options');
 foreach($option->newQuery()->with('UnitType')->get() as $row){
  $mapping=$db->table('source_record_map')->where('source_system','beloan')->where('source_table','tb_unit_types')->where('source_id',(string)$row->source_unit_type_id)->first();
  masterCheck($row->unit_type_id==($mapping?$mapping->target_id:null),'Payment option unit type map differs');
  if($row->UnitType)masterCheck($row->UnitType->id==$row->unit_type_id&&$row->UnitType->getConnectionName()==='unified_posting','Payment option linked original unit identity');
 }
 $borrower=new App\Models\CoBorrower();$borrower->setConnection('unified_posting');$borrower->setTable('beloan_compat_co_borrowers');
 masterCheck($borrower->newQuery()->count()===$db->table('beloan_reference_co_borrowers')->count(),'Co-borrower history count differs');
 $borrower=$borrower->newQuery()->whereHas('Clients',function($q){})->with('Clients')->first();masterCheck((bool)$borrower&&$borrower->Clients->id==$borrower->customer_id&&$borrower->Clients->getConnectionName()==='unified_posting','Co-borrower customer link escaped');
 $sourceAccount=$db->table('customer_accounts')->where('source_system','beloan')->where('source_id',$borrower->source_customer_id)->first();masterCheck($sourceAccount&&$sourceAccount->id==$borrower->customer_id,'Co-borrower canonical account differs');
 masterCheck($history->co_borrowers()->getRelated()->getTable()==='beloan_compat_co_borrowers'&&$history->co_borrowers()->getRelated()->getConnectionName()==='unified_posting','Loan co-borrower relation escaped');
 if(!defined('ROUND_NUM'))require '/var/www/html/public/global.php';
 $calendarLoan=new App\Models\Loan();$calendarLoan->setConnection('unified_posting');$calendarLoan->setTable('beloan_compat_loans');$calendarLoan->start_date='2026-01-01';$calendarLoan->loan_duration=11;
 $holidays=$calendarLoan->holiday;$expectedHolidays=$db->table('beloan_reference_holiday')->whereBetween('holiday_date',array('2026-01-01','2027-01-01'))->orderBy('holiday_date')->lists('holiday_date');
 masterCheck((array)$holidays===(array)$expectedHolidays,'Unified holiday calendar differs');
 $blocked=false;try{$record->save();}catch(LogicException $e){$blocked=true;}masterCheck($blocked,'Read model save was allowed');
 $denied=false;try{$db->statement('UPDATE beloan_compat_units SET price=price WHERE id=0');}catch(Exception $e){$denied=true;}masterCheck($denied,'Read projection allowed price writes');
 $journalModel=new App\Models\JournalDetail;$journalModel->setConnection('unified_posting');$journalModel->setTable('beloan_compat_journal_detail');$line=$journalModel->newQuery()->whereHas('journal',function($q){})->whereHas('account',function($q){})->whereHas('branch',function($q){})->with('journal.detail','account','branch')->first();masterCheck((bool)$line&&$line->journal&&$line->account&&$line->branch,'Journal relationships failed');
 $raw=$db->table('journal_detail')->where('id',$line->id)->first();foreach(array('debit','credit','b_debit','b_credit') as $field)masterCheck((string)$line->$field===(string)$raw->$field,'Stored ledger amount changed');
 masterCheck(count($line->journal->detail)===$db->table('journal_detail')->where('journal_id',$line->journal_id)->count(),'Journal detail count changed');masterCheck($line->branch->branch_code===$line->branch_code,'Journal branch code mismatch');
 $j=new App\Models\JournalRequiry;$j->setConnection('unified_posting');$j->setTable('beloan_compat_journal_requiry');$j=$j->newQuery()->whereHas('user',function($q){})->with('user','audit.audit1','audit.audit2')->first();masterCheck((bool)$j&&$j->user&&$j->user->source_system==='beloan','Journal actor source mismatch');masterCheck((int)$j->user->id===(int)$j->user_id,'Legacy actor reference changed');
 masterCheck(!array_key_exists('password',$j->user->getAttributes())&&!array_key_exists('remember_token',$j->user->getAttributes()),'Actor projection contains authentication secrets');
 foreach($j->audit as $audit){masterCheck($audit->getConnectionName()==='unified_posting','Audit escaped unified connection');if($audit->audit1)masterCheck($audit->audit1->source_system==='beloan','Audit actor collision');}
 $trans=new App\Models\TransactionsRequiry;$trans->setConnection('unified_posting');$trans->setTable('beloan_compat_transactions_requiry');$trans=$trans->newQuery()->whereHas('loan',function($q){})->whereHas('journal',function($q){})->with('loan','journal.transaction')->first();masterCheck((bool)$trans&&$trans->loan&&count($trans->journal)>0,'Transaction loan/journal links failed');
 foreach($trans->journal as $entry)masterCheck($entry->transaction&&$entry->transaction->id===$trans->id&&$entry->transaction->getConnectionName()==='unified_posting','Journal transaction reverse link escaped');$raw=$db->table('transactions_requiry')->where('id',$trans->id)->first();masterCheck((string)$trans->amount===(string)$raw->amount,'Transaction amount changed');
 $fee=$db->table('fee_charges')->where('loan_id','>',0)->first();$fees=$model->newQuery()->with('feecharge','costfee')->find($fee->loan_id);masterCheck((bool)$fees&&count($fees->feecharge)===$db->table('fee_charges')->where('loan_id',$fee->loan_id)->count(),'Loan fee history differs');foreach($fees->feecharge as $charge){$raw=$db->table('fee_charges')->where('id',$charge->id)->first();masterCheck($charge->getTable()==='beloan_compat_fee_charges'&&(string)$charge->charge_amount===(string)$raw->charge_amount&&(string)$charge->waived_amount===(string)$raw->waived_amount,'Stored fee values changed');}
 masterCheck(count($fees->costfee)===$db->table('loan_cost_fees')->where('loan_id',$fee->loan_id)->count(),'Saved cost history differs');
 foreach(array('App\Models\LoanApproval'=>'loan_approval','App\Models\LoanClose'=>'loan_close','App\Models\LoanWriteOff'=>'loan_write_off','App\Models\LoanPayOff'=>'loan_payoff') as $class=>$table){$state=new $class;$state->setConnection('unified_posting');$state->setTable('beloan_compat_'.$table);$state=$state->newQuery()->whereHas('loan',function($q){})->with('loan')->first();if(!$state){masterCheck($db->table($table)->count()===0,'Nonempty workflow table has no verified loan fixture');continue;}masterCheck($state->loan->getConnectionName()==='unified_posting'&&(int)$state->loan->id===(int)$state->loan_id,'Workflow loan link changed');$raw=(array)$db->table($table)->where('id',$state->id)->first();foreach($state->getAttributes() as $field=>$value)masterCheck((string)$value===(string)$raw[$field],'Saved workflow field changed');}
 foreach(array('AccountCustomer'=>array('App\\Models\\AccountCustomer','acc_customer',2),'vendor'=>array('App\\Models\\Vendor','acc_vendor',1)) as $relation=>$spec){
  $header=new App\Models\JournalRequiry;$header->setConnection('unified_posting');$header->setTable('beloan_compat_journal_requiry');
  $header=$header->newQuery()->where('ref_name_type',$spec[2])->whereHas($relation,function($q){})->with($relation)->first();
  $counter=new $spec[0];$counter->setConnection('unified_posting');$counter->setTable('beloan_compat_'.$spec[1]);
  foreach($counter->newQuery()->get() as $party){masterCheck($party->JournalRequiryWhere()->count()===$db->table('beloan_compat_journal_requiry')->where('ref_name_id',$party->id)->where('ref_name_type',$spec[2])->count(),'Typed accounting journal count differs');masterCheck($party->JournalRequiry()->getRelated()->getConnectionName()==='unified_posting','Accounting reverse journal escaped unified connection');}
  if($header){$party=$header->$relation;masterCheck($party->id==$header->ref_name_id&&$party->getConnectionName()==='unified_posting','Journal counterpart identity differs');$blocked=false;try{$party->save();}catch(LogicException $e){$blocked=true;}masterCheck($blocked,'Accounting counterpart read permits writes');}
  $original=new $spec[0];masterCheck($original->getTable()===$spec[1]&&$original->JournalRequiry()->getRelated()->getTable()==='journal_requiry','Original accounting default changed');
 }
 $accountModel=new App\Models\ClientLoanAccounts;$accountModel->setConnection('unified_posting');$accountModel->setTable('beloan_compat_client_loan_accounts');
 $sample=$accountModel->newQuery()->whereHas('schedule',function($q){})->with('schedule')->take(5)->get();masterCheck(count($sample)>0,'No loan-account schedule fixture');
 foreach($sample as $a){$expected=$db->table('beloan_compat_repayment_schedule as s')->join('beloan_compat_loans as l','l.id','=','s.loan_id')->where('l.loan_account_id',$a->id)->count();masterCheck(count($a->schedule)===$expected&&$a->schedule()->count()===$expected,'Account schedule count differs');foreach($a->schedule as $item){$l=$db->table('beloan_compat_loans')->where('id',$item->loan_id)->first();masterCheck($l&&$l->loan_account_id==$a->id&&$item->getConnectionName()==='unified_posting','Schedule belongs to unrelated account');}}
 masterCheck(get_class((new App\Models\ClientLoanAccounts)->schedule())==='Illuminate\Database\Eloquent\Relations\HasMany','Original schedule default changed');
 echo json_encode(array('status'=>'PASSED','original_models_read_common_fields'=>true,'explicit_project_branches'=>20,'writes_denied'=>true,'limits'=>'Connection and table are explicitly selected for this read test. Original relations, write paths and default connection are not switched.'),JSON_PRETTY_PRINT)."\n";
}catch(Exception $e){fwrite(fopen('php://stderr','w'),'Projection test failed: '.($e instanceof RuntimeException?$e->getMessage():get_class($e)).' at '.basename($e->getFile()).':'.$e->getLine()."\n");exit(1);}
