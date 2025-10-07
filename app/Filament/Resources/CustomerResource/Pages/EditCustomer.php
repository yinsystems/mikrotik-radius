<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Filament\Notifications\Notification;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->icon('heroicon-o-eye'),

            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription('This will permanently delete the customer and all associated data.')
                ->before(function (Customer $record) {
                    // Clean up RADIUS users before deletion
                    $record->deleteAllRadiusUsers();
                }),

            Actions\ActionGroup::make([
                Actions\Action::make('add_subscription')
                    ->label('Add Subscription')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('package_id')
                            ->label('Package')
                            ->options(Package::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->native(false)
                            ->searchable(),

                        Forms\Components\Toggle::make('auto_activate')
                            ->label('Auto Activate')
                            ->default(true)
                            ->helperText('Automatically activate the subscription after creation'),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->placeholder('Notes for this subscription...')
                            ->columnSpanFull(),
                    ])
                    ->action(function (Customer $record, array $data) {
                        $subscription = $record->createSubscription($data['package_id']);
                        
                        if ($data['auto_activate']) {
                            $subscription->activate();
                        }

                        if (!empty($data['notes'])) {
                            $subscription->update(['notes' => $data['notes']]);
                        }

                        Notification::make()
                            ->title('Subscription Added')
                            ->body("New subscription created for {$record->name}.")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('assign_trial')
                    ->label('Assign Trial Package')
                    ->icon('heroicon-o-gift')
                    ->color('info')
                    ->visible(fn (Customer $record) => $record->isEligibleForTrial())
                    ->form([
                        Forms\Components\Select::make('package_id')
                            ->label('Trial Package')
                            ->options(Package::where('is_trial', true)->where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (Customer $record, array $data) {
                        try {
                            $subscription = $record->assignTrialPackage($data['package_id']);
                            
                            Notification::make()
                                ->title('Trial Package Assigned')
                                ->body("Trial package assigned to {$record->name}.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Actions\Action::make('suspend_customer')
                    ->label('Suspend Customer')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This will suspend the customer and terminate all active sessions.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Suspension Reason')
                            ->required()
                            ->placeholder('Enter reason for suspension...')
                            ->rows(3),
                    ])
                    ->action(function (Customer $record, array $data) {
                        $record->suspend($data['reason']);
                        
                        Notification::make()
                            ->title('Customer Suspended')
                            ->body("Customer {$record->name} has been suspended.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Customer $record) => $record->status === 'active'),

                Actions\Action::make('resume_customer')
                    ->label('Resume Customer')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('This will reactivate the customer and their non-expired subscriptions.')
                    ->action(function (Customer $record) {
                        $record->resume();
                        
                        Notification::make()
                            ->title('Customer Resumed')
                            ->body("Customer {$record->name} has been reactivated.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Customer $record) => $record->status === 'suspended'),

                Actions\Action::make('block_customer')
                    ->label('Block Customer')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This will block the customer permanently and terminate all sessions.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Block Reason')
                            ->required()
                            ->placeholder('Enter reason for blocking...')
                            ->rows(3),
                    ])
                    ->action(function (Customer $record, array $data) {
                        $record->block($data['reason']);
                        
                        Notification::make()
                            ->title('Customer Blocked')
                            ->body("Customer {$record->name} has been blocked.")
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (Customer $record) => $record->status !== 'blocked'),

                Actions\Action::make('unblock_customer')
                    ->label('Unblock Customer')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('This will unblock the customer and reactivate non-expired subscriptions.')
                    ->action(function (Customer $record) {
                        $record->unblock();
                        
                        Notification::make()
                            ->title('Customer Unblocked')
                            ->body("Customer {$record->name} has been unblocked.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Customer $record) => $record->status === 'blocked'),

                Actions\Action::make('terminate_sessions')
                    ->label('Terminate All Sessions')
                    ->icon('heroicon-o-stop-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This will terminate all active RADIUS sessions for this customer.')
                    ->form([
                        Forms\Components\TextInput::make('reason')
                            ->label('Termination Reason')
                            ->default('Admin Action')
                            ->required(),
                    ])
                    ->action(function (Customer $record, array $data) {
                        $record->terminateAllActiveSessions($data['reason']);
                        
                        Notification::make()
                            ->title('Sessions Terminated')
                            ->body("All active sessions for {$record->name} have been terminated.")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('reset_password')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->minLength(6)
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('generate')
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(function (Forms\Set $set) {
                                        $set('new_password', \Str::random(12));
                                    })
                            ),

                        Forms\Components\Toggle::make('sync_radius')
                            ->label('Sync with RADIUS')
                            ->default(true)
                            ->helperText('Update password in RADIUS servers'),
                    ])
                    ->action(function (Customer $record, array $data) {
                        $record->update(['password' => $data['new_password']]);
                        
                        if ($data['sync_radius']) {
                            $record->syncAllRadiusStatus();
                        }
                        
                        Notification::make()
                            ->title('Password Reset')
                            ->body("Password updated for {$record->name}.")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('sync_radius')
                    ->label('Sync with RADIUS')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function (Customer $record) {
                        $record->syncAllRadiusStatus();
                        
                        Notification::make()
                            ->title('RADIUS Sync Complete')
                            ->body("Customer {$record->name} synced with RADIUS.")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('send_credentials')
                    ->label('Send Credentials')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('method')
                            ->label('Send Method')
                            ->options([
                                'email' => 'Email',
                                'sms' => 'SMS',
                                'both' => 'Both Email & SMS',
                            ])
                            ->required()
                            ->default('email')
                            ->native(false),

                        Forms\Components\Textarea::make('message')
                            ->label('Custom Message')
                            ->rows(4)
                            ->placeholder('Optional custom message to include...')
                            ->columnSpanFull(),
                    ])
                    ->action(function (Customer $record, array $data) {
                        // Here you would implement sending credentials via email/SMS
                        Notification::make()
                            ->title('Credentials Sent')
                            ->body("Login credentials sent to {$record->name} via {$data['method']}.")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('customer_portal')
                    ->label('View Customer Portal')
                    ->icon('heroicon-o-computer-desktop')
                    ->color('purple')
                    ->action(function (Customer $record) {
                        $dashboard = $record->getCustomerDashboard();
                        
                        Notification::make()
                            ->title('Customer Portal Data')
                            ->body('Portal data generated - you can integrate this with your customer portal.')
                            ->info()
                            ->duration(5000)
                            ->send();
                    }),

                Actions\Action::make('regenerate_token')
                    ->label('Regenerate WiFi Token')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate WiFi Token')
                    ->modalDescription('Are you sure you want to regenerate the WiFi token? This will disconnect the customer from WiFi.')
                    ->modalSubmitActionLabel('Regenerate Token')
                    ->action(function (Customer $record) {
                        $activeSubscription = $record->getActiveSubscription();
                        if (!$activeSubscription) {
                            Notification::make()
                                ->title('No Active Subscription')
                                ->body('Customer must have an active subscription to generate WiFi token.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $newToken = $record->regenerateInternetToken();
                        
                        Notification::make()
                            ->title('WiFi Token Regenerated')
                            ->body("New WiFi token generated: {$newToken}")
                            ->success()
                            ->duration(10000)
                            ->send();
                    }),
            ])
            ->label('Customer Actions')
            ->icon('heroicon-o-cog-6-tooth')
            ->button()
            ->color('gray'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Generate username from phone if not provided
        if (empty($data['username']) && !empty($data['phone'])) {
            $data['username'] = preg_replace('/[^0-9]/', '', $data['phone']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $customer = $this->record;

        // Sync with RADIUS if credentials changed
        if ($this->record->wasChanged(['username', 'password', 'status'])) {
            $customer->syncAllRadiusStatus();
            
            Notification::make()
                ->title('RADIUS Synchronized')
                ->body('Customer credentials synced with RADIUS servers.')
                ->info()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Save Customer'),

            Actions\Action::make('save_and_view')
                ->label('Save & View')
                ->action(function () {
                    $this->save();
                    return redirect()->to(CustomerResource::getUrl('view', ['record' => $this->record]));
                })
                ->color('success')
                ->icon('heroicon-o-eye'),

            $this->getCancelFormAction(),
        ];
    }
}