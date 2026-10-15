<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = config('history.purge_tables');
        if (! is_array($tables) || $tables === []) {
            throw new RuntimeException('History purge tables are not configured.');
        }

        $quoted = array_map(function (mixed $table): string {
            if (! is_string($table) || ! preg_match('/^[a-z_][a-z0-9_]*$/', $table)) {
                throw new RuntimeException('Unsafe SQL identifier.');
            }

            return "'{$table}'";
        }, $tables);

        $list = implode(', ', $quoted);

        DB::unprepared(<<<SQL
            CREATE OR REPLACE PROCEDURE pr_purge_history(older_than interval, batch_size integer)
            LANGUAGE plpgsql
            AS \$proc\$
            DECLARE
                hist_table text;
                removed integer;
                hist_tables text[] := ARRAY[{$list}];
            BEGIN
                IF batch_size < 1 THEN
                    RAISE EXCEPTION 'batch_size must be at least 1';
                END IF;

                FOREACH hist_table IN ARRAY hist_tables LOOP
                    LOOP
                        EXECUTE format(
                            'DELETE FROM %I WHERE id IN (SELECT id FROM (SELECT id FROM %I WHERE created_at < NOW() - \$1 ORDER BY id LIMIT \$2) AS doomed)',
                            hist_table,
                            hist_table
                        ) USING older_than, batch_size;

                        GET DIAGNOSTICS removed = ROW_COUNT;
                        COMMIT;
                        EXIT WHEN removed = 0;
                    END LOOP;
                END LOOP;
            END;
            \$proc\$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS pr_purge_history(interval, integer)');
    }
};
