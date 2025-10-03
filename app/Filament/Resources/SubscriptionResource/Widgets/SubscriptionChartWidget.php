<?php

namespace App\Filament\Resources\SubscriptionResource\Widgets;

use App\Models\Subscription;
use App\Models\Package;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class SubscriptionChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Subscription Analytics';

    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    public ?string $filter = 'week';

    protected function getData(): array
    {
        $activeColor = 'rgb(34, 197, 94)';
        $expiredColor = 'rgb(239, 68, 68)';
        $suspendedColor = 'rgb(249, 115, 22)';
        $trialColor = 'rgb(59, 130, 246)';

        switch ($this->filter) {
            case 'today':
                return $this->getTodayData();
            case 'week':
                return $this->getWeekData();
            case 'month':
                return $this->getMonthData();
            case 'year':
                return $this->getYearData();
            default:
                return $this->getWeekData();
        }
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
            'elements' => [
                'point' => [
                    'radius' => 4,
                    'hoverRadius' => 6,
                ],
                'line' => [
                    'tension' => 0.1,
                ],
            ],
        ];
    }

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Today',
            'week' => 'Last 7 days',
            'month' => 'Last 30 days',
            'year' => 'This year',
        ];
    }

    protected function getTodayData(): array
    {
        $hours = [];
        $activeData = [];
        $expiredData = [];
        $suspendedData = [];
        $trialData = [];

        for ($i = 23; $i >= 0; $i--) {
            $hour = now()->subHours($i);
            $hours[] = $hour->format('H:i');

            // Count subscriptions created up to this hour
            $activeData[] = Subscription::where('status', 'active')
                ->where('created_at', '<=', $hour)
                ->count();

            $expiredData[] = Subscription::where('status', 'expired')
                ->where('updated_at', '<=', $hour)
                ->count();

            $suspendedData[] = Subscription::where('status', 'suspended')
                ->where('updated_at', '<=', $hour)
                ->count();

            $trialData[] = Subscription::where('is_trial', true)
                ->where('status', 'active')
                ->where('created_at', '<=', $hour)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Active',
                    'data' => $activeData,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                ],
                [
                    'label' => 'Expired',
                    'data' => $expiredData,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                ],
                [
                    'label' => 'Suspended',
                    'data' => $suspendedData,
                    'borderColor' => 'rgb(249, 115, 22)',
                    'backgroundColor' => 'rgba(249, 115, 22, 0.1)',
                ],
                [
                    'label' => 'Trial',
                    'data' => $trialData,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
            ],
            'labels' => $hours,
        ];
    }

    protected function getWeekData(): array
    {
        $days = [];
        $activeData = [];
        $expiredData = [];
        $suspendedData = [];
        $trialData = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $days[] = $day->format('M j');

            $activeData[] = Subscription::where('status', 'active')
                ->whereDate('created_at', '<=', $day)
                ->count();

            $expiredData[] = Subscription::where('status', 'expired')
                ->whereDate('updated_at', '<=', $day)
                ->count();

            $suspendedData[] = Subscription::where('status', 'suspended')
                ->whereDate('updated_at', '<=', $day)
                ->count();

            $trialData[] = Subscription::where('is_trial', true)
                ->where('status', 'active')
                ->whereDate('created_at', '<=', $day)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Active',
                    'data' => $activeData,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Expired',
                    'data' => $expiredData,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Suspended',
                    'data' => $suspendedData,
                    'borderColor' => 'rgb(249, 115, 22)',
                    'backgroundColor' => 'rgba(249, 115, 22, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Trial',
                    'data' => $trialData,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $days,
        ];
    }

    protected function getMonthData(): array
    {
        $days = [];
        $activeData = [];
        $expiredData = [];
        $suspendedData = [];
        $trialData = [];

        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $days[] = $day->format('M j');

            $activeData[] = Subscription::where('status', 'active')
                ->whereDate('created_at', '<=', $day)
                ->count();

            $expiredData[] = Subscription::where('status', 'expired')
                ->whereDate('updated_at', '<=', $day)
                ->count();

            $suspendedData[] = Subscription::where('status', 'suspended')
                ->whereDate('updated_at', '<=', $day)
                ->count();

            $trialData[] = Subscription::where('is_trial', true)
                ->where('status', 'active')
                ->whereDate('created_at', '<=', $day)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Active',
                    'data' => $activeData,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Expired',
                    'data' => $expiredData,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Suspended',
                    'data' => $suspendedData,
                    'borderColor' => 'rgb(249, 115, 22)',
                    'backgroundColor' => 'rgba(249, 115, 22, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Trial',
                    'data' => $trialData,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $days,
        ];
    }

    protected function getYearData(): array
    {
        $months = [];
        $activeData = [];
        $expiredData = [];
        $suspendedData = [];
        $trialData = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $months[] = $month->format('M Y');

            $activeData[] = Subscription::where('status', 'active')
                ->whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->count();

            $expiredData[] = Subscription::where('status', 'expired')
                ->whereMonth('updated_at', $month->month)
                ->whereYear('updated_at', $month->year)
                ->count();

            $suspendedData[] = Subscription::where('status', 'suspended')
                ->whereMonth('updated_at', $month->month)
                ->whereYear('updated_at', $month->year)
                ->count();

            $trialData[] = Subscription::where('is_trial', true)
                ->where('status', 'active')
                ->whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Active',
                    'data' => $activeData,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Expired',
                    'data' => $expiredData,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Suspended',
                    'data' => $suspendedData,
                    'borderColor' => 'rgb(249, 115, 22)',
                    'backgroundColor' => 'rgba(249, 115, 22, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Trial',
                    'data' => $trialData,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $months,
        ];
    }
}