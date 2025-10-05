<?php

namespace App\Filament\Widgets;

use App\Models\RadAcct;
use App\Models\Customer;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SessionAnalyticsWidget extends ChartWidget
{
    protected static ?string $heading = 'Session Analytics';
    protected static ?string $description = 'User sessions and activity patterns';
    protected static ?int $sort = 3;
    protected static ?string $pollingInterval = '2m';

    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'week';

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Today',
            'week' => 'Last 7 days',
            'month' => 'Last 30 days',
            'quarter' => 'Last 3 months',
        ];
    }

    protected function getData(): array
    {
        $activeFilter = $this->filter;

        // Determine date range based on filter
        switch ($activeFilter) {
            case 'today':
                $startDate = now()->startOfDay();
                $dateFormat = '%H:00';
                $labelFormat = 'H:i';
                break;
            case 'week':
                $startDate = now()->subDays(7);
                $dateFormat = '%Y-%m-%d';
                $labelFormat = 'M j';
                break;
            case 'month':
                $startDate = now()->subDays(30);
                $dateFormat = '%Y-%m-%d';
                $labelFormat = 'M j';
                break;
            case 'quarter':
                $startDate = now()->subDays(90);
                $dateFormat = '%Y-%u';
                $labelFormat = '\W\e\e\k W';
                break;
            default:
                $startDate = now()->subDays(7);
                $dateFormat = '%Y-%m-%d';
                $labelFormat = 'M j';
        }

        // Get session start data
        $sessionStarts = RadAcct::where('acctstarttime', '>=', $startDate)
            ->selectRaw("DATE_FORMAT(acctstarttime, '{$dateFormat}') as period, COUNT(*) as count")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->pluck('count', 'period')
            ->toArray();

        // Get session end data
        $sessionEnds = RadAcct::where('acctstoptime', '>=', $startDate)
            ->whereNotNull('acctstoptime')
            ->selectRaw("DATE_FORMAT(acctstoptime, '{$dateFormat}') as period, COUNT(*) as count")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->pluck('count', 'period')
            ->toArray();

        // Get active sessions over time
        $activeSessions = [];
        $labels = [];

        // Generate time periods based on filter
        if ($activeFilter === 'today') {
            for ($i = 0; $i < 24; $i++) {
                $hour = now()->startOfDay()->addHours($i);
                $period = $hour->format('H:00');
                $labels[] = $hour->format($labelFormat);
                
                $activeSessions[] = RadAcct::where('acctstarttime', '<=', $hour->endOfHour())
                    ->where(function($query) use ($hour) {
                        $query->whereNull('acctstoptime')
                            ->orWhere('acctstoptime', '>', $hour->endOfHour());
                    })
                    ->count();
            }
        } else {
            $days = $activeFilter === 'week' ? 7 : ($activeFilter === 'month' ? 30 : 90);
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $period = $date->format($activeFilter === 'quarter' ? 'o-W' : 'Y-m-d');
                $labels[] = $date->format($labelFormat);
                
                $activeSessions[] = RadAcct::where('acctstarttime', '<=', $date->endOfDay())
                    ->where(function($query) use ($date) {
                        $query->whereNull('acctstoptime')
                            ->orWhere('acctstoptime', '>', $date->endOfDay());
                    })
                    ->count();
            }
        }

        // Prepare chart data
        $sessionStartData = [];
        $sessionEndData = [];

        foreach ($labels as $index => $label) {
            $period = $activeFilter === 'today' 
                ? sprintf('%02d:00', $index)
                : ($activeFilter === 'quarter' 
                    ? now()->subDays(($days - 1) - $index)->format('o-W')
                    : now()->subDays(($days - 1) - $index)->format('Y-m-d'));
            
            $sessionStartData[] = $sessionStarts[$period] ?? 0;
            $sessionEndData[] = $sessionEnds[$period] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sessions Started',
                    'data' => $sessionStartData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'fill' => true,
                ],
                [
                    'label' => 'Sessions Ended',
                    'data' => $sessionEndData,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.2)',
                    'fill' => true,
                ],
                [
                    'label' => 'Active Sessions',
                    'data' => $activeSessions,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'height' => 400,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'x' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Time'
                    ]
                ],
                'y' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Sessions'
                    ],
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}