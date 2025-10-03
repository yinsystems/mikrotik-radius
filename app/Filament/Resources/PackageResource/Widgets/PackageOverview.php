<?php

namespace App\Filament\Resources\PackageResource\Widgets;

use App\Models\Package;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PackageOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Get package statistics
        $totalPackages = Package::count();
        $activePackages = Package::where('is_active', true)->count();
        $trialPackages = Package::where('is_trial', true)->count();
        $inactivePackages = Package::where('is_active', false)->count();
        
        // Get subscription statistics
        $totalSubscriptions = DB::table('subscriptions')->count();
        $activeSubscriptions = DB::table('subscriptions')
            ->where('status', 'active')
            ->count();
        
        // Get revenue statistics
        $totalRevenue = DB::table('payments')
            ->where('status', 'completed')
            ->sum('amount');
            
        $monthlyRevenue = DB::table('payments')
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        
        // Calculate growth
        $lastMonthRevenue = DB::table('payments')
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('amount');
            
        $revenueGrowth = $lastMonthRevenue > 0 
            ? round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : 0;
        
        // Get most popular package
        $popularPackage = DB::table('packages')
            ->select('packages.name', DB::raw('COUNT(subscriptions.id) as subscription_count'))
            ->leftJoin('subscriptions', 'packages.id', '=', 'subscriptions.package_id')
            ->where('packages.is_active', true)
            ->groupBy('packages.id', 'packages.name')
            ->orderBy('subscription_count', 'desc')
            ->first();

        return [
            Stat::make('Total Packages', $totalPackages)
                ->description($activePackages . ' active, ' . $inactivePackages . ' inactive')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary')
                ->chart([
                    $totalPackages - $activePackages, // inactive
                    $activePackages, // active
                ]),

            Stat::make('Active Subscriptions', number_format($activeSubscriptions))
                ->description('Total: ' . number_format($totalSubscriptions))
                ->descriptionIcon('heroicon-m-users')
                ->color('success')
                ->chart([
                    $totalSubscriptions - $activeSubscriptions,
                    $activeSubscriptions,
                ]),

            Stat::make('Monthly Revenue', '₵' . number_format($monthlyRevenue, 2))
                ->description(
                    $revenueGrowth >= 0 
                        ? "+{$revenueGrowth}% from last month"
                        : "{$revenueGrowth}% from last month"
                )
                ->descriptionIcon($revenueGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueGrowth >= 0 ? 'success' : 'danger')
                ->chart([
                    $lastMonthRevenue ?: 0,
                    $monthlyRevenue,
                ]),

            Stat::make('Total Revenue', '₵' . number_format($totalRevenue, 2))
                ->description('All-time earnings')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Trial Packages', $trialPackages)
                ->description('Available for testing')
                ->descriptionIcon('heroicon-m-beaker')
                ->color('warning'),

            Stat::make('Most Popular', $popularPackage?->name ?? 'N/A')
                ->description(($popularPackage?->subscription_count ?? 0) . ' subscriptions')
                ->descriptionIcon('heroicon-m-star')
                ->color('info'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}