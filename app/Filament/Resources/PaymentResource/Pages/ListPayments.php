<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Payment;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Payments & Transactions';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $totalPayments = Payment::count();
        $totalRevenue = Payment::completed()->sum('amount');
        $pendingCount = Payment::pending()->count();

        return "Total: {$totalPayments} | Revenue: ₵" . number_format($totalRevenue, 2) . " | Pending: {$pendingCount}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Record Payment')
                ->icon('heroicon-o-plus-circle'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Payments')
                ->badge(Payment::count())
                ->badgeColor('gray'),

            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed'))
                ->badge(Payment::where('status', 'completed')->count())
                ->badgeColor('success'),

            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['pending', 'processing']))
                ->badge(Payment::whereIn('status', ['pending', 'processing'])->count())
                ->badgeColor('warning'),

            'failed' => Tab::make('Failed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['failed', 'cancelled']))
                ->badge(Payment::whereIn('status', ['failed', 'cancelled'])->count())
                ->badgeColor('danger'),

            'refunded' => Tab::make('Refunded')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['refunded', 'partially_refunded']))
                ->badge(Payment::whereIn('status', ['refunded', 'partially_refunded'])->count())
                ->badgeColor('info'),

            'mobile_money' => Tab::make('Mobile Money')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_method', 'mobile_money'))
                ->badge(Payment::where('payment_method', 'mobile_money')->count())
                ->badgeColor('success'),

            'cash' => Tab::make('Cash')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_method', 'cash'))
                ->badge(Payment::where('payment_method', 'cash')->count())
                ->badgeColor('warning'),

            'today' => Tab::make('Today')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('payment_date', today()))
                ->badge(Payment::whereDate('payment_date', today())->count())
                ->badgeColor('primary'),

            'this_month' => Tab::make('This Month')
                ->modifyQueryUsing(fn (Builder $query) => $query->thisMonth())
                ->badge(Payment::thisMonth()->count())
                ->badgeColor('primary'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PaymentResource\Widgets\PaymentOverview::class,
        ];
    }
}
