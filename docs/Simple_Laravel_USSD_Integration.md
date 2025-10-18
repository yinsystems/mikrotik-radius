# Simple Laravel USSD Integration Plan

## Package Installation
```bash
composer require sparors/laravel-ussd:^3.0-beta
```

## Basic Setup

### 1. USSD States (app/Http/Ussd/States/)

#### WifiMainState.php (Main Menu)
```php
<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\SelectPackageAction;
use App\Http\Ussd\Actions\GenerateTrialTokenAction;
use App\Http\Ussd\Actions\CheckActiveTokenAction;
use Sparors\Ussd\State;

class WifiMainState extends State
{
    protected function beforeRendering(): void
    {
        $customer = $this->record->get('customer');
        
        $this->menu->line('🌐 WiFi Portal')
            ->line('Welcome ' . $customer->name)
            ->listing([
                'Buy Internet Package',
                'Get Free Trial (30min)', 
                'My Active Token',
                'Help & Support'
            ]);
    }

    protected function afterRendering(string $argument): void
    {
        $this->decision->equal('1', SelectPackageAction::class);
        $this->decision->equal('2', GenerateTrialTokenAction::class);
        $this->decision->equal('3', CheckActiveTokenAction::class);
        $this->decision->equal('4', HelpState::class);
    }
}
```

#### SelectPackageState.php
```php
<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\ConfirmPackageAction;
use App\Http\Ussd\Actions\WifiWelcomeAction;
use Sparors\Ussd\State;

class SelectPackageState extends State
{
    protected function beforeRendering(): void
    {
        $packages = $this->record->get('packages');
        
        $this->menu->line('Select Internet Package:');
        
        foreach ($packages as $index => $package) {
            $this->menu->line(sprintf('%d. %s - GH₵%.2f', 
                $index + 1, 
                $package->name, 
                $package->price
            ));
            $this->menu->line(sprintf('   %s, %s', 
                $package->data_limit ? $package->data_limit . 'MB' : 'Unlimited',
                $package->duration_value . ' ' . $package->duration_type
            ));
        }
        
        $this->menu->line('0. Back to Main Menu');
    }

    protected function afterRendering(string $argument): void
    {
        $packages = $this->record->get('packages');
        $packageCount = $packages->count();
        
        // Store selected package
        if ($argument >= 1 && $argument <= $packageCount) {
            $selectedPackage = $packages[$argument - 1];
            $this->record->setMultiple([
                'selected_package_id' => $selectedPackage->id,
                'selected_package_name' => $selectedPackage->name,
                'selected_package_price' => $selectedPackage->price
            ]);
        }
        
        $this->decision->between(1, $packageCount, ConfirmPackageAction::class);
        $this->decision->equal('0', WifiWelcomeAction::class);
    }
}
```

#### ConfirmPackageState.php
```php
<?php

namespace App\Http\Ussd\States;

use App\Http\Ussd\Actions\ProcessPaymentAction;
use App\Http\Ussd\Actions\SelectPackageAction;
use Sparors\Ussd\State;

class ConfirmPackageState extends State
{
    protected function beforeRendering(): void
    {
        $packageName = $this->record->get('selected_package_name');
        $packagePrice = $this->record->get('selected_package_price');
        
        $this->menu
            ->line("Confirm Purchase:")
            ->line($packageName)
            ->line("Price: GH₵{$packagePrice}")
            ->lineBreak()
            ->line("1. Confirm & Pay")
            ->line("0. Back to Packages");
    }

    protected function afterRendering(string $argument): void
    {
        $this->decision->equal('1', ProcessPaymentAction::class);
        $this->decision->equal('0', SelectPackageAction::class);
    }
}
```

#### PaymentSuccessState.php
```php
<?php

namespace App\Http\Ussd\States;

use Sparors\Ussd\Contracts\State;
use Sparors\Ussd\Menu;
use Sparors\Ussd\Record;

class PaymentSuccessState implements State
{
    public function render(Record $record): Menu
    {
        $token = $record->get('wifi_token');
        
        return Menu::build()
            ->text('🎉 Payment Successful!')
            ->lineBreak()
            ->line('Your WiFi Token:')
            ->line($token)
            ->lineBreak()
            ->line('SMS sent with details.')
            ->text('Thank you!');
    }
}
```

