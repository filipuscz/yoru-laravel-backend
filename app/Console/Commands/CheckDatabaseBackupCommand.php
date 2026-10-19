<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('health:database-backup {--path=} {--max-age-hours=}')]
#[Description('Check that a readable PostgreSQL backup exists and is recent')]
class CheckDatabaseBackupCommand extends Command
{
    public function handle(): int
    {
        $pathOption = $this->option('path');
        $path = is_string($pathOption) && $pathOption !== ''
            ? $pathOption
            : (string) config('operations.database_backup.path');
        $maxAgeOption = $this->option('max-age-hours');
        $maxAgeHours = is_numeric($maxAgeOption)
            ? (int) $maxAgeOption
            : (int) config('operations.database_backup.max_age_hours', 24);

        if ($maxAgeHours < 1) {
            $this->error('Maximum backup age must be at least 1 hour.');

            return self::FAILURE;
        }
        if (! File::exists($path)) {
            $this->error("Database backup file does not exist: {$path}");

            return self::FAILURE;
        }
        if (! is_readable($path) || File::size($path) < 1) {
            $this->error("Database backup is unreadable or empty: {$path}");

            return self::FAILURE;
        }

        $ageSeconds = now()->timestamp - File::lastModified($path);
        if ($ageSeconds > $maxAgeHours * 3600) {
            $this->error("Database backup is stale: {$path}");

            return self::FAILURE;
        }

        $this->info("Database backup is healthy: {$path}");

        return self::SUCCESS;
    }
}
