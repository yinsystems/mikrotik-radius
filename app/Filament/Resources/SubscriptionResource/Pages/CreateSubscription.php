<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use App\Models\Package;
use App\Models\Customer;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-calculate expiry date if not set
        if (!isset($data['expires_at']) && isset($data['package_id'])) {
            $package = Package::find($data['package_id']);
            if ($package) {
                $startDate = $data['starts_at'] ? \Carbon\Carbon::parse($data['starts_at']) : now();
                
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

        // Set default values
        $data['data_used'] = $data['data_used'] ?? 0;
        $data['sessions_used'] = $data['sessions_used'] ?? 0;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $subscription = static::getModel()::create($data);

        // Create RADIUS user entries
        try {
            $subscription->createRadiusUser();
            $subscription->syncRadiusStatus();

            Notification::make()
                ->title('Subscription created successfully')
                ->body('RADIUS user has been configured automatically.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Subscription created but RADIUS configuration failed')
                ->body('Please manually configure RADIUS settings. Error: ' . $e->getMessage())
                ->warning()
                ->send();
        }

        return $subscription;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('quick_trial')
                ->label('Create Trial Subscription')
                ->icon('heroicon-o-star')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Select::make('customer_id')
                        ->label('Customer')
                        ->relationship('customer', 'name')
                        ->searchable(['name', 'phone', 'email'])
                        ->required(),
                    
                    \Filament\Forms\Components\Select::make('trial_package_id')
                        ->label('Trial Package')
                        ->options(Package::availableTrials()->pluck('name', 'id'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    $customer = Customer::find($data['customer_id']);
                    $package = Package::find($data['trial_package_id']);

                    // Check if customer already has an active trial
                    $existingTrial = $customer->subscriptions()
                        ->where('is_trial', true)
                        ->where('status', 'active')
                        ->exists();

                    if ($existingTrial) {
                        Notification::make()
                            ->title('Trial Already Exists')
                            ->body('Customer already has an active trial subscription.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $subscription = $customer->subscriptions()->create([
                        'package_id' => $package->id,
                        'status' => 'active',
                        'starts_at' => now(),
                        'expires_at' => now()->addHours($package->trial_duration_hours),
                        'is_trial' => true,
                        'auto_renew' => false,
                        'data_used' => 0,
                        'sessions_used' => 0,
                    ]);

                    try {
                        $subscription->createRadiusUser();
                        $subscription->syncRadiusStatus();
                    } catch (\Exception $e) {
                        // Log error but don't fail creation
                        \Log::error('Failed to create RADIUS user for trial subscription: ' . $e->getMessage());
                    }

                    Notification::make()
                        ->title('Trial Subscription Created')
                        ->body("Trial subscription created for {$customer->name}")
                        ->success()
                        ->send();

                    return redirect()->to(SubscriptionResource::getUrl('view', ['record' => $subscription]));
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCreateAnotherFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Create Subscription');
    }

    protected function getCreateAnotherFormAction(): Actions\Action
    {
        return parent::getCreateAnotherFormAction()
            ->label('Create & Create Another');
    }
}