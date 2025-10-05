<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RadGroupCheckResource\Pages;
use App\Models\RadGroupCheck;
use App\Models\Package;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\FiltersLayout;

class RadGroupCheckResource extends Resource
{
    protected static ?string $model = RadGroupCheck::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    
    protected static ?string $navigationLabel = 'Group Check';
    
    protected static ?string $modelLabel = 'Group Check';
    
    protected static ?string $pluralModelLabel = 'Group Checks';
    
    protected static ?string $navigationGroup = 'Mikrotik Router';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Group Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('groupname')
                            ->label('Group Name')
                            ->required()
                            ->maxLength(64)
                            ->placeholder('e.g., package_1, premium_users, trial_users'),

                        Forms\Components\Select::make('attribute')
                            ->label('Attribute')
                            ->required()
                            ->options([
                                'Simultaneous-Use' => 'Simultaneous Use',
                                'Login-Time' => 'Login Time',
                                'Auth-Type' => 'Authentication Type',
                                'Calling-Station-Id' => 'Calling Station ID (MAC)',
                                'Expiration' => 'Expiration',
                                'Session-Timeout' => 'Session Timeout',
                                'Idle-Timeout' => 'Idle Timeout',
                                'WISPr-Bandwidth-Max-Down' => 'Max Download Bandwidth',
                                'WISPr-Bandwidth-Max-Up' => 'Max Upload Bandwidth',
                                'ChilliSpot-Max-Total-Octets' => 'Data Limit (bytes)',
                                'Tunnel-Type' => 'Tunnel Type',
                                'Tunnel-Medium-Type' => 'Tunnel Medium Type',
                                'Tunnel-Private-Group-Id' => 'VLAN ID',
                                'NAS-Filter-Rule' => 'Firewall Rule',
                                'Acct-Interim-Interval' => 'Accounting Interval',
                                'Class' => 'Class',
                                'Pool-Name' => 'IP Pool Name',
                            ])
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // Set default operator based on attribute
                                $defaultOps = [
                                    'Simultaneous-Use' => ':=',
                                    'Login-Time' => ':=',
                                    'Auth-Type' => ':=',
                                    'Session-Timeout' => ':=',
                                    'Idle-Timeout' => ':=',
                                    'WISPr-Bandwidth-Max-Down' => ':=',
                                    'WISPr-Bandwidth-Max-Up' => ':=',
                                    'ChilliSpot-Max-Total-Octets' => ':=',
                                    'Calling-Station-Id' => '==',
                                    'Expiration' => ':=',
                                    'Tunnel-Type' => ':=',
                                    'Tunnel-Medium-Type' => ':=',
                                    'Tunnel-Private-Group-Id' => ':=',
                                ];
                                
