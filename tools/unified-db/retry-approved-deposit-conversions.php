<?php
require '/app/vendor/autoload.php';
$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local')){fwrite(fopen('php://stderr','w'),"Local-only retry utility.\n");exit(1);}
try{
 $count=0;do{$batch=(new App\Services\UnifiedApprovedDepositOutbox)->drain(100);$count+=$batch;}while($batch===100);
 echo json_encode(['processed'=>$count,'pending'=>Illuminate\Support\Facades\DB::table('unified_approved_deposit_outbox')->count()])."\n";
}catch(Throwable $e){fwrite(fopen('php://stderr','w'),"Conversion remains queued; review local mapping/configuration.\n");exit(1);}
