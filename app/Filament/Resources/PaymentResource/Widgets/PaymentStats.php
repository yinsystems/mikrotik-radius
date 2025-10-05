<?php

namespace App\Filament\Resources\PaymentResource\Widgets;

use App\Models\Payment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PaymentStats extends ChartWidget
{
    protected static ?string $heading = 'Payment Analytics';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'month';

    protected function getData(): array
    {
        $filter = $this->filter;
        
        switch ($filter) {
            case 'week':
                return $this->getWeeklyData();
            case 'month':
                return $this->getMonthlyData();
            case 'year':
                return $this->getYearlyData();
            case 'methods':
                return $this->getPaymentMethodsData();
            case 'status':
                return $this->getPaymentStatusData();
            default:
                return $this->getMonthlyData();
        }
    }

    protected function getFilters(): ?array
    {
        return [
            'week' => 'Last 7 Days',
            'month' => 'Last 12 Months',
            'year' => 'Last 5 Years',
            'methods' => 'Payment Methods',
            'status' => 'Payment Status',
        ];
    }

    protected function getType(): string
    {
        return $this->filter === 'methods' || $this->filter === 'status' ? 'doughnut' : 'line';
    }

    private function getWeeklyData(): array
    {
        $data = collect(range(6, 0))->map(function ($days) {
            $date = now()->subDays($days);
            $revenue = Payment::completed()
                ->whereDate('payment_date', $date)
                ->sum('amount');
            
            return [
                'date' => $date->format('M j'),
                'revenue' => $revenue,
                'count' => Payment::whereDate('payment_date', $date)->count(),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₵)',
                    'data' => $data->pluck('revenue')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Payment Count',
                    'data' => $data->pluck('count')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data->pluck('date')->toArray(),
        ];
    }

    private function getMonthlyData(): array
    {
        $data = collect(range(11, 0))->map(function ($months) {
            $date = now()->subMonths($months);
            $revenue = Payment::completed()
                ->whereYear('payment_date', $date->year)
                ->whereMonth('payment_date', $date->month)
                ->sum('amount');
            
            return [
                'date' => $date->format('M Y'),
                'revenue' => $revenue,
                'count' => Payment::whereYear('payment_date', $date->year)
                    ->whereMonth('payment_date', $date->month)
                    ->count(),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₵)',
                    'data' => $data->pluck('revenue')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Payment Count',
                    'data' => $data->pluck('count')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $data->pluck('date')->toArray(),
        ];
    }

    private function getYearlyData(): array
    {
        $data = collect(range(4, 0))->map(function ($years) {
            $year = now()->subYears($years)->year;
            $revenue = Payment::completed()
                ->whereYear('payment_date', $year)
                ->sum('amount');
            
            return [
                'year' => $year,
                'revenue' => $revenue,
                'count' => Payment::whereYear('payment_date', $year)->count(),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₵)',
                    'data' => $data->pluck('revenue')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Payment Count',
                    'data' => $data->pluck('count')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $data->pluck('year')->map(fn($year) => (string) $year)->toArray(),
        ];
    }

    private function getPaymentMethodsData(): array
    {
        $methods = Payment::completed()
            ->select('payment_method', DB::raw('count(*) as count'), DB::raw('sum(amount) as revenue'))
            ->groupBy('payment_method')
            ->get();

        $labels = $methods->map(function ($method) {
            return match($method->payment_method) {
                'mobile_money' => 'Mobile Money',
                'cash' => 'Cash',
                'bank_transfer' => 'Bank Transfer',
                'credit_card' => 'Credit Card',
                default => ucfirst(str_replace('_', ' ', $method->payment_method))
            };
        })->toArray();

        return [
            'datasets' => [
                [
                    'data' => $methods->pluck('revenue')->toArray(),
                    'backgroundColor' => [
                        '#10B981', // Green for mobile money
                        '#3B82F6', // Blue for cash
                        '#F59E0B', // Amber for bank transfer
                        '#EF4444', // Red for credit card
                        '#8B5CF6', // Purple for others
                    ],
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                ],
            ],
            'labels' => $labels,
        ];
    }

    private function getPaymentStatusData(): array
    {
        $statuses = Payment::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        $labels = $statuses->map(function ($status) {
            return match($status->status) {
                'completed' => 'Completed',
                'pending' => 'Pending',
                'failed' => 'Failed',
                'refunded' => 'Refunded',
                'cancelled' => 'Cancelled',
                default => ucfirst($status->status)
            };
        })->toArray();

        return [
            'datasets' => [
                [
                    'data' => $statuses->pluck('count')->toArray(),
                    'backgroundColor' => [
                        '#10B981', // Green for completed
                        '#F59E0B', // Amber for pending
                        '#EF4444', // Red for failed
                        '#8B5CF6', // Purple for refunded
                        '#6B7280', // Gray for cancelled
                    ],
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                ],
            ],
            'labels' => $labels,
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
                        'label' => 'function(context) {
                            if (context.chart.config.type === "doughnut") {
                                return context.label + ": ₵" + context.raw.toLocaleString();
                            }
                            return context.dataset.label + ": " + (context.dataset.label.includes("Revenue") ? "₵" : "") + context.raw.toLocaleString();
                        }',
                    ],
                ],
            ],
            'scales' => $this->filter === 'methods' || $this->filter === 'status' ? [] : [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) {
                            return "₵" + value.toLocaleString();
                        }',
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