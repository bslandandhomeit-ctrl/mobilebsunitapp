<?php
$backend = $argv[1] ?? dirname(__DIR__, 3).'/bsunitapp';
require $backend.'/vendor/autoload.php';
$factory = new Illuminate\Validation\Factory(new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(), 'en'));
$property = new ReflectionProperty(App\Http\Controllers\Api\UnitDepositRequestController::class, 'validationRules');
$property->setAccessible(true);
$controller = (new ReflectionClass(App\Http\Controllers\Api\UnitDepositRequestController::class))->newInstanceWithoutConstructor();
$rules = array_filter($property->getValue($controller), function ($key) { return strpos($key, 'customer') === 0 || strpos($key, 'additional_customers') === 0; }, ARRAY_FILTER_USE_KEY);
$payload = ['customer_name'=>'First', 'customer_gender'=>1, 'customer_phone_number'=>'012345678', 'customer2_name'=>'Second', 'customer2_gender'=>2, 'additional_customers'=>[['name'=>'Third', 'gender'=>1, 'phone_number'=>'011234567'], ['name'=>'Fourth', 'gender'=>2, 'phone_number'=>'']]];
if ($factory->make($payload, $rules)->fails()) throw new Exception('Valid four-buyer request rejected');
foreach (['name'=>'', 'gender'=>3, 'phone_number'=>'bad'] as $key => $value) {
 $bad = $payload; $bad['additional_customers'][0][$key] = $value;
 if (!$factory->make($bad, $rules)->fails()) throw new Exception('Invalid additional buyer accepted: '.$key);
}
$model = (new ReflectionClass(App\UnitDepositRequest::class))->newInstanceWithoutConstructor();
$model->fill(['additional_customers'=>$payload['additional_customers']]);
if ($model->additional_customers !== $payload['additional_customers']) throw new Exception('Buyer JSON failed to round-trip');
$duplicates = new ReflectionProperty(App\UnitDepositRequest::class, 'duplicate_attributes');
$duplicates->setAccessible(true);
if (!in_array('additional_customers', $duplicates->getValue($model), true)) throw new Exception('Transfers drop additional buyers');
echo "Backend checks passed: four buyers, invalid field rejection, JSON round-trip, transfer preservation.\n";
