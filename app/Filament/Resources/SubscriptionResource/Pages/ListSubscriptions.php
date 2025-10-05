<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use App\Models\Subscription;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Notifications\Notification;

class ListSubscriptions extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Subscription')
                ->icon('heroicon-o-plus'),

            Actions\Action::make('expire_old_subscriptions')
                ->label('Expire Old Subscriptions')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $result = Subscription::expireOldSubscriptions();
                    Notification::make()
                        ->title($result['message'])
                        ->success()
                        ->send();
                }),

            Actions\Action::make('auto_renew_subscriptions')
                ->label('Process Auto Renewals')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    $result = Subscription::autoRenewSubscriptions();
                    Notification::make()
                        ->title($result['message'])
                        ->success()
                        ->send();
                }),

            Actions\Action::make('cleanup_expired_sessions')
                ->label('Cleanup Expired Sessions')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    $result = Subscription::cleanupExpiredSessions();
                    Notification::make()
                        ->title($result['message'])
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Subscriptions')
                ->icon('heroicon-o-user-group')
                ->badge(Subscription::count()),

            'active' => Tab::make('Active')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->active())
                ->badge(Subscription::active()->count())
                ->badgeColor('success'),

            'pending' => Tab::make('Pending')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(Subscription::where('status', 'pending')->count())
                ->badgeColor('warning'),

            'suspended' => Tab::make('Suspended')
                ->icon('heroicon-o-pause')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'suspended'))
                ->badge(Subscription::where('status', 'suspended')->count())
                ->badgeColor('warning'),

            'expired' => Tab::make('Expired')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->expired())
                ->badge(Subscription::expired()->count())
                ->badgeColor('gray'),

            'blocked' => Tab::make('Blocked')
                ->icon('heroicon-o-no-symbol')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'blocked'))
                ->badge(Subscription::where('status', 'blocked')->count())
                ->badgeColor('danger'),

            'expiring_soon' => Tab::make('Expiring Soon')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query) => $query->expiringSoon(24))
                ->badge(Subscription::expiringSoon(24)->count())
                ->badgeColor('warning'),

            'trial' => Tab::make('Trial')
                ->icon('heroicon-o-star')
                ->modifyQueryUsing(fn (Builder $query) => $query->trial())
                ->badge(Subscription::trial()->count())
                ->badgeColor('info'),

            'auto_renew' => Tab::make('Auto Renew')
                ->icon('heroicon-o-arrow-path')
                ->modifyQueryUsing(fn (Builder $query) => $query->autoRenew())
                ->badge(Subscription::autoRenew()->count())
                ->badgeColor('primary'),

            'data_exceeded' => Tab::make('Data Exceeded')
                ->icon('heroicon-o-signal-slash')
                ->modifyQueryUsing(fn (Builder $query) => $query->dataLimitExceeded())
                ->badge(Subscription::dataLimitExceeded()->count())
                ->badgeColor('danger'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SubscriptionResource\Widgets\SubscriptionStatsWidget::class,
            SubscriptionResource\Widgets\SubscriptionChartWidget::class,
        ];
    }
}
