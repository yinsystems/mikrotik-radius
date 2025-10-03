<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Customer Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 20;

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Phone' => $record->phone,
            'Email' => $record->email ?: 'No email',
            'Status' => ucfirst($record->status),
            'Username' => $record->username ?: 'Not set',
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone', 'username'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Customer Information')
                    ->description('Basic customer details and contact information')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-o-user')
                                    ->placeholder('Enter customer full name'),

                                Forms\Components\TextInput::make('phone')
                                    ->required()
                                    ->tel()
                                    ->maxLength(20)
                                    ->prefixIcon('heroicon-o-phone')
                                    ->placeholder('+1234567890')
                                    ->unique(Customer::class, 'phone', ignoreRecord: true),

                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-o-envelope')
                                    ->placeholder('customer@example.com')
                                    ->unique(Customer::class, 'email', ignoreRecord: true),

                                Forms\Components\Select::make('status')
                                    ->required()
                                    ->options([
                                        'active' => 'Active',
                                        'suspended' => 'Suspended',
                                        'blocked' => 'Blocked',
                                    ])
                                    ->default('active')
                                    ->native(false)
                                    ->prefixIcon('heroicon-o-shield-check'),
                            ]),
                    ]),

                Forms\Components\Section::make('RADIUS Credentials')
                    ->description('Customer login credentials for internet access')
                    ->icon('heroicon-o-key')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('username')
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-o-user-circle')
                                    ->placeholder('Leave empty to use phone number')
                                    ->helperText('If left empty, phone number will be used as username')
                                    ->unique(Customer::class, 'username', ignoreRecord: true),

                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-o-lock-closed')
                                    ->placeholder('Generate secure password')
                                    ->helperText('Strong password recommended for security')
                                    ->suffixAction(
                                        Forms\Components\Actions\Action::make('generate')
                                            ->icon('heroicon-o-arrow-path')
                                            ->action(function (Forms\Set $set) {
                                                $set('password', \Str::random(12));
                                            })
                                    ),
                            ]),
                    ]),

                Forms\Components\Section::make('Additional Information')
                    ->description('Registration details and administrative notes')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\DateTimePicker::make('registration_date')
                            ->default(now())
                            ->native(false)
                            ->prefixIcon('heroicon-o-calendar'),

                        Forms\Components\DateTimePicker::make('last_login')
                            ->native(false)
                            ->prefixIcon('heroicon-o-clock')
                            ->helperText('Last successful login timestamp'),

                        Forms\Components\Textarea::make('notes')
                            ->rows(4)
                            ->maxLength(65535)
                            ->placeholder('Administrative notes, suspension reasons, special instructions...')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->icon('heroicon-o-user')
                    ->copyable()
                    ->copyMessage('Name copied')
                    ->copyMessageDuration(1500),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-phone')
                    ->copyable()
                    ->copyMessage('Phone copied')
                    ->copyMessageDuration(1500),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-envelope')
                    ->copyable()
                    ->copyMessage('Email copied')
                    ->copyMessageDuration(1500)
                    ->placeholder('No email'),

                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user-circle')
                    ->copyable()
                    ->copyMessage('Username copied')
                    ->copyMessageDuration(1500)
                    ->placeholder('Phone as username'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'blocked' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'active' => 'heroicon-o-check-circle',
                        'suspended' => 'heroicon-o-pause-circle',
                        'blocked' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('activeSubscription.package.name')
                    ->label('Active Package')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-o-cube')
                    ->placeholder('No active package')
                    ->tooltip('Current active subscription package'),

                Tables\Columns\TextColumn::make('activeSubscription.expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->color(function ($record) {
                        if (!$record->activeSubscription) return 'gray';
                        $hoursLeft = $record->activeSubscription->expires_at->diffInHours(now());
                        return match (true) {
                            $hoursLeft <= 24 => 'danger',
                            $hoursLeft <= 72 => 'warning',
                            default => 'success'
                        };
                    })
                    ->icon(function ($record) {
                        if (!$record->activeSubscription) return 'heroicon-o-minus-circle';
                        $hoursLeft = $record->activeSubscription->expires_at->diffInHours(now());
                        return match (true) {
                            $hoursLeft <= 24 => 'heroicon-o-exclamation-triangle',
                            $hoursLeft <= 72 => 'heroicon-o-clock',
                            default => 'heroicon-o-check-circle'
                        };
                    })
                    ->placeholder('No subscription'),

                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->money('GHS')
                    ->alignEnd()
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->getTotalSpent())
                    ->icon('heroicon-o-banknotes')
                    ->color('success'),

                Tables\Columns\TextColumn::make('registration_date')
                    ->label('Registered')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_login')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-clock')
                    ->placeholder('Never logged in')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('subscriptions_count')
                    ->label('Subscriptions')
                    ->counts('subscriptions')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-rectangle-stack')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('payments_count')
                    ->label('Payments')
                    ->counts([
                        'payments' => fn (Builder $query) => $query->where('status', 'completed')
                    ])
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-credit-card')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'blocked' => 'Blocked',
                    ])
                    ->multiple()
                    ->default(['active']),

                Tables\Filters\Filter::make('has_active_subscription')
                    ->label('Has Active Subscription')
                    ->query(fn (Builder $query): Builder => $query->withActiveSubscription())
                    ->toggle(),

                Tables\Filters\Filter::make('no_active_subscription')
                    ->label('No Active Subscription')
                    ->query(fn (Builder $query): Builder => $query->withoutActiveSubscription())
                    ->toggle(),

                Tables\Filters\Filter::make('recent_registrations')
                    ->label('Recent Registrations (7 days)')
                    ->query(fn (Builder $query): Builder => $query->where('registration_date', '>=', now()->subDays(7)))
                    ->toggle(),

                Tables\Filters\Filter::make('trial_users')
                    ->label('Trial Users')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('subscriptions.package', fn ($q) => $q->where('is_trial', true))
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('high_value_customers')
                    ->label('High Value (>₵1,000)')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('payments', function (Builder $q) {
                            $q->where('status', 'completed')
                              ->havingRaw('SUM(amount) > 1000')
                              ->groupBy('customer_id');
                        });
                    })
                    ->toggle(),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring Soon (48h)')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('activeSubscription', function (Builder $q) {
                            $q->where('expires_at', '<=', now()->addHours(48))
                              ->where('expires_at', '>', now());
                        });
                    })
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->color('info'),

                Tables\Actions\EditAction::make()
                    ->color('warning'),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('suspend')
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

                    Tables\Actions\Action::make('resume')
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

                    Tables\Actions\Action::make('block')
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

                    Tables\Actions\Action::make('unblock')
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

                    Tables\Actions\Action::make('assign_trial')
                        ->icon('heroicon-o-gift')
                        ->color('info')
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
                        })
                        ->visible(fn (Customer $record) => $record->isEligibleForTrial()),

                    Tables\Actions\Action::make('terminate_sessions')
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

                    Tables\Actions\Action::make('reset_password')
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
                        ])
                        ->action(function (Customer $record, array $data) {
                            $record->update(['password' => $data['new_password']]);
                            
                            // Sync with RADIUS
                            $record->syncAllRadiusStatus();
                            
                            Notification::make()
                                ->title('Password Reset')
                                ->body("Password updated for {$record->name}.")
                                ->success()
                                ->send();
                        }),
                ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->size('sm')
                ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalDescription('This will permanently delete the selected customers and all their data.'),

                    Tables\Actions\BulkAction::make('suspend_bulk')
                        ->label('Suspend Selected')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('This will suspend all selected customers.')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Suspension Reason')
                                ->required()
                                ->placeholder('Enter reason for bulk suspension...')
                                ->rows(3),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $records->each(function (Customer $customer) use ($data) {
                                if ($customer->status === 'active') {
                                    $customer->suspend($data['reason']);
                                }
                            });
                            
                            Notification::make()
                                ->title('Customers Suspended')
                                ->body(count($records) . ' customers have been suspended.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('resume_bulk')
                        ->label('Resume Selected')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('This will resume all selected suspended customers.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (Customer $customer) {
                                if ($customer->status === 'suspended') {
                                    $customer->resume();
                                }
                            });
                            
                            Notification::make()
                                ->title('Customers Resumed')
                                ->body(count($records) . ' customers have been resumed.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('sync_radius')
                        ->label('Sync RADIUS')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (Customer $customer) {
                                $customer->syncAllRadiusStatus();
                            });
                            
                            Notification::make()
                                ->title('RADIUS Sync Complete')
                                ->body(count($records) . ' customers synced with RADIUS.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('registration_date', 'desc')
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Customer Overview')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->icon('heroicon-o-user')
                                    ->weight(FontWeight::SemiBold),

                                Infolists\Components\TextEntry::make('phone')
                                    ->icon('heroicon-o-phone')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('email')
                                    ->icon('heroicon-o-envelope')
                                    ->copyable()
                                    ->placeholder('No email'),

                                Infolists\Components\TextEntry::make('username')
                                    ->icon('heroicon-o-user-circle')
                                    ->copyable()
                                    ->placeholder('Phone as username'),

                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'suspended' => 'warning',
                                        'blocked' => 'danger',
                                        default => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('registration_date')
                                    ->label('Registered')
                                    ->dateTime()
                                    ->icon('heroicon-o-calendar'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Current Subscription')
                    ->icon('heroicon-o-cube')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('activeSubscription.package.name')
                                    ->label('Package')
                                    ->placeholder('No active subscription'),

                                Infolists\Components\TextEntry::make('activeSubscription.status')
                                    ->label('Status')
                                    ->badge()
                                    ->placeholder('No active subscription'),

                                Infolists\Components\TextEntry::make('activeSubscription.expires_at')
                                    ->label('Expires')
                                    ->dateTime()
                                    ->placeholder('No active subscription'),

                                Infolists\Components\TextEntry::make('activeSubscription.days_remaining')
                                    ->label('Days Remaining')
                                    ->getStateUsing(fn ($record) => 
                                        $record->activeSubscription ? 
                                        max(0, $record->activeSubscription->expires_at->diffInDays(now())) . ' days' : 
                                        'No active subscription'
                                    ),
                            ]),
                    ])
                    ->visible(fn ($record) => $record->activeSubscription),

                Infolists\Components\Section::make('Financial Summary')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_spent')
                                    ->label('Total Spent')
                                    ->money('GHS')
                                    ->getStateUsing(fn ($record) => $record->getTotalSpent()),

                                Infolists\Components\TextEntry::make('total_refunds')
                                    ->label('Total Refunds')
                                    ->money('GHS')
                                    ->getStateUsing(fn ($record) => $record->getTotalRefunds()),

                                Infolists\Components\TextEntry::make('net_spent')
                                    ->label('Net Spent')
                                    ->money('GHS')
                                    ->getStateUsing(fn ($record) => $record->getNetSpent()),
                            ]),
                    ]),

                Infolists\Components\Section::make('Administrative Notes')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->placeholder('No notes')
                            ->prose(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SubscriptionsRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'active')->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }
}