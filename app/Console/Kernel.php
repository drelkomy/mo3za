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
        // تشغيل معالج الطابور كل دقيقة
        $schedule->command('queue:work --stop-when-empty --tries=3')
                 ->everyMinute()
                 ->withoutOverlapping();
                 
        // تشغيل معالج الطابور المفشل كل ساعة
        $schedule->command('queue:retry all')
                 ->hourly()
                 ->withoutOverlapping();
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