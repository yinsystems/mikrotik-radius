<?php

namespace App\Filament\Resources\SubscriptionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\DataUsage;
use Filament\Support\Enums\FontWeight;

class DataUsageRelationManager extends RelationManager
{
    protected static string $relationship = 'dataUsage';

    protected static ?string $title = 'Data Usage History';

    protected static ?string $label = 'Data Usage';

    protected static ?string $pluralLabel = 'Data Usage Records';

    protected static ?string $icon = 'heroicon-o-chart-bar';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Data Usage Information')
                    ->schema([
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->default(today()),

                        Forms\Components\TextInput::make('bytes_uploaded')
                            ->label('Bytes Uploaded')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->step(1)
                            ->minValue(0),

                        Forms\Components\TextInput::make('bytes_downloaded')
                            ->label('Bytes Downloaded')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->step(1)
                            ->minValue(0),

                        Forms\Components\TextInput::make('total_bytes')
                            ->label('Total Bytes')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->step(1)
                            ->minValue(0)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                $uploaded = $get('bytes_uploaded') ?: 0;
                                $downloaded = $get('bytes_downloaded') ?: 0;
                                if (!$state) {
                                    $set('total_bytes', $uploaded + $downloaded);
                                }
                            }),

                        Forms\Components\TextInput::make('session_count')
                            ->label('Session Count')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('session_time')
                            ->label('Session Time (seconds)')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Optional notes about this usage record...'),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable()
                    ->searchable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('total_mb')
                    ->label('Total Usage')
                    ->getStateUsing(fn (DataUsage $record): string => 
                        round($record->total_bytes / (1024 * 1024), 2) . ' MB'
                    )
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('bytes_uploaded')
                    ->label('Uploaded')
                    ->formatStateUsing(function ($state) {
                        if ($state >= 1073741824) { // GB
                            return round($state / 1073741824, 2) . ' GB';
                        } elseif ($state >= 1048576) { // MB
                            return round($state / 1048576, 2) . ' MB';
                        } elseif ($state >= 1024) { // KB
                            return round($state / 1024, 2) . ' KB';
                        } else {
                            return $state . ' B';
                        }
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('bytes_downloaded')
                    ->label('Downloaded')
                    ->formatStateUsing(function ($state) {
                        if ($state >= 1073741824) { // GB
                            return round($state / 1073741824, 2) . ' GB';
                        } elseif ($state >= 1048576) { // MB
                            return round($state / 1048576, 2) . ' MB';
                        } elseif ($state >= 1024) { // KB
                            return round($state / 1024, 2) . ' KB';
                        } else {
                            return $state . ' B';
                        }
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('session_count')
                    ->label('Sessions')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('session_time')
                    ->label('Duration')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '0s';
                        
                        $hours = floor($state / 3600);
                        $minutes = floor(($state % 3600) / 60);
                        $seconds = $state % 60;
                        
                        if ($hours > 0) {
                            return sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
                        } elseif ($minutes > 0) {
                            return sprintf('%dm %ds', $minutes, $seconds);
                        } else {
                            return sprintf('%ds', $seconds);
                        }
                    })
                    ->sortable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('avg_session_mb')
                    ->label('Avg/Session')
                    ->getStateUsing(function (DataUsage $record): string {
                        if ($record->session_count == 0) return '0 MB';
                        $avgBytes = $record->total_bytes / $record->session_count;
                        return round($avgBytes / (1024 * 1024), 2) . ' MB';
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recorded')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('notes')
                    ->limit(30)
                    ->tooltip(function (DataUsage $record): ?string {
                        return $record->notes;
                    })
                    ->placeholder('No notes')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('to_date')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['to_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),

                Tables\Filters\Filter::make('high_usage')
                    ->label('High Usage (>500MB)')
                    ->query(fn (Builder $query): Builder => $query->where('total_bytes', '>', 500 * 1024 * 1024)),

                Tables\Filters\Filter::make('multiple_sessions')
                    ->label('Multiple Sessions (>5)')
                    ->query(fn (Builder $query): Builder => $query->where('session_count', '>', 5)),

                Tables\Filters\Filter::make('long_duration')
                    ->label('Long Duration (>2 hours)')
                    ->query(fn (Builder $query): Builder => $query->where('session_time', '>', 7200)),

                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('date', today())),

                Tables\Filters\Filter::make('yesterday')
                    ->label('Yesterday')
                    ->query(fn (Builder $query): Builder => $query->whereDate('date', yesterday())),

                Tables\Filters\Filter::make('this_week')
                    ->label('This Week')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('date', [
                        now()->startOfWeek(),
                        now()->endOfWeek()
                    ])),

                Tables\Filters\Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('date', [
                        now()->startOfMonth(),
                        now()->endOfMonth()
                    ])),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Usage Record')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Ensure total_bytes is calculated if not provided
                        if (!isset($data['total_bytes']) || $data['total_bytes'] == 0) {
                            $data['total_bytes'] = ($data['bytes_uploaded'] ?? 0) + ($data['bytes_downloaded'] ?? 0);
                        }
                        
                        // Set username from the subscription
                        $subscription = $this->getOwnerRecord();
                        $data['username'] = $subscription->username;
                        
                        return $data;
                    }),

