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
            Schema::create($history['history'], function (Blueprint $table) use ($history): void {
                $table->id();
                $table->unsignedBigInteger($history['record'])->index();
                $table->string('action');
                $table->jsonb('snapshot');
                $table->jsonb('changes')->nullable();
                $table->timestamps();
                $table->index('created_at');
            });

            DB::unprepared(HistoryTriggerSql::create($history));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse($this->histories()) as $history) {
            DB::unprepared(HistoryTriggerSql::drop($history));

            Schema::dropIfExists($history['history']);
        }
    }

    /**
     * @return list<array{source: string, history: string, record: string, function: string, trigger: string, soft_deletes: bool, hidden: list<string>}>
     */
    private function histories(): array
    {
        return array_values(array_filter(
            HistoryTriggerSql::definitions(),
            fn (array $history): bool => $history['source'] !== 'tb_examples',
        ));
    }
};