                                if (isset($defaultOps[$state])) {
                                    $set('op', $defaultOps[$state]);
                                }
                            }),

                        Forms\Components\Select::make('op')
                            ->label('Operator')
                            ->required()
                            ->options([
                                ':=' => ':= (Set)',
                                '==' => '== (Equal)',
                                '+=' => '+= (Add)',
                                '!=' => '!= (Not Equal)',
                                '>' => '> (Greater Than)',
                                '>=' => '>= (Greater or Equal)',
                                '<' => '< (Less Than)',
                                '<=' => '<= (Less or Equal)',
                                '=~' => '=~ (Regex Match)',
                                '!~' => '!~ (Regex Not Match)',
                                '=*' => '=* (Exists)',
                                '!*' => '!* (Not Exists)',
                            ])
                            ->default(':='),

                        Forms\Components\TextInput::make('value')
                            ->label('Value')
                            ->required()
                            ->helperText(function (Forms\Get $get) {
                                $attribute = $get('attribute');
                                
                                return match ($attribute) {
                                    'Simultaneous-Use' => 'Number of simultaneous sessions allowed (e.g., 1, 2, 3)',
                                    'Login-Time' => 'Time restrictions (e.g., Al0900-1700 for 9AM-5PM daily)',
                                    'Session-Timeout' => 'Maximum session duration in seconds (e.g., 3600 for 1 hour)',
                                    'Idle-Timeout' => 'Idle timeout in seconds (e.g., 600 for 10 minutes)',
                                    'WISPr-Bandwidth-Max-Down' => 'Download bandwidth in bps (e.g., 1048576 for 1Mbps)',
                                    'WISPr-Bandwidth-Max-Up' => 'Upload bandwidth in bps (e.g., 512000 for 512Kbps)',
                                    'ChilliSpot-Max-Total-Octets' => 'Data limit in bytes (e.g., 1073741824 for 1GB)',
                                    'Calling-Station-Id' => 'MAC address pattern (e.g., 00:11:22:33:44:55)',
                                    'Expiration' => 'Expiration date (e.g., Jan 01 2025 12:00)',
                                    'Auth-Type' => 'Authentication type (e.g., Local, PAP, CHAP)',
                                    'Tunnel-Type' => 'Tunnel type (e.g., VLAN)',
                                    'Tunnel-Medium-Type' => 'Medium type (e.g., IEEE-802)',
                                    'Tunnel-Private-Group-Id' => 'VLAN ID (e.g., 100)',
                                    default => 'Enter the appropriate value for this attribute',
                                };
                            }),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('groupname')
                    ->label('Group Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->formatStateUsing(function ($state) {
                        if (str_starts_with($state, 'package_')) {
                            $packageId = str_replace('package_', '', $state);
                            $package = Package::find($packageId);
                            return $package ? "📦 {$package->name}" : $state;
                        }
                        return $state;
                    }),

                Tables\Columns\TextColumn::make('attribute')
                    ->label('Attribute')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Simultaneous-Use' => 'info',
                        'Login-Time' => 'warning',
                        'Session-Timeout', 'Idle-Timeout' => 'danger',
                        'WISPr-Bandwidth-Max-Down', 'WISPr-Bandwidth-Max-Up' => 'success',
                        'ChilliSpot-Max-Total-Octets' => 'primary',
                        'Auth-Type' => 'gray',
                        default => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('op')
                    ->label('Operator')
                    ->badge()
                    ->color('secondary'),

                Tables\Columns\TextColumn::make('value')
                    ->label('Value')
                    ->searchable()
                    ->formatStateUsing(function ($state, $record) {
                        return match ($record->attribute) {
                            'WISPr-Bandwidth-Max-Down', 'WISPr-Bandwidth-Max-Up' => 
                                number_format($state / 1000) . ' Kbps',
                            'ChilliSpot-Max-Total-Octets' => 
                                number_format($state / (1024 * 1024), 2) . ' MB',
                            'Session-Timeout', 'Idle-Timeout' => 
                                gmdate('H:i:s', $state),
                            default => $state,
                        };
                    }),

                Tables\Columns\TextColumn::make('package_relation')
                    ->label('Related Package')
                    ->getStateUsing(function ($record) {
                        if (str_starts_with($record->groupname, 'package_')) {
                            $packageId = str_replace('package_', '', $record->groupname);
                            $package = Package::find($packageId);
                            return $package ? $package->name : 'Package not found';
                        }
                        return 'Custom Group';
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Custom Group' ? 'warning' : 'success')
                    ->toggleable(),
            ])
            ->filters([
                Filters\SelectFilter::make('groupname')
                    ->label('Group')
                    ->multiple()
                    ->options(function () {
                        return RadGroupCheck::distinct()
                            ->pluck('groupname', 'groupname')
                            ->mapWithKeys(function ($value, $key) {
                                if (str_starts_with($key, 'package_')) {
                                    $packageId = str_replace('package_', '', $key);
                                    $package = Package::find($packageId);
                                    $displayName = $package ? "📦 {$package->name}" : $key;
                                } else {
                                    $displayName = $key;
                                }
                                return [$key => $displayName];
                            });
                    }),

                Filters\SelectFilter::make('attribute')
                    ->label('Attribute Type')
                    ->multiple()
                    ->options([
                        'Simultaneous-Use' => 'Simultaneous Use',
                        'Login-Time' => 'Login Time',
                        'Session-Timeout' => 'Session Timeout',
                        'Idle-Timeout' => 'Idle Timeout',
                        'WISPr-Bandwidth-Max-Down' => 'Download Bandwidth',
                        'WISPr-Bandwidth-Max-Up' => 'Upload Bandwidth',
                        'ChilliSpot-Max-Total-Octets' => 'Data Limit',
                        'Auth-Type' => 'Authentication Type',
                    ]),

                Filters\Filter::make('package_groups')
                    ->label('Package Groups')
                    ->query(fn (Builder $query): Builder => $query->where('groupname', 'like', 'package_%')),

                Filters\Filter::make('custom_groups')
                    ->label('Custom Groups')
                    ->query(fn (Builder $query): Builder => $query->where('groupname', 'not like', 'package_%')),

                Filters\Filter::make('bandwidth_limits')
                    ->label('Bandwidth Limits')
                    ->query(fn (Builder $query): Builder => $query->whereIn('attribute', [
                        'WISPr-Bandwidth-Max-Down',
                        'WISPr-Bandwidth-Max-Up'
                    ])),

                Filters\Filter::make('time_restrictions')
                    ->label('Time Restrictions')
                    ->query(fn (Builder $query): Builder => $query->whereIn('attribute', [
                        'Login-Time',
                        'Session-Timeout',
                        'Idle-Timeout'
                    ])),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('clone_to_group')
                        ->label('Clone to Group')
                        ->icon('heroicon-o-document-duplicate')
                        ->form([
                            Forms\Components\TextInput::make('target_group')
                                ->label('Target Group Name')
                                ->required()
                                ->placeholder('Enter the group name to clone to'),
                        ])
                        ->action(function (array $data, $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                RadGroupCheck::create([
                                    'groupname' => $data['target_group'],
                                    'attribute' => $record->attribute,
                                    'op' => $record->op,
                                    'value' => $record->value,
                                ]);
                                $count++;
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Attributes Cloned')
                                ->body("{$count} attributes have been cloned to group '{$data['target_group']}'.")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('groupname')
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
            'index' => Pages\ListRadGroupChecks::route('/'),
            'create' => Pages\CreateRadGroupCheck::route('/create'),
            'view' => Pages\ViewRadGroupCheck::route('/{record}'),
            'edit' => Pages\EditRadGroupCheck::route('/{record}/edit'),
        ];
    }
}
