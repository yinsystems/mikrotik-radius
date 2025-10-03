<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RadCheckResource\Pages;
use App\Models\RadCheck;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class RadCheckResource extends Resource
{
    protected static ?string $model = RadCheck::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    
    protected static ?string $navigationGroup = 'Mikrotik Router';
    
    protected static ?string $navigationLabel = 'RADIUS Check';
    
    protected static ?string $modelLabel = 'RADIUS Check';
    
    protected static ?string $pluralModelLabel = 'RADIUS Checks';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('RADIUS Check Configuration')
                    ->description('Configure RADIUS authentication and authorization attributes')
                    ->icon('heroicon-m-shield-check')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('username')
                                    ->label('Username')
                                    ->helperText('Select from existing subscription usernames')
                                    ->options(function () {
                                        return Subscription::with('customer')
                                            ->get()
                                            ->pluck('username', 'username')
                                            ->filter()
                                            ->unique();
                                    })
                                    ->searchable()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('username')
                                            ->label('New Username')
                                            ->required()
                                            ->maxLength(64),
                                    ])
                                    ->createOptionUsing(function (array $data): string {
                                        return $data['username'];
                                    }),
                                    
                                Forms\Components\Select::make('attribute')
                                    ->label('Attribute')
                                    ->helperText('RADIUS attribute name')
                                    ->options([
                                        'Cleartext-Password' => 'Cleartext-Password (User Password)',
                                        'Crypt-Password' => 'Crypt-Password (Encrypted Password)',
                                        'MD5-Password' => 'MD5-Password (MD5 Hash)',
                                        'Auth-Type' => 'Auth-Type (Authentication Type)',
                                        'Simultaneous-Use' => 'Simultaneous-Use (Session Limit)',
                                        'Login-Time' => 'Login-Time (Time Restrictions)',
                                        'Expiration' => 'Expiration (Account Expiry)',
                                        'Calling-Station-Id' => 'Calling-Station-Id (MAC Address)',
                                        'Called-Station-Id' => 'Called-Station-Id (AP MAC)',
                                        'Pool-Name' => 'Pool-Name (IP Pool)',
                                        'Framed-IP-Address' => 'Framed-IP-Address (Static IP)',
                                        'Framed-Netmask' => 'Framed-Netmask (Subnet Mask)',
                                        'Max-Daily-Session' => 'Max-Daily-Session (Daily Time Limit)',
                                        'Max-Monthly-Session' => 'Max-Monthly-Session (Monthly Time Limit)',
                                    ])
                                    ->required()
                                    ->searchable()
                                    ->native(false),
                            ]),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('op')
                                    ->label('Operator')
                                    ->helperText('RADIUS attribute operator')
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
                                    ->default(':=')
                                    ->required()
                                    ->native(false),
                                    
                                Forms\Components\TextInput::make('value')
                                    ->label('Value')
                                    ->helperText('RADIUS attribute value')
                                    ->required()
                                    ->maxLength(253)
                                    ->placeholder('Enter attribute value'),
                            ]),
                    ]),
                    
                Forms\Components\Section::make('Quick Actions')
                    ->description('Common RADIUS check configurations')
                    ->icon('heroicon-m-bolt')
                    ->schema([
                        Forms\Components\Placeholder::make('quick_help')
                            ->label('')
                            ->content('Use the buttons below for common configurations:')
                            ->columnSpanFull(),
                            
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('set_password')
                                ->label('Set Password')
                                ->icon('heroicon-m-key')
                                ->color('success')
                                ->action(function (Forms\Set $set) {
                                    $set('attribute', 'Cleartext-Password');
                                    $set('op', ':=');
                                }),
                                
                            Forms\Components\Actions\Action::make('block_user')
                                ->label('Block User')
                                ->icon('heroicon-m-no-symbol')
                                ->color('danger')
                                ->action(function (Forms\Set $set) {
                                    $set('attribute', 'Auth-Type');
                                    $set('op', ':=');
                                    $set('value', 'Reject');
                                }),
                                
                            Forms\Components\Actions\Action::make('session_limit')
                                ->label('Session Limit')
                                ->icon('heroicon-m-users')
                                ->color('warning')
                                ->action(function (Forms\Set $set) {
                                    $set('attribute', 'Simultaneous-Use');
                                    $set('op', ':=');
                                    $set('value', '1');
                                }),
                        ])->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-user')
                    ->color('primary')
                    ->copyable()
                    ->copyMessage('Username copied!'),
                    
                Tables\Columns\BadgeColumn::make('attribute')
                    ->label('Attribute')
                    ->searchable()
                    ->colors([
                        'success' => ['Cleartext-Password', 'Crypt-Password', 'MD5-Password'],
                        'danger' => 'Auth-Type',
                        'warning' => ['Simultaneous-Use', 'Login-Time'],
                        'info' => ['Expiration', 'Calling-Station-Id'],
                        'gray' => 'default',
                    ])
                    ->icons([
                        'heroicon-m-key' => ['Cleartext-Password', 'Crypt-Password', 'MD5-Password'],
                        'heroicon-m-no-symbol' => 'Auth-Type',
                        'heroicon-m-users' => 'Simultaneous-Use',
                        'heroicon-m-clock' => ['Login-Time', 'Expiration'],
                        'heroicon-m-device-phone-mobile' => 'Calling-Station-Id',
                    ]),
                    
                Tables\Columns\BadgeColumn::make('op')
                    ->label('Op')
                    ->color('gray')
                    ->size('sm'),
                    
                Tables\Columns\TextColumn::make('value')
                    ->label('Value')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 30) {
                            return null;
                        }
                        return $state;
                    })
                    ->formatStateUsing(function (string $state, $record): string {
                        // Mask passwords for security
                        if (in_array($record->attribute, ['Cleartext-Password', 'Crypt-Password', 'MD5-Password'])) {
                            return '••••••••';
                        }
                        return $state;
                    }),
                    
                Tables\Columns\TextColumn::make('subscription.customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('attribute')
                    ->label('Attribute Type')
                    ->options([
                        'Cleartext-Password' => 'Password',
                        'Auth-Type' => 'Auth Type',
                        'Simultaneous-Use' => 'Session Limit',
                        'Login-Time' => 'Time Restrictions',
                        'Expiration' => 'Expiration',
                        'Calling-Station-Id' => 'MAC Address',
                    ])
                    ->multiple(),
                    
                Tables\Filters\Filter::make('blocked_users')
                    ->label('Blocked Users')
                    ->query(fn (Builder $query): Builder => $query->blocked())
                    ->toggle(),
                    
                Tables\Filters\Filter::make('expired_users')
                    ->label('Expired Users')
                    ->query(fn (Builder $query): Builder => $query->expired())
                    ->toggle(),
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
                    ->label('Add RADIUS Check')
                    ->icon('heroicon-m-plus'),
            ])
            ->defaultSort('username');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('RADIUS Check Details')
                    ->icon('heroicon-m-shield-check')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('username')
                                    ->label('Username')
                                    ->icon('heroicon-m-user')
                                    ->color('primary')
                                    ->weight('bold')
                                    ->copyable(),
                                    
                                Infolists\Components\TextEntry::make('attribute')
                                    ->label('Attribute')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'Cleartext-Password', 'Crypt-Password', 'MD5-Password' => 'success',
                                        'Auth-Type' => 'danger',
                                        'Simultaneous-Use', 'Login-Time' => 'warning',
                                        'Expiration', 'Calling-Station-Id' => 'info',
                                        default => 'gray',
                                    }),
                            ]),
                            
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('op')
                                    ->label('Operator')
                                    ->badge()
                                    ->color('gray'),
                                    
                                Infolists\Components\TextEntry::make('value')
                                    ->label('Value')
                                    ->formatStateUsing(function (string $state, $record): string {
                                        // Mask passwords for security
                                        if (in_array($record->attribute, ['Cleartext-Password', 'Crypt-Password', 'MD5-Password'])) {
                                            return '••••••••••••';
                                        }
                                        return $state;
                                    }),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Related Information')
                    ->icon('heroicon-m-link')
                    ->schema([
                        Infolists\Components\TextEntry::make('subscription.customer.name')
                            ->label('Customer Name')
                            ->placeholder('No customer found'),
                            
                        Infolists\Components\TextEntry::make('subscription.package.name')
                            ->label('Package')
                            ->placeholder('No package found'),
                            
                        Infolists\Components\TextEntry::make('subscription.status')
                            ->label('Subscription Status')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'active' => 'success',
                                'expired' => 'danger',
                                'suspended' => 'warning',
                                'pending' => 'gray',
                                'blocked' => 'danger',
                                default => 'gray',
                            })
                            ->placeholder('No subscription found'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRadChecks::route('/'),
            'create' => Pages\CreateRadCheck::route('/create'),
            'view' => Pages\ViewRadCheck::route('/{record}'),
            'edit' => Pages\EditRadCheck::route('/{record}/edit'),
        ];
    }
}
