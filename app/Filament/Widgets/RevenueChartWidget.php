<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\Subscription;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue Trends';
    protected static ?string $description = 'Monthly revenue and subscription trends';
    protected static ?int $sort = 2;
    protected static ?string $pollingInterval = '1m';

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        // Get revenue data for the last 12 months
        $revenueData = Payment::where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('total', 'month')
            ->toArray();

        // Get subscription data for the last 12 months
        $subscriptionData = Subscription::where('created_at', '>=', now()->subMonths(12))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month')
            ->toArray();

        // Generate labels for the last 12 months
        $labels = [];
        $revenues = [];
        $subscriptions = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            $monthLabel = now()->subMonths($i)->format('M Y');
            
            $labels[] = $monthLabel;
            $revenues[] = $revenueData[$month] ?? 0;
            $subscriptions[] = $subscriptionData[$month] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₵)',
                    'data' => $revenues,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'New Subscriptions',
                    'data' => $subscriptions,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
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
                            if (context.datasetIndex === 0) {
                                return "Revenue: ₵" + context.parsed.y.toLocaleString();
                            } else {
                                return "New Subscriptions: " + context.parsed.y;
                            }
                        }'
                    ]
                ]
            ],
            'scales' => [
                'x' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Month'
                    ]
                ],
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (₵)'
                    ],
                    'ticks' => [
                        'callback' => 'function(value) { return "₵" + value.toLocaleString(); }'
                    ]
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Subscriptions'
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
        ];
    }
}