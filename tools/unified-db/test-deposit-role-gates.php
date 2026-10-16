<?php
require '/app/vendor/autoload.php';$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
function roleRequest($kernel,$role,$url,$expected){
 $user=App\User::role($role)->where('active',1)->firstOrFail();
 Illuminate\Support\Facades\Auth::guard('api')->setUser($user);Illuminate\Support\Facades\Auth::guard('web')->setUser($user);
 $response=$kernel->handle(Illuminate\Http\Request::create($url,'POST',[],[],[],['HTTP_ACCEPT'=>'application/json']));
 if($response->getStatusCode()!==$expected)throw new RuntimeException('Role check failed: '.$role.' expected '.$expected.' got '.$response->getStatusCode());
}
try{
 $id=App\UnitDepositRequest::where('status','APPROVED')->where('unit_controller_status','APPROVED')->where('sale_manager_status','APPROVED')->value('id');
 if(!$id)throw new RuntimeException('No completed approval fixture available.');
 roleRequest($kernel,'agent','/api/unit_deposit_requests',422);
 roleRequest($kernel,'administrator','/api/unit_deposit_requests',403);
 roleRequest($kernel,'agent','/api/unit_deposit_requests/'.$id.'/approve',403);
 roleRequest($kernel,'unit_controller','/api/unit_deposit_requests/'.$id.'/approve',422);
 roleRequest($kernel,'sale_manager','/api/unit_deposit_requests/'.$id.'/approve',422);
 echo json_encode(['status'=>'PASSED','sales_submission_role_gate'=>true,'admin_does_not_bypass_sales_role'=>true,'agent_cannot_approve'=>true,'controller_and_manager_cannot_reapprove'=>true,'authentication'=>'Existing local role users injected into API guard; passwords and device login not tested.','writes'=>false],JSON_PRETTY_PRINT)."\n";
}catch(Throwable $e){fwrite(fopen('php://stderr','w'),$e instanceof RuntimeException?$e->getMessage()."\n":'Role test failed: '.get_class($e)."\n");exit(1);}
