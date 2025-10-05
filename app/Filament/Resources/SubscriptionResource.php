<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Filament\Resources\SubscriptionResource\RelationManagers;
use App\Models\Subscription;
use App\Models\Customer;
use App\Models\Package;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'Subscriptions';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'username';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Customer Information')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable(['name', 'phone', 'email', 'username'])
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->required()
                                    ->unique(Customer::class, 'phone'),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->unique(Customer::class, 'email'),
                                Forms\Components\TextInput::make('username')
                                    ->required()
                                    ->unique(Customer::class, 'username')
                                    ->alphaDash(),
                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->required()
                                    ->minLength(6),
                                Forms\Components\Textarea::make('address')
                                    ->rows(3),
                            ])
                            ->required(),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Package & Subscription Details')
                    ->schema([
                        Forms\Components\Select::make('package_id')
                            ->label('Package')
                            ->relationship('package', 'name')
                            ->searchable(['name'])
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $package = Package::find($state);
                                    if ($package) {
                                        $set('is_trial', $package->is_trial);
                                        if ($package->is_trial) {
                                            $set('expires_at', now()->addHours($package->trial_duration_hours));
                                        }
                                    }
                                }
                            })
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                                'expired' => 'Expired',
                                'blocked' => 'Blocked',
                            ])
                            ->default('pending')
                            ->required(),

                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Start Date')
                            ->default(now())
                            ->required(),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expiry Date')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                $starts = $get('starts_at');
                                if ($starts && $state) {
                                    $duration = now()->parse($starts)->diffInHours(now()->parse($state));
                                    $set('duration_hours', $duration);
                                }
                            }),

                        Forms\Components\TextInput::make('duration_hours')
                            ->label('Duration (Hours)')
                            ->numeric()
                            ->readonly()
                            ->helperText('Calculated automatically based on start and end dates'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Usage & Limits')
                    ->schema([
                        Forms\Components\TextInput::make('data_used')
                            ->label('Data Used (MB)')
                            ->numeric()
                            ->default(0)
                            ->suffix('MB'),

                        Forms\Components\TextInput::make('sessions_used')
                            ->label('Sessions Used')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('is_trial')
                            ->label('Trial Subscription')
                            ->disabled()
                            ->helperText('Automatically set based on selected package'),

                        Forms\Components\Toggle::make('auto_renew')
                            ->label('Auto Renew')
                            ->default(false)
                            ->helperText('Automatically renew when expired'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Additional Settings')
                    ->schema([
                        Forms\Components\Select::make('renewal_package_id')
                            ->label('Renewal Package')
                            ->relationship('renewalPackage', 'name')
                            ->searchable(['name'])
                            ->preload()
                            ->helperText('Package to use for auto-renewal (leave empty to use same package)'),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(['name', 'phone'])
                    ->sortable()
                    ->limit(30)
                    ->tooltip(function (Subscription $record): ?string {
                        return $record->customer->phone ?? null;
                    }),

                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->copyable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('package.name')
                    ->label('Package')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (Subscription $record): string => match ($record->package->duration_type) {
                        'trial' => 'warning',
                        'hourly' => 'gray',
                        'daily' => 'info',
                        'weekly' => 'primary',
                        'monthly' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        'expired' => 'gray',
                        'blocked' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Start Date')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->color(fn (Subscription $record): string => match (true) {
                        $record->isExpired() => 'danger',
                        $record->days_until_expiry <= 1 => 'warning',
                        $record->days_until_expiry <= 7 => 'info',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('time_remaining')
                    ->label('Time Left')
                    ->badge()
                    ->color(fn (Subscription $record): string => match (true) {
                        $record->isExpired() => 'danger',
                        $record->days_until_expiry <= 1 => 'warning',
                        $record->days_until_expiry <= 7 => 'info',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('data_usage_percentage')
                    ->label('Data Usage')
                    ->formatStateUsing(fn ($state): string => number_format($state, 1) . '%')
                    ->badge()
                    ->color(fn (Subscription $record): string => match (true) {
                        $record->data_usage_percentage >= 100 => 'danger',
                        $record->data_usage_percentage >= 80 => 'warning',
                        $record->data_usage_percentage >= 60 => 'info',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('remaining_data')
                    ->label('Data Left')
                    ->badge()
                    ->color(fn (Subscription $record): string => match (true) {
                        $record->hasDataLimitExceeded() => 'danger',
                        $record->data_usage_percentage >= 80 => 'warning',
                        default => 'success',
                    }),

                Tables\Columns\IconColumn::make('is_trial')
                    ->label('Trial')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus'),

                Tables\Columns\IconColumn::make('auto_renew')
                    ->label('Auto Renew')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-path')
                    ->falseIcon('heroicon-o-minus'),

                Tables\Columns\TextColumn::make('active_sessions_count')
                    ->label('Active Sessions')
                    ->getStateUsing(fn (Subscription $record): int => $record->getCurrentSessionCount())
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'pending' => 'Pending',
                        'suspended' => 'Suspended',
                        'expired' => 'Expired',
                        'blocked' => 'Blocked',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('package')
                    ->relationship('package', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('is_trial')
                    ->label('Trial Subscription'),

                Tables\Filters\TernaryFilter::make('auto_renew')
                    ->label('Auto Renew'),

                Tables\Filters\Filter::make('expires_soon')
                    ->label('Expires Soon (24h)')
                    ->query(fn (Builder $query): Builder => $query->expiringSoon(24)),

                Tables\Filters\Filter::make('data_limit_exceeded')
                    ->label('Data Limit Exceeded')
                    ->query(fn (Builder $query): Builder => $query->dataLimitExceeded()),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->color('info'),

                    Tables\Actions\EditAction::make()
                        ->color('warning'),

                    Tables\Actions\Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Subscription $record) {
                            $record->activate();
                            Notification::make()
                                ->title('Subscription Activated')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Subscription $record): bool => in_array($record->status, ['pending', 'suspended'])),

                    Tables\Actions\Action::make('suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-pause')
                        ->color('warning')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Suspension Reason')
                                ->required()
                                ->placeholder('Please provide a reason for suspension...'),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Subscription $record, array $data) {
                            $record->suspend($data['reason']);
                            Notification::make()
                                ->title('Subscription Suspended')
                                ->warning()
                                ->send();
                        })
                        ->visible(fn (Subscription $record): bool => $record->status === 'active'),

                    Tables\Actions\Action::make('resume')
                        ->label('Resume')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Subscription $record) {
                            $record->resume();
                            Notification::make()
                                ->title('Subscription Resumed')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Subscription $record): bool => $record->status === 'suspended'),

                    Tables\Actions\Action::make('block')
                        ->label('Block')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Block Reason')
                                ->required()
                                ->placeholder('Please provide a reason for blocking...'),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Subscription $record, array $data) {
                            $record->block($data['reason']);
                            Notification::make()
                                ->title('Subscription Blocked')
                                ->danger()
                                ->send();
                        })
                        ->visible(fn (Subscription $record): bool => !in_array($record->status, ['blocked', 'expired'])),

                    Tables\Actions\Action::make('unblock')
                        ->label('Unblock')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Subscription $record) {
                            $record->unblock();
                            Notification::make()
                                ->title('Subscription Unblocked')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Subscription $record): bool => $record->status === 'blocked'),

                    Tables\Actions\Action::make('renew')
                        ->label('Renew')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->form([
                            Forms\Components\Select::make('package_id')
                                ->label('Renewal Package')
                                ->relationship('renewalPackage', 'name', fn (Builder $query) => $query->active())
                                ->searchable()
                                ->preload()
                                ->default(fn (Subscription $record) => $record->renewal_package_id ?? $record->package_id),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Subscription $record, array $data) {
                            $newPackage = Package::find($data['package_id']);
                            $record->renew($newPackage);
                            Notification::make()
                                ->title('Subscription Renewed')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Subscription $record): bool => $record->canRenew()),

                    Tables\Actions\Action::make('disconnect_sessions')
                        ->label('Disconnect Sessions')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Disconnection Reason')
                                ->default('Admin Disconnect')
                                ->required(),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Subscription $record, array $data) {
                            $result = $record->disconnectAllSessions($data['reason']);
                            Notification::make()
                                ->title($result['message'])
                                ->color($result['success'] ? 'success' : 'warning')
                                ->send();
                        })
                        ->visible(fn (Subscription $record): bool => $record->getCurrentSessionCount() > 0),

                    Tables\Actions\Action::make('sync_data_usage')
                        ->label('Sync Data Usage')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function (Subscription $record) {
                            $record->updateDataUsageFromRadius();
                            Notification::make()
                                ->title('Data Usage Synced')
                                ->success()
                                ->send();
                        }),
                ])->label('Actions')->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('activate_selected')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $activated = 0;
                            foreach ($records as $record) {
                                if (in_array($record->status, ['pending', 'suspended'])) {
                                    $record->activate();
                                    $activated++;
                                }
                            }
                            Notification::make()
                                ->title("Activated {$activated} subscriptions")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('suspend_selected')
                        ->label('Suspend Selected')
                        ->icon('heroicon-o-pause')
                        ->color('warning')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Suspension Reason')
                                ->required()
                                ->placeholder('Please provide a reason for suspension...'),
                        ])
                        ->requiresConfirmation()
                        ->action(function ($records, array $data) {
                            $suspended = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'active') {
                                    $record->suspend($data['reason']);
                                    $suspended++;
                                }
                            }
                            Notification::make()
                                ->title("Suspended {$suspended} subscriptions")
                                ->warning()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('sync_data_usage_bulk')
                        ->label('Sync Data Usage')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $synced = 0;
                            foreach ($records as $record) {
                                $record->updateDataUsageFromRadius();
                                $synced++;
                            }
                            Notification::make()
                                ->title("Synced data usage for {$synced} subscriptions")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s') // Auto-refresh every 30 seconds
            ->deferLoading()
            ->striped();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Customer Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('customer.name')
                            ->label('Customer Name'),
                        Infolists\Components\TextEntry::make('customer.phone')
                            ->label('Phone'),
                        Infolists\Components\TextEntry::make('customer.email')
                            ->label('Email'),
                        Infolists\Components\TextEntry::make('username')
                            ->label('Username')
                            ->copyable(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Subscription Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('package.name')
                            ->label('Package')
                            ->badge(),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'pending' => 'warning',
                                'suspended' => 'danger',
                                'expired' => 'gray',
                                'blocked' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('starts_at')
                            ->label('Start Date')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('expires_at')
                            ->label('Expiry Date')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('time_remaining')
                            ->label('Time Remaining')
                            ->badge(),
                        Infolists\Components\IconEntry::make('is_trial')
                            ->label('Trial Subscription')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('auto_renew')
                            ->label('Auto Renew')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('renewal_package.name')
                            ->label('Renewal Package')
                            ->placeholder('Same package'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Usage Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('data_used')
                            ->label('Data Used')
                            ->suffix(' MB'),
                        Infolists\Components\TextEntry::make('package.data_limit')
                            ->label('Data Limit')
                            ->formatStateUsing(fn ($state) => $state ? $state . ' MB' : 'Unlimited'),
                        Infolists\Components\TextEntry::make('data_usage_percentage')
                            ->label('Data Usage %')
                            ->suffix('%'),
                        Infolists\Components\TextEntry::make('remaining_data')
                            ->label('Remaining Data'),
                        Infolists\Components\TextEntry::make('sessions_used')
                            ->label('Sessions Used'),
                        Infolists\Components\TextEntry::make('active_sessions_count')
                            ->label('Active Sessions')
                            ->getStateUsing(fn (Subscription $record): int => $record->getCurrentSessionCount()),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Package Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('package.duration_display')
                            ->label('Duration'),
                        Infolists\Components\TextEntry::make('package.bandwidth_display')
                            ->label('Bandwidth'),
                        Infolists\Components\TextEntry::make('package.price')
                            ->label('Price')
                            ->money('GHS'),
                        Infolists\Components\TextEntry::make('package.simultaneous_users')
                            ->label('Simultaneous Users'),
                        Infolists\Components\TextEntry::make('package.vlan_id')
                            ->label('VLAN ID')
                            ->placeholder('Not set'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->prose()
                            ->placeholder('No notes available'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SessionsRelationManager::class,
            RelationManagers\DataUsageRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'view' => Pages\ViewSubscription::route('/{record}'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
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

    public static function getGloballySearchableAttributes(): array
    {
        return ['username', 'customer.name', 'customer.phone', 'customer.email'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->username . ' (' . $record->customer->name . ')';
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Package' => $record->package->name,
            'Status' => $record->status,
            'Expires' => $record->expires_at->format('M j, Y H:i'),
        ];
    }
}