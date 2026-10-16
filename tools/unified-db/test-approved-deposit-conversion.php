<?php
require '/app/vendor/autoload.php';
$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
function convertCheck($ok,$label){if(!$ok)throw new RuntimeException('Conversion check failed: '.$label);}
try {
 $pdo=new PDO('mysql:host=host.docker.internal;port=3309;dbname=unified_deposit_conversion_test;charset=utf8mb4','unified_deposit_writer','local-only-unified-deposit-writer',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db=new Illuminate\Database\MySqlConnection($pdo,'unified_deposit_conversion_test','');
 $converter=new App\Services\UnifiedApprovedDepositConverter($db);
 $unit=$db->table('units')->first();$actor=$db->table('bsunits_actor_aliases')->first();
 $request=['id'=>99990001,'unit_id'=>$unit->id,'status'=>'APPROVED','unit_controller_status'=>'APPROVED','sale_manager_status'=>'NOT_REQUIRED',
  'unit_controller_id'=>$actor->source_user_id,'unit_controller_actioned_at'=>'2026-10-07 10:00:00','unit_sale_price'=>'90000.25',
  'discount_promotion'=>'3000.10','other_discount_allowance'=>'500.05','deposit_amount'=>'200.25','special_discount'=>'5.50',
  'customer_name'=>'Local Test Buyer','customer_gender'=>1,'customer2_name'=>'','customer2_gender'=>0,'customer_phone_number'=>'0990000000','customer_phone_number2'=>''];
 if(getenv('CONVERSION_TEST_MODE')==='concurrent'){
  $request['id']=99990003;$result=$converter->convert($request);echo json_encode(['ok'=>true,'replayed'=>$result['replayed']]);exit;
 }
 $one=$converter->convert($request);$two=$converter->convert($request);
 convertCheck((string)$one['sale_id']===(string)$two['sale_id']&&$two['replayed'],'idempotency');
 convertCheck($db->table('beloan_ui_customer_profiles')->where('id',$one['customer_account_id'])->count()===1&&$db->table('beloan_ui_client_cbc_general')->where('client_id',$one['customer_account_id'])->count()===1,'original customer profile once');
 convertCheck($db->table('sales')->count()===1&&$db->table('customer_accounts')->count()===1,'one account and sale');
 $sale=$db->table('sales')->first();convertCheck($sale->final_price==='81742.59'&&$sale->diposit_amount==='200.25','approved prices preserved');
 convertCheck($db->table('sale_items')->first()->unit_sale_price==='90000.25','saved price not catalog price');
 $changed=$request;$changed['deposit_amount']='300';$blocked=false;try{$converter->convert($changed);}catch(LogicException $e){$blocked=true;}convertCheck($blocked,'changed approval rejected');
 $linked=$request;$linked['id']=99990100;$linked['from_unit_deposit_request_id']=$request['id'];
 $beforeLinked=[];foreach(['customer_accounts','persons','sales','sale_items','approved_deposit_sale_links','approved_deposit_conversion_keys','unified_id_allocators'] as $table){$beforeLinked[$table]=$db->table($table)->orderBy($table==='unified_id_allocators'?'entity':(in_array($table,['approved_deposit_conversion_keys','approved_deposit_sale_links'])?'deposit_request_id':'id'))->get()->toJson();}
 $blocked=false;try{$converter->convert($linked);}catch(LogicException $e){$blocked=true;}convertCheck($blocked,'linked replacement requires amendment');
 foreach($beforeLinked as $table=>$snapshot){convertCheck($db->table($table)->orderBy($table==='unified_id_allocators'?'entity':(in_array($table,['approved_deposit_conversion_keys','approved_deposit_sale_links'])?'deposit_request_id':'id'))->get()->toJson()===$snapshot,'linked replacement leaves '.$table.' unchanged');}
 $invalid=$request;$invalid['id']=99990002;$invalid['customer_gender']=0;
 $blocked=false;try{$converter->convert($invalid);}catch(InvalidArgumentException $e){$blocked=true;}convertCheck($blocked,'invalid customer fails');
 convertCheck($db->table('customer_accounts')->count()===1&&$db->table('persons')->count()===1&&$db->table('sales')->count()===1,'atomic rollback after account insertion');
 $joint=$request;$joint['id']=99990002;$joint['customer2_name']='Second Local Buyer';$joint['customer2_gender']=2;$converter->convert($joint);
 convertCheck($db->table('customer_account_members')->count()===3,'joint account members');
 convertCheck($db->table('persons')->where('identity_review_state','PENDING')->count()===3,'no name-only identity merge');
 $pending=$request;$pending['id']=99990004;$pending['sale_manager_status']='PENDING';$blocked=false;try{$converter->convert($pending);}catch(LogicException $e){$blocked=true;}convertCheck($blocked,'incomplete approval denied');
 $blocked=false;try{$db->select('SELECT id FROM bsunits_beloan_final.loans LIMIT 1');}catch(Throwable $e){$blocked=true;}convertCheck($blocked,'financial access denied');
 // Verify model hook and decimal queue capture, then roll back the source approval.
 $source=Illuminate\Support\Facades\DB::connection();$source->beginTransaction();
 $model=App\UnitDepositRequest::where('status','APPROVED')->where('unit_controller_status','APPROVED')->whereIn('sale_manager_status',['APPROVED','NOT_REQUIRED'])->firstOrFail();
 config(['unified_catalog.deposit_conversion_enabled'=>false]);$model->status='PENDING';$model->save();
 config(['unified_catalog.deposit_conversion_enabled'=>true]);$model->status='APPROVED';$model->save();
 $event=$source->table('unified_approved_deposit_outbox')->where('deposit_request_id',$model->id)->first();
 convertCheck($event!==null,'approval event captured');$payload=json_decode($event->request_snapshot,true);
 convertCheck(is_string($payload['unit_sale_price'])&&is_string($payload['deposit_amount']),'decimal strings captured');
 $source->rollBack();convertCheck($source->table('unified_approved_deposit_outbox')->where('deposit_request_id',$model->id)->count()===0,'approval event rollback');
 // Exercise the real retry worker with a synthetic, committed source fixture and scratch target.
 $seed=$model->getAttributes();unset($seed['id']);
 $seed=array_replace($seed,['status'=>'PENDING','unit_id'=>$unit->id,'customer_name'=>'Local Worker Fixture','customer_gender'=>1,
  'customer2_name'=>'','customer2_gender'=>0,'customer_phone_number'=>'0990000000','customer_phone_number2'=>'',
  'unit_sale_price'=>'90000.25','discount_promotion'=>'3000.10','other_discount_allowance'=>'500.05','deposit_amount'=>'200.25','special_discount'=>'5.50',
  'unit_controller_status'=>'APPROVED','unit_controller_id'=>$actor->source_user_id,'sale_manager_status'=>'NOT_REQUIRED',
  'deleted_at'=>null,'to_unit_deposit_request_id'=>null,'from_unit_deposit_request_id'=>null,'remark'=>'Local integration fixture','document_no'=>'','entry_no'=>'']);
 $linkedSeed=$seed;$linkedSeed['from_unit_deposit_request_id']=99990001;
 $linkedFixtureId=$source->table('unit_deposit_requests')->insertGetId($linkedSeed);
 $linkedFixture=App\UnitDepositRequest::findOrFail($linkedFixtureId);$linkedFixture->status='APPROVED';$linkedFixture->save();
 $fixtureId=$source->table('unit_deposit_requests')->insertGetId($seed);
 $fixture=App\UnitDepositRequest::findOrFail($fixtureId);$fixture->status='APPROVED';$fixture->save();
 config(['unified_catalog.deposit_database.host'=>'host.docker.internal','unified_catalog.deposit_database.port'=>3309,
  'unified_catalog.deposit_database.database'=>'unified_deposit_conversion_test','unified_catalog.deposit_database.password'=>'local-only-unified-deposit-writer']);
 Illuminate\Support\Facades\DB::purge('unified_deposits');
 $branches=$db->table('project_accounting_branch_links')->get()->map(function($row){return (array)$row;})->all();
 $db->table('project_accounting_branch_links')->delete();
 $failed=false;try{(new App\Services\UnifiedApprovedDepositOutbox)->drain();}catch(LogicException $e){$failed=true;}
 convertCheck($failed&&$source->table('unified_approved_deposit_outbox')->where('deposit_request_id',$fixtureId)->count()===1,'worker failure retained event');
 $db->table('project_accounting_branch_links')->insert($branches);
 convertCheck((new App\Services\UnifiedApprovedDepositOutbox)->drain(1)===1,'worker recovery skips lower-id linked amendment before batch limit');
 convertCheck($source->table('unified_approved_deposit_outbox')->where('deposit_request_id',$linkedFixtureId)->count()===1 && $db->table('approved_deposit_sale_links')->where('deposit_request_id',$linkedFixtureId)->count()===0,'linked amendment retained without duplicate sale');
 App\Services\UnifiedApprovedDepositOutbox::enqueue($fixture);
 convertCheck((new App\Services\UnifiedApprovedDepositOutbox)->drain()===1&&$db->table('approved_deposit_sale_links')->where('deposit_request_id',$fixtureId)->count()===1,'worker replay');
 $source->table('unified_approved_deposit_outbox')->where('deposit_request_id',$fixtureId)->delete();
 $source->table('unit_deposit_requests')->where('id',$fixtureId)->delete();$fixtureId=null;
 $source->table('unified_approved_deposit_outbox')->where('deposit_request_id',$linkedFixtureId)->delete();$source->table('unit_deposit_requests')->where('id',$linkedFixtureId)->delete();$linkedFixtureId=null;
 echo json_encode(['status'=>'PASSED','atomic_customer_sale_item'=>true,'original_customer_profile_created_once'=>true,'idempotent_retry'=>true,'changed_approval_rejected'=>true,'linked_replacement_duplicate_sale_blocked'=>true,'linked_replacement_leaves_customer_sales_allocators_unchanged'=>true,'failure_rollback'=>true,'joint_buyers'=>true,'strict_identity_policy'=>true,'partial_approval_denied'=>true,'saved_request_prices'=>true,'durable_event_capture'=>true,'worker_failure_recovery_replay'=>true,'linked_amendment_retained_without_batch_starvation'=>true,'source_event_rollback'=>true,'financial_access_denied'=>true,'scope'=>'Scratch target, rolled-back historical source mutation and committed synthetic source fixture cleaned afterward; no production or final business records created.'],JSON_PRETTY_PRINT)."\n";
}catch(Throwable $e){if(isset($linkedFixtureId)&&$linkedFixtureId&&isset($source)){$source->table('unified_approved_deposit_outbox')->where('deposit_request_id',$linkedFixtureId)->delete();$source->table('unit_deposit_requests')->where('id',$linkedFixtureId)->delete();}if(isset($fixtureId)&&$fixtureId&&isset($source)){ $source->table('unified_approved_deposit_outbox')->where('deposit_request_id',$fixtureId)->delete();$source->table('unit_deposit_requests')->where('id',$fixtureId)->delete(); }if(isset($source)&&$source->transactionLevel()>0)$source->rollBack();fwrite(fopen('php://stderr','w'),$e instanceof RuntimeException&&strpos($e->getMessage(),'Conversion check failed:')===0?$e->getMessage()."\n":'Conversion test failed: '.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine()."\n");exit(1);}
