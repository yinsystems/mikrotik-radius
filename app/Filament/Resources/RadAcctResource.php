<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RadAcctResource\Pages;
use App\Models\RadAcct;
use App\Models\Nas;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\FiltersLayout;

class RadAcctResource extends Resource
{
    protected static ?string $model = RadAcct::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    
    protected static ?string $navigationLabel = 'RADIUS Accounting';
    
    protected static ?string $modelLabel = 'RADIUS Accounting';
    
    protected static ?string $pluralModelLabel = 'RADIUS Accounting';
    
    protected static ?string $navigationGroup = 'Mikrotik Router';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Session Information')
                    ->schema([
                        Forms\Components\TextInput::make('username')
                            ->required()
                            ->maxLength(64),
                        
                        Forms\Components\TextInput::make('acctsessionid')
                            ->label('Session ID')
                            ->required()
                            ->maxLength(64),
                            
                        Forms\Components\TextInput::make('acctuniqueid')
                            ->label('Unique ID')
                            ->required()
                            ->maxLength(32),
                    ])->columns(3),

                Forms\Components\Section::make('NAS Information')
                    ->schema([
                        Forms\Components\Select::make('nasipaddress')
                            ->label('NAS IP Address')
                            ->options(Nas::pluck('shortname', 'nasname'))
                            ->searchable()
                            ->required(),
                            
                        Forms\Components\TextInput::make('nasportid')
                            ->label('NAS Port ID')
                            ->maxLength(32),
                            
                        Forms\Components\TextInput::make('nasporttype')
                            ->label('NAS Port Type')
                            ->maxLength(32),
                    ])->columns(3),

                Forms\Components\Section::make('Session Timing')
                    ->schema([
                        Forms\Components\DateTimePicker::make('acctstarttime')
                            ->label('Start Time')
                            ->required(),
                            
                        Forms\Components\DateTimePicker::make('acctupdatetime')
                            ->label('Update Time'),
                            
                        Forms\Components\DateTimePicker::make('acctstoptime')
                            ->label('Stop Time'),
                            
                        Forms\Components\TextInput::make('acctsessiontime')
                            ->label('Session Time (seconds)')
                            ->numeric()
                            ->suffix('seconds'),
                    ])->columns(2),

                Forms\Components\Section::make('Data Usage')
                    ->schema([
                        Forms\Components\TextInput::make('acctinputoctets')
                            ->label('Input Octets')
                            ->numeric()
                            ->formatStateUsing(fn ($state) => $state ? number_format($state) : '0'),
                            
                        Forms\Components\TextInput::make('acctoutputoctets')
                            ->label('Output Octets')
                            ->numeric()
                            ->formatStateUsing(fn ($state) => $state ? number_format($state) : '0'),
                    ])->columns(2),

