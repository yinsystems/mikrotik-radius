<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DataUsageResource\Pages;
use App\Filament\Resources\DataUsageResource\RelationManagers;
use App\Filament\Resources\DataUsageResource\Widgets;
use App\Models\DataUsage;
use App\Models\Subscription;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DataUsageResource extends Resource
{
    protected static ?string $model = DataUsage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Analytics & Monitoring';

    protected static ?string $navigationLabel = 'Data Usage';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'username';

    public static function getNavigationBadge(): ?string
    {
        try {
            return number_format(static::getModel()::today()->count());
        } catch (\Exception $e) {
            return '0';
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        try {
            $todayCount = static::getModel()::today()->count();
            $yesterdayCount = static::getModel()::yesterday()->count();
            
            if ($todayCount > $yesterdayCount) {
                return 'success';
            } elseif ($todayCount < $yesterdayCount) {
                return 'warning';
            }
            
            return 'primary';
        } catch (\Exception $e) {
            return 'primary';
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Select::make('subscription_id')
                            ->label('Subscription')
                            ->relationship('subscription', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->username . ' (' . $record->customer->name . ')')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $subscription = Subscription::find($state);
                                    if ($subscription) {
                                        $set('username', $subscription->username);
                                    }
                                }
                            })
                            ->createOptionForm([
                                Forms\Components\Select::make('customer_id')
                                    ->relationship('customer', 'name')
                                    ->required(),
                                Forms\Components\Select::make('package_id')
                                    ->relationship('package', 'name')
                                    ->required(),
                            ]),
                            
                        Forms\Components\TextInput::make('username')
                            ->label('Username')
                            ->required()
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(),
                            
                        Forms\Components\DatePicker::make('date')
                            ->label('Usage Date')
                            ->required()
                            ->default(today())
                            ->maxDate(today())
                            ->displayFormat('Y-m-d'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Data Usage Statistics')
                    ->schema([
                        Forms\Components\TextInput::make('bytes_uploaded')
                            ->label('Bytes Uploaded')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->suffix('bytes')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                $uploaded = (int) $state;
                                $downloaded = (int) $get('bytes_downloaded') ?? 0;
                                $set('total_bytes', $uploaded + $downloaded);
                            }),
                            
                        Forms\Components\TextInput::make('bytes_downloaded')
                            ->label('Bytes Downloaded')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->suffix('bytes')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                $downloaded = (int) $state;
                                $uploaded = (int) $get('bytes_uploaded') ?? 0;
                                $set('total_bytes', $uploaded + $downloaded);
                            }),
                            
                        Forms\Components\TextInput::make('total_bytes')
                            ->label('Total Bytes')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->suffix('bytes')
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Session Information')
                    ->schema([
                        Forms\Components\TextInput::make('session_count')
                            ->label('Session Count')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Number of login sessions'),
                            
                        Forms\Components\TextInput::make('session_time')
                            ->label('Total Session Time')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('seconds')
                            ->helperText('Total time spent online in seconds'),
                            
                        Forms\Components\TextInput::make('peak_concurrent_sessions')
                            ->label('Peak Concurrent Sessions')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Maximum simultaneous sessions'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Calculated Values')
                    ->schema([
                        Forms\Components\Placeholder::make('formatted_total_size')
                            ->label('Total Data Usage')
                            ->content(fn ($record) => $record?->formatted_total_size ?? '0 B'),
                            
                        Forms\Components\Placeholder::make('formatted_session_time')
                            ->label('Formatted Session Time')
                            ->content(fn ($record) => $record?->formatted_session_time ?? '0s'),
                            
                        Forms\Components\Placeholder::make('average_session_time')
                            ->label('Average Session Duration')
                            ->content(fn ($record) => $record ? $record->average_session_time . ' seconds' : '0 seconds'),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->tooltip('Click to copy'),
                    
                Tables\Columns\TextColumn::make('subscription.customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->limit(20),
                    
                Tables\Columns\TextColumn::make('subscription.package.name')
                    ->label('Package')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('formatted_total_size')
                    ->label('Total Usage')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('total_bytes', $direction);
                    })
                    ->badge()
                    ->color(fn ($record) => $record->total_gb > 1 ? 'danger' : ($record->total_mb > 500 ? 'warning' : 'success')),
                    
                Tables\Columns\TextColumn::make('upload_mb')
                    ->label('Upload (MB)')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('download_mb')
                    ->label('Download (MB)')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('session_count')
                    ->label('Sessions')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->session_count > 20 ? 'warning' : 'primary'),
                    
                Tables\Columns\TextColumn::make('formatted_session_time')
                    ->label('Session Time')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('session_time', $direction);
                    })
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('peak_concurrent_sessions')
                    ->label('Peak Sessions')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recorded At')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date')
                            ->default(now()->subDays(7)),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date')
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'From ' . Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Until ' . Carbon::parse($data['until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
                    
                SelectFilter::make('subscription')
                    ->relationship('subscription', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->username . ' (' . $record->customer->name . ')')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                    
                Filter::make('high_usage')
                    ->label('High Usage (>1GB)')
                    ->query(fn (Builder $query): Builder => $query->where('total_bytes', '>=', 1024 * 1024 * 1024))
                    ->toggle(),
                    
                Filter::make('many_sessions')
                    ->label('Many Sessions (>20)')
                    ->query(fn (Builder $query): Builder => $query->where('session_count', '>', 20))
                    ->toggle(),
                    
                SelectFilter::make('today_usage')
                    ->label('Quick Filters')
                    ->options([
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        'this_week' => 'This Week',
                        'last_week' => 'Last Week',
                        'this_month' => 'This Month',
                        'last_month' => 'Last Month',
                    ])
                    ->query(function (Builder $query, array $data) {
                        return match ($data['value'] ?? null) {
                            'today' => $query->today(),
                            'yesterday' => $query->yesterday(),
                            'this_week' => $query->thisWeek(),
                            'last_week' => $query->lastWeek(),
                            'this_month' => $query->thisMonth(),
                            'last_month' => $query->lastMonth(),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->color('info'),
                    Tables\Actions\EditAction::make()
                        ->color('warning'),
                    Action::make('sync_from_radius')
                        ->label('Sync from RADIUS')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (DataUsage $record) {
                            if ($record->updateUsageFromRadius()) {
                                Notification::make()
                                    ->title('Usage data synced successfully')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Failed to sync usage data')
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('generate_report')
                        ->label('Generate Report')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->action(function (DataUsage $record) {
                            $report = $record->generateUsageReport();
                            
                            Notification::make()
                                ->title('Usage report generated')
                                ->body('Report contains usage details and warnings')
                                ->success()
                                ->send();
                                
                            // You could download or display the report here
                        }),
                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation(),
                ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->size('sm')
                ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('sync_all_from_radius')
                        ->label('Sync All from RADIUS')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $synced = 0;
                            foreach ($records as $record) {
                                if ($record->updateUsageFromRadius()) {
                                    $synced++;
                                }
                            }
                            
                            Notification::make()
                                ->title("Synced {$synced} out of {$records->count()} records")
                                ->success()
                                ->send();
                        }),
                        
                    BulkAction::make('export_usage_report')
                        ->label('Export Usage Report')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(function (Collection $records) {
                            $startDate = $records->min('date');
                            $endDate = $records->max('date');
                            
                            $csvData = DataUsage::exportUsageReport($startDate, $endDate, 'csv');
                            
                            $filename = 'usage_report_' . $startDate . '_to_' . $endDate . '.csv';
                            Storage::put('reports/' . $filename, $csvData);
                            
                            Notification::make()
                                ->title('Usage report exported')
                                ->body("Report saved as {$filename}")
                                ->success()
                                ->send();
                        }),
                        
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('date', 'desc')
            ->poll('30s')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SubscriptionRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\UsageOverview::class,
            Widgets\UsageAnalytics::class,
            Widgets\TopUsers::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDataUsages::route('/'),
            'create' => Pages\CreateDataUsage::route('/create'),
            'view' => Pages\ViewDataUsage::route('/{record}'),
            'edit' => Pages\EditDataUsage::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['subscription.customer']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['username', 'subscription.customer.name'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Customer' => $record->subscription->customer->name ?? 'Unknown',
            'Date' => $record->date->format('M j, Y'),
            'Usage' => $record->formatted_total_size,
        ];
    }
}
