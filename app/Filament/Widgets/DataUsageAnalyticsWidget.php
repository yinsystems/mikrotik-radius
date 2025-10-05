<?php

namespace App\Filament\Widgets;

use App\Models\DataUsage;
use App\Models\Customer;
use App\Models\Subscription;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DataUsageAnalyticsWidget extends ChartWidget
{
    protected static ?string $heading = 'Data Usage Analytics';
    protected static ?string $description = 'Data consumption trends and top users';
    protected static ?int $sort = 4;
    protected static ?string $pollingInterval = '2m';

    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'month';

    protected function getFilters(): ?array
    {
        return [
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
            case 'week':
                $startDate = now()->subDays(7);
                $dateFormat = '%Y-%m-%d';
                $days = 7;
                break;
            case 'month':
                $startDate = now()->subDays(30);
                $dateFormat = '%Y-%m-%d';
                $days = 30;
                break;
            case 'quarter':
                $startDate = now()->subDays(90);
                $dateFormat = '%Y-%u';
                $days = 90;
                break;
            default:
                $startDate = now()->subDays(30);
                $dateFormat = '%Y-%m-%d';
                $days = 30;
        }

        // Get daily data usage
        $dailyUsage = DataUsage::where('date', '>=', $startDate)
            ->selectRaw("DATE_FORMAT(date, '{$dateFormat}') as period, 
                        SUM(bytes_uploaded) as uploaded, 
                        SUM(bytes_downloaded) as downloaded,
                        SUM(total_bytes) as total")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        // Generate labels and data arrays
        $labels = [];
        $uploadedData = [];
        $downloadedData = [];
        $totalData = [];

        if ($activeFilter === 'quarter') {
            // Group by weeks for quarter view
            for ($i = 12; $i >= 0; $i--) {
                $week = now()->subWeeks($i);
                $weekPeriod = $week->format('o-W');
                $weekLabel = 'Week ' . $week->format('W');
                
                $labels[] = $weekLabel;
                
                $usage = $dailyUsage->get($weekPeriod);
                $uploadedData[] = $usage ? round($usage->uploaded / (1024 * 1024), 2) : 0; // MB
                $downloadedData[] = $usage ? round($usage->downloaded / (1024 * 1024), 2) : 0; // MB
                $totalData[] = $usage ? round($usage->total / (1024 * 1024), 2) : 0; // MB
            }
        } else {
            // Daily view
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $datePeriod = $date->format('Y-m-d');
                $dateLabel = $date->format($days <= 7 ? 'D M j' : 'M j');
                
                $labels[] = $dateLabel;
                
                $usage = $dailyUsage->get($datePeriod);
                $uploadedData[] = $usage ? round($usage->uploaded / (1024 * 1024), 2) : 0; // MB
                $downloadedData[] = $usage ? round($usage->downloaded / (1024 * 1024), 2) : 0; // MB
                $totalData[] = $usage ? round($usage->total / (1024 * 1024), 2) : 0; // MB
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Downloaded (MB)',
                    'data' => $downloadedData,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.3)',
                    'stack' => 'Stack 0',
                ],
                [
                    'label' => 'Uploaded (MB)',
                    'data' => $uploadedData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.3)',
                    'stack' => 'Stack 0',
                ],
                [
                    'label' => 'Total Usage (MB)',
                    'data' => $totalData,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'type' => 'line',
                    'fill' => false,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
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
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            const label = context.dataset.label || "";
                            const value = context.parsed.y;
                            return label + ": " + value.toLocaleString() + " MB";
                        }'
                    ]
                ]
            ],
            'scales' => [
                'x' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Time Period'
                    ]
                ],
                'y' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Data Usage (MB)'
                    ],
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return value.toLocaleString() + " MB"; }'
                    ]
                ],
            ],
        ];
    }
}