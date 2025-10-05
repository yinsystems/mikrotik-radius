<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NasResource\Pages;
use App\Models\Nas;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class NasResource extends Resource
{
    protected static ?string $model = Nas::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';
    
    protected static ?string $navigationGroup = 'Mikrotik Router';
    
    protected static ?int $navigationSort = 7;
    
    protected static ?string $navigationLabel = 'NAS Devices';
    
    protected static ?string $modelLabel = 'NAS Device';
    
    protected static ?string $pluralModelLabel = 'NAS Devices';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->description('Configure the basic NAS device settings')
                    ->icon('heroicon-m-information-circle')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('nasname')
                                    ->label('NAS Name/IP')
                                    ->helperText('IP address or hostname of the NAS device')
                                    ->required()
                                    ->maxLength(128)
                                    ->placeholder('192.168.1.1'),
                                    
                                Forms\Components\TextInput::make('shortname')
                                    ->label('Short Name')
                                    ->helperText('Short descriptive name for the NAS')
                                    ->required()
                                    ->maxLength(32)
                                    ->placeholder('main-router'),
                            ]),
                    ]),
                    
                Forms\Components\Section::make('Device Configuration')
                    ->description('Technical configuration for the NAS device')
                    ->icon('heroicon-m-cog-6-tooth')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Device Type')
                                    ->options([
                                        'mikrotik' => 'MikroTik Router',
                                        'cisco' => 'Cisco Router',
                                        'other' => 'Other Device',
                                        'unifi' => 'Ubiquiti UniFi',
                                        'pfsense' => 'pfSense',
                                        'openwrt' => 'OpenWrt',
                                    ])
                                    ->default('mikrotik')
                                    ->required()
                                    ->native(false),
                                    
                                Forms\Components\TextInput::make('ports')
                                    ->label('Ports')
                                    ->helperText('Number of ports on the device')
                                    ->numeric()
                                    ->default(1812)
                                    ->minValue(1)
                                    ->maxValue(65535),
                            ]),
                            
                        Forms\Components\Grid::make(1)
                            ->schema([
                                Forms\Components\TextInput::make('secret')
                                    ->label('Shared Secret')
                                    ->helperText('RADIUS shared secret for authentication')
                                    ->required()
                                    ->password()
                                    ->revealable()
                                    ->maxLength(60)
                                    ->placeholder('Enter secure shared secret'),
                            ]),
                    ]),
                    
                Forms\Components\Section::make('Network Settings')
                    ->description('Additional network configuration')
                    ->icon('heroicon-m-globe-alt')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('server')
                                    ->label('Server')
                                    ->helperText('RADIUS server IP/hostname')
                                    ->maxLength(64)
                                    ->placeholder('radius.example.com'),
                                    
                                Forms\Components\TextInput::make('community')
                                    ->label('SNMP Community')
                                    ->helperText('SNMP community string for monitoring')
                                    ->maxLength(50)
                                    ->placeholder('public'),
                            ]),
                            
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->helperText('Additional notes about this NAS device')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Main router for building A, located in server room...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shortname')
                    ->label('Short Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-server-stack')
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('nasname')
                    ->label('NAS IP/Hostname')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('IP address copied!')
                    ->icon('heroicon-m-globe-alt'),
                    
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'success' => 'mikrotik',
                        'info' => 'cisco',
                        'warning' => 'unifi',
                        'danger' => 'other',
                        'gray' => 'pfsense',
                        'primary' => 'openwrt',
                    ])
                    ->icons([
                        'heroicon-m-server' => 'mikrotik',
                        'heroicon-m-cpu-chip' => 'cisco',
                        'heroicon-m-wifi' => 'unifi',
                        'heroicon-m-cube' => 'other',
                        'heroicon-m-shield-check' => 'pfsense',
                        'heroicon-m-code-bracket' => 'openwrt',
                    ]),
                    
                Tables\Columns\TextColumn::make('ports')
                    ->label('Ports')
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                    
                Tables\Columns\TextColumn::make('server')
                    ->label('Server')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                    
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->placeholder('No description')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Device Type')
                    ->options([
                        'mikrotik' => 'MikroTik Router',
                        'cisco' => 'Cisco Router',
                        'unifi' => 'Ubiquiti UniFi',
                        'pfsense' => 'pfSense',
                        'openwrt' => 'OpenWrt',
                        'other' => 'Other Device',
                    ])
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton(),
                Tables\Actions\EditAction::make()
                    ->iconButton(),
                Tables\Actions\DeleteAction::make()
                    ->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add First NAS Device')
                    ->icon('heroicon-m-plus'),
            ])
            ->defaultSort('shortname');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Device Overview')
                    ->icon('heroicon-m-information-circle')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('shortname')
                                    ->label('Short Name')
                                    ->icon('heroicon-m-server-stack')
                                    ->color('primary')
                                    ->weight('bold'),
                                    
                                Infolists\Components\TextEntry::make('nasname')
                                    ->label('NAS IP/Hostname')
                                    ->icon('heroicon-m-globe-alt')
                                    ->copyable()
                                    ->copyMessage('IP address copied!'),
                            ]),
                            
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('type')
                                    ->label('Device Type')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'mikrotik' => 'success',
                                        'cisco' => 'info',
                                        'unifi' => 'warning',
                                        'pfsense' => 'gray',
                                        'openwrt' => 'primary',
                                        default => 'danger',
                                    }),
                                    
                                Infolists\Components\TextEntry::make('ports')
                                    ->label('Ports')
                                    ->badge()
                                    ->color('gray'),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Network Configuration')
                    ->icon('heroicon-m-cog-6-tooth')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('server')
                                    ->label('RADIUS Server')
                                    ->icon('heroicon-m-server')
                                    ->placeholder('Not configured'),
                                    
                                Infolists\Components\TextEntry::make('community')
                                    ->label('SNMP Community')
                                    ->icon('heroicon-m-eye')
                                    ->placeholder('Not configured'),
                            ]),
                            
                        Infolists\Components\TextEntry::make('secret')
                            ->label('Shared Secret')
                            ->icon('heroicon-m-lock-closed')
                            ->formatStateUsing(fn (): string => '••••••••••••')
                            ->color('warning'),
                    ]),
                    
                Infolists\Components\Section::make('Description')
                    ->icon('heroicon-m-document-text')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->label('Notes')
                            ->placeholder('No description provided')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNases::route('/'),
            'create' => Pages\CreateNas::route('/create'),
            'view' => Pages\ViewNas::route('/{record}'),
            'edit' => Pages\EditNas::route('/{record}/edit'),
        ];
    }
}