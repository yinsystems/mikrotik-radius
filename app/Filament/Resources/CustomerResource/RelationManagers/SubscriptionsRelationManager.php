<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\Package;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Subscription Details')
                    ->schema([
                        Forms\Components\Select::make('package_id')
                            ->label('Package')
                            ->options(Package::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                                'expired' => 'Expired',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\DateTimePicker::make('starts_at')
                            ->required()
                            ->default(now())
                            ->native(false),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->required()
                            ->native(false),

                        Forms\Components\Toggle::make('auto_renew')
                            ->label('Auto Renew')
                            ->default(false),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('package.name')
                    ->label('Package')
                    ->sortable()
                    ->searchable()
                    ->weight(FontWeight::SemiBold)
                    ->icon('heroicon-o-cube'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        'expired' => 'gray',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'active' => 'heroicon-o-check-circle',
                        'pending' => 'heroicon-o-clock',
                        'suspended' => 'heroicon-o-pause-circle',
                        'expired' => 'heroicon-o-x-circle',
                        'cancelled' => 'heroicon-o-no-symbol',
                        default => 'heroicon-o-question-mark-circle',
                    }),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Started')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-play-circle'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->color(function ($record) {
                        if (!$record->expires_at) return 'gray';
                        $hoursLeft = $record->expires_at->diffInHours(now());
                        return match (true) {
                            $hoursLeft <= 24 => 'danger',
                            $hoursLeft <= 72 => 'warning',
                            default => 'success'
                        };
                    })
                    ->icon(function ($record) {
                        if (!$record->expires_at) return 'heroicon-o-minus-circle';
                        $hoursLeft = $record->expires_at->diffInHours(now());
                        return match (true) {
                            $hoursLeft <= 24 => 'heroicon-o-exclamation-triangle',
                            $hoursLeft <= 72 => 'heroicon-o-clock',
                            default => 'heroicon-o-check-circle'
                        };
                    }),

                Tables\Columns\TextColumn::make('days_remaining')
                    ->label('Remaining')
                    ->getStateUsing(fn ($record) => 
                        $record->expires_at 
                        ? max(0, $record->expires_at->diffInDays(now())) . ' days'
                        : 'No expiry'
                    )
                    ->color(function ($record) {
                        if (!$record->expires_at) return 'gray';
                        $daysLeft = $record->expires_at->diffInDays(now());
                        return match (true) {
                            $daysLeft <= 1 => 'danger',
                            $daysLeft <= 3 => 'warning',
                            default => 'success'
                        };
                    }),

                Tables\Columns\IconColumn::make('auto_renew')
                    ->label('Auto Renew')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('payment.amount')
                    ->label('Amount')
                    ->money('GHS')
                    ->placeholder('Free')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
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
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('package')
                    ->relationship('package', 'name')
                    ->searchable()
                    ->multiple(),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring Soon (48h)')
                    ->query(fn (Builder $query) => 
                        $query->where('expires_at', '<=', now()->addHours(48))
                              ->where('expires_at', '>', now())
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('auto_renew')
                    ->label('Auto Renew Enabled')
                    ->query(fn (Builder $query) => $query->where('auto_renew', true))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        // Auto-calculate expiration based on package duration
                        if (isset($data['package_id'])) {
                            $package = Package::find($data['package_id']);
                            if ($package) {
                                $startDate = \Carbon\Carbon::parse($data['starts_at']);
                                $data['expires_at'] = match($package->duration_type) {
                                    'hourly' => $startDate->addHours($package->duration_value),
                                    'daily' => $startDate->addDays($package->duration_value),
                                    'weekly' => $startDate->addWeeks($package->duration_value),
                                    'monthly' => $startDate->addMonths($package->duration_value),
                                    default => $startDate->addDays(1)
                                };
                            }
                        }
                        return $data;
                    })
                    ->after(function (Subscription $record) {
                        // Create RADIUS user when subscription is created
                        $record->createRadiusUser();
                        
                        Notification::make()
                            ->title('Subscription Created')
                            ->body('Subscription created and RADIUS user configured.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('activate')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->action(function (Subscription $record) {
                            $record->activate();
                            
                            Notification::make()
                                ->title('Subscription Activated')
                                ->body('Subscription has been activated.')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Subscription $record) => $record->status === 'pending'),

                    Tables\Actions\Action::make('suspend')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Suspension Reason')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (Subscription $record, array $data) {
                            $record->suspend($data['reason']);
                            
                            Notification::make()
                                ->title('Subscription Suspended')
                                ->body('Subscription has been suspended.')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Subscription $record) => $record->status === 'active'),

                    Tables\Actions\Action::make('resume')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->action(function (Subscription $record) {
                            $record->activate();
                            
                            Notification::make()
                                ->title('Subscription Resumed')
                                ->body('Subscription has been resumed.')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Subscription $record) => $record->status === 'suspended'),

                    Tables\Actions\Action::make('extend')
                        ->icon('heroicon-o-clock')
                        ->color('info')
                        ->form([
                            Forms\Components\Select::make('extend_type')
                                ->label('Extend By')
                                ->options([
                                    'hours' => 'Hours',
                                    'days' => 'Days',
                                    'weeks' => 'Weeks',
                                    'months' => 'Months',
                                ])
                                ->required()
                                ->native(false),

                            Forms\Components\TextInput::make('extend_value')
                                ->label('Amount')
                                ->numeric()
                                ->required()
                                ->minValue(1),
                        ])
                        ->action(function (Subscription $record, array $data) {
                            $currentExpiry = $record->expires_at;
                            
                            $newExpiry = match($data['extend_type']) {
                                'hours' => $currentExpiry->addHours($data['extend_value']),
                                'days' => $currentExpiry->addDays($data['extend_value']),
                                'weeks' => $currentExpiry->addWeeks($data['extend_value']),
                                'months' => $currentExpiry->addMonths($data['extend_value']),
                                default => $currentExpiry->addDays($data['extend_value'])
                            };
                            
                            $record->update(['expires_at' => $newExpiry]);
                            
                            Notification::make()
                                ->title('Subscription Extended')
                                ->body("Subscription extended by {$data['extend_value']} {$data['extend_type']}.")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('renew')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->form([
                            Forms\Components\Select::make('new_package_id')
                                ->label('Renewal Package')
                                ->options(Package::where('is_active', true)->pluck('name', 'id'))
                                ->default(fn (Subscription $record) => $record->package_id)
                                ->required()
                                ->native(false),
                        ])
                        ->action(function (Subscription $record, array $data) {
                            $newSubscription = $record->customer->createSubscription($data['new_package_id']);
                            $newSubscription->activate();
                            
                            // Mark current subscription as expired
                            $record->update(['status' => 'expired']);
                            
                            Notification::make()
                                ->title('Subscription Renewed')
                                ->body('New subscription created and activated.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('terminate_sessions')
                        ->icon('heroicon-o-stop-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Subscription $record) {
                            if ($record->username) {
                                \App\Models\RadAcct::terminateUserSessions($record->username, 'Admin Action');
                            }
                            
                            Notification::make()
                                ->title('Sessions Terminated')
                                ->body('All active sessions have been terminated.')
                                ->success()
                                ->send();
                        }),
                ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->size('sm'),

                Tables\Actions\DeleteAction::make()
                    ->before(function (Subscription $record) {
                        // Clean up RADIUS user before deletion
                        $record->deleteRadiusUser();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('activate_bulk')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (Subscription $subscription) {
                                if ($subscription->status === 'pending') {
                                    $subscription->activate();
                                }
                            });
                            
                            Notification::make()
                                ->title('Subscriptions Activated')
                                ->body(count($records) . ' subscriptions have been activated.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('suspend_bulk')
                        ->label('Suspend Selected')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Suspension Reason')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $records->each(function (Subscription $subscription) use ($data) {
                                if ($subscription->status === 'active') {
                                    $subscription->suspend($data['reason']);
                                }
                            });
                            
                            Notification::make()
                                ->title('Subscriptions Suspended')
                                ->body(count($records) . ' subscriptions have been suspended.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}