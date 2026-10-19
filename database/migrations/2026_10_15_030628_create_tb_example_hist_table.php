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
        $history = HistoryTriggerSql::definition('examples');

        Schema::create('tb_example_hist', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('example_id')->index();
            $table->string('action');
            $table->jsonb('snapshot');
            $table->jsonb('changes')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        DB::unprepared(HistoryTriggerSql::create($history));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(HistoryTriggerSql::drop(HistoryTriggerSql::definition('examples')));

        Schema::dropIfExists('tb_example_hist');
    }
};
