<?php

namespace App\Filament\Resources\PaymentResource\Widgets;

use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PaymentOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Get payment statistics
        $totalPayments = Payment::count();
        $completedPayments = Payment::completed()->count();
        $pendingPayments = Payment::pending()->count();
        $failedPayments = Payment::failed()->count();
        
        // Get revenue statistics - use completedOrRefunded to include partially refunded payments
        $totalRevenue = Payment::completedOrRefunded()->sum('amount');
        $todayRevenue = Payment::completedOrRefunded()->whereDate('payment_date', today())->sum('amount');
        $monthlyRevenue = Payment::completedOrRefunded()->thisMonth()->sum('amount');
        
        // Get refund statistics
        $totalRefunds = Payment::refunded()->sum('refund_amount');
        $refundCount = Payment::refunded()->count();
        
        // Calculate growth
        $lastMonthRevenue = Payment::completedOrRefunded()
            ->whereMonth('payment_date', now()->subMonth()->month)
            ->whereYear('payment_date', now()->subMonth()->year)
            ->sum('amount');
            
        $revenueGrowth = $lastMonthRevenue > 0 
            ? round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : 0;
        
        // Get payment method breakdown
        $mobileMoneyPayments = Payment::completedOrRefunded()->where('payment_method', 'mobile_money')->count();
        $cashPayments = Payment::completedOrRefunded()->where('payment_method', 'cash')->count();
        
        // Calculate success rate
        $successRate = $totalPayments > 0 ? round(($completedPayments / $totalPayments) * 100, 1) : 0;
        
        // Calculate net revenue (revenue minus refunds)
        $netRevenue = $totalRevenue - $totalRefunds;

        return [
            Stat::make('Total Revenue', '₵' . number_format($totalRevenue, 2))
                ->description('All-time revenue from completed payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([
                    $lastMonthRevenue ?: 0,
                    $monthlyRevenue,
                ]),

            Stat::make('Monthly Revenue', '₵' . number_format($monthlyRevenue, 2))
                ->description(
                    $revenueGrowth >= 0 
                        ? "+{$revenueGrowth}% from last month"
                        : "{$revenueGrowth}% from last month"
                )
                ->descriptionIcon($revenueGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueGrowth >= 0 ? 'success' : 'danger'),

            Stat::make('Today Revenue', '₵' . number_format($todayRevenue, 2))
                ->description('Revenue from today\'s payments')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('Net Revenue', '₵' . number_format($netRevenue, 2))
                ->description('Revenue minus refunds: ₵' . number_format($totalRefunds, 2))
                ->descriptionIcon('heroicon-m-calculator')
                ->color($netRevenue >= 0 ? 'success' : 'danger'),

            Stat::make('Total Payments', number_format($totalPayments))
                ->description("{$completedPayments} completed, {$pendingPayments} pending")
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('primary')
                ->chart([
                    $failedPayments,
                    $pendingPayments,
                    $completedPayments,
                ]),

            Stat::make('Success Rate', $successRate . '%')
                ->description('Payment completion rate')
                ->descriptionIcon($successRate >= 80 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($successRate >= 80 ? 'success' : 'warning'),

            Stat::make('Pending Payments', number_format($pendingPayments))
                ->description('Awaiting approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Mobile Money', number_format($mobileMoneyPayments))
                ->description("Cash: {$cashPayments} | Digital dominance")
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('success'),

            Stat::make('Refunds Issued', number_format($refundCount))
                ->description('₵' . number_format($totalRefunds, 2) . ' total refunded')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color('danger'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}