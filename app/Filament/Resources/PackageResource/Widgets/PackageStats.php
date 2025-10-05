<?php

namespace App\Filament\Resources\PackageResource\Widgets;

use App\Models\Package;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PackageStats extends BaseWidget
{
    public ?Package $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        $package = $this->record;

        // Get subscription statistics
        $totalSubscriptions = $package->subscriptions()->count();
        $activeSubscriptions = $package->activeSubscriptions()->count();
        $expiredSubscriptions = $package->subscriptions()
            ->where('status', 'expired')
            ->count();

        // Get revenue statistics
        $totalRevenue = $package->subscriptions()
            ->join('payments', 'subscriptions.id', '=', 'payments.subscription_id')
            ->where('payments.status', 'completed')
            ->sum('payments.amount');

        $monthlyRevenue = $package->subscriptions()
            ->join('payments', 'subscriptions.id', '=', 'payments.subscription_id')
            ->where('payments.status', 'completed')
            ->whereMonth('payments.created_at', now()->month)
            ->whereYear('payments.created_at', now()->year)
            ->sum('payments.amount');

        $averageRevenue = $totalSubscriptions > 0 ? $totalRevenue / $totalSubscriptions : 0;

        // Get usage statistics
        $totalDataUsed = DB::table('data_usage')
            ->join('subscriptions', 'data_usage.subscription_id', '=', 'subscriptions.id')
            ->where('subscriptions.package_id', $package->id)
            ->sum('data_usage.bytes_downloaded');

        $totalDataUsedGB = round($totalDataUsed / (1024 * 1024 * 1024), 2);

        // Get time-based statistics
        $subscriptionsThisMonth = $package->subscriptions()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $subscriptionsLastMonth = $package->subscriptions()
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        $subscriptionGrowth = $subscriptionsLastMonth > 0 
            ? round((($subscriptionsThisMonth - $subscriptionsLastMonth) / $subscriptionsLastMonth) * 100, 1)
            : ($subscriptionsThisMonth > 0 ? 100 : 0);

        // Calculate customer retention (customers who renewed)
        $renewalRate = 0;
        if ($totalSubscriptions > 0) {
            $renewedCustomers = DB::table('subscriptions as s1')
                ->join('subscriptions as s2', 's1.customer_id', '=', 's2.customer_id')
                ->where('s1.package_id', $package->id)
                ->where('s2.package_id', $package->id)
                ->where('s1.id', '!=', 's2.id')
                ->where('s2.created_at', '>', 's1.expires_at')
                ->distinct('s1.customer_id')
                ->count();
                
            $renewalRate = round(($renewedCustomers / $totalSubscriptions) * 100, 1);
        }

        return [
            Stat::make('Total Subscriptions', number_format($totalSubscriptions))
                ->description($activeSubscriptions . ' active, ' . $expiredSubscriptions . ' expired')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
                ->chart([
                    $expiredSubscriptions,
                    $activeSubscriptions,
                ]),

            Stat::make('Monthly Subscriptions', number_format($subscriptionsThisMonth))
                ->description(
                    $subscriptionGrowth >= 0 
                        ? "+{$subscriptionGrowth}% from last month"
                        : "{$subscriptionGrowth}% from last month"
                )
                ->descriptionIcon($subscriptionGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($subscriptionGrowth >= 0 ? 'success' : 'danger')
                ->chart([
                    $subscriptionsLastMonth ?: 0,
                    $subscriptionsThisMonth,
                ]),

            Stat::make('Total Revenue', '₵' . number_format($totalRevenue, 2))
                ->description('This month: ₵' . number_format($monthlyRevenue, 2))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Average Revenue', '₵' . number_format($averageRevenue, 2))
                ->description('Per subscription')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make('Data Usage', number_format($totalDataUsedGB, 1) . ' GB')
                ->description('Total downloaded by all users')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('warning'),

            Stat::make('Renewal Rate', $renewalRate . '%')
                ->description('Customer retention rate')
                ->descriptionIcon($renewalRate >= 50 ? 'heroicon-m-heart' : 'heroicon-m-x-circle')
                ->color($renewalRate >= 50 ? 'success' : 'danger'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}