                Forms\Components\Section::make('Connection Details')
                    ->schema([
                        Forms\Components\TextInput::make('calledstationid')
                            ->label('Called Station ID')
                            ->maxLength(50),
                            
                        Forms\Components\TextInput::make('callingstationid')
                            ->label('Calling Station ID')
                            ->maxLength(50),
                            
                        Forms\Components\TextInput::make('acctterminatecause')
                            ->label('Terminate Cause')
                            ->maxLength(32),
                            
                        Forms\Components\TextInput::make('framedipaddress')
                            ->label('Framed IP Address')
                            ->maxLength(15),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\IconColumn::make('status')
                    ->label('Status')
                    ->icon(fn (RadAcct $record): string => $record->isActiveSession() ? 'heroicon-s-play-circle' : 'heroicon-s-stop-circle')
                    ->color(fn (RadAcct $record): string => $record->isActiveSession() ? 'success' : 'gray')
                    ->tooltip(fn (RadAcct $record): string => $record->isActiveSession() ? 'Active Session' : 'Completed Session'),

                Tables\Columns\TextColumn::make('acctstarttime')
                    ->label('Start Time')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('acctstoptime')
                    ->label('Stop Time')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Active'),

                Tables\Columns\TextColumn::make('session_duration')
                    ->label('Duration')
                    ->formatStateUsing(fn (RadAcct $record): string => $record->session_duration),

                Tables\Columns\TextColumn::make('total_bytes')
                    ->label('Data Usage')
                    ->formatStateUsing(fn (RadAcct $record): string => $record->formatted_size)
                    ->sortable(),

                Tables\Columns\TextColumn::make('nasipaddress')
                    ->label('NAS')
                    ->formatStateUsing(function ($state) {
                        $nas = Nas::where('nasname', $state)->first();
                        return $nas ? $nas->shortname : $state;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('framedipaddress')
                    ->label('IP Address')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('callingstationid')
                    ->label('MAC Address')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('acctterminatecause')
                    ->label('Terminate Cause')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'User-Request' => 'success',
                        'Idle-Timeout' => 'warning',
                        'Session-Timeout' => 'warning',
                        'Admin-Reset' => 'danger',
                        'Admin-Reboot' => 'danger',
                        'Port-Error' => 'danger',
                        'NAS-Error' => 'danger',
                        'NAS-Request' => 'info',
                        'NAS-Reboot' => 'info',
                        'Port-Unneeded' => 'gray',
                        'Port-Preempted' => 'gray',
                        'Port-Suspended' => 'gray',
                        'Service-Unavailable' => 'danger',
                        'Callback' => 'info',
                        'User-Error' => 'warning',
                        'Host-Request' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active Sessions',
                        'completed' => 'Completed Sessions',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] === 'active',
                            fn (Builder $query): Builder => $query->whereNull('acctstoptime'),
                        )->when(
                            $data['value'] === 'completed',
                            fn (Builder $query): Builder => $query->whereNotNull('acctstoptime'),
                        );
                    }),

                Filters\SelectFilter::make('nasipaddress')
                    ->label('NAS')
                    ->options(Nas::pluck('shortname', 'nasname')),

                Filters\Filter::make('session_date')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date'),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['start_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('acctstarttime', '>=', $date),
                            )
                            ->when(
                                $data['end_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('acctstarttime', '<=', $date),
                            );
                    }),

                Filters\Filter::make('high_usage')
                    ->label('High Data Usage (>1GB)')
                    ->query(fn (Builder $query): Builder => $query->whereRaw('(acctinputoctets + acctoutputoctets) >= ?', [1024 * 1024 * 1024])),

                Filters\Filter::make('long_sessions')
                    ->label('Long Sessions (>4 hours)')
                    ->query(fn (Builder $query): Builder => $query->where('acctsessiontime', '>=', 14400)),

                Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('acctstarttime', today())),

                Filters\Filter::make('this_week')
                    ->label('This Week')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('acctstarttime', [now()->startOfWeek(), now()->endOfWeek()])),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('terminate')
                    ->label('Terminate')
                    ->icon('heroicon-o-stop-circle')
                    ->color('danger')
                    ->visible(fn (RadAcct $record) => $record->isActiveSession())
                    ->requiresConfirmation()
                    ->action(function (RadAcct $record) {
                        $record->update([
                            'acctstoptime' => now(),
                            'acctterminatecause' => 'Admin-Reset'
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Session Terminated')
                            ->body("Session for {$record->username} has been terminated.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('terminate_selected')
                        ->label('Terminate Sessions')
                        ->icon('heroicon-o-stop-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->isActiveSession()) {
                                    $record->update([
                                        'acctstoptime' => now(),
                                        'acctterminatecause' => 'Admin-Reset'
                                    ]);
                                    $count++;
                                }
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Sessions Terminated')
                                ->body("{$count} active sessions have been terminated.")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('acctstarttime', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRadAccts::route('/'),
            'view' => Pages\ViewRadAcct::route('/{record}'),
        ];
    }
}
