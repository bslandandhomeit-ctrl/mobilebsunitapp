<?php
require '/app/vendor/autoload.php';
$app=require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
function syncCheck($ok,$label){if(!$ok)throw new RuntimeException('Sync check failed: '.$label);}
try {
 config(['unified_catalog.action_sync_enabled'=>true]);
 $source=DB::connection();
 $pdo=new PDO('mysql:host=host.docker.internal;port=3309;dbname=unified_action_sync_test;charset=utf8mb4','unified_unit_action_writer','local-only-unified-action-writer',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $target=new Illuminate\Database\MySqlConnection($pdo,'unified_action_sync_test','');
 $sync=new App\Services\UnifiedUnitActionSync($source,$target);
 $fixture=$target->table('units')->first();$id=$fixture->id;
 $before=$source->table('units')->where('id',$id)->first();
 // Source mutations stay inside an outer transaction; target writes go only to scratch tables.
$stage='source begin'; $source->beginTransaction();
 $states=[['HOLD','PENDING','RESERVED'],['DEPOSIT','APPROVED','RESERVED'],['CONTRACT','PENDING','RESERVED'],['CONTRACT','APPROVED','SOLD'],['AVAILABLE','','AVAILABLE']];
 foreach($states as $state){
  $stage='create source action';
  $action=App\UnitAction::create(['user_id'=>1,'unit_id'=>$id,'actionable_type'=>'','actionable_id'=>0,'action'=>$state[0],'status_action'=>$state[1],'description'=>'Local migration synchronization test']);
  syncCheck($source->table('unified_unit_action_outbox')->where('unit_id',$id)->count()===1,'durable event created');
  $stage='drain first event';
  syncCheck($sync->drain()===1,'event processed');
  $current=$target->table('units')->where('id',$id)->first();
  syncCheck($current->current_action_id==$action->id&&$current->availability_state===$state[2],'canonical state transition');
  $count=$target->table('unit_actions')->count();
  App\Services\UnifiedUnitActionSync::enqueue($action);
  syncCheck($sync->drain()===1&&$target->table('unit_actions')->count()===$count,'idempotent replay');
 }
 $action=App\UnitAction::create(['user_id'=>1,'unit_id'=>$id,'actionable_type'=>'','actionable_id'=>0,'action'=>'HOLD','status_action'=>'PENDING']);
 $target->table('units')->where('id',$id)->delete();
 $failed=false;try{$sync->drain();}catch(LogicException $e){$failed=true;}
 syncCheck($failed&&$source->table('unified_unit_action_outbox')->where('unit_id',$id)->count()===1,'failed target leaves retry event');
 $target->table('units')->insert((array)$fixture);
 syncCheck($sync->drain()===1,'recovered retry');
 $source->rollBack();
 syncCheck((array)$source->table('units')->where('id',$id)->first()===(array)$before,'source record rollback');
 syncCheck($source->table('unified_unit_action_outbox')->where('unit_id',$id)->count()===0,'source rollback removed events');
 $blocked=false;try{$target->select('SELECT id FROM bsunits_beloan_final.loans LIMIT 1');}catch(Throwable $e){$blocked=true;}
 syncCheck($blocked,'loan data denied');
 $blocked=false;try{$target->statement('UPDATE bsunits_beloan_final.units SET list_price=list_price WHERE id=?',[$id]);}catch(Throwable $e){$blocked=true;}
 syncCheck($blocked,'price writes denied');
 echo json_encode(['status'=>'PASSED','transitions'=>count($states),'idempotent_retry'=>true,'failed_event_retained'=>true,'recovery'=>true,'source_rollback'=>true,'financial_access_denied'=>true,'price_writes_denied'=>true,'target'=>'Disposable scratch schema; no final catalog changes.'],JSON_PRETTY_PRINT)."\n";
}catch(Throwable $e){if(isset($source)&&$source->transactionLevel()>0)$source->rollBack();fwrite(fopen('php://stderr','w'),$e instanceof RuntimeException&&strpos($e->getMessage(),'Sync check failed:')===0?$e->getMessage()."\n":'Action sync test failed: '.get_class($e).' stage '.($stage??'initialization').' at '.basename($e->getFile()).':'.$e->getLine()."\n");exit(1);}
