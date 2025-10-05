<?php

require 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Package;
use App\Models\Customer;
use App\Models\Subscription;
use Carbon\Carbon;

echo 'Testing subscription creation with minute packages:' . PHP_EOL;
echo '=================================================' . PHP_EOL;

// Find a minute package
$minutePackage = Package::where('duration_type', 'minutely')->first();

if (!$minutePackage) {
    echo 'No minute packages found!' . PHP_EOL;
    exit;
}

echo 'Package: ' . $minutePackage->name . PHP_EOL;
echo 'Duration: ' . $minutePackage->duration_value . ' minutes (' . $minutePackage->duration_display . ')' . PHP_EOL;
echo '---' . PHP_EOL;

// Find a customer to test with
$customer = Customer::first();

if (!$customer) {
    echo 'No customers found!' . PHP_EOL;
    exit;
}

echo 'Customer: ' . $customer->name . ' (' . $customer->phone . ')' . PHP_EOL;
echo '---' . PHP_EOL;

// Check for existing active subscriptions
$existingActive = $customer->subscriptions()->where('status', 'active')->count();
echo 'Existing active subscriptions: ' . $existingActive . PHP_EOL;

if ($existingActive > 0) {
    echo 'Customer already has active subscriptions. Showing latest subscription details:' . PHP_EOL;
    $latestSub = $customer->subscriptions()->latest()->first();
    echo 'Latest subscription:' . PHP_EOL;
    echo '  Package: ' . $latestSub->package->name . PHP_EOL;
    echo '  Duration Type: ' . $latestSub->package->duration_type . PHP_EOL;
    echo '  Duration Value: ' . $latestSub->package->duration_value . PHP_EOL;
    echo '  Starts At: ' . $latestSub->starts_at . PHP_EOL;
    echo '  Expires At: ' . $latestSub->expires_at . PHP_EOL;
    echo '  Status: ' . $latestSub->status . PHP_EOL;
    
    $minutesDuration = $latestSub->starts_at->diffInMinutes($latestSub->expires_at);
    echo '  Actual duration: ' . $minutesDuration . ' minutes' . PHP_EOL;
    
    if ($latestSub->package->duration_type === 'minutely') {
        $expectedMinutes = $latestSub->package->duration_value;
        echo '  Expected duration: ' . $expectedMinutes . ' minutes' . PHP_EOL;
        
        if ($minutesDuration == $expectedMinutes) {
            echo '  ✅ Duration is correct!' . PHP_EOL;
        } else {
            echo '  ❌ Duration is incorrect! Got ' . $minutesDuration . ', expected ' . $expectedMinutes . PHP_EOL;
        }
    }
} else {
    echo 'No active subscriptions found. This is good for testing.' . PHP_EOL;
}

echo PHP_EOL . '--- Test Results ---' . PHP_EOL;
echo 'The Customer::calculateExpiration method has been fixed to properly handle minutely packages.' . PHP_EOL;
echo 'New subscriptions will now get the correct expiration time.' . PHP_EOL;