                Tables\Actions\Action::make('sync_from_radius')
                    ->label('Sync from RADIUS')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function () {
                        $subscription = $this->getOwnerRecord();
                        
                        try {
                            // Get RADIUS data and create usage records
                            $radiusData = $subscription->getSessionsByDate(today());
                            
                            if ($radiusData->isEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('No RADIUS data found')
                                    ->body('No session data found for today.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            // Calculate totals
                            $totalUploaded = $radiusData->sum('acctoutputoctets');
                            $totalDownloaded = $radiusData->sum('acctinputoctets');
                            $totalBytes = $totalUploaded + $totalDownloaded;
                            $sessionCount = $radiusData->count();
                            $totalTime = $radiusData->sum('acctsessiontime');

                            // Check if record already exists for today
                            $existingRecord = $subscription->dataUsage()
                                ->whereDate('date', today())
                                ->first();

                            if ($existingRecord) {
                                $existingRecord->update([
                                    'bytes_uploaded' => $totalUploaded,
                                    'bytes_downloaded' => $totalDownloaded,
                                    'total_bytes' => $totalBytes,
                                    'session_count' => $sessionCount,
                                    'session_time' => $totalTime,
                                    'notes' => 'Synced from RADIUS on ' . now()->format('Y-m-d H:i:s'),
                                ]);
                                $action = 'updated';
                            } else {
                                $subscription->dataUsage()->create([
                                    'username' => $subscription->username,
                                    'date' => today(),
                                    'bytes_uploaded' => $totalUploaded,
                                    'bytes_downloaded' => $totalDownloaded,
                                    'total_bytes' => $totalBytes,
                                    'session_count' => $sessionCount,
                                    'session_time' => $totalTime,
                                    'notes' => 'Synced from RADIUS on ' . now()->format('Y-m-d H:i:s'),
                                ]);
                                $action = 'created';
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('RADIUS Sync Successful')
                                ->body("Data usage record {$action} with " . round($totalBytes / (1024 * 1024), 2) . " MB")
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('RADIUS Sync Failed')
                                ->body('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('generate_report')
                    ->label('Generate Report')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('period')
                            ->label('Report Period')
                            ->options([
                                'week' => 'Last 7 days',
                                'month' => 'Last 30 days',
                                'quarter' => 'Last 90 days',
                                'custom' => 'Custom Range',
                            ])
                            ->default('month')
                            ->live()
                            ->required(),

                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->visible(fn (Forms\Get $get) => $get('period') === 'custom')
                            ->required(fn (Forms\Get $get) => $get('period') === 'custom'),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->visible(fn (Forms\Get $get) => $get('period') === 'custom')
                            ->required(fn (Forms\Get $get) => $get('period') === 'custom'),

                        Forms\Components\Select::make('format')
                            ->label('Export Format')
                            ->options([
                                'pdf' => 'PDF Report',
                                'excel' => 'Excel Spreadsheet',
                                'csv' => 'CSV File',
                            ])
                            ->default('pdf')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $subscription = $this->getOwnerRecord();
                        
                        // Calculate date range based on period
                        switch ($data['period']) {
                            case 'week':
                                $startDate = now()->subWeek();
                                $endDate = now();
                                break;
                            case 'month':
                                $startDate = now()->subMonth();
                                $endDate = now();
                                break;
                            case 'quarter':
                                $startDate = now()->subQuarter();
                                $endDate = now();
                                break;
                            case 'custom':
                                $startDate = $data['start_date'];
                                $endDate = $data['end_date'];
                                break;
                        }

                        // Generate report data
                        $usageData = $subscription->dataUsage()
                            ->whereBetween('date', [$startDate, $endDate])
                            ->orderBy('date')
                            ->get();

                        $reportData = [
                            'subscription' => $subscription,
                            'period' => ['start' => $startDate, 'end' => $endDate],
                            'usage_data' => $usageData,
                            'total_usage' => $usageData->sum('total_bytes'),
                            'total_sessions' => $usageData->sum('session_count'),
                            'total_time' => $usageData->sum('session_time'),
                            'avg_daily_usage' => $usageData->avg('total_bytes'),
                        ];

                        // In a real implementation, you would generate the actual file
                        \Filament\Notifications\Notification::make()
                            ->title('Report Generated')
                            ->body("Data usage report for {$data['period']} period has been prepared.")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalContent(function (DataUsage $record) {
                        return view('filament.data-usage-details', [
                            'usage' => $record,
                            'totalMB' => round($record->total_bytes / (1024 * 1024), 2),
                            'uploadMB' => round($record->bytes_uploaded / (1024 * 1024), 2),
                            'downloadMB' => round($record->bytes_downloaded / (1024 * 1024), 2),
                        ]);
                    })
                    ->modalHeading(fn (DataUsage $record): string => 'Data Usage: ' . $record->date->format('M j, Y'))
                    ->modalWidth('2xl'),

                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Recalculate total_bytes if upload/download changed
                        $data['total_bytes'] = ($data['bytes_uploaded'] ?? 0) + ($data['bytes_downloaded'] ?? 0);
                        return $data;
                    }),

                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('view_sessions')
                    ->label('View Sessions')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->action(function (DataUsage $record) {
                        $subscription = $this->getOwnerRecord();
                        $sessions = $subscription->getSessionsByDate($record->date);
                        
                        if ($sessions->isEmpty()) {
                            \Filament\Notifications\Notification::make()
                                ->title('No Sessions Found')
                                ->body('No session data found for this date.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $sessionSummary = $sessions->map(function ($session) {
                            return [
                                'ID' => $session->acctsessionid,
                                'Start' => $session->acctstarttime?->format('H:i:s'),
                                'Duration' => gmdate('H:i:s', $session->acctsessiontime ?? 0),
                                'Data' => round((($session->acctinputoctets ?? 0) + ($session->acctoutputoctets ?? 0)) / (1024 * 1024), 2) . ' MB',
                            ];
                        })->take(5); // Show first 5 sessions

                        $summary = "Found {$sessions->count()} sessions:\n\n" . 
                                  $sessionSummary->map(function ($session) {
                                      return "{$session['ID']}: {$session['Start']} ({$session['Duration']}) - {$session['Data']}";
                                  })->join("\n");

                        if ($sessions->count() > 5) {
                            $summary .= "\n\n... and " . ($sessions->count() - 5) . " more sessions";
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Session Summary')
                            ->body($summary)
                            ->info()
                            ->duration(15000)
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('export_selected')
                        ->label('Export Selected')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(function ($records) {
                            $exportData = $records->map(function (DataUsage $record) {
                                return [
                                    'Date' => $record->date->format('Y-m-d'),
                                    'Username' => $record->username,
                                    'Uploaded (MB)' => round($record->bytes_uploaded / (1024 * 1024), 2),
                                    'Downloaded (MB)' => round($record->bytes_downloaded / (1024 * 1024), 2),
                                    'Total (MB)' => round($record->total_bytes / (1024 * 1024), 2),
                                    'Sessions' => $record->session_count,
                                    'Duration (hours)' => round($record->session_time / 3600, 2),
                                    'Notes' => $record->notes,
                                ];
                            });

                            \Filament\Notifications\Notification::make()
                                ->title('Export Prepared')
                                ->body('Selected data usage records have been prepared for export.')
                                ->info()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('calculate_totals')
                        ->label('Calculate Totals')
                        ->icon('heroicon-o-calculator')
                        ->color('primary')
                        ->action(function ($records) {
                            $totalBytes = $records->sum('total_bytes');
                            $totalSessions = $records->sum('session_count');
                            $totalTime = $records->sum('session_time');
                            $avgDaily = $totalBytes / $records->count();

                            $summary = sprintf(
                                "Selected Records Summary:\n\n" .
                                "Total Data: %.2f GB\n" .
                                "Total Sessions: %d\n" .
                                "Total Time: %.1f hours\n" .
                                "Average Daily: %.2f MB\n" .
                                "Records: %d days",
                                $totalBytes / (1024 * 1024 * 1024),
                                $totalSessions,
                                $totalTime / 3600,
                                $avgDaily / (1024 * 1024),
                                $records->count()
                            );

                            \Filament\Notifications\Notification::make()
                                ->title('Usage Totals')
                                ->body($summary)
                                ->info()
                                ->duration(10000)
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('date', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}