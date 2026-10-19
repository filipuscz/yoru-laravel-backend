<?php

namespace App\Support;

final class HistoryTriggerSql
{
    /**
     * @return list<array{source: string, history: string, record: string, function: string, trigger: string, soft_deletes: bool, hidden: list<string>, redact_when: array{column: string, value: string, hidden: list<string>}|null}>
     */
    public static function definitions(): array
    {
        $configured = config('history.tables');
        if (! is_array($configured)) {
            throw new \RuntimeException('History tables are not configured.');
        }

        $definitions = [];
        foreach ($configured as $definition) {
            if (! is_array($definition) || ! is_array($definition['hidden'] ?? null)) {
                throw new \RuntimeException('Invalid history table definition.');
            }

            $hidden = array_values(array_filter(
                $definition['hidden'],
                fn (mixed $column): bool => is_string($column),
            ));
            $redactWhen = self::redactWhen($definition['redact_when'] ?? null);

            $definitions[] = [
                'source' => self::configuredString($definition, 'source'),
                'history' => self::configuredString($definition, 'history'),
                'record' => self::configuredString($definition, 'record'),
                'function' => self::configuredString($definition, 'function'),
                'trigger' => self::configuredString($definition, 'trigger'),
                'soft_deletes' => (bool) ($definition['soft_deletes'] ?? false),
                'hidden' => $hidden,
                'redact_when' => $redactWhen,
            ];
        }

        return $definitions;
    }

    /**
     * @return array{source: string, history: string, record: string, function: string, trigger: string, soft_deletes: bool, hidden: list<string>, redact_when: array{column: string, value: string, hidden: list<string>}|null}
     */
    public static function definition(string $key): array
    {
        foreach (self::definitions() as $definition) {
            if ($definition['history'] === config("history.tables.{$key}.history")) {
                return $definition;
            }
        }

        throw new \RuntimeException("History table [{$key}] is not configured.");
    }

    /**
     * @param  array{source: string, history: string, record: string, function: string, trigger: string, soft_deletes: bool, hidden: list<string>, redact_when?: array{column: string, value: string, hidden: list<string>}|null}  $definition
     */
    public static function create(array $definition): string
    {
        $source = self::identifier($definition['source']);
        $historyTable = self::identifier($definition['history']);
        $record = self::identifier($definition['record']);
        $function = self::identifier($definition['function']);
        $trigger = self::identifier($definition['trigger']);
        $oldRow = self::rowJson('OLD', $definition);
        $newRow = self::rowJson('NEW', $definition);
        $softDelete = $definition['soft_deletes']
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
                    row_snapshot := {$oldRow};
                    row_changes := NULL;
                    row_action := 'deleted';
                ELSE
                    row_id := NEW.id;
                    new_row := {$newRow};
                    row_snapshot := new_row;

                    IF TG_OP = 'INSERT' THEN
                        row_action := 'created';
                        row_changes := NULL;{$softDelete}
                    ELSE
                        row_action := 'updated';
                        old_row := {$oldRow};

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

            DROP TRIGGER IF EXISTS {$trigger} ON {$source};

            CREATE TRIGGER {$trigger}
            AFTER INSERT OR UPDATE OR DELETE ON {$source}
            FOR EACH ROW
            EXECUTE FUNCTION {$function}();
        SQL;
    }

    /**
     * @param  array{source: string, function: string, trigger: string}  $definition
     */
    public static function drop(array $definition): string
    {
        return sprintf(
            'DROP TRIGGER IF EXISTS %s ON %s; DROP FUNCTION IF EXISTS %s();',
            self::identifier($definition['trigger']),
            self::identifier($definition['source']),
            self::identifier($definition['function']),
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private static function hiddenColumns(array $columns): string
    {
        if ($columns === []) {
            return '';
        }

        $quoted = array_map(
            fn (string $column): string => "'".self::identifier($column)."'",
            $columns,
        );

        return ' - ARRAY['.implode(', ', $quoted).']::text[]';
    }

    /**
     * @param  array{hidden: list<string>, redact_when?: array{column: string, value: string, hidden: list<string>}|null}  $definition
     */
    private static function rowJson(string $record, array $definition): string
    {
        $base = 'to_jsonb('.$record.')'.self::hiddenColumns($definition['hidden']);
        $redactWhen = $definition['redact_when'] ?? null;
        if ($redactWhen === null) {
            return $base;
        }

        $column = self::identifier($redactWhen['column']);
        $value = str_replace("'", "''", $redactWhen['value']);
        $hidden = self::hiddenColumns($redactWhen['hidden']);

        return "(CASE WHEN {$record}.{$column}::text = '{$value}' THEN {$base}{$hidden} ELSE {$base} END)";
    }

    private static function identifier(string $name): string
    {
        if (! preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Unsafe SQL identifier.');
        }

        return $name;
    }

    /**
     * @param  array<mixed>  $definition
     */
    private static function configuredString(array $definition, string $key): string
    {
        $value = $definition[$key] ?? null;
        if (! is_string($value)) {
            throw new \RuntimeException("Invalid history table {$key}.");
        }

        return $value;
    }

    /**
     * @return array{column: string, value: string, hidden: list<string>}|null
     */
    private static function redactWhen(mixed $configured): ?array
    {
        if ($configured === null) {
            return null;
        }
        if (! is_array($configured) || ! is_array($configured['hidden'] ?? null)) {
            throw new \RuntimeException('Invalid conditional history redaction.');
        }

        return [
            'column' => self::configuredString($configured, 'column'),
            'value' => self::configuredString($configured, 'value'),
            'hidden' => array_values(array_filter(
                $configured['hidden'],
                fn (mixed $column): bool => is_string($column),
            )),
        ];
    }
}
