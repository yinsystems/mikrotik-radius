<?php

namespace App\Filament\Widgets;

use App\Models\Package;
use App\Models\Subscription;
use App\Models\Customer;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PackageDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Package Distribution';
    protected static ?string $description = 'Package popularity and subscription distribution';
    protected static ?int $sort = 5;
    protected static ?string $pollingInterval = '5m';

    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'active';

    protected function getFilters(): ?array
    {
        return [
            'all' => 'All Subscriptions',
            'active' => 'Active Only',
            'revenue' => 'By Revenue',
        ];
    }

    protected function getData(): array
    {
        $activeFilter = $this->filter;

        if ($activeFilter === 'revenue') {
            // Get package distribution by revenue
            $packageRevenue = DB::table('subscriptions')
                ->join('packages', 'subscriptions.package_id', '=', 'packages.id')
                ->join('payments', 'subscriptions.id', '=', 'payments.subscription_id')
                ->where('payments.status', 'completed')
                ->select('packages.name', 'packages.price')
                ->selectRaw('COUNT(subscriptions.id) as subscription_count')
                ->selectRaw('SUM(payments.amount) as total_revenue')
                ->groupBy('packages.id', 'packages.name', 'packages.price')
                ->orderByDesc('total_revenue')
                ->limit(10)
                ->get();

            $labels = $packageRevenue->pluck('name')->toArray();
            $data = $packageRevenue->pluck('total_revenue')->toArray();
            $colors = $this->generateColors(count($labels));

            return [
                'datasets' => [
                    [
                        'label' => 'Revenue (₵)',
                        'data' => $data,
                        'backgroundColor' => $colors,
                        'borderColor' => array_map(fn($color) => str_replace('0.8', '1', $color), $colors),
                        'borderWidth' => 2,
                    ],
                ],
                'labels' => $labels,
            ];
        } else {
            // Get package distribution by subscription count
            $query = Subscription::join('packages', 'subscriptions.package_id', '=', 'packages.id')
                ->select('packages.name', 'packages.price')
                ->selectRaw('COUNT(subscriptions.id) as subscription_count');

            if ($activeFilter === 'active') {
                $query->where('subscriptions.status', 'active');
            }

            $packageData = $query
                ->groupBy('packages.id', 'packages.name', 'packages.price')
                ->orderByDesc('subscription_count')
                ->limit(10)
                ->get();

            $labels = $packageData->pluck('name')->toArray();
            $data = $packageData->pluck('subscription_count')->toArray();
            $colors = $this->generateColors(count($labels));

            return [
                'datasets' => [
                    [
                        'label' => $activeFilter === 'active' ? 'Active Subscriptions' : 'Total Subscriptions',
                        'data' => $data,
                        'backgroundColor' => $colors,
                        'borderColor' => array_map(fn($color) => str_replace('0.8', '1', $color), $colors),
                        'borderWidth' => 2,
                    ],
                ],
                'labels' => $labels,
            ];
        }
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        $isRevenue = $this->filter === 'revenue';

        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'height' => 400,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'padding' => 20,
                        'usePointStyle' => true,
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => $isRevenue 
                            ? 'function(context) {
                                const label = context.label || "";
                                const value = context.parsed;
                                return label + ": ₵" + value.toLocaleString();
                            }'
                            : 'function(context) {
                                const label = context.label || "";
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return label + ": " + value + " (" + percentage + "%)";
                            }'
                    ]
                ]
            ],
            'cutout' => '50%',
        ];
    }

    private function generateColors(int $count): array
    {
        $baseColors = [
            'rgba(59, 130, 246, 0.8)',   // Blue
            'rgba(16, 185, 129, 0.8)',   // Green
            'rgba(245, 158, 11, 0.8)',   // Yellow
            'rgba(239, 68, 68, 0.8)',    // Red
            'rgba(139, 92, 246, 0.8)',   // Purple
            'rgba(236, 72, 153, 0.8)',   // Pink
            'rgba(20, 184, 166, 0.8)',   // Teal
            'rgba(251, 146, 60, 0.8)',   // Orange
            'rgba(34, 197, 94, 0.8)',    // Emerald
            'rgba(168, 85, 247, 0.8)',   // Violet
        ];

        if ($count <= count($baseColors)) {
            return array_slice($baseColors, 0, $count);
        }

        // Generate additional colors if needed
        $colors = $baseColors;
        for ($i = count($baseColors); $i < $count; $i++) {
            $hue = ($i * 137.5) % 360; // Golden angle approximation
            $colors[] = "hsla({$hue}, 70%, 60%, 0.8)";
        }

        return array_slice($colors, 0, $count);
    }
}