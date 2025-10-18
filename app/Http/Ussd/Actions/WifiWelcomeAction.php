<?php

namespace App\Http\Ussd\Actions;

use App\Http\Ussd\States\WifiMainState;
use App\Http\Ussd\States\CustomerBlockedState;
use App\Models\Customer;
use App\Models\Package;
use Illuminate\Support\Str;
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

                'username'=> $msisdn, // Customer's chosen RADIUS username (defaults to phone)
                'password'=> Str::random(12), // Customer's chosen RADIUS password
                'name' => 'USSD-' . substr($msisdn, -4),
                'phone' => $msisdn,
                'status' => 'active',
                'registration_date' => now()
            ]
        );

        // Check if customer is blocked
        if ($customer->isBlocked()) {
            $this->record->set('error', 'Customer account is blocked');
            return CustomerBlockedState::class;
        }

        // Get available packages (excluding trial packages for main menu)
        // Use pagination from config
        $packagesPerPage = config('ussd.packages_per_page', 3);
        $currentPage = $this->record->get('packages_page', 1);

        $packages = Package::where('is_active', true)
            ->where('is_trial', false)
            ->orderBy('price')
            ->skip(($currentPage - 1) * $packagesPerPage)
            ->take($packagesPerPage)
            ->get();

        // Get total count for pagination
        $totalPackages = Package::where('is_active', true)
            ->where('is_trial', false)
            ->count();

        $totalPages = ceil($totalPackages / $packagesPerPage);

        // Store customer and packages in record
        $this->record->setMultiple([
            'customer' => $customer,
            'customer_id' => $customer->id,
            'phone_number' => $msisdn,
            'packages' => $packages,
            'packages_page' => $currentPage,
            'total_packages' => $totalPackages,
            'total_pages' => $totalPages,
            'packages_per_page' => $packagesPerPage,
            'error' => ''
        ]);

        return WifiMainState::class;
    }
}
