<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
class DepositRejectRoleFixture {
    private $role;
    public function __construct($role) { $this->role = $role; }
    public function hasRole($roles) { return in_array($this->role, (array) $roles, true); }
}
try {
    $controller = new App\Http\Controllers\Api\UnitDepositRequestController();
    $checks = [];
    foreach (['agent', 'sale_team_leader', 'administrator', 'accountant', 'unit_controller', 'sale_manager'] as $role) {
        $request = Illuminate\Http\Request::create('/api/unit_deposit_requests/0/reject', 'POST');
        $request->setUserResolver(function () use ($role) { return new DepositRejectRoleFixture($role); });
        $allowed = in_array($role, ['unit_controller', 'sale_manager'], true);
        try {
            $response = $controller->reject($request, 0);
            if ($allowed || $response->getStatusCode() !== 403) throw new RuntimeException('Unexpected permission result');
        } catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            if (!$allowed) throw new RuntimeException('Unauthorized role reached request lookup');
        }
        $checks[$role] = $allowed ? 'permitted_to_reach_request_lookup' : 'denied_before_request_lookup';
    }
    echo json_encode(['status' => 'PASSED', 'checks' => $checks, 'writes' => false, 'scope' => 'Actual original controller; synthetic role fixtures; nonexistent request; authentication and valid rejection transaction not covered'], JSON_PRETTY_PRINT)."\n";
} catch (Throwable $e) { echo json_encode(['status'=>'FAILED','error_type'=>get_class($e)])."\n"; exit(1); }
