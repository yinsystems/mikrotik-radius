<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CustomerStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Get comprehensive customer statistics
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('status', 'active')->count();
        $suspendedCustomers = Customer::where('status', 'suspended')->count();
        $blockedCustomers = Customer::where('status', 'blocked')->count();
        
        $customersWithActiveSubscriptions = Customer::withActiveSubscription()->count();
        $customersWithoutSubscriptions = Customer::withoutActiveSubscription()->count();
        
        $newCustomersToday = Customer::whereDate('registration_date', today())->count();
        $newCustomersThisWeek = Customer::where('registration_date', '>=', now()->startOfWeek())->count();
        $newCustomersThisMonth = Customer::where('registration_date', '>=', now()->startOfMonth())->count();
        
        $trialUsers = Customer::whereHas('subscriptions.package', fn ($q) => $q->where('is_trial', true))->count();
        
        // Calculate growth percentage
        $lastMonthCustomers = Customer::whereBetween('registration_date', [
            now()->subMonth()->startOfMonth(),
            now()->subMonth()->endOfMonth()
        ])->count();
        
        $growthPercentage = $lastMonthCustomers > 0 
            ? (($newCustomersThisMonth - $lastMonthCustomers) / $lastMonthCustomers) * 100 
            : 0;

        return [
            Stat::make('Total Customers', number_format($totalCustomers))
                ->description($newCustomersThisMonth > 0 ? "$newCustomersThisMonth new this month" : 'No new customers this month')
                ->descriptionIcon($growthPercentage >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($growthPercentage >= 0 ? 'success' : 'danger')
                ->chart([
                    Customer::whereDate('registration_date', today()->subDays(6))->count(),
                    Customer::whereDate('registration_date', today()->subDays(5))->count(),
                    Customer::whereDate('registration_date', today()->subDays(4))->count(),
                    Customer::whereDate('registration_date', today()->subDays(3))->count(),
                    Customer::whereDate('registration_date', today()->subDays(2))->count(),
                    Customer::whereDate('registration_date', today()->subDays(1))->count(),
                    Customer::whereDate('registration_date', today())->count(),
                ]),

            Stat::make('Active Customers', number_format($activeCustomers))
                ->description(round(($activeCustomers / max($totalCustomers, 1)) * 100, 1) . '% of total')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('With Active Subscriptions', number_format($customersWithActiveSubscriptions))
                ->description(round(($customersWithActiveSubscriptions / max($totalCustomers, 1)) * 100, 1) . '% of customers')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('New This Week', number_format($newCustomersThisWeek))
                ->description($newCustomersToday > 0 ? "$newCustomersToday new today" : 'None today')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),

            Stat::make('Trial Users', number_format($trialUsers))
                ->description(round(($trialUsers / max($totalCustomers, 1)) * 100, 1) . '% on trial')
                ->descriptionIcon('heroicon-m-gift')
                ->color('warning'),

            Stat::make('Issues', number_format($suspendedCustomers + $blockedCustomers))
                ->description("$suspendedCustomers suspended, $blockedCustomers blocked")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($suspendedCustomers + $blockedCustomers > 0 ? 'danger' : 'success'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}