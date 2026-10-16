<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

function catalogRequest($kernel, $path, $expected) {
    $request = Illuminate\Http\Request::create($path, 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    $response = $kernel->handle($request);
    if ($response->getStatusCode() !== $expected) {
        throw new RuntimeException('HTTP check failed: expected '.$expected.', received '.$response->getStatusCode());
    }
    return json_decode($response->getContent(), true);
}

try {
    catalogRequest($kernel, '/api/unified/catalog/units', 401);
    // Existing local-only test user. Exercise route authorization without issuing new tokens.
    $tester = App\User::where('phone_number', '0990000001')->firstOrFail();
    Illuminate\Support\Facades\Auth::guard('api')->setUser($tester);
    $data = catalogRequest($kernel, '/api/unified/catalog/units?per_page=3', 200);
    if (count($data['data']) !== 3 || $data['meta']['per_page'] !== 3) {
        throw new RuntimeException('HTTP check failed: pagination response');
    }
    catalogRequest($kernel, '/api/unified/catalog/units?per_page=101', 422);
    $types = catalogRequest($kernel, '/api/unified/catalog/unit_types?per_page=3', 200);
    if (count($types['data']) !== 3) {
        throw new RuntimeException('HTTP check failed: unit type pagination');
    }
    catalogRequest($kernel, '/api/unified/catalog/units/'.PHP_INT_MAX, 404);
    config(['unified_catalog.enabled' => false]);
    catalogRequest($kernel, '/api/unified/catalog/units', 404);
    echo json_encode(['status' => 'PASSED', 'anonymous_rejected' => true,
        'authenticated_catalog' => true, 'pagination' => true, 'invalid_page_size_rejected' => true,
        'unit_type_listing' => true, 'missing_unit_404' => true, 'disabled_endpoint_404' => true,
        'authentication_test' => 'Existing local fixture injected into API guard; no new tokens or login mutations.'], JSON_PRETTY_PRINT)."\n";
} catch (Throwable $e) {
    fwrite(fopen('php://stderr', 'w'), $e instanceof RuntimeException && strpos($e->getMessage(), 'HTTP check failed:') === 0
        ? $e->getMessage()."\n" : 'Catalog HTTP test failed ('.get_class($e).'); details withheld.' ."\n");
    exit(1);
}
