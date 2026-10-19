<?php

namespace Tests\Feature;

use App\Console\Commands\CheckDatabaseBackupCommand;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OperationsMonitoringTest extends TestCase
{
    protected function tearDown(): void
    {
        File::delete(storage_path('framework/testing/database-backup.dump'));

        parent::tearDown();
    }

    public function test_queue_monitoring_is_scheduled_and_logs_busy_queues(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('queue:monitor')
            ->expectsOutputToContain('health:database-backup')
            ->assertSuccessful();

        Log::spy();
        Event::dispatch(new QueueBusy('database', 'default', 101));

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Queue is busy.', [
                'connection' => 'database',
                'queue' => 'default',
                'size' => 101,
            ]);
    }

    public function test_database_backup_check_fails_for_missing_or_stale_backups(): void
    {
        $this->assertTrue(class_exists(CheckDatabaseBackupCommand::class));
        $path = storage_path('framework/testing/database-backup.dump');

        $this->artisan('health:database-backup', ['--path' => $path])
            ->expectsOutputToContain('backup file does not exist')
            ->assertFailed();

        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'backup');
        touch($path, now()->subHours(25)->timestamp);

        $this->artisan('health:database-backup', [
            '--path' => $path,
            '--max-age-hours' => 24,
        ])->expectsOutputToContain('backup is stale')
            ->assertFailed();
    }

    public function test_database_backup_check_accepts_a_recent_readable_backup(): void
    {
        $this->assertTrue(class_exists(CheckDatabaseBackupCommand::class));
        $path = storage_path('framework/testing/database-backup.dump');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'backup');

        $this->artisan('health:database-backup', [
            '--path' => $path,
            '--max-age-hours' => 24,
        ])->expectsOutputToContain('Database backup is healthy')
            ->assertSuccessful();
    }
}
