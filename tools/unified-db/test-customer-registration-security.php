<?php
// Standalone security regression checks; no database writes or SMS messages.
require __DIR__.'/backend-adapters/bsunits/Services/FirebasePhoneIdentity.php';
require __DIR__.'/backend-adapters/bsunits/Middleware/EnsureApiAccountEnabled.php';

function config($key) { return $key === 'customer_registration.firebase_project_id' ? 'test-project' : null; }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function b64($value) { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }

$private = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$public = openssl_pkey_get_details($private)['key'];
class TestPhoneIdentity extends App\Services\FirebasePhoneIdentity {
    public $publicKey;
    protected function certificates() { return ['test-key' => $this->publicKey]; }
}
$verifier = new TestPhoneIdentity();
$verifier->publicKey = $public;
$claims = ['aud' => 'test-project', 'iss' => 'https://securetoken.google.com/test-project',
    'sub' => 'firebase-user', 'iat' => time() - 5, 'exp' => time() + 3600,
    'auth_time' => time() - 5, 'phone_number' => '+85512345678',
    'firebase' => ['sign_in_provider' => 'phone']];
function token($claims, $private, $header = null) {
    $body = b64(json_encode($header ?: ['alg' => 'RS256', 'kid' => 'test-key'])).'.'.b64(json_encode($claims));
    openssl_sign($body, $signature, $private, OPENSSL_ALGO_SHA256);
    return $body.'.'.b64($signature);
}
$verified = $verifier->verify(token($claims, $private));
check($verified['phone_number'] === '012345678', 'Verified phone must be normalized.');
foreach ([
    ['aud' => 'other-project'], ['iss' => 'https://attacker.example'], ['exp' => time() - 1],
    ['iat' => time() + 100], ['auth_time' => time() - 601], ['auth_time' => time() + 100],
    ['sub' => ''], ['phone_number' => '+15551234567'],
    ['firebase' => ['sign_in_provider' => 'password']],
] as $overrides) {
    try { $verifier->verify(token(array_replace($claims, $overrides), $private)); throw new RuntimeException('Invalid claims accepted.'); }
    catch (InvalidArgumentException $e) {}
}
foreach ([token($claims, $private, ['alg' => 'HS256', 'kid' => 'test-key']),
    token($claims, $private, ['alg' => 'RS256', 'kid' => 'unknown']), 'not-a-token',
    // Change the phone without resigning the token.
    explode('.', token($claims, $private))[0].'.'.b64(json_encode(array_replace($claims, ['phone_number' => '+85598765432']))).'.'.explode('.', token($claims, $private))[2],
] as $invalid) {
    try { $verifier->verify($invalid); throw new RuntimeException('Invalid signature accepted.'); }
    catch (InvalidArgumentException $e) {}
}

class TestResponse {
    public $status; private $data;
    public function __construct($data, $status = 200) { $this->data = $data; $this->status = $status; }
    public function getData($assoc = false) { return $this->data; }
    public function setData($data) { $this->data = $data; }
}
class TestResponseFactory { public function json($data, $status) { return new TestResponse($data, $status); } }
function response() { return new TestResponseFactory(); }
class TestCustomer {
    public $active = true, $verified = true, $customer = true;
    public function hasRole($role) { return $this->customer && $role === 'customer'; }
}
class TestRequest {
    public $actor, $verb, $url, $input = [];
    public function __construct($method, $url) { $this->actor = new TestCustomer(); $this->verb = $method; $this->url = $url; }
    public function user() { return $this->actor; }
    public function isMethod($method) { return $this->verb === $method; }
    public function path() { return $this->url; }
    public function input($key, $default) { return $this->input[$key] ?? $default; }
    public function merge($values) { $this->input = array_merge($this->input, $values); }
}
$middleware = new App\Http\Middleware\EnsureApiAccountEnabled();
$next = function ($request) { return new TestResponse(['data' => ['code' => 'A01', 'price' => 150000,
    'action' => ['customer_phone_number' => 'private'], 'unit_type' => ['created_by' => ['name' => 'Staff'], 'name' => 'Villa']]]); };
foreach ([['POST', 'api/unit_hold_requests'], ['POST', 'api/units/1/comments'], ['GET', 'api/users/1'],
    ['GET', 'api/unit_deposit_requests'], ['GET', 'api/units/1/actions'], ['PUT', 'api/projects/1'],
    ['GET', 'api/unified/catalog/units'], ['GET', 'api/projects/1/units/extra']] as $route) {
    check($middleware->handle(new TestRequest($route[0], $route[1]), $next)->status === 403, 'Staff route must be denied.');
}
foreach (['api/whoami', 'api/projects', 'api/projects/2/units', 'api/units/1', 'api/unit_types/1/payment_options'] as $path) {
    check($middleware->handle(new TestRequest('GET', $path), $next)->status === 200, 'Catalog route must be allowed.');
}
$request = new TestRequest('GET', 'api/units/1');
$request->input = ['embed' => 'action.createdBy,unitType.project,unitType.media'];
$data = $middleware->handle($request, $next)->getData(true)['data'];
check($request->input['embed'] === 'unitType.project,unitType.media', 'Staff embeds must be stripped.');
check(!isset($data['action']) && !isset($data['unit_type']['created_by']), 'Staff data must be stripped recursively.');
check($data['price'] === 150000 && $data['unit_type']['name'] === 'Villa', 'Catalog details must remain.');
$request->actor->active = false;
check($middleware->handle($request, $next)->status === 401, 'Disabled customers must be denied.');
$request->actor->active = true; $request->actor->customer = false;
check($middleware->handle(new TestRequest('POST', 'api/whoami/logout'), $next)->status === 200, 'Customers must be able to log out.');
check($middleware->handle($request, $next)->getData(true)['data']['action']['customer_phone_number'] === 'private', 'Staff responses must be unchanged.');
echo "Customer token verification and access restriction checks passed.\n";
