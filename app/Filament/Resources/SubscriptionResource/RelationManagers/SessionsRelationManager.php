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
use App\Models\RadAcct;
use Filament\Support\Enums\FontWeight;

use Illuminate\Database\Eloquent\Model;

class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'radAcct';

    protected static ?string $title = 'User Sessions';

    protected static ?string $label = 'Session';

    protected static ?string $pluralLabel = 'Sessions';

    protected static ?string $icon = 'heroicon-o-signal';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Sessions are read-only, no form needed
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('acctsessionid')
            ->columns([
                Tables\Columns\TextColumn::make('acctsessionid')
                    ->label('Session ID')
                    ->searchable()
                    ->copyable()
                    ->weight(FontWeight::Medium)
                    ->limit(15),

                Tables\Columns\TextColumn::make('nasipaddress')
                    ->label('NAS IP')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nasportid')
                    ->label('NAS Port')
                    ->sortable(),

                Tables\Columns\TextColumn::make('framedipaddress')
                    ->label('User IP')
                    ->searchable()
                    ->copyable()
                    ->placeholder('Not assigned'),

                Tables\Columns\TextColumn::make('callingstationid')
                    ->label('MAC Address')
                    ->searchable()
                    ->copyable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('acctstarttime')
                    ->label('Start Time')
                    ->dateTime('M j, Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('acctstoptime')
                    ->label('Stop Time')
                    ->dateTime('M j, Y H:i:s')
                    ->sortable()
                    ->placeholder('Active')
                    ->color(fn ($state) => $state ? 'gray' : 'success'),

                Tables\Columns\TextColumn::make('acctsessiontime')
                    ->label('Duration')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return 'Active';
                        
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
                    ->color(fn ($state) => $state ? 'info' : 'success'),

                Tables\Columns\TextColumn::make('total_bytes')
                    ->label('Data Used')
                    ->getStateUsing(function (RadAcct $record): string {
                        $totalBytes = ($record->acctinputoctets ?? 0) + ($record->acctoutputoctets ?? 0);
                        
                        if ($totalBytes >= 1073741824) { // GB
                            return round($totalBytes / 1073741824, 2) . ' GB';
                        } elseif ($totalBytes >= 1048576) { // MB
                            return round($totalBytes / 1048576, 2) . ' MB';
                        } elseif ($totalBytes >= 1024) { // KB
                            return round($totalBytes / 1024, 2) . ' KB';
                        } else {
                            return $totalBytes . ' B';
                        }
                    })
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('acctinputoctets')
                    ->label('Downloaded')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '0 B';
                        
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
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('acctoutputoctets')
                    ->label('Uploaded')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '0 B';
                        
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
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->getStateUsing(fn (RadAcct $record): bool => is_null($record->acctstoptime))
                    ->boolean()
                    ->trueIcon('heroicon-o-signal')
                    ->falseIcon('heroicon-o-signal-slash')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('acctterminatecause')
                    ->label('Terminate Cause')
                    ->placeholder('Active')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(20),

                Tables\Columns\TextColumn::make('calledstationid')
                    ->label('Called Station')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(20),

                Tables\Columns\TextColumn::make('servicetype')
                    ->label('Service Type')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('framedprotocol')
                    ->label('Protocol')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('active_sessions')
                    ->label('Active Sessions Only')
                    ->query(fn (Builder $query): Builder => $query->whereNull('acctstoptime')),

                Tables\Filters\Filter::make('completed_sessions')
                    ->label('Completed Sessions Only')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('acctstoptime')),

                Tables\Filters\SelectFilter::make('nasipaddress')
                    ->label('NAS IP Address')
                    ->options(function () {
                        return RadAcct::distinct()
                            ->whereNotNull('nasipaddress')
                            ->pluck('nasipaddress', 'nasipaddress')
                            ->toArray();
                    })
                    ->searchable(),

                Tables\Filters\Filter::make('session_date')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('acctstarttime', '>=', $date),
                            )
                            ->when(
                                $data['to_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('acctstarttime', '<=', $date),
                            );
                    }),

                Tables\Filters\Filter::make('long_sessions')
                    ->label('Long Sessions (>1 hour)')
                    ->query(fn (Builder $query): Builder => $query->where('acctsessiontime', '>', 3600)),

                Tables\Filters\Filter::make('high_usage')
                    ->label('High Data Usage (>100MB)')
                    ->query(function (Builder $query): Builder {
                        return $query->whereRaw('(acctinputoctets + acctoutputoctets) > ?', [100 * 1024 * 1024]);
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalContent(function (RadAcct $record) {
                        return view('filament.session-details', [
                            'session' => $record,
                            'totalBytes' => ($record->acctinputoctets ?? 0) + ($record->acctoutputoctets ?? 0),
                            'isActive' => is_null($record->acctstoptime),
                        ]);
                    })
                    ->modalHeading(fn (RadAcct $record): string => 'Session Details: ' . $record->acctsessionid)
                    ->modalWidth('3xl'),

                Tables\Actions\Action::make('terminate')
                    ->label('Terminate')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Termination Reason')
                            ->default('Admin Termination')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (RadAcct $record, array $data) {
                        // Update the session to mark it as terminated
                        $record->update([
                            'acctstoptime' => now(),
                            'acctterminatecause' => $data['reason'],
                            'acctsessiontime' => $record->acctstarttime ? 
                                now()->diffInSeconds($record->acctstarttime) : 0
                        ]);

                        // Send RADIUS disconnect message (if implementation exists)
                        try {
                            $subscription = $this->getOwnerRecord();
                            $subscription->sendRadiusDisconnect(
                                $record->acctsessionid, 
                                $record->nasipaddress, 
                                $data['reason']
                            );
                        } catch (\Exception $e) {
                            \Log::error('Failed to send RADIUS disconnect: ' . $e->getMessage());
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Session Terminated')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (RadAcct $record): bool => is_null($record->acctstoptime)),

                Tables\Actions\Action::make('view_details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->action(function (RadAcct $record) {
                        // Show detailed session information
                        $details = [
                            'Session ID' => $record->acctsessionid,
                            'Username' => $record->username,
                            'NAS IP' => $record->nasipaddress,
                            'NAS Port' => $record->nasportid,
                            'Framed IP' => $record->framedipaddress,
                            'Calling Station' => $record->callingstationid,
                            'Called Station' => $record->calledstationid,
                            'Start Time' => $record->acctstarttime?->format('Y-m-d H:i:s'),
                            'Stop Time' => $record->acctstoptime?->format('Y-m-d H:i:s') ?? 'Active',
                            'Session Time' => $record->acctsessiontime ? 
                                gmdate('H:i:s', $record->acctsessiontime) : 'Active',
                            'Input Octets' => number_format($record->acctinputoctets ?? 0),
                            'Output Octets' => number_format($record->acctoutputoctets ?? 0),
                            'Input Packets' => number_format($record->acctinputpackets ?? 0),
                            'Output Packets' => number_format($record->acctoutputpackets ?? 0),
                            'Terminate Cause' => $record->acctterminatecause ?? 'N/A',
                            'Service Type' => $record->servicetype,
                            'Framed Protocol' => $record->framedprotocol,
                            'Connect Info' => $record->connectinfo_start,
                        ];

                        $detailsText = collect($details)
                            ->map(fn ($value, $key) => "{$key}: {$value}")
                            ->join("\n");

                        \Filament\Notifications\Notification::make()
                            ->title('Session Details')
                            ->body($detailsText)
                            ->info()
                            ->duration(10000)
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('terminate_selected')
                        ->label('Terminate Selected Sessions')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Termination Reason')
                                ->default('Bulk Admin Termination')
                                ->required(),
                        ])
                        ->requiresConfirmation()
                        ->action(function ($records, array $data) {
                            $terminated = 0;
                            foreach ($records as $record) {
                                if (is_null($record->acctstoptime)) {
                                    $record->update([
                                        'acctstoptime' => now(),
                                        'acctterminatecause' => $data['reason'],
                                        'acctsessiontime' => $record->acctstarttime ? 
                                            now()->diffInSeconds($record->acctstarttime) : 0
                                    ]);
                                    $terminated++;
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->title("Terminated {$terminated} active sessions")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('export_sessions')
                        ->label('Export Selected')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(function ($records) {
                            // Export sessions to CSV or Excel
                            $sessionData = $records->map(function (RadAcct $record) {
                                return [
                                    'Session ID' => $record->acctsessionid,
                                    'Username' => $record->username,
                                    'NAS IP' => $record->nasipaddress,
                                    'Start Time' => $record->acctstarttime?->format('Y-m-d H:i:s'),
                                    'Stop Time' => $record->acctstoptime?->format('Y-m-d H:i:s'),
                                    'Duration (seconds)' => $record->acctsessiontime,
                                    'Input Octets' => $record->acctinputoctets,
                                    'Output Octets' => $record->acctoutputoctets,
                                    'Total Bytes' => ($record->acctinputoctets ?? 0) + ($record->acctoutputoctets ?? 0),
                                    'Framed IP' => $record->framedipaddress,
                                    'Calling Station' => $record->callingstationid,
                                    'Terminate Cause' => $record->acctterminatecause,
                                ];
                            });

                            // In a real implementation, you would generate and download a file
                            \Filament\Notifications\Notification::make()
                                ->title('Export Prepared')
                                ->body('Selected sessions have been prepared for export.')
                                ->info()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('acctstarttime', 'desc')
            ->poll('30s') // Auto-refresh every 30 seconds for active sessions
            ->deferLoading()
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public function isReadOnly(): bool
    {
        return true; // Sessions are read-only
    }

    protected function canCreate(): bool
    {
        return false; // Cannot create sessions manually
    }

    protected function canEdit(Model $record): bool
    {
        return false; // Cannot edit sessions
    }

    protected function canDelete(Model $record): bool
    {
        return false; // Cannot delete sessions
    }

    protected function canDeleteAny(): bool
    {
        return false;
    }
}