<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Package;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Colors\Color;
use Filament\Notifications\Notification;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus')
                ->label('Add New Customer'),

            Actions\ActionGroup::make([

                Actions\Action::make('sync_all_radius')
                    ->label('Sync All RADIUS')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This will sync all customers with RADIUS servers. This may take a while.')
                    ->action(function () {
                        $customers = Customer::with('subscriptions')->get();

                        foreach ($customers as $customer) {
                            $customer->syncAllRadiusStatus();
                        }

                        Notification::make()
                            ->title('All customers synced with RADIUS successfully!')
                            ->success()
                            ->send();
                    }),
            ])
            ->label('More Actions')
            ->icon('heroicon-o-ellipsis-horizontal')
            ->button()
            ->color('gray'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Customers')
                ->icon('heroicon-o-users')
                ->badge(Customer::count()),

            'active' => Tab::make('Active')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active'))
                ->badge(Customer::where('status', 'active')->count())
                ->badgeColor('success'),

            'suspended' => Tab::make('Suspended')
                ->icon('heroicon-o-pause-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'suspended'))
                ->badge(Customer::where('status', 'suspended')->count())
                ->badgeColor('warning'),

            'blocked' => Tab::make('Blocked')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'blocked'))
                ->badge(Customer::where('status', 'blocked')->count())
                ->badgeColor('danger'),

            'with_subscriptions' => Tab::make('With Active Subscriptions')
                ->icon('heroicon-o-cube')
                ->modifyQueryUsing(fn (Builder $query) => $query->withActiveSubscription())
                ->badge(Customer::withActiveSubscription()->count())
                ->badgeColor('primary'),

            'without_subscriptions' => Tab::make('Without Subscriptions')
                ->icon('heroicon-o-minus-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->withoutActiveSubscription())
                ->badge(Customer::withoutActiveSubscription()->count())
                ->badgeColor('gray'),

            'trial_users' => Tab::make('Trial Users')
                ->icon('heroicon-o-gift')
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->whereHas('subscriptions.package', fn ($q) => $q->where('is_trial', true))
                )
                ->badge(Customer::whereHas('subscriptions.package', fn ($q) => $q->where('is_trial', true))->count())
                ->badgeColor('info'),

            'recent' => Tab::make('Recent (7 days)')
                ->icon('heroicon-o-calendar')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('registration_date', '>=', now()->subDays(7)))
                ->badge(Customer::where('registration_date', '>=', now()->subDays(7))->count())
                ->badgeColor('purple'),

            'expiring_soon' => Tab::make('Expiring Soon')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(function (Builder $query) {
                    return $query->whereHas('activeSubscription', function (Builder $q) {
                        $q->where('expires_at', '<=', now()->addHours(48))
                          ->where('expires_at', '>', now());
                    });
                })
                ->badge(Customer::whereHas('activeSubscription', function (Builder $q) {
                    $q->where('expires_at', '<=', now()->addHours(48))
                      ->where('expires_at', '>', now());
                })->count())
                ->badgeColor('orange'),

            'high_value' => Tab::make('High Value (>₵1K)')
                ->icon('heroicon-o-banknotes')
                ->modifyQueryUsing(function (Builder $query) {
                    return $query->whereHas('payments', function (Builder $q) {
                        $q->where('status', 'completed')
                          ->havingRaw('SUM(amount) > 1000')
                          ->groupBy('customer_id');
                    });
                })
                ->badge(Customer::whereHas('payments', function (Builder $q) {
                    $q->where('status', 'completed')
                      ->havingRaw('SUM(amount) > 1000')
                      ->groupBy('customer_id');
                })->count())
                ->badgeColor('yellow'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CustomerResource\Widgets\CustomerStatsWidget::class,
            CustomerResource\Widgets\CustomerRevenueWidget::class,
        ];
    }
}
