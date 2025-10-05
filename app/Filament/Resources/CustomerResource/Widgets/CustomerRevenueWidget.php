<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use App\Models\Customer;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CustomerRevenueWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Get revenue statistics - use proper scopes and payment_date
        $totalRevenue = Payment::completedOrRefunded()->sum('amount');
        $totalRefunds = Payment::refunded()->sum('refund_amount');
        $netRevenue = $totalRevenue - $totalRefunds;
        
        $todayRevenue = Payment::completedOrRefunded()
            ->whereDate('payment_date', today())
            ->sum('amount');
            
        $thisWeekRevenue = Payment::completedOrRefunded()
            ->where('payment_date', '>=', now()->startOfWeek())
            ->sum('amount');
            
        $thisMonthRevenue = Payment::completedOrRefunded()
            ->thisMonth()
            ->sum('amount');
            
        $lastMonthRevenue = Payment::completedOrRefunded()
            ->whereMonth('payment_date', now()->subMonth()->month)
            ->whereYear('payment_date', now()->subMonth()->year)
            ->sum('amount');

        // Calculate growth
        $monthlyGrowth = $lastMonthRevenue > 0 
            ? (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 
            : 0;

        // Average revenue per customer
        $totalCustomers = Customer::count();
        $avgRevenuePerCustomer = $totalCustomers > 0 ? $totalRevenue / $totalCustomers : 0;

        // High value customers (>₵1,000) - calculate net amount after refunds
        $highValueCustomers = DB::table('customers')
            ->join('payments', 'customers.id', '=', 'payments.customer_id')
            ->whereIn('payments.status', ['completed', 'refunded', 'partially_refunded'])
            ->select('customers.id')
            ->groupBy('customers.id')
            ->havingRaw('SUM(payments.amount - COALESCE(payments.refund_amount, 0)) > 1000')
            ->get()
            ->count();

        return [
            Stat::make('Total Revenue', '₵' . number_format($totalRevenue, 2))
                ->description($monthlyGrowth >= 0 ? 
                    '+' . number_format($monthlyGrowth, 1) . '% from last month' : 
                    number_format($monthlyGrowth, 1) . '% from last month')
                ->descriptionIcon($monthlyGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthlyGrowth >= 0 ? 'success' : 'danger')
                ->chart([
                    Payment::completedOrRefunded()->whereDate('payment_date', today()->subDays(6))->sum('amount'),
                    Payment::completedOrRefunded()->whereDate('payment_date', today()->subDays(5))->sum('amount'),
                    Payment::completedOrRefunded()->whereDate('payment_date', today()->subDays(4))->sum('amount'),
                    Payment::completedOrRefunded()->whereDate('payment_date', today()->subDays(3))->sum('amount'),
                    Payment::completedOrRefunded()->whereDate('payment_date', today()->subDays(2))->sum('amount'),
                    Payment::completedOrRefunded()->whereDate('payment_date', today()->subDays(1))->sum('amount'),
                    Payment::completedOrRefunded()->whereDate('payment_date', today())->sum('amount'),
                ]),

            Stat::make('This Month', '₵' . number_format($thisMonthRevenue, 2))
                ->description($thisWeekRevenue > 0 ? '₵' . number_format($thisWeekRevenue, 2) . ' this week' : 'No revenue this week')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make('Net Revenue', '₵' . number_format($netRevenue, 2))
                ->description($totalRefunds > 0 ? '₵' . number_format($totalRefunds, 2) . ' refunded' : 'No refunds')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Today', '₵' . number_format($todayRevenue, 2))
                ->description(Payment::completedOrRefunded()->whereDate('payment_date', today())->count() . ' payments')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            Stat::make('Average per Customer', '₵' . number_format($avgRevenuePerCustomer, 2))
                ->description($totalCustomers . ' total customers')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('warning'),

            Stat::make('High Value Customers', number_format($highValueCustomers))
                ->description('Spent >₵1,000')
                ->descriptionIcon('heroicon-m-star')
                ->color('yellow'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}