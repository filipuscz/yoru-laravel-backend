# API Request Flow & Endpoints

This document outlines how to call all of the endpoints we have implemented during this session, including the required payloads and URLs. 

All endpoints (except login, register, and public language endpoints) require the `Authorization: Bearer <token>` header AND an `X-API-TOKEN: <api_token>` header (enforced by the `CheckApiToken` middleware).
Base URL: `http://localhost:8000/api/v1`

---

## Global Query Parameters

All list endpoints (`GET /user`, `GET /role`, `GET /notification`, etc.) driven by the `BaseService` support the following global parameters:

- `limit`: The number of records to return per page (default: 10 or 15). Use `-1` to fetch all records (disables pagination).
- `page`: The offset page number to retrieve.
- `use_cursor`: Set to `true` to use highly-performant cursor pagination instead of standard offset pagination.
- `cursor`: The encoded cursor string provided from the previous request's `next_cursor` to fetch the next batch.

**Example: Requesting the first page with cursor pagination**
```http
GET /user?use_cursor=true&limit=15
Authorization: Bearer <token>
```

**Example: Next Page Request (using the cursor from previous response)**
```http
GET /user?use_cursor=true&cursor=eyJpZCI6MTUsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0=&limit=15
Authorization: Bearer <token>
```

---

## 1. Authentication & Registration
When a user registers, if the `send_welcome_email` setting is `1` in the database, a welcome email will automatically be queued.

**Register User**
```http
POST /auth/register
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Login User**
Returns a Bearer token that you must use for subsequent requests.
```http
POST /auth/login
Content-Type: application/json

{
    "username": "john@example.com", // can be email or username
    "password": "password123"
}
```

**Generate API Token**
Requires the Bearer token from login. Returns an API Token that must be passed in the `X-API-TOKEN` header for all other endpoints.
```http
GET /auth/api-token
Authorization: Bearer <token>
```

**Get Current Authenticated User (Me)**
Requires both Bearer token and X-API-TOKEN.
```http
GET /auth/me
Authorization: Bearer <token>
X-API-TOKEN: <api_token>
```

**Logout User**
```http
POST /auth/logout
Authorization: Bearer <token>
X-API-TOKEN: <api_token>
```

---

## 2. Settings (Dynamic Database Config)
Manage global application settings natively. These directly override Laravel configs like SMTP.

**List Settings**
```http
GET /setting
Authorization: Bearer <token>
```

**Bulk Update Settings**
```http
PUT /setting/bulk_action/update
Authorization: Bearer <token>
Content-Type: application/json

{
    "settings": {
        "send_welcome_email": "1",
        "smtp_host": "smtp.mailtrap.io",
        "smtp_port": "2525",
        "smtp_username": "your_username",
        "smtp_password": "your_password",
        "smtp_encryption": "tls"
    }
}
```

---

## 3. Notifications
Manage the authenticated user's database notifications.

**List User's Notifications**
```http
GET /notification?limit=15
Authorization: Bearer <token>
```

**Mark Specific Notification as Read**
```http
PUT /notification/{notification_id}/mark-as-read
Authorization: Bearer <token>
```

**Mark All Notifications as Read**
```http
PUT /notification/mark-all-as-read
Authorization: Bearer <token>
```

**Delete a Notification**
```http
DELETE /notification/{notification_id}
Authorization: Bearer <token>
```

**Internal PHP Usage (How to trigger a notification to a target user)**
To actually create and send a notification to a user from within your Laravel backend code, use the `notify()` method natively provided by Laravel.

1. First, create your notification class: `php artisan make:notification SystemAlert`
2. Then, trigger it on the target user model:
```php
$user = User::find($targetUserId);

$user->notify(new \App\Notifications\SystemAlert([
    'title' => 'New Message',
    'message' => 'You have a new message from Admin.',
    'url' => '/messages/123'
]));
```

---

## 4. Users CRUD
Full management of users, their profile details, and their roles.

**List Users**
```http
GET /user?limit=10&page=1
Authorization: Bearer <token>
```

**Create User**
*(Automatically hashes password and syncs roles & profile details)*
```http
POST /user
Authorization: Bearer <token>
Content-Type: application/json

{
    "name": "Jane Smith",
    "email": "jane@example.com",
    "password": "securepassword",
    "roles": ["admin"],
    "bio": "I am an administrator",
    "phone": "+123456789"
}
```

**Update User**
```http
PUT /user/{user_id}
Authorization: Bearer <token>
Content-Type: application/json

{
    "name": "Jane Updated",
    "roles": ["user"],
    "bio": "Updated bio"
}
```

**Delete User**
```http
DELETE /user/{user_id}
Authorization: Bearer <token>
```

---

## 5. Roles & Permissions CRUD
Role-Based Access Control (RBAC) via Spatie.

**List Roles**
```http
GET /role
Authorization: Bearer <token>
```

**Create Role (and assign permissions)**
```http
POST /role
Authorization: Bearer <token>
Content-Type: application/json

