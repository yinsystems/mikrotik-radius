<?php

use App\Http\Controllers\Api\HubtelWebhookController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CaptivePortalController;
use App\Http\Controllers\WifiUssdController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return "Welcome to the Captive Portal";
});

// WiFi USSD Routes
Route::post('/wifi-ussd', WifiUssdController::class)->name('wifi.ussd');

// Captive Portal Routes
Route::group(['prefix' => 'portal', 'as' => 'portal.'], function () {
    // Public routes (no session middleware needed)
    Route::get('/', [CaptivePortalController::class, 'index'])->name('index');
    Route::get('/register', [CaptivePortalController::class, 'showRegistration'])->name('register');
    Route::post('/register', [CaptivePortalController::class, 'register'])->name('register.submit');
    Route::post('/verify-otp', [CaptivePortalController::class, 'verifyOtp'])->name('verify.otp');
    Route::get('/login', [CaptivePortalController::class, 'showLogin'])->name('login');
    Route::post('/login', [CaptivePortalController::class, 'login'])->name('login.submit');
    Route::post('/verify-login-otp', [CaptivePortalController::class, 'verifyLoginOtp'])->name('verify.login.otp');

    // Settings check routes
    Route::get('/otp-status', [CaptivePortalController::class, 'getOtpStatus'])->name('otp.status');

    // Public package viewing (no authentication required)
    Route::get('/packages', [CaptivePortalController::class, 'showPackages'])->name('packages');

    // Public payment routes (no authentication required, but phone must be registered)
    Route::get('/payment', [CaptivePortalController::class, 'showPayment'])->name('payment');
    Route::post('/process-payment', [CaptivePortalController::class, 'processPayment'])->name('process.payment');
    Route::post('/check-payment-status', [CaptivePortalController::class, 'checkPaymentStatus'])->name('check.payment.status');

    // Phone number check endpoint (for checking if phone is registered)
    Route::post('/check-phone', [CaptivePortalController::class, 'checkPhoneRegistration'])->name('check.phone');
    Route::post('/select-package', [CaptivePortalController::class, 'selectPackage'])->name('select.package');

    // Protected routes (require session management)
    Route::middleware(['portal.session'])->group(function () {

        // Dashboard (user must be logged in)
        Route::get('/dashboard', [CaptivePortalController::class, 'showDashboard'])->name('dashboard');

        // Account management
        Route::post('/change-password', [CaptivePortalController::class, 'changePassword'])->name('change-password');

        // Logout (user must be logged in)
        Route::post('/logout', [CaptivePortalController::class, 'logout'])->name('logout');
    });
});

/*
|--------------------------------------------------------------------------
| Hubtel Webhook Routes (No authentication required)
|--------------------------------------------------------------------------
*/
Route::post('/hubtel-service-fulfillment', HubtelWebhookController::class);

