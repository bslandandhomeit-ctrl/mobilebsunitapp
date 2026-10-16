<?php
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try{
$cfg=config('unified_posting.database');$cfg['host']='host.docker.internal';$cfg['port']=3309;$cfg['database']='bsunits_beloan_final';$cfg['username']='unified_beloan_posting_reader';$cfg['password']='local-only-unified-posting-reader';$cfg['prefix']='';config(array('database.connections.change_history'=>$cfg));
$model=new App\Models\ChangeUnit;$model->setConnection('change_history');$model->setTable('beloan_compat_change_unit');$rows=$model->newQuery()->get();if(count($rows)!==71)throw new RuntimeException('History count mismatch');
foreach($rows as $row){if($row->getTable()!=='beloan_compat_change_unit'||$row->getConnectionName()!=='change_history')throw new RuntimeException('Hydration lost unified scope');}
$blocked=false;try{$rows[0]->save();}catch(LogicException $e){$blocked=true;}if(!$blocked)throw new RuntimeException('Projection write allowed');
$blocked=false;try{$rows[0]->delete();}catch(LogicException $e){$blocked=true;}if(!$blocked)throw new RuntimeException('Projection delete allowed');
$db=Illuminate\Support\Facades\DB::connection('change_history');$blocked=false;try{$db->table('beloan_unit_change_history')->where('id',$rows[0]->id)->update(array('sale_id'=>$rows[0]->sale_id));}catch(Exception $e){$blocked=true;}if(!$blocked)throw new RuntimeException('Database write allowed');
if((new App\Models\ChangeUnit)->getTable()!=='change_unit')throw new RuntimeException('Original model default changed');
echo json_encode(array('status'=>'PASSED','rows'=>count($rows),'original_model_hydration'=>true,'model_and_database_writes_denied'=>true,'original_default_preserved'=>true));
}catch(Exception $e){echo json_encode(array('status'=>'FAILED','type'=>get_class($e)));exit(1);}