{
    "name": "manager",
    "permissions": ["view users", "update users"]
}
```

**List Permissions**
```http
GET /permission
Authorization: Bearer <token>
```
```

---

## 6. Localization (Translations API)
Expose the backend Laravel localization files (e.g. `messages.php`, `validation.php`) to frontend SPA/Mobile applications.

**Get Translations by Locale**
```http
GET /language/{locale}
```
*Note: This endpoint is accessible without an authentication token so the frontend can load translations before login.*

---

## 7. MCP Server

This template includes an authenticated, remote [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) server. It starts intentionally empty: it establishes secure OAuth access but does not expose application data or actions until you add tools.

### 7.1 Configure a deployment

Set these values in `.env` before allowing an MCP client to connect:

```dotenv
# Public HTTPS URL of this Laravel application. Do not use an internal Docker URL.
MCP_AUTHORIZATION_SERVER=https://api.example.com

# Comma-separated, exact URL prefixes that MCP clients may use for OAuth callbacks.
MCP_REDIRECT_DOMAINS=https://claude.example.com,https://cursor.example.com

# Optional private callback URI schemes for approved desktop clients only.
MCP_CUSTOM_SCHEMES=cursor,vscode
```

`MCP_REDIRECT_DOMAINS` is deny-by-default. Never use `*` or add untrusted callback domains: dynamic client registration is available at `POST /oauth/register` and validates every requested redirect URI against this allowlist.

Ensure Passport keys exist in each environment:

```bash
php artisan passport:keys --force --no-interaction
php artisan config:clear --no-interaction
```

### 7.2 Start the application and verify the endpoints

Run the application using the normal project environment, then replace `https://api.example.com` below with its public URL:

```bash
curl https://api.example.com/.well-known/oauth-protected-resource
curl https://api.example.com/.well-known/oauth-authorization-server
```

The server endpoint is `POST /mcp/v1`. It is not under `/api/v1` and does not use `X-API-TOKEN`; MCP clients authenticate with Passport OAuth instead. An unauthenticated request receives a `WWW-Authenticate` header pointing the client to the protected-resource metadata.

### 7.3 Connect an MCP client

Add a remote MCP server in a client that supports OAuth 2.1 and dynamic client registration. Use this URL:

```text
https://api.example.com/mcp/v1
```

The client discovers the OAuth endpoints from the `WWW-Authenticate` response, registers its callback URL, and opens the browser authorization flow. This backend template provides the Passport consent screen at `/oauth/authorize`, but deliberately does not provide a login page or frontend UI.

Before enabling OAuth, the consuming application must provide session authentication for the `web` guard and configure its unauthenticated redirect to its own login or identity-provider flow. A Passport API bearer token does not create that browser session. Once the user is authenticated by the application, Passport displays the consent screen and issues the MCP OAuth authorization code.

For local development, use a publicly reachable HTTPS tunnel and add that tunnel's URL to `MCP_AUTHORIZATION_SERVER`. Add only the specific client callback origins or desktop schemes you need. `http://localhost` is accepted only when it is explicitly included in `MCP_REDIRECT_DOMAINS`.

### 7.4 Add the first tool

Create a tool and register it with the server:

```bash
php artisan make:mcp-tool ListUsers --no-interaction
```

In `app/Mcp/Tools/ListUsers.php`, define a narrow input schema, validate the arguments, authorize the authenticated MCP user, and call an existing service rather than a controller. For example:

```php
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lists users the authenticated user is allowed to view.')]
class ListUsers extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Maximum number of users to return'),
        ];
    }

    public function handle(Request $request): Response
    {
        abort_unless($request->user()?->can('view users'), 403);
        $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:100']]);

        // Resolve the existing application service and return a minimal response.
        return Response::text('Implement the authorized service query here.');
    }
}
```

Register the tool in `app/Mcp/Servers/TemplateServer.php`:

```php
protected array $tools = [
    \App\Mcp\Tools\ListUsers::class,
];
```

Do not expose destructive tools by default. Every tool must validate input, perform authorization with `$request->user()`, return only the data required by the request, and delegate reusable data operations to an application service. Add a focused feature test for every tool before connecting it to a production client.

### 7.5 Test and inspect

Use the MCP Inspector while developing a server:

```bash
php artisan mcp:inspector /mcp/v1 --no-interaction
```

Run the MCP feature tests after changing MCP routes, configuration, authentication, or tools:

```bash
php artisan test --filter=McpTest
```

---

## 8. Quality Assurance & CI Checks

To ensure the backend API remains stable, performant, and clean, all developers (and AI agents) must run the following sequence of checks before finalizing any feature or prompt:

**1. Code Formatting (Pint)**
Ensure the code perfectly follows Laravel's opinionated formatting.
```bash
vendor/bin/pint --test
```

**2. Static Analysis (PHPStan / Larastan)**
Catch type errors, undefined variables, and logic bugs without executing the code.
```bash
vendor/bin/phpstan analyse --error-format=github
```

**3. Feature & Unit Testing (PHPUnit)**
Ensure no business logic or API endpoints are broken.
```bash
php artisan test
```