#### TrialTokenState.php
```php
<?php

namespace App\Http\Ussd\States;

use Sparors\Ussd\Contracts\State;
use Sparors\Ussd\Menu;
use Sparors\Ussd\Record;

class TrialTokenState implements State
{
    public function render(Record $record): Menu
    {
        $token = $record->get('trial_token');
        
        return Menu::build()
            ->text('🎁 Free Trial Token!')
            ->lineBreak()
            ->line('Your 30min Token:')
            ->line($token)
            ->lineBreak()
            ->line('Valid for 30 minutes')
            ->text('Enjoy your free internet!');
    }
}
```

### 2. USSD Actions (app/Http/Ussd/Actions/)

#### WifiWelcomeAction.php (Initial Action)
```php
<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\WifiMainState;
use App\Http\Ussd\States\CustomerBlockedState;
use App\Models\Customer;
use App\Models\Package;
use Sparors\Ussd\Action;

class WifiWelcomeAction extends Action
{
    public function run(): string
    {
        $msisdn = request('MSISDN');
        
        // Get or create customer
        $customer = Customer::firstOrCreate(
            ['phone' => $msisdn], 
            [
                'name' => 'USSD User', 
                'phone' => $msisdn,
                'status' => 'active',
                'registration_date' => now()
            ]
        );
        
        // Check if customer is blocked
        if ($customer->isBlocked()) {
            return CustomerBlockedState::class;
        }
        
        // Get available packages
        $packages = Package::where('is_active', true)
            ->where('is_trial', false)
            ->limit(4)
            ->get();
            
        // Store customer and packages in record
        $this->record->setMultiple([
            'customer' => $customer,
            'phone_number' => $msisdn,
            'packages' => $packages,
            'error' => ''
        ]);
        
        return WifiMainState::class;
    }
}
```

#### ProcessPaymentAction.php
```php
<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\PaymentSuccessState;
use App\Http\Ussd\States\PaymentFailedState;
use App\Models\Customer;
use App\Models\Package;
use Sparors\Ussd\Action;

class ProcessPaymentAction extends Action
{
    public function run(): string
    {
        $customer = $this->record->get('customer');
        $packageId = $this->record->get('selected_package_id');
        $package = Package::find($packageId);
        
        try {
            // Create subscription for customer
            $subscription = $customer->createSubscription($packageId);
            
            // For USSD, we'll simulate payment success for now
            // In production, integrate with your mobile money API
            $paymentSuccess = true; // Replace with actual payment processing
            
            if ($paymentSuccess) {
                // Activate subscription
                $subscription->activate();
                
                // Generate internet token
                $token = $customer->generateInternetToken();
                
                $this->record->setMultiple([
                    'wifi_token' => $token,
                    'package_name' => $package->name,
                    'subscription' => $subscription
                ]);
                
                // TODO: Send SMS with token details
                
                return PaymentSuccessState::class;
            }
            
            return PaymentFailedState::class;
            
        } catch (\Exception $e) {
            \Log::error('USSD Payment Error: ' . $e->getMessage());
            $this->record->set('error', 'Payment processing failed');
            return PaymentFailedState::class;
        }
    }
}
```

#### GenerateTrialTokenAction.php
```php
<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\TrialTokenState;
use App\Http\Ussd\States\TrialNotEligibleState;
use App\Models\Customer;
use App\Models\Package;
use Sparors\Ussd\Action;

class GenerateTrialTokenAction extends Action
{
    public function run(): string
    {
        $customer = $this->record->get('customer');
        
        // Check if customer is eligible for trial
        if (!$customer->isEligibleForTrial()) {
            return TrialNotEligibleState::class;
        }
        
        try {
            // Find trial package
            $trialPackage = Package::where('is_trial', true)
                ->where('is_active', true)
                ->first();
                
            if (!$trialPackage) {
                $this->record->set('error', 'Trial package not available');
                return TrialNotEligibleState::class;
            }
            
            // Assign trial package to customer
            $subscription = $customer->assignTrialPackage($trialPackage->id);
            
            // Generate internet token
            $token = $customer->generateInternetToken();
            
            $this->record->setMultiple([
                'trial_token' => $token,
                'trial_package' => $trialPackage,
                'subscription' => $subscription
            ]);
            
            // TODO: Send SMS with trial token details
            
            return TrialTokenState::class;
            
        } catch (\Exception $e) {
            \Log::error('USSD Trial Error: ' . $e->getMessage());
            $this->record->set('error', $e->getMessage());
            return TrialNotEligibleState::class;
        }
    }
}
```

