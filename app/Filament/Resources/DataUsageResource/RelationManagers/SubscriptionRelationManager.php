<?php

namespace App\Filament\Resources\DataUsageResource\RelationManagers;

use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionRelationManager extends RelationManager
{
    protected static string $relationship = 'subscription';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Subscription Information')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                            
                        Forms\Components\TextInput::make('package_id')
                            ->label('Package ID')
                            ->numeric()
                            ->required(),
                            
                        Forms\Components\Select::make('package_id')
                            ->relationship('package', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                            
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'expired' => 'Expired',
                                'suspended' => 'Suspended',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->default('active'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dates & Limits')
                    ->schema([
                        Forms\Components\DateTimePicker::make('activated_at')
                            ->default(now())
                            ->required(),
                            
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->required()
                            ->after('activated_at'),
                            
                        Forms\Components\TextInput::make('data_used')
                            ->label('Data Used (bytes)')
                            ->numeric()
                            ->default(0)
                            ->helperText('This will be calculated from usage records'),
                            
                        Forms\Components\TextInput::make('simultaneous_sessions')
                            ->label('Max Simultaneous Sessions')
                            ->numeric()
                            ->default(1)
                            ->minValue(1),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('username')
                    ->getStateUsing(fn ($record) => $record->username)
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->limit(20),
                    
                Tables\Columns\TextColumn::make('package.name')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'expired' => 'danger',
                        'suspended' => 'warning',
                        'cancelled' => 'gray',
                        default => 'primary',
                    }),
                    
                Tables\Columns\TextColumn::make('data_used')
                    ->label('Data Used')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '0 B';
                        
                        if ($state >= 1024 * 1024 * 1024) {
                            return number_format($state / (1024 * 1024 * 1024), 2) . ' GB';
                        } elseif ($state >= 1024 * 1024) {
                            return number_format($state / (1024 * 1024), 2) . ' MB';
                        } else {
                            return number_format($state / 1024, 2) . ' KB';
                        }
                    })
                    ->badge()
                    ->color(function ($record) {
                        if (!$record->package?->data_limit) return 'gray';
                        
                        $usageMb = $record->data_used / (1024 * 1024);
                        $limitMb = $record->package->data_limit;
                        $percentage = ($usageMb / $limitMb) * 100;
                        
                        if ($percentage >= 100) return 'danger';
                        if ($percentage >= 90) return 'warning';
                        return 'success';
                    }),
                    
                Tables\Columns\TextColumn::make('package.data_limit')
                    ->label('Data Limit (MB)')
                    ->numeric()
                    ->placeholder('Unlimited'),
                    
                Tables\Columns\TextColumn::make('activated_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->color(fn ($record) => $record->expires_at < now() ? 'danger' : 'success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                    ]),
                    
                Tables\Filters\SelectFilter::make('package')
                    ->relationship('package', 'name')
                    ->preload(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalWidth('2xl'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalWidth('2xl'),
                    
                Tables\Actions\EditAction::make()
                    ->modalWidth('2xl'),
                    
                Action::make('sync_usage')
                    ->label('Sync Usage')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        // Calculate total usage from all usage records
                        $totalUsed = $record->dataUsages()->sum('total_bytes');
                        $record->update(['data_used' => $totalUsed]);
                        
                        Notification::make()
                            ->title('Usage data synced')
                            ->body("Total usage: " . number_format($totalUsed / (1024 * 1024), 2) . ' MB')
                            ->success()
                            ->send();
                    }),
                    
                Action::make('check_limit')
                    ->label('Check Limit')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->action(function ($record) {
                        if (!$record->package?->data_limit) {
                            Notification::make()
                                ->title('No data limit set')
                                ->body('This package has unlimited data')
                                ->info()
                                ->send();
                            return;
                        }
                        
                        $usageMb = $record->data_used / (1024 * 1024);
                        $limitMb = $record->package->data_limit;
                        $percentage = ($usageMb / $limitMb) * 100;
                        
                        $color = 'success';
                        $message = "Usage: {$percentage}% of limit";
                        
                        if ($percentage >= 100) {
                            $color = 'danger';
                            $message = 'Data limit exceeded!';
                        } elseif ($percentage >= 90) {
                            $color = 'warning';
                            $message = 'Approaching data limit (90%+)';
                        }
                        
                        Notification::make()
                            ->title($message)
                            ->body("Used: " . number_format($usageMb, 2) . " MB / " . number_format($limitMb, 2) . " MB")
                            ->color($color)
                            ->send();
                    }),
                    
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}