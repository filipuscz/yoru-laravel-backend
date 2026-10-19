<?php

use App\Support\HistoryTriggerSql;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->histories() as $history) {
            if (! Schema::hasIndex($history['history'], ['created_at'])) {
                Schema::table($history['history'], function (Blueprint $table): void {
                    $table->index('created_at');
                });
            }

            DB::unprepared(HistoryTriggerSql::create($history));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->histories() as $history) {
            if (Schema::hasIndex($history['history'], ['created_at'])) {
                Schema::table($history['history'], function (Blueprint $table): void {
                    $table->dropIndex(['created_at']);
                });
            }
        }
    }

    /**
     * @return list<array{source: string, history: string, record: string, function: string, trigger: string, soft_deletes: bool, hidden: list<string>}>
     */
    private function histories(): array
    {
        return HistoryTriggerSql::definitions();
    }
};
