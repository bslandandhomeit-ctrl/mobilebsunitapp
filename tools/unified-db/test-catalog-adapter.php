<?php
// Run inside the original web PHP container; print aggregate results only.
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function check($condition, $label) {
    if (!$condition) {
        throw new RuntimeException('Catalog adapter check failed: '.$label);
    }
}

function decimalKey($value) {
    $value = (string) $value;
    check(preg_match('/^([+-]?)([0-9]+)(?:\.([0-9]+))?$/', $value, $parts) === 1, 'valid decimal representation');
    $integer = ltrim($parts[2], '0');
    $integer = $integer === '' ? '0' : $integer;
    $fraction = rtrim($parts[3] ?? '', '0');
    $sign = ($parts[1] === '-' && ($integer !== '0' || $fraction !== '')) ? '-' : '';
    return $sign.$integer.($fraction !== '' ? '.'.$fraction : '');
}

try {
    $catalog = new App\Services\UnifiedCatalogRepository;
    $originalDatabase = Illuminate\Support\Facades\DB::connection()->getDatabaseName();
    check($originalDatabase === 'bsunits_backup_20261005', 'original default database');
    $page = $catalog->units(['per_page' => 10]);
    check($page->count() > 0, 'catalog has units');
    $oldTotal = App\Unit::saleable()->publishedProject()->count();
    check($page->total() === $oldTotal, 'original catalog visibility: adapter '.$page->total().' / source '.$oldTotal);
    foreach ($page->items() as $unit) {
        $original = Illuminate\Support\Facades\DB::table('units')->where('id', $unit['id'])->first();
        check($original && decimalKey($unit['price']) === decimalKey($original->price), 'current source price');
        $action = Illuminate\Support\Facades\DB::table('unit_actions')->where('id', $original->unit_action_id)->first();
        check($action && $unit['availability_status'] === $action->action, 'BS current action');
        $oldType = App\UnitType::withTrashed()->findOrFail($unit['unit_type_id']);
        check($unit['unit_type']['feature_image_url'] === $oldType->feature_image_url, 'unit image URL');
        check(!isset($unit['attributes']) && !isset($unit['password']), 'no private source payload');
    }
    $first = $page->items()[0];
    $filtered = $catalog->units(['project_id' => $first['unit_type']['project']['id'], 'per_page' => 5]);
    foreach ($filtered->items() as $unit) {
        check($unit['unit_type']['project']['id'] === $first['unit_type']['project']['id'], 'project boundaries');
    }
    check($catalog->unit($first['id'])['price'] === $first['price'], 'detail price');
    check($catalog->unit(PHP_INT_MAX) === null, 'missing unit');
    $types = $catalog->unitTypes(['project_id' => $first['unit_type']['project']['id'], 'per_page' => 10]);
    check($types->count() > 0, 'unit type listing');
    foreach ($types->items() as $type) {
        check($type['project_id'] === $first['unit_type']['project']['id'], 'unit type project boundaries');
    }
    $available = $catalog->units(['status' => 'AVAILABLE', 'per_page' => 10]);
    foreach ($available->items() as $unit) {
        check($unit['availability_status'] === 'AVAILABLE', 'available filter');
    }
    $reader = Illuminate\Support\Facades\DB::connection('unified_catalog');
    $writeDenied = false;
    try { $reader->statement('UPDATE units SET code=code WHERE 1=0'); }
    catch (Illuminate\Database\QueryException $e) { $writeDenied = ($e->errorInfo[1] ?? null) === 1142; }
    check($writeDenied, 'reader cannot write');
    $privateDenied = false;
    try { $reader->select('SELECT 1 FROM login_identities LIMIT 1'); }
    catch (Illuminate\Database\QueryException $e) { $privateDenied = ($e->errorInfo[1] ?? null) === 1142; }
    check($privateDenied, 'reader cannot access credentials');
    config(['unified_catalog.enabled' => false]);
    $disabled = false;
    try { $catalog->units(); } catch (LogicException $e) { $disabled = true; }
    check($disabled, 'disabled gate');
    config(['unified_catalog.enabled' => true]);
    check(Illuminate\Support\Facades\DB::connection()->getDatabaseName() === $originalDatabase, 'default connection unchanged');
    echo json_encode(['status' => 'PASSED', 'visible_units' => $page->total(),
        'sample_price_action_matches' => $page->count(), 'projects' => $catalog->projects()->count(),
        'project_filter' => true, 'availability_filter' => true, 'unit_type_listing_and_images' => true, 'detail_and_missing_unit' => true,
        'writes_denied' => true, 'private_credentials_denied' => true,
        'disabled_gate' => true, 'default_database_unchanged' => true], JSON_PRETTY_PRINT)."\n";
} catch (Throwable $e) {
    // Do not disclose query bindings, connection settings or source records.
    fwrite(fopen('php://stderr', 'w'), $e instanceof RuntimeException && strpos($e->getMessage(), 'Catalog adapter check failed:') === 0
        ? $e->getMessage()."\n" : 'Catalog adapter test failed ('.get_class($e).'); details withheld.' ."\n");
    exit(1);
}
