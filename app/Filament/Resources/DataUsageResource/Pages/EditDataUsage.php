<?php

namespace App\Filament\Resources\DataUsageResource\Pages;

use App\Filament\Resources\DataUsageResource;
use App\Models\DataUsage;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditDataUsage extends EditRecord
{
    protected static string $resource = DataUsageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->color('info'),
                
            Actions\Action::make('sync_from_radius')
                ->label('Sync from RADIUS')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Sync Usage Data from RADIUS')
                ->modalDescription('This will overwrite the current usage data with data from RADIUS accounting.')
                ->modalSubmitActionLabel('Sync Data')
                ->action(function () {
                    if ($this->record->updateUsageFromRadius()) {
                        $this->refreshFormData([
                            'bytes_uploaded',
                            'bytes_downloaded', 
                            'total_bytes',
                            'session_count',
                            'session_time'
                        ]);
                        
                        Notification::make()
                            ->title('Usage data synced successfully')
                            ->body('Data has been updated from RADIUS accounting')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Failed to sync usage data')
                            ->body('No RADIUS data found for this date')
                            ->warning()
                            ->send();
                    }
                }),
                
            Actions\Action::make('generate_report')
                ->label('Generate Report')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->action(function () {
                    $report = $this->record->generateUsageReport();
                    
                    // Store report for download or display
                    session(['usage_report_' . $this->record->id => $report]);
                    
                    Notification::make()
                        ->title('Usage report generated')
                        ->body('Report contains detailed usage analysis')
                        ->success()
                        ->send();
                }),
                
            Actions\DeleteAction::make()
                ->requiresConfirmation(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Data usage record updated successfully';
    }

    protected function afterSave(): void
    {
        // Update subscription's total data used after editing usage record
        $subscription = $this->record->subscription;
        if ($subscription) {
            $totalUsed = DataUsage::where('subscription_id', $subscription->id)
                               ->sum('total_bytes');
            
            $subscription->update(['data_used' => $totalUsed]);
            
            // Check if data limit is exceeded
            $this->record->checkDataLimitExceeded();
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure total_bytes is calculated correctly
        $data['total_bytes'] = ($data['bytes_uploaded'] ?? 0) + ($data['bytes_downloaded'] ?? 0);
        
        return $data;
    }
}
