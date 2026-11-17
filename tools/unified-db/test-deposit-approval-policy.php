<?php
// Isolated model policy regression test; no database or Laravel runtime needed.
namespace Illuminate\Database\Eloquent {
    class Model {
        public static $hooks = [];
        private $attributes = [];
        private $original = [];
        protected static function boot() {}
        public static function __callStatic($name, $args) { self::$hooks[$name] = $args[0]; }
        public function __get($key) { return $this->attributes[$key] ?? null; }
        public function __set($key, $value) { $this->attributes[$key] = $value; }
        public function getAttributes() { return $this->attributes; }
        public function getOriginal($key) { return $this->original[$key] ?? null; }
        public function syncOriginal() { $this->original = $this->attributes; }
    }
}
namespace App\Helpers {
    class UserRole { const AGENT = 'agent'; const SALE_TEAM_LEADER = 'sale_team_leader'; }
    class UnitDepositStatus { const PENDING = 'PENDING'; }
}
namespace {
    require __DIR__.'/backend-adapters/bsunits/UnitDepositRequest.php';
    class PolicyDeposit extends \App\UnitDepositRequest {
        public static function registerHooks() { parent::boot(); }
    }
    class PolicySubmitter {
        private $roles;
        public function __construct($roles) { $this->roles = $roles; }
        public function hasRole($role) { return in_array($role, $this->roles, true); }
    }
    function checkPolicy($actual, $expected, $label) {
        if ($actual !== $expected) throw new \RuntimeException($label.': '.var_export($actual, true));
    }
    PolicyDeposit::registerHooks();
    $checks = 0;
    foreach ([['agent'], ['sale_team_leader'], ['agent', 'sale_team_leader'], [], null] as $roles) {
        foreach ([false, true] as $selected) {
            foreach ([null, 7] as $payment) {
                foreach ([0, 50] as $discount) {
                    $deposit = new PolicyDeposit;
                    $deposit->createdBy = $roles === null ? null : new PolicySubmitter($roles);
                    $deposit->status = 'PENDING';
                    $deposit->is_selected_payment_option = $selected;
                    $deposit->payment_option_id = $payment;
                    $deposit->other_discount_allowance = $discount;
                    $expected = $roles === ['sale_team_leader'] ? 'NOT_REQUIRED' : 'PENDING';
                    checkPolicy($deposit->requiresSaleManagerApproval(), $expected === 'PENDING', 'Role requirement');
                    foreach (['NOT_REQUIRED', 'PENDING', null] as $stored) {
                        checkPolicy($deposit->getSaleManagerStatusAttribute($stored), $expected, 'Pending status');
                    }
                    foreach (['APPROVED', 'REJECTED'] as $decision) {
                        checkPolicy($deposit->getSaleManagerStatusAttribute($decision), $decision, 'Recorded decision');
                    }
                    (\Illuminate\Database\Eloquent\Model::$hooks['creating'])($deposit);
                    checkPolicy($deposit->sale_manager_status, $expected, 'Creation');
                    $deposit->sale_manager_status = 'NOT_REQUIRED';
                    $deposit->syncOriginal();
                    $deposit->status = 'APPROVED';
                    (\Illuminate\Database\Eloquent\Model::$hooks['saving'])($deposit);
                    checkPolicy($deposit->sale_manager_status, $expected, 'Stored status before conversion');
                    $deposit->syncOriginal();
                    checkPolicy($deposit->getSaleManagerStatusAttribute('NOT_REQUIRED'), 'NOT_REQUIRED', 'Completed history');
                    $checks++;
                }
            }
        }
    }
    echo "PASSED: $checks role/payment combinations, creation, pending repair, decisions and history.\n";
}
