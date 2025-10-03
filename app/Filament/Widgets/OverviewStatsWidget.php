<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\RadAcct;
use App\Models\Payment;
use App\Models\DataUsage;
use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class OverviewStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Total customers
        $totalCustomers = Customer::count();
        $newCustomersThisMonth = Customer::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Active sessions
        $activeSessions = RadAcct::whereNull('acctstoptime')->count();
        $sessionsToday = RadAcct::whereDate('acctstarttime', today())->count();

        // Revenue
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $revenueThisMonth = Payment::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        $revenueLastMonth = Payment::where('status', 'completed')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('amount');

        $revenueChange = $revenueLastMonth > 0 
            ? (($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100 
            : 0;

        // Data usage
        $totalDataUsage = DataUsage::sum('total_bytes');
        $dataUsageThisMonth = DataUsage::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('total_bytes');

        // Active subscriptions
        $activeSubscriptions = Subscription::where('status', 'active')->count();
        $expiringSubscriptions = Subscription::where('status', 'active')
            ->where('expires_at', '<=', now()->addDays(7))
            ->count();

        return [
            Stat::make('Total Customers', Number::format($totalCustomers))
                ->description("+{$newCustomersThisMonth} new this month")
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success')
                ->chart([
                    Customer::whereDate('created_at', '>=', now()->subDays(7))
                        ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                        ->groupBy('date')
                        ->orderBy('date')
                        ->pluck('count')
                        ->toArray()
                ]),

            Stat::make('Active Sessions', Number::format($activeSessions))
                ->description("{$sessionsToday} sessions today")
                ->descriptionIcon('heroicon-m-wifi')
                ->color('info')
                ->chart([
                    RadAcct::whereDate('acctstarttime', '>=', now()->subDays(7))
                        ->selectRaw('DATE(acctstarttime) as date, COUNT(*) as count')
                        ->groupBy('date')
                        ->orderBy('date')
                        ->pluck('count')
                        ->toArray()
                ]),

            Stat::make('Monthly Revenue', '₵' . Number::format($revenueThisMonth, 2))
                ->description(number_format($revenueChange, 1) . '% from last month')
                ->descriptionIcon($revenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueChange >= 0 ? 'success' : 'danger')
                ->chart([
                    Payment::where('status', 'completed')
                        ->whereDate('created_at', '>=', now()->subDays(30))
                        ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
                        ->groupBy('date')
                        ->orderBy('date')
                        ->pluck('total')
                        ->toArray()
                ]),

            Stat::make('Data Usage (Monthly)', $this->formatBytes($dataUsageThisMonth))
                ->description('Total: ' . $this->formatBytes($totalDataUsage))
                ->descriptionIcon('heroicon-m-cloud-arrow-down')
                ->color('warning')
                ->chart([
                    DataUsage::whereDate('date', '>=', now()->subDays(30))
                        ->selectRaw('DATE(date) as date, SUM(total_bytes) as total')
                        ->groupBy('date')
                        ->orderBy('date')
                        ->pluck('total')
                        ->map(fn($bytes) => $bytes / (1024 * 1024 * 1024)) // Convert to GB
                        ->toArray()
                ]),

            Stat::make('Active Subscriptions', Number::format($activeSubscriptions))
                ->description("{$expiringSubscriptions} expiring soon")
                ->descriptionIcon('heroicon-m-clock')
                ->color($expiringSubscriptions > 0 ? 'warning' : 'success'),

            Stat::make('System Status', 'Operational')
                ->description('All services running')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}