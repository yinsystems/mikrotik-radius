<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    protected static string $view = 'filament.pages.dashboard';

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'sm' => 2,
            'md' => 4,
            'lg' => 6,
            'xl' => 8,
            '2xl' => 12,
        ];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\OverviewStatsWidget::class,
            \App\Filament\Widgets\RevenueChartWidget::class,
            \App\Filament\Widgets\SessionAnalyticsWidget::class,
            \App\Filament\Widgets\DataUsageAnalyticsWidget::class,
            \App\Filament\Widgets\PackageDistributionWidget::class,
            \App\Filament\Widgets\RecentActivitiesWidget::class,
        ];
    }
}