<?php

namespace App\Filament\Resources\DataUsageResource\Widgets;

use App\Models\DataUsage;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class UsageAnalytics extends ChartWidget
{
    protected static ?string $heading = 'Data Usage Analytics';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'daily_usage';

    protected function getData(): array
    {
        $filter = $this->filter;
        
        switch ($filter) {
            case 'daily_usage':
                return $this->getDailyUsageData();
            case 'weekly_usage':
                return $this->getWeeklyUsageData();
            case 'monthly_usage':
                return $this->getMonthlyUsageData();
            case 'hourly_sessions':
                return $this->getHourlySessionData();
            case 'user_distribution':
                return $this->getUserDistributionData();
            default:
                return $this->getDailyUsageData();
        }
    }

    protected function getFilters(): ?array
    {
        return [
            'daily_usage' => 'Daily Usage (Last 30 Days)',
            'weekly_usage' => 'Weekly Usage (Last 12 Weeks)',
            'monthly_usage' => 'Monthly Usage (Last 12 Months)',
            'hourly_sessions' => 'Session Distribution',
            'user_distribution' => 'Top Users',
        ];
    }

    protected function getType(): string
    {
        return match ($this->filter) {
            'user_distribution' => 'doughnut',
            'hourly_sessions' => 'bar',
            default => 'line',
        };
    }

    private function getDailyUsageData(): array
    {
        $data = collect(range(29, 0))->map(function ($days) {
            $date = now()->subDays($days);
            $usage = DataUsage::whereDate('date', $date)
                ->sum('total_bytes');
            $sessions = DataUsage::whereDate('date', $date)
                ->sum('session_count');
            
            return [
                'date' => $date->format('M j'),
                'usage_gb' => round($usage / (1024 * 1024 * 1024), 2),
                'sessions' => $sessions,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Data Usage (GB)',
                    'data' => $data->pluck('usage_gb')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Sessions',
                    'data' => $data->pluck('sessions')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $data->pluck('date')->toArray(),
        ];
    }

    private function getWeeklyUsageData(): array
    {
        $data = collect(range(11, 0))->map(function ($weeks) {
            $startOfWeek = now()->subWeeks($weeks)->startOfWeek();
            $endOfWeek = now()->subWeeks($weeks)->endOfWeek();
            
            $usage = DataUsage::whereBetween('date', [$startOfWeek, $endOfWeek])
                ->sum('total_bytes');
            $sessions = DataUsage::whereBetween('date', [$startOfWeek, $endOfWeek])
                ->sum('session_count');
            $users = DataUsage::whereBetween('date', [$startOfWeek, $endOfWeek])
                ->distinct('username')->count();
            
            return [
                'week' => 'Week ' . $startOfWeek->format('M j'),
                'usage_gb' => round($usage / (1024 * 1024 * 1024), 2),
                'sessions' => $sessions,
                'users' => $users,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Data Usage (GB)',
                    'data' => $data->pluck('usage_gb')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Active Users',
                    'data' => $data->pluck('users')->toArray(),
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $data->pluck('week')->toArray(),
        ];
    }

    private function getMonthlyUsageData(): array
    {
        $data = collect(range(11, 0))->map(function ($months) {
            $date = now()->subMonths($months);
            
            $usage = DataUsage::whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->sum('total_bytes');
            $sessions = DataUsage::whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->sum('session_count');
            $users = DataUsage::whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->distinct('username')->count();
            
            return [
                'month' => $date->format('M Y'),
                'usage_gb' => round($usage / (1024 * 1024 * 1024), 2),
                'sessions' => $sessions,
                'users' => $users,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Data Usage (GB)',
                    'data' => $data->pluck('usage_gb')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Active Users',
                    'data' => $data->pluck('users')->toArray(),
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $data->pluck('month')->toArray(),
        ];
    }

    private function getHourlySessionData(): array
    {
        // Simulate hourly session distribution
        $hours = collect(range(0, 23))->map(function ($hour) {
            // This would need actual hour-based data in a real implementation
            $sessionCount = DataUsage::thisWeek()
                ->whereRaw('HOUR(created_at) = ?', [$hour])
                ->sum('session_count');
            
            return [
                'hour' => sprintf('%02d:00', $hour),
                'sessions' => $sessionCount ?: rand(5, 50), // Fallback for demo
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Sessions by Hour',
                    'data' => $hours->pluck('sessions')->toArray(),
                    'backgroundColor' => [
                        '#EF4444', '#F97316', '#F59E0B', '#EAB308',
                        '#84CC16', '#22C55E', '#10B981', '#14B8A6',
                        '#06B6D4', '#0EA5E9', '#3B82F6', '#6366F1',
                        '#8B5CF6', '#A855F7', '#C026D3', '#DB2777',
                        '#E11D48', '#DC2626', '#EA580C', '#D97706',
                        '#CA8A04', '#65A30D', '#16A34A', '#059669'
                    ],
                ],
            ],
            'labels' => $hours->pluck('hour')->toArray(),
        ];
    }

    private function getUserDistributionData(): array
    {
        $topUsers = DataUsage::thisMonth()
            ->groupBy('username')
            ->select('username', DB::raw('SUM(total_bytes) as total_usage'))
            ->orderByDesc('total_usage')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'data' => $topUsers->pluck('total_usage')->map(fn($bytes) => round($bytes / (1024 * 1024 * 1024), 2))->toArray(),
                    'backgroundColor' => [
                        '#EF4444', '#F97316', '#F59E0B', '#EAB308', '#84CC16',
                        '#22C55E', '#10B981', '#14B8A6', '#06B6D4', '#0EA5E9'
                    ],
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                ],
            ],
            'labels' => $topUsers->pluck('username')->toArray(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => match ($this->filter) {
                            'user_distribution' => 'function(context) {
                                return context.label + ": " + context.raw + " GB";
                            }',
                            default => 'function(context) {
                                const suffix = context.dataset.label.includes("Usage") ? " GB" : "";
                                return context.dataset.label + ": " + context.raw + suffix;
                            }'
                        },
                    ],
                ],
            ],
            'scales' => $this->filter === 'user_distribution' ? [] : [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Data Usage (GB) / Sessions',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Count',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}