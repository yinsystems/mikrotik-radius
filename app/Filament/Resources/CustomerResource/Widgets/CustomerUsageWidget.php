<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerUsageWidget extends BaseWidget
{
    public ?Customer $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        $usageStats = $this->record->getCurrentUsageStats();

        if (!$usageStats['has_active_subscription']) {
            return [
                Stat::make('No Active Subscription', 'No Data Available')
                    ->description('Customer does not have an active subscription')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('warning'),
            ];
        }

        $dataLimits = $usageStats['data_limits'];
        $todayStats = $usageStats['today'];
        $monthlyStats = $usageStats['monthly_stats'];

        return [
            Stat::make('Data Usage', $dataLimits['used_mb'] . ' MB')
                ->description($dataLimits['limit_mb'] 
                    ? 'of ' . number_format($dataLimits['limit_mb']) . ' MB (' . $dataLimits['usage_percentage'] . '%)'
                    : 'Unlimited package')
                ->descriptionIcon($dataLimits['is_over_limit'] 
                    ? 'heroicon-m-exclamation-triangle' 
                    : 'heroicon-m-signal')
                ->color($dataLimits['is_over_limit'] 
                    ? 'danger' 
                    : ($dataLimits['is_approaching_limit'] ? 'warning' : 'success')),

            Stat::make("Today's Usage", number_format($todayStats['data_used_mb'], 2) . ' MB')
                ->description($todayStats['sessions'] . ' sessions, ' . $todayStats['time_spent'])
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),

            Stat::make('Monthly Sessions', $monthlyStats['total_sessions'])
                ->description($monthlyStats['active_sessions'] . ' currently active')
                ->descriptionIcon('heroicon-m-play-circle')
                ->color('info'),

            Stat::make('Monthly Data', number_format($monthlyStats['total_data_gb'], 2) . ' GB')
                ->description($monthlyStats['total_time_hours'] . ' hours total')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('success'),

            Stat::make('Average Session', $monthlyStats['avg_session_time'])
                ->description(number_format($monthlyStats['avg_data_per_session_mb'], 1) . ' MB avg')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}