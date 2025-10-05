<?php

require 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Package;
use App\Models\Customer;
use Carbon\Carbon;

echo 'Testing minute package expiration calculation:' . PHP_EOL;
echo '==============================================' . PHP_EOL;

// Find a minute package
$minutePackage = Package::where('duration_type', 'minutely')->first();

if (!$minutePackage) {
    echo 'No minute packages found!' . PHP_EOL;
    exit;
}

echo 'Found package: ' . $minutePackage->name . PHP_EOL;
echo 'Duration: ' . $minutePackage->duration_value . ' minutes' . PHP_EOL;
echo 'Duration Display: ' . $minutePackage->duration_display . PHP_EOL;
echo '---' . PHP_EOL;

// Find a customer to test with
$customer = Customer::first();

if (!$customer) {
    echo 'No customers found!' . PHP_EOL;
    exit;
}

echo 'Testing with customer: ' . $customer->name . ' (' . $customer->phone . ')' . PHP_EOL;
echo '---' . PHP_EOL;

// Test Customer model calculateExpiration method
echo 'Testing Customer::calculateExpiration method:' . PHP_EOL;

$startTime = Carbon::now();
echo 'Start time: ' . $startTime . PHP_EOL;

// Call the private method using reflection
$customerReflection = new ReflectionClass($customer);
$calculateExpirationMethod = $customerReflection->getMethod('calculateExpiration');
$calculateExpirationMethod->setAccessible(true);

$calculatedExpiry = $calculateExpirationMethod->invoke($customer, $minutePackage);
echo 'Calculated expiry: ' . $calculatedExpiry . PHP_EOL;

$minutesDifference = $startTime->diffInMinutes($calculatedExpiry);
echo 'Minutes difference: ' . $minutesDifference . PHP_EOL;

// Check if it matches the package duration
if ($minutesDifference == $minutePackage->duration_value) {
    echo '✅ SUCCESS: Duration calculation is correct!' . PHP_EOL;
} else {
    echo '❌ ERROR: Expected ' . $minutePackage->duration_value . ' minutes, got ' . $minutesDifference . ' minutes' . PHP_EOL;
}

echo '---' . PHP_EOL;

// Test different minute packages
echo 'Testing different minute durations:' . PHP_EOL;

$testCases = [5, 15, 30, 60];

foreach ($testCases as $testMinutes) {
    // Create a temporary package object for testing
    $testPackage = new Package([
        'duration_type' => 'minutely',
        'duration_value' => $testMinutes,
        'name' => "{$testMinutes}-Minute Test"
    ]);

    $testStart = Carbon::now();
    $testExpiry = $calculateExpirationMethod->invoke($customer, $testPackage);
    $testDiff = $testStart->diffInMinutes($testExpiry);
    
    echo "  {$testMinutes} minutes: {$testDiff} minutes calculated";
    echo ($testDiff == $testMinutes ? ' ✅' : ' ❌');
    echo PHP_EOL;
}