<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            
            Actions\Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->activate();
                    Notification::make()
                        ->title('Subscription Activated')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => in_array($this->record->status, ['pending', 'suspended'])),

            Actions\Action::make('suspend')
                ->label('Suspend')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Suspension Reason')
                        ->required()
                        ->placeholder('Please provide a reason for suspension...'),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) {
                    $this->record->suspend($data['reason']);
                    Notification::make()
                        ->title('Subscription Suspended')
                        ->warning()
                        ->send();
                })
                ->visible(fn (): bool => $this->record->status === 'active'),

            Actions\Action::make('resume')
                ->label('Resume')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->resume();
                    Notification::make()
                        ->title('Subscription Resumed')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->record->status === 'suspended'),

            Actions\Action::make('renew')
                ->label('Renew')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\Select::make('package_id')
                        ->label('Renewal Package')
                        ->relationship('renewalPackage', 'name', fn ($query) => $query->active())
                        ->searchable()
                        ->preload()
                        ->default($this->record->renewal_package_id ?? $this->record->package_id),
                    
                    \Filament\Forms\Components\DateTimePicker::make('new_expiry')
                        ->label('New Expiry Date')
                        ->default(function () {
                            $package = $this->record->package;
                            return match($package->duration_type) {
                                'hourly' => now()->addHours($package->duration_value),
                                'daily' => now()->addDays($package->duration_value),
                                'weekly' => now()->addWeeks($package->duration_value),
                                'monthly' => now()->addMonths($package->duration_value),
                                default => now()->addDays(1)
                            };
                        }),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) {
                    $newPackage = \App\Models\Package::find($data['package_id']);
                    $this->record->update([
                        'package_id' => $newPackage->id,
                        'expires_at' => $data['new_expiry'],
                        'status' => 'active'
                    ]);
                    $this->record->updateRadiusUser();
                    $this->record->syncRadiusStatus();
                    
                    Notification::make()
                        ->title('Subscription Renewed')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->record->canRenew()),

            Actions\Action::make('sync_radius')
                ->label('Sync RADIUS')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $this->record->updateRadiusUser();
                        $this->record->syncRadiusStatus();
                        
                        Notification::make()
                            ->title('RADIUS Synced Successfully')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('RADIUS Sync Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If package changed, update expiry date
        if (isset($data['package_id']) && $data['package_id'] !== $this->record->package_id) {
            $package = \App\Models\Package::find($data['package_id']);
            if ($package && (!isset($data['expires_at']) || $data['expires_at'] === $this->record->expires_at)) {
                $startDate = isset($data['starts_at']) ? \Carbon\Carbon::parse($data['starts_at']) : $this->record->starts_at;
                
                $data['expires_at'] = match($package->duration_type) {
                    'hourly' => $startDate->addHours($package->duration_value),
                    'daily' => $startDate->addDays($package->duration_value),
                    'weekly' => $startDate->addWeeks($package->duration_value),
                    'monthly' => $startDate->addMonths($package->duration_value),
                    'trial' => $startDate->addHours($package->trial_duration_hours),
                    default => $startDate->addDays(1)
                };
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        try {
            // Update RADIUS configuration after any changes
            $this->record->updateRadiusUser();
            $this->record->syncRadiusStatus();

            Notification::make()
                ->title('Subscription updated and RADIUS synced')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Subscription updated but RADIUS sync failed')
                ->body('Please manually sync RADIUS settings. Error: ' . $e->getMessage())
                ->warning()
                ->send();
        }
    }
}