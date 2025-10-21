<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\SecurityCheck::class,
        \App\Console\Commands\ValidateEnvironment::class,
        Commands\SecurityScanCommand::class,
        // Add other commands here
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Daily backup at 2 AM
        $schedule->command('backup:run')->dailyAt('02:00');
        $schedule->command('backup:clean')->dailyAt('03:00');
        $schedule->command('backup:monitor')->dailyAt('04:00');
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