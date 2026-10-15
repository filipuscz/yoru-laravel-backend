<?php

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
        Schema::create('tb_example_hist', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('example_id')->index();
            $table->string('action');
            $table->jsonb('snapshot');
            $table->jsonb('changes')->nullable();
            $table->timestamps();
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_tb_example_hist()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                row_id bigint;
                row_snapshot jsonb;
                row_changes jsonb;
                row_action text;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    row_id := OLD.id;
                    row_snapshot := to_jsonb(OLD);
                    row_changes := NULL;
                    row_action := 'deleted';
                ELSE
                    row_id := NEW.id;
                    row_snapshot := to_jsonb(NEW);

                    IF TG_OP = 'INSERT' THEN
                        row_action := 'created';
                        row_changes := NULL;
                    ELSIF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL THEN
                        row_action := 'deleted';
                        row_changes := NULL;
                    ELSE
                        row_action := 'updated';

                        SELECT COALESCE(jsonb_object_agg(key, value), '{}'::jsonb)
                        INTO row_changes
                        FROM jsonb_each(to_jsonb(NEW))
                        WHERE to_jsonb(NEW) -> key IS DISTINCT FROM to_jsonb(OLD) -> key;
                    END IF;
                END IF;

                INSERT INTO tb_example_hist (example_id, action, snapshot, changes, created_at, updated_at)
                VALUES (row_id, row_action, row_snapshot, row_changes, NOW(), NOW());

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER trg_tb_examples_hist
            AFTER INSERT OR UPDATE OR DELETE ON tb_examples
            FOR EACH ROW
            EXECUTE FUNCTION fn_tb_example_hist();
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trg_tb_examples_hist ON tb_examples;
            DROP FUNCTION IF EXISTS fn_tb_example_hist();
        SQL);

        Schema::dropIfExists('tb_example_hist');
    }
};
