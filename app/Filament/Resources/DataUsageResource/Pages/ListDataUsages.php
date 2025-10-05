<?php

namespace App\Filament\Resources\DataUsageResource\Pages;

use App\Filament\Resources\DataUsageResource;
use App\Filament\Resources\DataUsageResource\Widgets;
use App\Models\DataUsage;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ListDataUsages extends ListRecords
{
    protected static string $resource = DataUsageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus'),
                
            Action::make('sync_all_usage')
                ->label('Sync All from RADIUS')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Sync All Usage Data')
                ->modalDescription('This will sync usage data for all active subscriptions from RADIUS accounting. This may take several minutes.')
                ->modalSubmitActionLabel('Start Sync')
                ->action(function () {
                    $synced = DataUsage::syncAllUsageFromRadius();
                    
                    Notification::make()
                        ->title('Usage data sync completed')
                        ->body("Synced data for {$synced} subscriptions")
                        ->success()
                        ->send();
                }),
                
            Action::make('export_analytics')
                ->label('Export Analytics')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('start_date')
                        ->label('Start Date')
                        ->required()
                        ->default(now()->subMonth()),
                    \Filament\Forms\Components\DatePicker::make('end_date')
                        ->label('End Date')
                        ->required()
                        ->default(now()),
                    \Filament\Forms\Components\Select::make('format')
                        ->label('Export Format')
                        ->options([
                            'csv' => 'CSV',
                            'json' => 'JSON'
                        ])
                        ->default('csv')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $report = DataUsage::generateAnalyticsReport($data['start_date'], $data['end_date']);
                    
                    $filename = 'usage_analytics_' . $data['start_date'] . '_to_' . $data['end_date'];
                    
                    if ($data['format'] === 'csv') {
                        $csvData = DataUsage::exportUsageReport($data['start_date'], $data['end_date'], 'csv');
                        Storage::put('reports/' . $filename . '.csv', $csvData);
                        $message = "Analytics exported as {$filename}.csv";
                    } else {
                        Storage::put('reports/' . $filename . '.json', json_encode($report, JSON_PRETTY_PRINT));
                        $message = "Analytics exported as {$filename}.json";
                    }
                    
                    Notification::make()
                        ->title('Analytics exported successfully')
                        ->body($message)
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            Widgets\UsageOverview::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            Widgets\UsageAnalytics::class,
            Widgets\TopUsers::class,
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }

    public function getTabs(): array
    {
        return [
            'all' => \Filament\Resources\Components\Tab::make('All Usage')
                ->badge(DataUsage::count()),
                
            'today' => \Filament\Resources\Components\Tab::make('Today')
                ->badge(DataUsage::today()->count())
                ->modifyQueryUsing(fn ($query) => $query->today()),
                
            'this_week' => \Filament\Resources\Components\Tab::make('This Week')
                ->badge(DataUsage::thisWeek()->count())
                ->modifyQueryUsing(fn ($query) => $query->thisWeek()),
                
            'this_month' => \Filament\Resources\Components\Tab::make('This Month')
                ->badge(DataUsage::thisMonth()->count())
                ->modifyQueryUsing(fn ($query) => $query->thisMonth()),
                
            'high_usage' => \Filament\Resources\Components\Tab::make('High Usage')
                ->badge(DataUsage::highUsage()->count())
                ->modifyQueryUsing(fn ($query) => $query->highUsage()),
        ];
    }
}
