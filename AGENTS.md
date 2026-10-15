# AGENTS.md

This document serves as the master guidelines file for AI Agents operating in this repository. It combines project-specific context, security rules, and Laravel Boost best practices.

## 1. Project Context & Architecture

- This repository is a Laravel backend API template.
- The installed dependency set in `composer.lock` currently includes Laravel `v13.x` (or `v12.x`), Laravel Passport `v13.x`, Laravel Boost, Laravel Pint, and PHPUnit `v11.x`. (Treat `composer.json` as the source of truth).
- API routes live under `routes/api.php`, with the main pattern using `/api/v1/...`, `CheckApiToken`, and `auth:api` where required.
- Stick to existing directory structure - don't create new top-level folders or change architecture patterns without approval.
- Keep controllers thin. Existing API controllers extend `App\Http\Controllers\BaseApiController` and delegate business logic to services.
- Put reusable data operations in services. Existing services extend `App\Services\BaseService`.
- Use API resources for response data where the existing endpoint pattern does so.

## 2. Database & Eloquent Conventions (CRITICAL)

- **Default engine is PostgreSQL**. The Laravel connection name is `pgsql` (`config/database.php` fallback and `.env.example`). Do not point new config, PHPUnit, or CI at `sqlite` or `mysql`.
- **Local server**: PostgreSQL 16 runs as container `global_postgres` from `../docker-stack` (`docker compose --profile postgres up -d`). App database `yorupus_be_template`, test database `testing`, user `root`, password `123`, port `5432`, host `global_postgres`.
- **PHP driver**: `pdo_pgsql` is required. `global_php84` provides it. Run database Artisan commands inside that container when the host PHP build has no `pdo_pgsql`: `docker exec -w /var/www/yoru-laravel-backend global_php84 php artisan migrate --no-interaction --force`.
- **Tests**: `phpunit.xml` uses `pgsql` / database `testing` / host `127.0.0.1` so GitHub Actions and a published port work. Inside `global_php84`, pass `DB_HOST=global_postgres` or PHPUnit targets the container loopback.
- **Schema**: Do not use MySQL-only blueprint calls (`set()`, `->after()`). Do not add `geometry` / `geography` columns unless PostGIS is installed. `enum()` is valid (Laravel emits a check constraint). Prefer `jsonb()` when JSON will be queried.
- **Queries**: On PostgreSQL, `LIKE` is case-sensitive and `ILIKE` is the case-insensitive operator. List search defaults to `ilike` and casts non-text columns to text. Do not use MySQL functions such as `IFNULL`, `GROUP_CONCAT`, `FIND_IN_SET`, or `DATE_FORMAT`.
- **Mandatory Table Prefixes**: Every table name MUST begin with the approved system prefix (`me_` for Master Entity, `tb_` for Transaction Base, `st_` for Setup/Static, `mg_` for Management).
- **Format**: All table names must be written in lowercase `snake_case`.
- **Eloquent Binding**: When creating or modifying Eloquent Models, you MUST explicitly define the `$table` property with the prefixed table name.
- **Validation Rules (FormRequests)**: NEVER hardcode string table names (e.g., `unique:me_users,email`). ALWAYS use the Eloquent Model class natively via `Rule::unique(User::class, 'email')` or `Rule::exists(Role::class, 'id')`.
- Always use proper Eloquent relationship methods with return type hints. Avoid `DB::` facade when `Model::query()` can be used. Prevent N+1 query problems by using eager loading.
- When modifying a column, the migration must include all attributes previously defined on the column. Otherwise, they will be dropped.
- Casts should be set in a `casts()` method on a model rather than the `$casts` property.

### Row history

PostgreSQL writes row history. Do not record it in PHP with model events, observers, or services.

- Each audited table has its own history table, trigger function (`RETURNS trigger`), and `AFTER INSERT OR UPDATE OR DELETE` trigger. A trigger attaches to one table.
- Names follow the source table. `tb_examples` uses history table `tb_example_hist`, record column `example_id`, function `fn_tb_example_hist`, and trigger `trg_tb_examples_hist`. Keep the source prefix (`me_`, `tb_`, `st_`, `mg_`).
- History columns are `id`, the record id (`unsignedBigInteger`, indexed, no foreign key), `action` (`created`, `updated`, or `deleted`), `snapshot` (`jsonb`), `changes` (`jsonb`, nullable), and timestamps. The missing foreign key lets a hard delete insert its history row.
- `INSERT` stores `created` and `to_jsonb(NEW)`. An `UPDATE` that sets `deleted_at` from null stores `deleted`. Any other `UPDATE` stores `updated` and `changes` for keys whose values differ. `DELETE` stores `deleted` and `to_jsonb(OLD)`.
- `to_jsonb(NEW)` includes columns added later. Leave the trigger unchanged when a column is added. Older history rows omit that key. A missing key means the column did not exist yet. A JSON `null` means the column existed and the value was null.
- Remove `password`, `remember_token`, and `transaction_pin` from `snapshot` and `changes`.
- History rows do not store the acting Laravel user. PostgreSQL cannot see `Auth::id()`.
- Audited tables: `tb_examples`, `me_users`, `me_user_details`, `tb_posts`, `st_settings`, `st_roles`, `st_permissions`. Leave cache, jobs, sessions, OAuth tokens, password-reset tokens, and notifications unaudited.
- Put trigger SQL in a migration with `DB::unprepared`. A migration file is in the global namespace; a `use RuntimeException` statement there is a no-op and becomes an error.
- There is no history Eloquent model. Tests assert rows through the query builder.
- A history API pages one record id at a time. The list returns `action`, `created_at`, and `changes`. Load `snapshot` for a single event.

