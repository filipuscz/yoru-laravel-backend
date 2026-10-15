<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('history:purge {--days=365} {--batch=1000}')]
#[Description('Delete history rows older than the given number of days')]
class PurgeHistoryCommand extends Command
{
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $batch = (int) $this->option('batch');

        if ($days < 1 || $batch < 1) {
            $this->error('Days and batch must be at least 1.');

            return self::FAILURE;
        }

        DB::unprepared(sprintf(
            'CALL pr_purge_history(%d * interval \'1 day\', %d)',
            $days,
            $batch,
        ));

        $this->info("Purged history rows older than {$days} days.");

        return self::SUCCESS;
    }
}
