<?php

namespace App\Filament\Resources\DataUsageResource\Widgets;

use App\Models\DataUsage;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class UsageOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Basic statistics
        $totalRecords = DataUsage::count();
        $todayRecords = DataUsage::today()->count();
        $thisWeekRecords = DataUsage::thisWeek()->count();
        
        // Data usage statistics
        $totalDataUsage = DataUsage::sum('total_bytes');
        $todayDataUsage = DataUsage::today()->sum('total_bytes');
        $thisWeekDataUsage = DataUsage::thisWeek()->sum('total_bytes');
        $lastWeekDataUsage = DataUsage::lastWeek()->sum('total_bytes');
        
        // Session statistics
        $totalSessions = DataUsage::sum('session_count');
        $todaySessions = DataUsage::today()->sum('session_count');
        $averageSessionsPerDay = DataUsage::thisMonth()->avg('session_count');
        
        // Session time statistics
        $totalSessionTime = DataUsage::sum('session_time');
        $averageSessionTime = DataUsage::withSessions()->avg('session_time');
        
        // Calculate growth rates
        $weeklyDataGrowth = $lastWeekDataUsage > 0 
            ? round((($thisWeekDataUsage - $lastWeekDataUsage) / $lastWeekDataUsage) * 100, 1)
            : 0;
            
        // Active users
        $activeUsersToday = DataUsage::today()->distinct('username')->count();
        $activeUsersThisWeek = DataUsage::thisWeek()->distinct('username')->count();
        
        // High usage count
        $highUsageCount = DataUsage::thisWeek()->highUsage()->count();
        
        // Format data usage
        $formatBytes = function ($bytes) {
            if ($bytes >= 1024 * 1024 * 1024 * 1024) {
                return number_format($bytes / (1024 * 1024 * 1024 * 1024), 2) . ' TB';
            } elseif ($bytes >= 1024 * 1024 * 1024) {
                return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
            } elseif ($bytes >= 1024 * 1024) {
                return number_format($bytes / (1024 * 1024), 2) . ' MB';
            } else {
                return number_format($bytes / 1024, 2) . ' KB';
            }
        };

        return [
            Stat::make('Total Data Usage', $formatBytes($totalDataUsage))
                ->description('All-time data consumption')
                ->descriptionIcon('heroicon-m-server-stack')
                ->color('primary')
                ->chart([
                    $lastWeekDataUsage / (1024 * 1024 * 1024), // GB
                    $thisWeekDataUsage / (1024 * 1024 * 1024),
                ]),

            Stat::make('Weekly Data Usage', $formatBytes($thisWeekDataUsage))
                ->description(
                    $weeklyDataGrowth >= 0 
                        ? "+{$weeklyDataGrowth}% from last week"
                        : "{$weeklyDataGrowth}% from last week"
                )
                ->descriptionIcon($weeklyDataGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($weeklyDataGrowth >= 0 ? 'success' : 'danger'),

            Stat::make('Today\'s Usage', $formatBytes($todayDataUsage))
                ->description("Records: {$todayRecords}")
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('Active Users Today', number_format($activeUsersToday))
                ->description("This week: {$activeUsersThisWeek}")
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),

            Stat::make('Total Sessions', number_format($totalSessions))
                ->description("Today: " . number_format($todaySessions))
                ->descriptionIcon('heroicon-m-play-circle')
                ->color('warning')
                ->chart([
                    $todaySessions ?: 0,
                    $averageSessionsPerDay ?: 0,
                ]),

            Stat::make('Average Session Time', gmdate('H:i:s', $averageSessionTime ?: 0))
                ->description('Per session across all users')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            Stat::make('Total Session Time', gmdate('H:i:s', $totalSessionTime))
                ->description('Cumulative online time')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make('High Usage This Week', number_format($highUsageCount))
                ->description('Users with >1GB usage')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($highUsageCount > 10 ? 'danger' : 'warning'),

            Stat::make('Usage Records', number_format($totalRecords))
                ->description("This week: {$thisWeekRecords}")
                ->descriptionIcon('heroicon-m-document-text')
                ->color('gray'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}