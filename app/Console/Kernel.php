<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Expire subscriptions every minute
        $schedule->command('subscriptions:expire --send-notices --cleanup')
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Data usage tracking is handled automatically by Subscription model on interim updates
        // No need for separate sync command
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
