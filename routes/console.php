<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$queueConnection = (string) config('operations.queue_monitor.connection', 'database');
$queues = (string) config('operations.queue_monitor.queues', 'default,notifications');
$maxJobs = (int) config('operations.queue_monitor.max_jobs', 100);

Schedule::command('queue:monitor', [
    "{$queueConnection}:{$queues}",
    '--max' => $maxJobs,
])->everyMinute()->withoutOverlapping();

Schedule::command('health:database-backup')
    ->hourly()
    ->withoutOverlapping();
