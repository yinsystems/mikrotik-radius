<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Package;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_with_trial')
                ->label('Create with Trial Package')
                ->icon('heroicon-o-gift')
                ->color('info')
                ->visible(fn () => Package::where('is_trial', true)->where('is_active', true)->exists())
                ->form([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Customer full name'),

                            Forms\Components\TextInput::make('phone')
                                ->required()
                                ->tel()
                                ->maxLength(20)
                                ->placeholder('+1234567890')
                                ->unique(Customer::class, 'phone'),

                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->maxLength(255)
                                ->placeholder('customer@example.com')
                                ->unique(Customer::class, 'email'),

                            Forms\Components\Select::make('trial_package_id')
                                ->label('Trial Package')
                                ->options(Package::where('is_trial', true)->where('is_active', true)->pluck('name', 'id'))
                                ->required()
                                ->native(false),
                        ]),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('username')
                                ->maxLength(255)
                                ->placeholder('Leave empty to use phone number')
                                ->unique(Customer::class, 'username'),

                            Forms\Components\TextInput::make('password')
                                ->password()
                                ->maxLength(255)
                                ->placeholder('Generate secure password')
                                ->suffixAction(
                                    Forms\Components\Actions\Action::make('generate')
                                        ->icon('heroicon-o-arrow-path')
                                        ->action(function (Forms\Set $set) {
                                            $set('password', \Str::random(12));
                                        })
                                ),
                        ]),

                    Forms\Components\Textarea::make('notes')
                        ->rows(3)
                        ->placeholder('Initial notes for this customer...')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    // Create customer
                    $customer = Customer::create([
                        'name' => $data['name'],
                        'phone' => $data['phone'],
                        'email' => $data['email'] ?? null,
                        'username' => $data['username'] ?? null,
                        'password' => $data['password'] ?? \Str::random(12),
                        'status' => 'active',
                        'registration_date' => now(),
                        'notes' => $data['notes'] ?? null,
                    ]);

                    // Assign trial package
                    try {
                        $subscription = $customer->assignTrialPackage($data['trial_package_id']);
                        
                        Notification::make()
                            ->title('Customer Created with Trial')
                            ->body("Customer {$customer->name} created and trial package assigned successfully!")
                            ->success()
                            ->duration(5000)
                            ->send();

                        return redirect()->to(CustomerResource::getUrl('view', ['record' => $customer]));
                    } catch (\Exception $e) {
                        // If trial assignment fails, still keep the customer but notify
                        Notification::make()
                            ->title('Customer Created')
                            ->body("Customer created but trial assignment failed: {$e->getMessage()}")
                            ->warning()
                            ->duration(7000)
                            ->send();

                        return redirect()->to(CustomerResource::getUrl('edit', ['record' => $customer]));
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default values
        $data['registration_date'] = $data['registration_date'] ?? now();
        $data['status'] = $data['status'] ?? 'active';

        // Generate username from phone if not provided
        if (empty($data['username']) && !empty($data['phone'])) {
            $data['username'] = preg_replace('/[^0-9]/', '', $data['phone']);
        }

        // Generate password if not provided
        if (empty($data['password'])) {
            $data['password'] = \Str::random(12);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $customer = $this->record;

        // Log customer creation
        if ($customer->notes) {
            $customer->update([
                'notes' => $customer->notes . "\n" . "Customer created on " . now()->format('Y-m-d H:i:s')
            ]);
        } else {
            $customer->update([
                'notes' => "Customer created on " . now()->format('Y-m-d H:i:s')
            ]);
        }

        Notification::make()
            ->title('Customer Created Successfully')
            ->body("Customer {$customer->name} has been created with credentials.")
            ->success()
            ->duration(5000)
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('View Customer')
                    ->url(CustomerResource::getUrl('view', ['record' => $customer]))
                    ->button(),
                \Filament\Notifications\Actions\Action::make('add_subscription')
                    ->label('Add Subscription')
                    ->url(CustomerResource::getUrl('edit', ['record' => $customer]))
                    ->button(),
            ])
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Create Customer'),

            Actions\Action::make('create_and_add_subscription')
                ->label('Create & Add Subscription')
                ->action(function () {
                    // Create the customer first
                    $this->create();
                    
                    // Redirect to edit page where they can add subscription
                    return redirect()->to(CustomerResource::getUrl('edit', ['record' => $this->record]));
                })
                ->color('success')
                ->icon('heroicon-o-plus-circle'),

            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}