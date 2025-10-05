<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PackageResource\Pages;
use App\Models\Package;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Packages & Plans';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Package Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Premium WiFi, Basic Internet')
                                    ->helperText('Descriptive name for the package'),
                                
                                Forms\Components\TextInput::make('priority')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->helperText('Higher numbers = higher priority in listings'),
                            ]),
                        
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->placeholder('Describe the package features and benefits')
                            ->helperText('Optional description for customers'),
                        
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->default(true)
                                    ->helperText('Active packages are available for purchase'),
                                
                                Forms\Components\Toggle::make('is_trial')
                                    ->default(false)
                                    ->reactive()
                                    ->helperText('Trial packages for testing purposes'),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Duration & Pricing')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('duration_type')
                                    ->required()
                                    ->options([
                                        'minutely' => 'Minutely',
                                        'hourly' => 'Hourly',
                                        'daily' => 'Daily',
                                        'weekly' => 'Weekly',
                                        'monthly' => 'Monthly',
                                        'trial' => 'Trial',
                                    ])
                                    ->reactive()
                                    ->helperText('Billing cycle type'),
                                
                                Forms\Components\TextInput::make('duration_value')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->placeholder('1')
                                    ->helperText(function (callable $get) {
                                        $type = $get('duration_type');
                                        return match($type) {
                                            'minutely' => 'Number of minutes',
                                            'hourly' => 'Number of hours',
                                            'daily' => 'Number of days',
                                            'weekly' => 'Number of weeks',
                                            'monthly' => 'Number of months',
                                            'trial' => 'Trial duration value',
                                            default => 'Duration value'
                                        };
                                    }),
                                
                                Forms\Components\TextInput::make('price')
                                    ->required()
                                    ->numeric()
                                    ->prefix('₵')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->placeholder('0.00')
                                    ->helperText('Price in Ghanaian Cedis'),
                            ]),
                        
                        Forms\Components\TextInput::make('trial_duration_hours')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('24')
                            ->helperText('Trial duration in hours (for trial packages)')
                            ->visible(fn (callable $get) => $get('is_trial')),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Bandwidth Limits')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('bandwidth_upload')
                                    ->numeric()
                                    ->minValue(1)
                                    ->suffix('Kbps')
                                    ->placeholder('512')
                                    ->helperText('Upload speed limit (leave empty for unlimited)'),
                                
                                Forms\Components\TextInput::make('bandwidth_download')
                                    ->numeric()
                                    ->minValue(1)
                                    ->suffix('Kbps')
                                    ->placeholder('1024')
                                    ->helperText('Download speed limit (leave empty for unlimited)'),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Data & Access Limits')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('data_limit')
                                    ->numeric()
                                    ->minValue(1)
                                    ->suffix('MB')
                                    ->placeholder('1024')
                                    ->helperText('Data limit in MB (leave empty for unlimited)'),
                                
                                Forms\Components\TextInput::make('simultaneous_users')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->placeholder('1')
                                    ->helperText('Maximum concurrent users per subscription'),
                                
                                Forms\Components\TextInput::make('vlan_id')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(4094)
                                    ->placeholder('100')
                                    ->helperText('VLAN ID for network segmentation (optional)'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Package $record): string => $record->description ?? ''),
                
                Tables\Columns\BadgeColumn::make('duration_display')
                    ->label('Duration')
                    ->color(fn (string $state): string => match (true) {
                        str_contains(strtolower($state), 'trial') => 'warning',
                        str_contains(strtolower($state), 'minute') => 'secondary',
                        str_contains(strtolower($state), 'hour') => 'info',
                        str_contains(strtolower($state), 'day') => 'success',
                        str_contains(strtolower($state), 'week') => 'primary',
                        str_contains(strtolower($state), 'month') => 'danger',
                        default => 'gray',
                    })
                    ->sortable(['duration_type', 'duration_value']),
                
                Tables\Columns\TextColumn::make('price')
                    ->money('GHS')
                    ->sortable()
                    ->weight('semibold')
                    ->color('success'),
                
                Tables\Columns\TextColumn::make('bandwidth_display')
                    ->label('Bandwidth')
                    ->badge()
                    ->color('info')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('data_limit')
                    ->label('Data Limit')
                    ->formatStateUsing(function (?int $state): string {
                        if (!$state) return 'Unlimited';
                        
                        if ($state >= 1024) {
                            return number_format($state / 1024, 1) . ' GB';
                        }
                        
                        return number_format($state) . ' MB';
                    })
                    ->badge()
                    ->color(fn (?int $state): string => $state ? 'warning' : 'success')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('simultaneous_users')
                    ->label('Max Users')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                Tables\Columns\IconColumn::make('is_trial')
                    ->label('Trial')
                    ->boolean()
                    ->trueIcon('heroicon-o-beaker')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('subscriptions_count')
                    ->label('Active Subs')
                    ->counts('activeSubscriptions')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('priority')
                    ->sortable()
                    ->toggleable()
                    ->badge()
                    ->color('gray'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('duration_type')
                    ->options([
                        'minutely' => 'Minutely',
                        'hourly' => 'Hourly',
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        'trial' => 'Trial',
                    ])
                    ->multiple(),
                
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All packages')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                
                Tables\Filters\TernaryFilter::make('is_trial')
                    ->label('Trial Status')
                    ->placeholder('All packages')
                    ->trueLabel('Trial packages')
                    ->falseLabel('Regular packages'),
                
                Tables\Filters\Filter::make('has_data_limit')
                    ->label('Data Limited')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('data_limit'))
                    ->toggle(),
                
                Tables\Filters\Filter::make('has_bandwidth_limit')
                    ->label('Bandwidth Limited')
                    ->query(fn (Builder $query): Builder => 
                        $query->where(function ($q) {
                            $q->whereNotNull('bandwidth_upload')
                              ->orWhereNotNull('bandwidth_download');
                        })
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->color('info'),
                
                Tables\Actions\EditAction::make()
                    ->color('warning'),
                
                Tables\Actions\Action::make('toggle_status')
                    ->label(fn (Package $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Package $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Package $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Package $record) => ($record->is_active ? 'Deactivate' : 'Activate') . ' Package')
                    ->modalDescription(fn (Package $record) => 
                        $record->is_active 
                            ? 'This will prevent new subscriptions to this package. Existing subscriptions will not be affected.'
                            : 'This will allow new subscriptions to this package.'
                    )
                    ->action(function (Package $record) {
                        $record->update(['is_active' => !$record->is_active]);
                        
                        Notification::make()
                            ->title('Package ' . ($record->is_active ? 'activated' : 'deactivated'))
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->default(fn (Package $record) => $record->name . ' (Copy)')
                            ->helperText('Name for the duplicated package'),
                    ])
                    ->action(function (Package $record, array $data) {
                        $newPackage = $record->replicate();
                        $newPackage->name = $data['name'];
                        $newPackage->is_active = false; // Start as inactive
                        $newPackage->save();
                        
                        Notification::make()
                            ->title('Package duplicated successfully')
                            ->body('The new package has been created as inactive.')
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Package')
                    ->modalDescription('This will permanently delete the package. This action cannot be undone.')
                    ->before(function (Package $record) {
                        if ($record->subscriptions()->exists()) {
                            Notification::make()
                                ->title('Cannot delete package')
                                ->body('This package has associated subscriptions and cannot be deleted.')
                                ->danger()
                                ->send();
                            
                            return false;
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['is_active' => true]);
                            
                            Notification::make()
                                ->title('Packages activated')
                                ->body(count($records) . ' packages have been activated.')
                                ->success()
                                ->send();
                        }),
                    
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['is_active' => false]);
                            
                            Notification::make()
                                ->title('Packages deactivated')
                                ->body(count($records) . ' packages have been deactivated.')
                                ->success()
                                ->send();
                        }),
                    
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->before(function ($records) {
                            $hasSubscriptions = $records->filter(function ($record) {
                                return $record->subscriptions()->exists();
                            });
                            
                            if ($hasSubscriptions->count() > 0) {
                                Notification::make()
                                    ->title('Cannot delete packages')
                                    ->body($hasSubscriptions->count() . ' packages have associated subscriptions and cannot be deleted.')
                                    ->danger()
                                    ->send();
                                
                                return false;
                            }
                        }),
                ]),
            ])
            ->defaultSort('priority', 'desc')
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
            'index' => Pages\ListPackages::route('/'),
            'create' => Pages\CreatePackage::route('/create'),
            'view' => Pages\ViewPackage::route('/{record}'),
            'edit' => Pages\EditPackage::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }
}