<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if (!preg_match('/^bsunits_price_test_[a-f0-9]{8}$/', config('unified_catalog.write_database.database'))) {
        throw new LogicException('Dedicated scratch schema required.');
    }
    $user = App\User::where('phone_number', '0990000001')->firstOrFail();
    $result = (new App\Services\UnifiedUnitPriceService)->update(
        $user, getenv('PRICE_TEST_UNIT'), '123456.78', getenv('PRICE_TEST_EXPECTED'),
        getenv('PRICE_TEST_KEY'), 'Concurrent integration test in disposable scratch schema');
    echo json_encode(['status' => 'PASSED', 'change_id' => $result['change_id']])."\n";
} catch (Throwable $e) {
    fwrite(fopen('php://stderr', 'w'), 'Concurrent price worker failed ('.get_class($e).'); details withheld.' ."\n");
    exit(1);
}
