<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
$source=config('database.default');$cfg=config('unified_catalog.database');$cfg['database']='unified_request_read_test';$cfg['username']='unified_request_test';$cfg['password']='local-only-request-test';$cfg['prefix']='';config(array('database.connections.request_rehearsal'=>$cfg));
$result=array();
foreach(array('App\UnitHoldRequest','App\UnitDepositRequest','App\UnitContractRequest') as $class) {
 $snapshots=array();
 foreach(array($source,'request_rehearsal') as $connection) {
  config(array('database.default'=>$connection));$model=new $class;$table=$model->getTable();
  $hash=hash_init('sha256');$count=0;
  $class::query()->setEagerLoads(array())->orderBy('id')->chunk(500,function($rows)use($hash,&$count){foreach($rows as $row){$encoded=json_encode(array($row->id,$row->getAttributes()));if($encoded===false)throw new RuntimeException('Request snapshot encoding failed');hash_update($hash,$encoded."\n");$count++;}});
  $snapshots[]=hash_final($hash);
  if($connection==='request_rehearsal') {
   $sample=$class::query()->setEagerLoads(array())->with('createdBy')->orderBy('id','desc')->limit(20)->get();
   $missing=0;foreach($sample as $row)if(!$row->createdBy)$missing++;
   $result[$table]=array('rows'=>$count,'sample_details'=>count($sample),'sample_missing_source_actors'=>$missing,'exact_raw_attribute_parity'=>true);
  }
 }
 if($snapshots[0]!==$snapshots[1])throw new RuntimeException('Request attribute parity differs');
}
config(array('database.default'=>'request_rehearsal'));
$attachments=App\UnitContractRequest::query()->setEagerLoads(array())->with('attachments')->whereHas('attachments')->limit(20)->get();$paths=0;
foreach($attachments as $request)foreach($request->attachments as $attachment){if(!$attachment->getAttributes()['path'])throw new RuntimeException('Attachment path missing');if(strpos($attachment->path,'/storage/')===false)throw new RuntimeException('Attachment URL missing');$paths++;}
$denied=false;try{Illuminate\Support\Facades\DB::table('unit_hold_requests')->where('id',-1)->update(array('remark'=>'test'));}catch(Exception $e){$denied=strpos($e->getMessage(),'denied')!==false;}if(!$denied)throw new RuntimeException('Read rehearsal permits writes');
echo json_encode(array('status'=>'PASSED','requests'=>$result,'attachment_records_checked'=>$paths,'writes_denied'=>true,'scope'=>'Original request models read unified history and BS actor projections; stored field parity and attachment URLs only. Unit relations, file existence, API authorization and CRUD remain pending.'),JSON_PRETTY_PRINT);
} catch(Exception $e){echo json_encode(array('status'=>'FAILED','error'=>get_class($e),'location'=>basename($e->getFile()).':'.$e->getLine()));exit(1);}
