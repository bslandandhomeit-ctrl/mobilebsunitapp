<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$loggedFailure='';Illuminate\Support\Facades\Log::listen(function($event)use(&$loggedFailure){$msg=(string)$event->message;if(preg_match('/SQLSTATE\[[^\]]+\].*?(?:Unknown column [^\n]+|Table [^\n]+|Field [^\n]+|doesn.t have a default value)/',$msg,$match))$loggedFailure=$match[0];});
try {
$cfg=config('unified_catalog.database');$cfg['database']='unified_request_write_test';$cfg['username']='unified_request_writer_test';$cfg['password']='local-only-request-write-test';$cfg['prefix']='';
config(array('database.connections.request_write_test'=>$cfg,'database.default'=>'request_write_test','unified_catalog.request_writes_enabled'=>true,'unified_catalog.request_unit_table'=>'canonical_units','unified_catalog.action_sync_enabled'=>false,'unified_catalog.deposit_conversion_enabled'=>false,'filesystems.disks.public.root'=>'/tmp/unified-request-upload-test'));
$user=new App\User;$user->id=1;Illuminate\Support\Facades\Auth::setUser($user);
$deposit=App\UnitDepositRequest::first();$controller=new App\Http\Controllers\Api\UnitContractRequestController;
function req($fields,$files,$user) {$r=Illuminate\Http\Request::create('/api/unit_contract_requests','POST',$fields,array(),$files);$r->setUserResolver(function()use($user){return $user;});return $r;}
$png='/tmp/unified-request-test.png';file_put_contents($png,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
$upload=new Illuminate\Http\UploadedFile($png,'fixture.png','image/png',null,true);
$fields=array('unit_deposit_request_id'=>$deposit->id,'signed_at'=>date('Y-m-d'),'start_payment_date'=>date('Y-m-d'),'remark'=>'Synthetic contract');
$other=new App\User;$other->id=2;
$denied=$controller->create(req($fields,array('attachments'=>array($upload)),$other));if($denied->getStatusCode()!==422)throw new Exception('Foreign deposit accepted');
$created=$controller->create(req($fields,array('attachments'=>array($upload)),$user));if(!($created instanceof App\Http\Resources\UnitContractRequest))throw new Exception('Contract create failed: '.json_encode($created->getData()));
$model=$created->resource;$attachment=$model->attachments()->first();if(!$attachment||!Illuminate\Support\Facades\Storage::disk('public')->exists($attachment->getAttributes()['path']))throw new Exception('Attachment file/record missing');
if(App\UnitDepositRequest::find($deposit->id)->status!=='RELEASE')throw new Exception('Deposit transition failed');
$unit=Illuminate\Support\Facades\DB::table('canonical_units')->first();if(!$unit->current_action_id || $unit->availability_state!=='RESERVED')throw new Exception('Canonical action pointer not written');
$denied=$controller->update(req(array('remark'=>'Unauthorized'),array(),$other),$model->id);if($denied->getStatusCode()!==403)throw new Exception('Foreign edit accepted');
$updated=$controller->update(req(array('remark'=>'Updated synthetic contract'),array(),$user),$model->id);if(!($updated instanceof App\Http\Resources\UnitContractRequest)||App\UnitContractRequest::find($model->id)->remark!=='Updated synthetic contract')throw new Exception('Owner edit failed');
$invalid=$controller->update(req(array('deleted_attachments'=>array($attachment->id)),array(),$user),$model->id);if($invalid->getStatusCode()!==422||!$attachment->fresh())throw new Exception('Last attachment removal allowed');
$before=Illuminate\Support\Facades\DB::table('unit_actions')->count();
$rolledBack=false;try {App\UnitAction::create(array('user_id'=>1,'unit_id'=>99999999,'action'=>'AVAILABLE','status_action'=>'','actionable_type'=>'App\\UnitContractRequest','actionable_id'=>$model->id));}catch(LogicException $e){$rolledBack=true;}
if(!$rolledBack||Illuminate\Support\Facades\DB::table('unit_actions')->count()!==$before)throw new Exception('Failed canonical action was not rolled back');
$originalPrice=Illuminate\Support\Facades\DB::table('canonical_units')->where('id',$unit->id)->value('list_price');
App\UnitAction::create(array('user_id'=>1,'unit_id'=>$unit->id,'action'=>'AVAILABLE','status_action'=>'','actionable_type'=>'App\\UnitContractRequest','actionable_id'=>$model->id));
$available=Illuminate\Support\Facades\DB::table('canonical_units')->where('id',$unit->id)->first();
if($available->availability_state!=='AVAILABLE'||$available->list_price!=$originalPrice)throw new Exception('Availability change altered price');
echo json_encode(array('status'=>'PASSED','original_controller_create'=>true,'canonical_unit_pointer'=>true,'availability_transition'=>true,'failed_action_atomic_rollback'=>true,'price_preserved'=>true,'deposit_release_transition'=>true,'attachment_file_and_record'=>true,'foreign_create_edit_denied'=>true,'owner_update'=>true,'last_attachment_removal_denied'=>true,'scope'=>'Disposable request tables and unchanged canonical unit schema; same-connection action pointer and availability writes. Action/conversion outboxes disabled; live API cutover, concurrency and cancellation permissions pending.'),JSON_PRETTY_PRINT);
} catch(Exception $e){echo json_encode(array('status'=>'FAILED','error'=>get_class($e),'location'=>basename($e->getFile()).':'.$e->getLine(),'sql_error'=>($e instanceof Illuminate\Database\QueryException)?$e->errorInfo[2]:null,'logged_failure'=>$loggedFailure,'diagnostic'=>get_class($e)==='Exception'?$e->getMessage():'withheld'));exit(1);}
finally {if(isset($png))@unlink($png);if(isset($cfg)){ $disk=Illuminate\Support\Facades\Storage::disk('public');$disk->delete($disk->allFiles());}}
