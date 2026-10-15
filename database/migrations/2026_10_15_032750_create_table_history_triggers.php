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
        foreach ($this->histories() as $history) {
            Schema::create($history['history'], function (Blueprint $table) use ($history): void {
                $table->id();
                $table->unsignedBigInteger($history['record'])->index();
                $table->string('action');
                $table->jsonb('snapshot');
                $table->jsonb('changes')->nullable();
                $table->timestamps();
            });

            DB::unprepared($this->triggerSql($history));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse($this->histories()) as $history) {
            DB::unprepared(sprintf(
                'DROP TRIGGER IF EXISTS %s ON %s; DROP FUNCTION IF EXISTS %s();',
                $this->ident($history['trigger']),
                $this->ident($history['source']),
                $this->ident($history['function']),
            ));

            Schema::dropIfExists($history['history']);
        }
    }

    /**
     * @return list<array{source: string, history: string, record: string, function: string, trigger: string, soft_deletes: bool, hidden: list<string>}>
     */
    private function histories(): array
    {
        return [
            [
                'source' => 'me_users',
                'history' => 'me_user_hist',
                'record' => 'user_id',
                'function' => 'fn_me_user_hist',
                'trigger' => 'trg_me_users_hist',
                'soft_deletes' => true,
                'hidden' => ['password', 'remember_token'],
            ],
            [
                'source' => 'me_user_details',
                'history' => 'me_user_detail_hist',
                'record' => 'user_detail_id',
                'function' => 'fn_me_user_detail_hist',
                'trigger' => 'trg_me_user_details_hist',
                'soft_deletes' => true,
                'hidden' => ['transaction_pin'],
            ],
            [
                'source' => 'tb_posts',
                'history' => 'tb_post_hist',
                'record' => 'post_id',
                'function' => 'fn_tb_post_hist',
                'trigger' => 'trg_tb_posts_hist',
                'soft_deletes' => true,
                'hidden' => [],
            ],
            [
                'source' => 'st_settings',
                'history' => 'st_setting_hist',
                'record' => 'setting_id',
                'function' => 'fn_st_setting_hist',
                'trigger' => 'trg_st_settings_hist',
                'soft_deletes' => false,
                'hidden' => [],
            ],
            [
                'source' => 'st_roles',
                'history' => 'st_role_hist',
                'record' => 'role_id',
                'function' => 'fn_st_role_hist',
                'trigger' => 'trg_st_roles_hist',
                'soft_deletes' => false,
                'hidden' => [],
            ],
            [
                'source' => 'st_permissions',
                'history' => 'st_permission_hist',
                'record' => 'permission_id',
                'function' => 'fn_st_permission_hist',
                'trigger' => 'trg_st_permissions_hist',
                'soft_deletes' => false,
                'hidden' => [],
            ],
        ];
    }

    /**
     * @param  array{source: string, history: string, record: string, function: string, trigger: string, soft_deletes: bool, hidden: list<string>}  $history
     */
    private function triggerSql(array $history): string
    {
        $source = $this->ident($history['source']);
        $historyTable = $this->ident($history['history']);
        $record = $this->ident($history['record']);
        $function = $this->ident($history['function']);
        $trigger = $this->ident($history['trigger']);
        $hide = $this->hiddenSql($history['hidden']);
        $softDelete = $history['soft_deletes']
            ? <<<'SQL'

                    ELSIF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL THEN
                        row_action := 'deleted';
                        row_changes := NULL;
            SQL
            : '';

        return <<<SQL
            CREATE OR REPLACE FUNCTION {$function}()
            RETURNS trigger
            LANGUAGE plpgsql
            AS \$fn\$
            DECLARE
                row_id bigint;
                row_snapshot jsonb;
                row_changes jsonb;
                row_action text;
                new_row jsonb;
                old_row jsonb;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    row_id := OLD.id;
                    row_snapshot := to_jsonb(OLD){$hide};
                    row_changes := NULL;
                    row_action := 'deleted';
                ELSE
                    row_id := NEW.id;
                    new_row := to_jsonb(NEW){$hide};
                    row_snapshot := new_row;

                    IF TG_OP = 'INSERT' THEN
                        row_action := 'created';
                        row_changes := NULL;{$softDelete}
                    ELSE
                        row_action := 'updated';
                        old_row := to_jsonb(OLD){$hide};

                        SELECT COALESCE(jsonb_object_agg(key, value), '{}'::jsonb)
                        INTO row_changes
                        FROM jsonb_each(new_row)
                        WHERE new_row -> key IS DISTINCT FROM old_row -> key;
                    END IF;
                END IF;

                INSERT INTO {$historyTable} ({$record}, action, snapshot, changes, created_at, updated_at)
                VALUES (row_id, row_action, row_snapshot, row_changes, NOW(), NOW());

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                RETURN NEW;
            END;
            \$fn\$;

            CREATE TRIGGER {$trigger}
            AFTER INSERT OR UPDATE OR DELETE ON {$source}
            FOR EACH ROW
            EXECUTE FUNCTION {$function}();
        SQL;
    }

    /**
     * @param  list<string>  $columns
     */
    private function hiddenSql(array $columns): string
    {
        if ($columns === []) {
            return '';
        }

        $quoted = array_map(
            fn (string $column): string => "'".$this->ident($column)."'",
            $columns,
        );

        return ' - ARRAY['.implode(', ', $quoted).']::text[]';
    }

    private function ident(string $name): string
    {
        if (! preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
            throw new RuntimeException('Unsafe SQL identifier.');
        }

        return $name;
    }
};
