<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\RadAcct;
use Filament\Widgets\Widget;

class RecentActivitiesWidget extends Widget
{
    protected static ?string $heading = 'Recent Activities';
    protected static ?int $sort = 6;
    protected static ?string $pollingInterval = '30s';
    protected static string $view = 'filament.widgets.recent-activities';

    protected int | string | array $columnSpan = 'full';

    public function getActivities(): array
    {
        $activities = [];

        // Recent customers (last 7 days)
        $recentCustomers = Customer::where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentCustomers as $customer) {
            $activities[] = [
                'type' => 'new_customer',
                'icon' => 'heroicon-o-user-plus',
                'color' => 'success',
                'title' => 'New Customer',
                'description' => "Customer {$customer->name} registered",
                'customer' => $customer->name,
                'time' => $customer->created_at,
                'amount' => null,
            ];
        }

        // Recent subscriptions (last 7 days)
        $recentSubscriptions = Subscription::with(['customer', 'package'])
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentSubscriptions as $subscription) {
            $activities[] = [
                'type' => 'new_subscription',
                'icon' => 'heroicon-o-rectangle-stack',
                'color' => 'info',
                'title' => 'New Subscription',
                'description' => "Subscribed to {$subscription->package->name}",
                'customer' => $subscription->customer->name,
                'time' => $subscription->created_at,
                'amount' => $subscription->package->price,
            ];
        }

        // Recent payments (last 7 days)
        $recentPayments = Payment::with(['customer', 'subscription.package'])
            ->where('created_at', '>=', now()->subDays(7))
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentPayments as $payment) {
            $activities[] = [
                'type' => 'payment_completed',
                'icon' => 'heroicon-o-banknotes',
                'color' => 'success',
                'title' => 'Payment Completed',
                'description' => "Payment for {$payment->subscription->package->name}",
                'customer' => $payment->customer->name,
                'time' => $payment->created_at,
                'amount' => $payment->amount,
            ];
        }

        // Recent sessions (last 24 hours)
        $recentSessions = RadAcct::where('acctstarttime', '>=', now()->subDay())
            ->orderBy('acctstarttime', 'desc')
            ->limit(8)
            ->get();

        foreach ($recentSessions as $session) {
            $customer = Customer::where('username', $session->username)->first();
            $customerName = $customer ? $customer->name : $session->username;

            $activities[] = [
                'type' => 'session_started',
                'icon' => 'heroicon-o-wifi',
                'color' => 'warning',
                'title' => 'Session Started',
                'description' => "Session on {$session->nasipaddress}",
                'customer' => $customerName,
                'time' => $session->acctstarttime,
                'amount' => null,
            ];
        }

        // Sort by time desc and take latest 20
        usort($activities, function ($a, $b) {
            return $b['time'] <=> $a['time'];
        });

        return array_slice($activities, 0, 20);
    }
}