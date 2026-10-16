<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
function bridgeCheck($ok, $label) { if (!$ok) throw new RuntimeException('Bridge check failed: '.$label); }
function bridgeGet($kernel, $url, $code=200) {
 $r=$kernel->handle(Illuminate\Http\Request::create($url,'GET',[],[],[],['HTTP_ACCEPT'=>'application/json']));
 bridgeCheck($r->getStatusCode()===$code, 'HTTP '.$code);
 return json_decode($r->getContent(),true);
}
try {
 config(['unified_catalog.mobile_reads_enabled'=>false]);
 bridgeGet($kernel,'/api/units',401);
 Illuminate\Support\Facades\Auth::guard('api')->setUser(App\User::where('phone_number','010866035')->firstOrFail());
 $url='/api/units?per_page=3&embed=unitType.project';
 $old=bridgeGet($kernel,$url);
 config(['unified_catalog.enabled'=>true,'unified_catalog.mobile_reads_enabled'=>true,
  'unified_catalog.database.host'=>'host.docker.internal','unified_catalog.database.port'=>3309,
  'unified_catalog.database.password'=>'local-only-unified-reader']);
 $new=bridgeGet($kernel,$url);
 bridgeCheck(count($new['data'])===3 && $new['meta']['total']===$old['meta']['total'],'pagination and catalog total');
 foreach($new['data'] as $row) {
  bridgeCheck(!isset($row['_unified_catalog'])&&!isset($row['_unifiedCatalog']),'internal metadata hidden');
  bridgeCheck(isset($row['unit_type']['project']) && $row['__type']==='unit','nested relationships');
 }
 $projectId=$new['data'][0]['unit_type']['project']['id'];
 $projectUnits=bridgeGet($kernel,'/api/projects/'.$projectId.'/units?per_page=3');
 bridgeCheck(count($projectUnits['data'])>0,'project unit route');
 $id=$new['data'][0]['id'];
 $detailUrl='/api/units/'.$id.'?embed=unitType.project,unitType.media,unitType.activeDiscountPromotions';
 $detail=bridgeGet($kernel,$detailUrl)['data'];
 config(['unified_catalog.mobile_reads_enabled'=>false]);
 $legacy=bridgeGet($kernel,$detailUrl)['data'];
 bridgeCheck($detail['unit_type']===$legacy['unit_type'],'media/promotions/project payload preserved');
 config(['unified_catalog.mobile_reads_enabled'=>true]);
 $repo=app(App\Services\UnifiedCatalogRepository::class);
 $canonical=$repo->unit($id);
 bridgeCheck((string)$detail['price']===(string)$canonical['price'],'canonical decimal price');
 foreach($canonical['specifications'] as $key=>$value) bridgeCheck(array_key_exists($key,$detail)&&$detail[$key]===$value,'canonical specifications');
 $filtered=bridgeGet($kernel,'/api/units?per_page=3&unit_type_id='.$canonical['unit_type_id']);
 foreach($filtered['data'] as $row) bridgeCheck($row['unit_type_id']==$canonical['unit_type_id'],'type filter');
 bridgeGet($kernel,'/api/units/'.PHP_INT_MAX,404);
 $before=App\Unit::findOrFail($id)->getAttributes();
 // Different read fixture proves serialization uses canonical fields, not legacy attributes.
 $fake=new class($canonical) extends App\Services\UnifiedCatalogRepository {
  private $data; public function __construct($data){$this->data=$data;}
  public function unit($id){$d=$this->data;$d['price']='123.45';$d['specifications']['bedroom']=99;$d['action']=['action'=>'CONTRACT','status_action'=>'PENDING'];return $d;}
 };
 $app->instance(App\Services\UnifiedCatalogRepository::class,$fake);
 $changed=bridgeGet($kernel,$detailUrl)['data'];
 bridgeCheck($changed['price']==='123.45'&&$changed['bedroom']===99&&$changed['availability_status']==='PENDING CONTRACT','canonical override fixture');
 bridgeCheck(App\Unit::findOrFail($id)->getAttributes()===$before,'legacy record unchanged');
 echo json_encode(['status'=>'PASSED','anonymous_rejected'=>true,'pagination'=>true,'media_promotions_preserved'=>true,'canonical_price_and_specs'=>true,'canonical_override_fixture'=>true,'missing_unit_404'=>true,'legacy_record_unchanged'=>true,'scope'=>'API guard injected; no login or real writes; supporting media remains on legacy connection.'],JSON_PRETTY_PRINT)."\n";
} catch(Throwable $e) { fwrite(fopen('php://stderr','w'),$e instanceof RuntimeException && strpos($e->getMessage(),'Bridge check failed:')===0?$e->getMessage()."\n":'Mobile unit bridge failed: '.get_class($e)."\n");exit(1); }