## 3. USSD Controller (Your Pattern)

```php
<?php

namespace App\Http\Controllers;

use App\Http\Ussd\Actions\WifiWelcomeAction;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Sparors\Ussd\Facades\Ussd;
use Exception;

class WifiUssdController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            Log::info('WiFi USSD Request:', $request->all());
            
            $session = uniqid();
            $user_input = $request->get('USERDATA');
            $msisdn = $request->get('MSISDN');
            
            // Check if this is initial USSD code dial (*123#)
            if ($user_input === "123") { // Your WiFi USSD code without #
                Cache::put($msisdn, $session);
            }
            
            // Get or create customer
            $customer = Customer::firstOrCreate(
                ['phone' => $msisdn], 
                [
                    'name' => 'USSD User', 
                    'phone' => $msisdn,
                    'status' => 'active',
                    'registration_date' => now()
                ]
            );
            
            $ussd = Ussd::machine()
                ->setSessionId(Cache::get($msisdn))
                ->setFromRequest([
                    'phone_number' => 'MSISDN',
                    'network' => 'NETWORK',
                ])
                ->setInput(
                    strpos($request->get('USERDATA'), '*') !== false ?
                        substr($request->get('USERDATA'), strrpos($request->get('USERDATA'), '*') + 1) :
                        $request->get('USERDATA')
                )
                ->setInitialState(WifiWelcomeAction::class)
                ->setResponse(function (string $message, string $action) {
                    return [
                        "USERID" => request('USERID'),
                        "MSISDN" => request('MSISDN'),
                        "USERDATA" => request('USERDATA'),
                        "MSG" => $message,
                        "MSGTYPE" => $action === "input",
                    ];
                });
                
            return $ussd->run();
            
        } catch (Exception $e) {
            Log::error('WiFi USSD Error: ' . $e->getMessage());
            
            return [
                "USERID" => request('USERID'),
                "MSISDN" => request('MSISDN'),
                "USERDATA" => request('USERDATA'),
                "MSG" => "Service temporarily unavailable. Please try again later.",
                "MSGTYPE" => false, // END
            ];
        }
    }
}
```

## 4. Add Missing Actions

#### SelectPackageAction.php
```php
<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\SelectPackageState;
use Sparors\Ussd\Action;

class SelectPackageAction extends Action
{
    public function run(): string
    {
        return SelectPackageState::class;
    }
}
```

#### ConfirmPackageAction.php
```php
<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\ConfirmPackageState;
use Sparors\Ussd\Action;

class ConfirmPackageAction extends Action
{
    public function run(): string
    {
        return ConfirmPackageState::class;
    }
}
```

#### CheckActiveTokenAction.php
```php
<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\ActiveTokenState;
use App\Http\Ussd\States\NoActiveTokenState;
use Sparors\Ussd\Action;

class CheckActiveTokenAction extends Action
{
    public function run(): string
    {
        $customer = $this->record->get('customer');
        
        if ($customer->hasActiveSubscription() && $customer->hasValidInternetToken()) {
            $activeSubscription = $customer->getActiveSubscription();
            $this->record->setMultiple([
                'current_token' => $customer->getInternetToken(),
                'package_name' => $activeSubscription->package->name,
                'expires_at' => $activeSubscription->expires_at->format('d/m/Y H:i')
            ]);
            
            return ActiveTokenState::class;
        }
        
        return NoActiveTokenState::class;
    }
}
```

## 5. Route
```php
// routes/web.php or routes/api.php
Route::post('/wifi-ussd', WifiUssdController::class);
```

## That's It!
Just States for screens and Actions for business logic. Simple and clean! 🚀