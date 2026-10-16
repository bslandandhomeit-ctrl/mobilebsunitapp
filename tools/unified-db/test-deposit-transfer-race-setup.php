<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try{
$cfg=config('unified_catalog.database');$cfg['database']='unified_deposit_decision_test';$cfg['username']='unified_deposit_test';$cfg['password']='local-only-deposit-test';$cfg['prefix']='';config(['database.connections.deposit_test'=>$cfg,'database.default'=>'deposit_test','unified_catalog.request_writes_enabled'=>true,'unified_catalog.request_unit_table'=>'canonical_units','unified_catalog.action_sync_enabled'=>false,'unified_catalog.deposit_conversion_enabled'=>false,'app.default_system_user_id'=>1]);
Illuminate\Support\Facades\Notification::fake();Illuminate\Support\Facades\Bus::fake();
$db=Illuminate\Support\Facades\DB::connection();$units=$db->table('canonical_units')->orderBy('id')->get();if(count($units)!==3)throw new RuntimeException('Three-unit fixture missing');
$fixture=App\UnitDepositRequest::first()->getAttributes();unset($fixture['id']);$db->table('unit_deposit_requests')->delete();$db->table('unit_actions')->delete();$db->table('test_barrier')->delete();$db->table('canonical_units')->update(['current_action_id'=>null]);
$users=App\User::role('agent')->orderBy('id')->limit(2)->get();
foreach($units as $unit){App\UnitAction::create(['user_id'=>1,'unit_id'=>$unit->id,'action'=>'AVAILABLE','status_action'=>'','actionable_type'=>'','actionable_id'=>0]);}
for($i=0;$i<2;$i++){$row=$fixture;$row['user_id']=$users[$i]->id;$row['unit_id']=$units[$i]->id;$row['unit_sale_price']=$units[$i]->list_price;$row['deposit_amount']=100;$row['other_discount_allowance']=0;$row['status']='PENDING';$row['unit_controller_status']='PENDING';$row['unit_controller_id']=null;$row['sale_manager_status']='NOT_REQUIRED';$row['sale_manager_id']=null;$id=$db->table('unit_deposit_requests')->insertGetId($row);App\UnitAction::create(['user_id'=>$users[$i]->id,'unit_id'=>$units[$i]->id,'action'=>'DEPOSIT','status_action'=>'PENDING','actionable_type'=>'App\\UnitDepositRequest','actionable_id'=>$id]);}
echo json_encode(['status'=>'PASSED']);
}catch(Throwable $e){echo json_encode(['status'=>'FAILED','type'=>get_class($e)]);exit(1);}