### Functions, procedures, and views

- A trigger runs a function. `CREATE PROCEDURE` cannot be that function. `EXECUTE PROCEDURE` on `CREATE TRIGGER` is the old spelling and still requires a function that returns `trigger`.
- A procedure (`pr_{purpose}`, invoked with `CALL`) is a maintenance job that commits in batches, such as purging old `*_hist` rows. One shared purge procedure covers every history table. Call it from an Artisan command outside `DB::transaction()`, because a procedure cannot `COMMIT` while Laravel already holds a transaction.
- A view is a saved `SELECT` for a stable read shape, such as history list columns or a user joined to user details without secrets. It stores no rows. Writes stay on the base tables so the history triggers run. `CREATE OR REPLACE VIEW` may add columns only at the end.

## 3. Security & Authentication

- **User Enumeration**: Do NOT implement custom validation rules (like `UserExists`) that explicitly reveal whether a user exists during authentication. Rely on `Auth::attempt` to fail cleanly and return a generic `auth.failed` message.
- Use Form Request classes for validation rather than inline validation in controllers. Include validation rules and custom error messages if needed.
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum/Passport, etc.).
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## 4. Code Style & PHP Rules

- Always use curly braces for control structures, even for single lines.
- Use explicit PHP return types and parameter types for all methods and functions.
- Use PHP 8 constructor property promotion in `__construct()`. Do not allow empty `__construct()` methods with zero parameters.
- Prefer PHPDoc blocks with useful array shape type definitions over inline code comments. Keep comments sparse; add them only for very complex logic.
- Enums should use TitleCase keys (e.g., `FavoritePerson`).
- Use ASCII unless an edited file already uses another character set for a clear reason.

## 5. Testing And Verification (CRITICAL PROMPT WORKFLOW)

- **Mandatory Verification**: Before concluding *every* prompt or task, you MUST run the following three commands to guarantee codebase stability:
  1. `vendor/bin/pint --test`
  2. `vendor/bin/phpstan analyse --error-format=github`
  3. `php artisan test` (inside `global_php84`, run `docker exec -w /var/www/yoru-laravel-backend -e DB_HOST=global_postgres global_php84 php artisan test`)
- This application uses **PHPUnit** exclusively. Convert any Pest tests to PHPUnit. Use `php artisan make:test --phpunit {name}`.
- Run the minimal relevant test set using `php artisan test --filter=testName` during development, but always run the full suite at the end.
- **Spatie Permissions in Tests**: Guarded permissions (e.g., `guard_name => 'api'`) require explicit lookup via `Permission::whereIn('name', [...])->get()` when assigning to models in tests to avoid `PermissionDoesNotExist` exceptions.
- Tests should cover all happy paths, failure paths, and edge cases. When creating models for tests, use factories and check for custom states.
- Do not remove tests or test files without explicit approval.
- Format changed PHP files before finalizing edits using `vendor/bin/pint --dirty`.

## 6. Commands

- Install PHP dependencies: `composer install`
- Install frontend dependencies: `npm install`
- Run the app/dev stack: `composer run dev`
- Build frontend assets when needed: `npm run build`
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input.

## 7. Laravel Boost & Ecosystem

- This project includes **Laravel Boost**. Use Boost MCP tools for Laravel-specific work.
- **`search-docs`** (Critically Important): Use this tool to get version-specific documentation before falling back to other approaches. Search docs before making code changes to ensure the correct approach.
- **`list-artisan-commands`**: Use this to double-check available parameters for Artisan commands.
- **`tinker` & `database-query`**: Use these tools for targeted debugging or querying instead of creating temporary scripts.
- **`browser-logs`**: Read frontend logs, errors, and exceptions. Ignore old logs.
- **`get-absolute-url`**: Use this tool to ensure you're using the correct scheme, domain, and port when sharing a project URL.

## 8. Codex Skills

### skill-creator
- Use `/home/filip/.codex/skills/.system/skill-creator/SKILL.md` when creating or updating Codex skills. Read the full `SKILL.md` before acting.
- Keep skill instructions concise and focused on non-obvious procedural knowledge. Validate completed skill folders with the skill validation script.

### skill-installer
- Use `/home/filip/.codex/skills/.system/skill-installer/SKILL.md` when the user asks to list, install, or update Codex skills from curated sources or GitHub.
- The installer scripts require network access, so request escalation when running them in a restricted sandbox. After installing, tell the user to restart Codex.

## 9. Git And Workspace Safety

- The working tree may contain user edits. Inspect `git status --short` before editing.
- Do not revert or overwrite changes you did not make.
- Avoid destructive commands such as `git reset --hard` or broad deletes unless the user explicitly requests them.
- Keep edits scoped to the user request.
