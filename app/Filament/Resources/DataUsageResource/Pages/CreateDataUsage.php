<?php

namespace App\Filament\Resources\DataUsageResource\Pages;

use App\Filament\Resources\DataUsageResource;
use App\Models\DataUsage;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateDataUsage extends CreateRecord
{
    protected static string $resource = DataUsageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_from_radius')
                ->label('Sync from RADIUS Instead')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function () {
                    $this->redirect(DataUsageResource::getUrl('index'));
                    
                    Notification::make()
                        ->title('Tip: Use Sync from RADIUS')
                        ->body('For accurate data, consider syncing from RADIUS instead of manual entry')
                        ->info()
                        ->send();
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Data usage record created successfully';
    }

    protected function afterCreate(): void
    {
        // Update subscription's total data used after creating usage record
        $subscription = $this->record->subscription;
        if ($subscription) {
            $totalUsed = DataUsage::where('subscription_id', $subscription->id)
                               ->sum('total_bytes');
            
            $subscription->update(['data_used' => $totalUsed]);
            
            // Check if data limit is exceeded
            $this->record->checkDataLimitExceeded();
        }

        Notification::make()
            ->title('Usage record created')
            ->body('Subscription data usage has been updated')
            ->success()
            ->send();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure total_bytes is calculated correctly
        $data['total_bytes'] = ($data['bytes_uploaded'] ?? 0) + ($data['bytes_downloaded'] ?? 0);
        
        return $data;
    }
}
