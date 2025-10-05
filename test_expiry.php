<?php

require 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use Carbon\Carbon;

echo 'Testing Minutely Package Expiration Behavior' . PHP_EOL;
echo '============================================' . PHP_EOL;

// Get first customer
$customer = Customer::first();
if (!$customer) {
    echo 'No customers found!' . PHP_EOL;
    exit;
}

echo 'Customer: ' . $customer->name . ' (' . $customer->username . ')' . PHP_EOL;

// Get minutely package
$package = Package::where('duration_type', 'minutely')->first();
if (!$package) {
    echo 'No minutely packages found!' . PHP_EOL;
    exit;
}

echo 'Package: ' . $package->name . ' (' . $package->duration_display . ')' . PHP_EOL;

// Check current subscription
$activeSubscription = $customer->getActiveSubscription();
if ($activeSubscription) {
    echo 'Customer has active subscription: ' . $activeSubscription->package->name . PHP_EOL;
    echo 'Expires at: ' . $activeSubscription->expires_at . PHP_EOL;
    echo 'Is expired: ' . ($activeSubscription->isExpired() ? 'YES' : 'NO') . PHP_EOL;
} else {
    echo 'Customer has no active subscription' . PHP_EOL;
}

// Test expiration calculation
$now = now();
$startTime = $now->copy()->subMinutes(10);  // Started 10 minutes ago
$expiryTime = $startTime->copy()->addMinutes($package->duration_value);

echo PHP_EOL . 'Expiration Test:' . PHP_EOL;
echo 'Start time: ' . $startTime->format('Y-m-d H:i:s') . PHP_EOL;
echo 'Expiry time: ' . $expiryTime->format('Y-m-d H:i:s') . PHP_EOL;
echo 'Current time: ' . $now->format('Y-m-d H:i:s') . PHP_EOL;
echo 'Would be expired: ' . ($expiryTime < $now ? 'YES' : 'NO') . PHP_EOL;

// Check if package duration would cause immediate expiry
$futureExpiry = $now->copy()->addMinutes($package->duration_value);
echo 'If started now, would expire at: ' . $futureExpiry->format('Y-m-d H:i:s') . PHP_EOL;