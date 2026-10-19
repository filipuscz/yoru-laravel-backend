<?php

use App\Support\HistoryTriggerSql;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('st_setting_hist')
            ->whereRaw("snapshot->>'key' = ?", ['smtp_password'])
            ->update([
                'snapshot' => DB::raw("snapshot - 'value'"),
                'changes' => DB::raw("CASE WHEN changes IS NULL THEN NULL ELSE changes - 'value' END"),
            ]);

        DB::unprepared(HistoryTriggerSql::create(
            HistoryTriggerSql::definition('settings'),
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(HistoryTriggerSql::create(
            HistoryTriggerSql::definition('settings'),
        ));
    }
};
