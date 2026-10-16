<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
$cfg=config('unified_catalog.database');$cfg['database']='unified_hold_flow_test';$cfg['username']='unified_hold_test';$cfg['password']='local-only-hold-test';$cfg['prefix']='';
config(array('database.connections.hold_test'=>$cfg,'database.default'=>'hold_test','unified_catalog.request_writes_enabled'=>true,'unified_catalog.request_unit_table'=>'canonical_units','unified_catalog.action_sync_enabled'=>false,'app.default_system_user_id'=>1,'queue.default'=>'database','queue.connections.database.connection'=>'hold_test'));
// Keep external push delivery isolated while exercising real queue serialization and worker execution.
Illuminate\Support\Facades\Notification::fake();
if(getenv('HOLD_QUEUE_PHASE')==='produce') {
 $unit=Illuminate\Support\Facades\DB::table('canonical_units')->first();
 $hold=App\UnitHoldRequest::create(array('user_id'=>1,'unit_id'=>$unit->id,'status'=>'APPROVED','hold_day'=>1,'release_date'=>now()->subMinute()));
 App\UnitAction::create(array('user_id'=>1,'unit_id'=>$unit->id,'action'=>'HOLD','status_action'=>'APPROVED','actionable_type'=>'App\\UnitHoldRequest','actionable_id'=>$hold->id));
 $job=(new App\Jobs\ProcessUnitHold($hold))->onConnection('database')->onQueue('unified-test')->delay(now()->addHour());
 $app->make(Illuminate\Contracts\Bus\Dispatcher::class)->dispatch($job);
 if(Illuminate\Support\Facades\DB::table('jobs')->count()!==1 || $app['queue']->connection('database')->pop('unified-test')!==null)throw new Exception('Queue delay not honored');
 Illuminate\Support\Facades\DB::table('jobs')->update(array('available_at'=>time()-1));
 echo json_encode(array('status'=>'PASSED','delayed_job_enqueued'=>true,'early_pop_denied'=>true));
} else {
 $app['queue.worker']->runNextJob('database','unified-test',new Illuminate\Queue\WorkerOptions());
 $hold=App\UnitHoldRequest::orderBy('id','desc')->first();
 if(!$hold || $hold->status!=='OVERDUE' || Illuminate\Support\Facades\DB::table('jobs')->count()!==0 || Illuminate\Support\Facades\DB::table('canonical_units')->value('availability_state')!=='AVAILABLE')throw new Exception('Queued expiry failed');
 echo json_encode(array('status'=>'PASSED','serialized_model_restored'=>true,'worker_expiry_completed'=>true,'job_removed'=>true,'canonical_unit_released'=>true));
}
}catch(Exception $e){echo json_encode(array('status'=>'FAILED','error'=>get_class($e),'location'=>basename($e->getFile()).':'.$e->getLine(),'diagnostic'=>get_class($e)==='Exception'?$e->getMessage():'withheld'));exit(1);}
