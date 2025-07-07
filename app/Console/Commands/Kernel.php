<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Run the reminder command daily at 8:00 AM
        $schedule->command('reminders:send')->dailyAt('08:00');
        $schedule->command('renewals:cancel-stale')->daily();
        // Check for overdue books daily at midnight
        // $schedule->command('books:mark-overdue')
        //     // ->dailyAt('00:00')
        //     ->everyMinute()
        //     ->onFailure(function () {});
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
