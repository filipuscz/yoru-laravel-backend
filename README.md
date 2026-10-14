# Yoru Laravel Backend

Laravel API template for new Yoru backends. Controllers stay thin, services own persistence, and every JSON response uses the same envelope.

## Stack

- PHP 8.4, Laravel 13
- Laravel Passport for bearer tokens
- Spatie Permission for roles and permissions on the `api` guard
- PostgreSQL
- PHPUnit, Pint, and PHPStan

## What you get

- Versioned routes under `/api/v1`
- Register, login, logout, and the current user
- Users, examples, roles, permissions, settings, and notifications
- Shared list queries: search, filters, paging, and cursor paging
- Table prefixes: `me_` master, `tb_` transaction, `st_` setup, `mg_` management

`Example` is the module to copy when adding a resource. Endpoint payloads and headers are in [DEVELOPER.md](DEVELOPER.md). Agent and schema rules are in [AGENTS.md](AGENTS.md).

## Setup

PostgreSQL runs in Docker as `global_postgres` from `../docker-stack`.

```bash
cd ../docker-stack
docker compose --profile postgres up -d
```

Copy `.env.example` to `.env`, set `APP_KEY` and `API_SECRET_KEY`, then migrate. Local database defaults:

| Setting | Value |
| --- | --- |
| Host | `global_postgres` |
| Port | `5432` |
| Database | `yorupus_be_template` |
| Username | `root` |
| Password | `123` |

`global_php84` needs `pdo_pgsql`. When the host PHP build does not have it, migrate from that container:

```bash
docker exec -w /var/www/yoru-laravel-backend global_php84 php artisan migrate --no-interaction --force
docker exec -w /var/www/yoru-laravel-backend global_php84 php artisan db:seed --no-interaction --force
```

Seeded roles are `admin` and `user`, both on the `api` guard.

## Calling the API

Login and register are public. Other routes need `Authorization: Bearer <token>` and `X-API-TOKEN`. Get the second header from `GET /api/v1/auth/api-token` after login.

```http
POST /api/v1/auth/login
Content-Type: application/json

{
    "username": "jane@example.com",
    "password": "secret123"
}
```

## Tests

PHPUnit uses the `testing` database. From the PHP container:

```bash
docker exec -w /var/www/yoru-laravel-backend -e DB_HOST=global_postgres global_php84 php artisan test
```

Before finishing a change, also run:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --error-format=github
```

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
