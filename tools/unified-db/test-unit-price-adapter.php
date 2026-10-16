<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function priceCheck($value, $label) {
    if (!$value) { throw new RuntimeException('Price adapter check failed: '.$label); }
}

$db = null;
try {
    $user = App\User::where('phone_number', '0990000001')->firstOrFail();
    $service = new App\Services\UnifiedUnitPriceService;
    config(['database.connections.unified_catalog_write' => config('unified_catalog.write_database')]);
    $db = Illuminate\Support\Facades\DB::connection('unified_catalog_write');
    $db->beginTransaction();
    $unit = $db->table('units')->where('catalog_state', 'CURRENT')->whereNull('deleted_at')->orderBy('id')->first();
    $before = $unit->list_price;
    $amount = $before === '123456.78' ? '123456.79' : '123456.78';
    $key = 'rollback-test-'.bin2hex(random_bytes(16));
    $first = $service->update($user, $unit->id, $amount, $before, $key, 'Integration test; transaction rolled back');
    $retry = $service->update($user, $unit->id, $amount, $before, $key, 'Integration test; transaction rolled back');
    priceCheck((string) $first['change_id'] === (string) $retry['change_id'], 'same-key retry');
    priceCheck($db->table('unit_price_changes')->where('request_key', $key)->count() === 1, 'single audit record');
    priceCheck($db->table('units')->where('id', $unit->id)->value('list_price') === $amount, 'current price update');
    $stale = false;
    try { $service->update($user, $unit->id, '123456.80', $before, $key.'-stale'); }
    catch (DomainException $e) { $stale = true; }
    priceCheck($stale, 'stale price rejected');
    $reuse = false;
    try { $service->update($user, $unit->id, '123456.80', $before, $key); }
    catch (DomainException $e) { $reuse = true; }
    priceCheck($reuse, 'key reuse with different amount rejected');
    foreach (['-1', '1.001', '1e2'] as $bad) {
        $rejected = false;
        try { $service->update($user, $unit->id, $bad, $amount, $key.'-invalid'); }
        catch (InvalidArgumentException $e) { $rejected = true; }
        priceCheck($rejected, 'invalid decimal rejected');
    }
    $deniedUser = new class extends App\User {
        public function can($abilities, $arguments = []) { return false; }
    };
    $denied = false;
    try { $service->update($deniedUser, $unit->id, $amount, $amount, $key.'-denied'); }
    catch (Illuminate\Auth\Access\AuthorizationException $e) { $denied = true; }
    priceCheck($denied, 'permission required');
    $financeDenied = false;
    try { $db->statement('UPDATE loans SET final_price=final_price WHERE 1=0'); }
    catch (Illuminate\Database\QueryException $e) { $financeDenied = ($e->errorInfo[1] ?? null) === 1142; }
    priceCheck($financeDenied, 'historical financial writes denied');
    $db->rollBack();
    priceCheck($db->table('units')->where('id', $unit->id)->value('list_price') === $before, 'price restored after rollback');
    priceCheck($db->table('unit_price_changes')->where('request_key', $key)->count() === 0, 'audit rolled back');
    config(['unified_catalog.writes_enabled' => false]);
    $disabled = false;
    try { $service->update($user, $unit->id, $amount, $before, $key); }
    catch (LogicException $e) { $disabled = true; }
    priceCheck($disabled, 'write feature gate');
    echo json_encode(['status' => 'PASSED', 'price_and_audit_transaction' => true,
        'same_key_retry' => true, 'stale_price_rejected' => true,
        'conflicting_key_rejected' => true, 'invalid_decimals_rejected' => true,
        'permission_required' => true, 'historical_financial_writes_denied' => true,
        'write_feature_gate' => true, 'test_changes_rolled_back' => true,
        'limits' => 'Sequential checks here; separate concurrency harness verifies simultaneous identical retries. Existing controllers are not switched to this service.'], JSON_PRETTY_PRINT)."\n";
} catch (Throwable $e) {
    if ($db && $db->transactionLevel() > 0) { $db->rollBack(); }
    fwrite(fopen('php://stderr', 'w'), $e instanceof RuntimeException && strpos($e->getMessage(), 'Price adapter check failed:') === 0
        ? $e->getMessage()."\n" : 'Unit price adapter test failed ('.get_class($e).'); details withheld.' ."\n");
    exit(1);
}
