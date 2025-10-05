<?php

namespace App\Filament\Resources\DataUsageResource\Widgets;

use App\Models\DataUsage;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopUsers extends BaseWidget
{
    protected static ?string $heading = 'Top Data Users This Month';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DataUsage::query()
                    ->thisMonth()
                    ->with(['subscription.customer', 'subscription.package'])
                    ->select([
                        'username',
                        DB::raw('ROW_NUMBER() OVER (ORDER BY SUM(total_bytes) DESC) as id'),
                        DB::raw('SUM(total_bytes) as total_usage'),
                        DB::raw('SUM(session_count) as total_sessions'),
                        DB::raw('SUM(session_time) as total_time'),
                        DB::raw('AVG(session_time / NULLIF(session_count, 0)) as avg_session_duration'),
                        DB::raw('MAX(total_bytes) as peak_daily_usage'),
                        DB::raw('COUNT(*) as usage_days')
                    ])
                    ->groupBy('username')
                    ->orderByDesc('total_usage')
                    ->limit(20)
            )
            ->recordKey('username')
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('subscription.customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->limit(20)
                    ->tooltip(function ($record) {
                        return $record->subscription?->customer?->name;
                    }),
                    
                Tables\Columns\TextColumn::make('subscription.package.name')
                    ->label('Package')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('total_usage')
                    ->label('Total Usage')
                    ->formatStateUsing(function ($state) {
                        $bytes = (int) $state;
                        if ($bytes >= 1024 * 1024 * 1024) {
                            return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
                        } elseif ($bytes >= 1024 * 1024) {
                            return number_format($bytes / (1024 * 1024), 2) . ' MB';
                        } else {
                            return number_format($bytes / 1024, 2) . ' KB';
                        }
                    })
                    ->sortable()
                    ->badge()
                    ->color(function ($state) {
                        $gb = $state / (1024 * 1024 * 1024);
                        if ($gb >= 5) return 'danger';
                        if ($gb >= 2) return 'warning';
                        return 'success';
                    }),
                    
                Tables\Columns\TextColumn::make('total_sessions')
                    ->label('Sessions')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('total_time')
                    ->label('Total Time')
                    ->formatStateUsing(function ($state) {
                        $seconds = (int) $state;
                        $hours = floor($seconds / 3600);
                        $minutes = floor(($seconds % 3600) / 60);
                        return $hours . 'h ' . $minutes . 'm';
                    })
                    ->sortable()
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('avg_session_duration')
                    ->label('Avg Session')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '0m';
                        $minutes = floor($state / 60);
                        $seconds = $state % 60;
                        return $minutes . 'm ' . $seconds . 's';
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('peak_daily_usage')
                    ->label('Peak Daily')
                    ->formatStateUsing(function ($state) {
                        $bytes = (int) $state;
                        if ($bytes >= 1024 * 1024 * 1024) {
                            return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
                        } elseif ($bytes >= 1024 * 1024) {
                            return number_format($bytes / (1024 * 1024), 2) . ' MB';
                        } else {
                            return number_format($bytes / 1024, 2) . ' KB';
                        }
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('usage_days')
                    ->label('Active Days')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('subscription.status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'expired' => 'danger',
                        'suspended' => 'warning',
                        default => 'gray'
                    })
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('package')
                    ->relationship('subscription.package', 'name')
                    ->preload(),
                    
                Tables\Filters\Filter::make('high_usage')
                    ->label('High Usage (>1GB)')
                    ->query(fn (Builder $query): Builder => 
                        $query->havingRaw('SUM(total_bytes) >= ?', [1024 * 1024 * 1024])
                    )
                    ->toggle(),
                    
                Tables\Filters\Filter::make('many_sessions')
                    ->label('Many Sessions (>100)')
                    ->query(fn (Builder $query): Builder => 
                        $query->havingRaw('SUM(session_count) > ?', [100])
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('View Usage')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(function ($record) {
                        return route('filament.admin.resources.data-usages.index', [
                            'tableFilters[subscription][values][0]' => $record->subscription?->id,
                        ]);
                    }),
                    
                Tables\Actions\Action::make('view_subscription')
                    ->label('View Subscription')
                    ->icon('heroicon-o-user-circle')
                    ->color('warning')
                    ->url(function ($record) {
                        return route('filament.admin.resources.subscriptions.view', [
                            'record' => $record->subscription?->id,
                        ]);
                    })
                    ->visible(fn ($record) => $record->subscription),
            ])
            ->defaultSort('total_usage', 'desc')
            ->poll('60s')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50];
    }
}