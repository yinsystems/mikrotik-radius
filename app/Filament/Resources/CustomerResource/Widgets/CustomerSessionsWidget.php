<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use App\Models\Customer;
use App\Models\RadAcct;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CustomerSessionsWidget extends ChartWidget
{
    protected static ?string $heading = 'Recent Session Activity';

    public ?Customer $record = null;

    protected function getData(): array
    {
        if (!$this->record || !$this->record->activeSubscription) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Get session data for the last 7 days
        $sessions = [];
        $labels = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M j');
            
            // Count sessions for this customer on this date
            $sessionCount = RadAcct::where('username', $this->record->username)
                ->whereDate('acctstarttime', $date)
                ->count();
                
            $sessions[] = $sessionCount;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Daily Sessions',
                    'data' => $sessions,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
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
            'plugins' => [
                'legend' => [
                    'display' => false,
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
        ];
    }
}