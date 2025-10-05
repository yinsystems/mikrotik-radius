<?php

namespace App\Filament\Resources\DataUsageResource\Pages;

use App\Filament\Resources\DataUsageResource;
use App\Filament\Resources\DataUsageResource\Widgets;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewDataUsage extends ViewRecord
{
    protected static string $resource = DataUsageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->color('warning'),
                
            Actions\Action::make('sync_from_radius')
                ->label('Sync from RADIUS')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->updateUsageFromRadius()) {
                        $this->refreshRecord();
                        
                        Notification::make()
                            ->title('Usage data synced successfully')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('No RADIUS data found')
                            ->warning()
                            ->send();
                    }
                }),
                
            Actions\Action::make('view_subscription')
                ->label('View Subscription')
                ->icon('heroicon-o-user-circle')
                ->color('info')
                ->url(fn () => route('filament.admin.resources.subscriptions.view', ['record' => $this->record->subscription_id]))
                ->visible(fn () => $this->record->subscription_id),
                
            Actions\DeleteAction::make()
                ->requiresConfirmation(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Usage Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('date')
                                    ->label('Usage Date')
                                    ->date('l, F j, Y'),
                                    
                                Infolists\Components\TextEntry::make('username')
                                    ->label('Username')
                                    ->copyable()
                                    ->badge()
                                    ->color('primary'),
                            ]),
                            
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('formatted_total_size')
                                    ->label('Total Data Usage')
                                    ->badge()
                                    ->color(fn () => $this->record->total_gb > 1 ? 'danger' : 'success'),
                                    
                                Infolists\Components\TextEntry::make('upload_mb')
                                    ->label('Upload (MB)')
                                    ->numeric(2)
                                    ->suffix(' MB'),
                                    
                                Infolists\Components\TextEntry::make('download_mb')
                                    ->label('Download (MB)')
                                    ->numeric(2)
                                    ->suffix(' MB'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Session Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('session_count')
                                    ->label('Total Sessions')
                                    ->badge()
                                    ->color('info'),
                                    
                                Infolists\Components\TextEntry::make('formatted_session_time')
                                    ->label('Total Session Time')
                                    ->badge()
                                    ->color('success'),
                                    
                                Infolists\Components\TextEntry::make('average_session_time')
                                    ->label('Average Session Duration')
                                    ->formatStateUsing(fn ($state) => gmdate('H:i:s', $state))
                                    ->badge()
                                    ->color('warning'),
                            ]),
                            
                        Infolists\Components\TextEntry::make('peak_concurrent_sessions')
                            ->label('Peak Concurrent Sessions')
                            ->badge()
                            ->color('danger')
                            ->visible(fn () => $this->record->peak_concurrent_sessions > 0),
                    ]),

                Infolists\Components\Section::make('Subscription Details')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('subscription.customer.name')
                                    ->label('Customer')
                                    ->badge()
                                    ->color('info'),
                                    
                                Infolists\Components\TextEntry::make('subscription.package.name')
                                    ->label('Package')
                                    ->badge()
                                    ->color('success'),
                            ]),
                            
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('subscription.status')
                                    ->label('Subscription Status')
                                    ->badge()
                                    ->color(fn ($state) => match ($state) {
                                        'active' => 'success',
                                        'expired' => 'danger',
                                        'suspended' => 'warning',
                                        default => 'gray'
                                    }),
                                    
                                Infolists\Components\TextEntry::make('subscription.package.data_limit')
                                    ->label('Data Limit (MB)')
                                    ->numeric()
                                    ->suffix(' MB')
                                    ->placeholder('Unlimited'),
                                    
                                Infolists\Components\TextEntry::make('subscription.expires_at')
                                    ->label('Expires At')
                                    ->dateTime('M j, Y H:i'),
                            ]),
                    ])
                    ->visible(fn () => $this->record->subscription),

                Infolists\Components\Section::make('Usage Analysis')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('data_usage_percentage')
                                    ->label('Data Usage Percentage')
                                    ->formatStateUsing(function () {
                                        $package = $this->record->subscription?->package;
                                        if (!$package || !$package->data_limit) {
                                            return 'Unlimited';
                                        }
                                        
                                        $percentage = ($this->record->total_mb / $package->data_limit) * 100;
                                        return number_format($percentage, 1) . '%';
                                    })
                                    ->badge()
                                    ->color(function () {
                                        $package = $this->record->subscription?->package;
                                        if (!$package || !$package->data_limit) {
                                            return 'gray';
                                        }
                                        
                                        $percentage = ($this->record->total_mb / $package->data_limit) * 100;
                                        
                                        if ($percentage >= 100) return 'danger';
                                        if ($percentage >= 90) return 'warning';
                                        return 'success';
                                    }),
                                    
                                Infolists\Components\TextEntry::make('remaining_data')
                                    ->label('Remaining Data')
                                    ->formatStateUsing(function () {
                                        $package = $this->record->subscription?->package;
                                        if (!$package || !$package->data_limit) {
                                            return 'Unlimited';
                                        }
                                        
                                        $remaining = max(0, $package->data_limit - $this->record->total_mb);
                                        return number_format($remaining, 2) . ' MB';
                                    })
                                    ->badge()
                                    ->color('info'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Record Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Recorded At')
                                    ->dateTime('M j, Y H:i:s'),
                                    
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime('M j, Y H:i:s'),
                            ]),
                    ]),
            ]);
    }

    protected function getFooterWidgets(): array
    {
        return [
            // Could add individual usage charts here
        ];
    }
}
