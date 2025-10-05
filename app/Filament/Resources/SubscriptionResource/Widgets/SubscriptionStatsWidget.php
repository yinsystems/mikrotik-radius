<?php

namespace App\Filament\Resources\SubscriptionResource\Widgets;

use App\Models\Subscription;
use App\Models\Customer;
use App\Models\Package;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class SubscriptionStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        // Active subscriptions
        $activeCount = Subscription::active()->count();
        $activeGrowth = $this->calculateGrowth('active');

        // Total subscriptions
        $totalCount = Subscription::count();
        $totalGrowth = $this->calculateGrowth('total');

        // Expired/Expiring subscriptions
        $expiredCount = Subscription::expired()->count();
        $expiringSoonCount = Subscription::expiringSoon(24)->count();

        // Trial subscriptions
        $trialCount = Subscription::trial()->where('status', 'active')->count();

        // Revenue calculations
        $monthlyRevenue = $this->calculateMonthlyRevenue();
        $revenueGrowth = $this->calculateRevenueGrowth();

        // Data usage statistics
        $totalDataUsed = $this->calculateTotalDataUsage();
        $avgDataPerUser = $activeCount > 0 ? $totalDataUsed / $activeCount : 0;

        // Session statistics
        $activeSessions = $this->getActiveSessionsCount();

        return [
            Stat::make('Active Subscriptions', $activeCount)
                ->description($activeGrowth['description'])
                ->descriptionIcon($activeGrowth['icon'])
                ->color($activeGrowth['color'])
                ->chart($this->getSubscriptionChart('active')),

            Stat::make('Total Subscriptions', $totalCount)
                ->description($totalGrowth['description'])
                ->descriptionIcon($totalGrowth['icon'])
                ->color($totalGrowth['color'])
                ->chart($this->getSubscriptionChart('total')),

            Stat::make('Expiring Soon', $expiringSoonCount)
                ->description('Expires within 24 hours')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($expiringSoonCount > 0 ? 'warning' : 'success')
                ->url(route('filament.admin.resources.subscriptions.index', [
                    'tableFilters[expires_soon][value]' => true
                ])),

            Stat::make('Expired', $expiredCount)
                ->description('Require attention')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($expiredCount > 0 ? 'danger' : 'success')
                ->url(route('filament.admin.resources.subscriptions.index', [
                    'activeTab' => 'expired'
                ])),

            Stat::make('Trial Users', $trialCount)
                ->description('Active trial subscriptions')
                ->descriptionIcon('heroicon-m-star')
                ->color('info')
                ->url(route('filament.admin.resources.subscriptions.index', [
                    'activeTab' => 'trial'
                ])),

            Stat::make('Monthly Revenue', '₵' . Number::format($monthlyRevenue, 2))
                ->description($revenueGrowth['description'])
                ->descriptionIcon($revenueGrowth['icon'])
                ->color($revenueGrowth['color'])
                ->chart($this->getRevenueChart()),

            Stat::make('Total Data Used', $this->formatDataUsage($totalDataUsed))
                ->description('This month')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),

            Stat::make('Active Sessions', $activeSessions)
                ->description('Currently online')
                ->descriptionIcon('heroicon-m-signal')
                ->color('success')
                ->chart($this->getSessionChart()),
        ];
    }

    protected function calculateGrowth(string $type): array
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        $query = Subscription::query();
        
        if ($type === 'active') {
            $current = $query->active()->whereBetween('created_at', [$currentMonth, now()])->count();
            $previous = Subscription::active()->whereBetween('created_at', [$lastMonth, $currentMonth])->count();
        } else {
            $current = $query->whereBetween('created_at', [$currentMonth, now()])->count();
            $previous = Subscription::whereBetween('created_at', [$lastMonth, $currentMonth])->count();
        }

        if ($previous == 0) {
            return [
                'description' => $current > 0 ? 'New this month' : 'No change',
                'icon' => $current > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus',
                'color' => $current > 0 ? 'success' : 'gray'
            ];
        }

        $percentageChange = (($current - $previous) / $previous) * 100;
        $isIncrease = $percentageChange > 0;

        return [
            'description' => abs($percentageChange) . '% ' . ($isIncrease ? 'increase' : 'decrease'),
            'icon' => $isIncrease ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down',
            'color' => $isIncrease ? 'success' : 'danger'
        ];
    }

    protected function calculateMonthlyRevenue(): float
    {
        return Subscription::whereHas('payments', function ($query) {
            $query->where('status', 'completed')
                  ->whereBetween('payment_date', [now()->startOfMonth(), now()]);
        })->with('payments')->get()->sum(function ($subscription) {
            return $subscription->payments()
                ->where('status', 'completed')
                ->whereBetween('payment_date', [now()->startOfMonth(), now()])
                ->sum('amount');
        });
    }

    protected function calculateRevenueGrowth(): array
    {
        $currentMonth = $this->calculateMonthlyRevenue();
        
        $lastMonthRevenue = Subscription::whereHas('payments', function ($query) {
            $query->where('status', 'completed')
                  ->whereBetween('payment_date', [
                      now()->subMonth()->startOfMonth(),
                      now()->subMonth()->endOfMonth()
                  ]);
        })->with('payments')->get()->sum(function ($subscription) {
            return $subscription->payments()
                ->where('status', 'completed')
                ->whereBetween('payment_date', [
                    now()->subMonth()->startOfMonth(),
                    now()->subMonth()->endOfMonth()
                ])
                ->sum('amount');
        });

        if ($lastMonthRevenue == 0) {
            return [
                'description' => $currentMonth > 0 ? 'First month revenue' : 'No revenue',
                'icon' => $currentMonth > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus',
                'color' => $currentMonth > 0 ? 'success' : 'gray'
            ];
        }

        $percentageChange = (($currentMonth - $lastMonthRevenue) / $lastMonthRevenue) * 100;
        $isIncrease = $percentageChange > 0;

        return [
            'description' => abs($percentageChange) . '% vs last month',
            'icon' => $isIncrease ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down',
            'color' => $isIncrease ? 'success' : 'danger'
        ];
    }

    protected function calculateTotalDataUsage(): float
    {
        return Subscription::active()
            ->whereBetween('created_at', [now()->startOfMonth(), now()])
            ->sum('data_used') ?? 0;
    }

    protected function getActiveSessionsCount(): int
    {
        return \App\Models\RadAcct::whereNull('acctstoptime')->count();
    }

    protected function formatDataUsage(float $bytes): string
    {
        if ($bytes >= 1073741824) { // GB
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) { // MB
            return round($bytes / 1048576, 2) . ' MB';
        } else {
            return round($bytes / 1024, 2) . ' KB';
        }
    }

    protected function getSubscriptionChart(string $type): array
    {
        $data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            
            if ($type === 'active') {
                $count = Subscription::active()
                    ->whereDate('created_at', '<=', $date)
                    ->count();
            } else {
                $count = Subscription::whereDate('created_at', '<=', $date)->count();
            }
            
            $data[] = $count;
        }
        
        return $data;
    }

    protected function getRevenueChart(): array
    {
        $data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            
            $revenue = Subscription::whereHas('payments', function ($query) use ($date) {
                $query->where('status', 'completed')
                      ->whereDate('payment_date', $date);
            })->with('payments')->get()->sum(function ($subscription) use ($date) {
                return $subscription->payments()
                    ->where('status', 'completed')
                    ->whereDate('payment_date', $date)
                    ->sum('amount');
            });
            
            $data[] = $revenue;
        }
        
        return $data;
    }

    protected function getSessionChart(): array
    {
        $data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            
            $count = \App\Models\RadAcct::whereDate('acctstarttime', $date)
                ->count();
            
            $data[] = $count;
        }
        
        return $data;
    }
}