# Setup guide (local development)

How to get ARKS Messages Platform running on your machine. For deployment see
[`DEPLOY_SITEGROUND.md`](DEPLOY_SITEGROUND.md); for connecting channels see
[`CHANNELS.md`](CHANNELS.md).

## Requirements

- **PHP 8.3+** with extensions: `mbstring`, `pdo_sqlite` (local) / `pdo_mysql`, `intl`, `bcmath`, `openssl`.
- **Composer 2**
- **Node 22+** and npm
- Git

## Quick start

```bash
git clone <repo> arks && cd arks

composer install
cp .env.example .env
php artisan key:generate

# Local DB = SQLite (zero config)
touch database/database.sqlite
php artisan migrate --seed

npm install
npm run dev            # Vite dev server (hot reload)
# in a second terminal:
php artisan serve      # http://127.0.0.1:8000
```

Open http://127.0.0.1:8000 and log in with the seeded account:

- **Email:** `demo@myalice.test`
- **Password:** `password`

The demo workspace ("Acme DTC", Business plan) comes pre-seeded with contacts,
conversations, orders, templates, broadcasts, bots, automations and a wallet so
every screen has realistic data.

> One-shot helper: `composer run setup` performs install + key + migrate + npm
> build in one go.

## Environment reference

`.env.example` is the source of truth. Key groups:

| Group | Keys | Notes |
|---|---|---|
| App | `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL` | `APP_NAME="ARKS Messages Platform"` |
| Database | `DB_CONNECTION` (+ `DB_*`) | `sqlite` locally, `mysql` in prod |
| Cache/session/queue | `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, `MEMCACHED_*` | DB locally; Memcached + DB-queue in prod |
| Realtime | `BROADCAST_CONNECTION`, `PUSHER_*`, `VITE_PUSHER_*` | `log` locally; `pusher` in prod (degrades gracefully) |
| Storage | `FILESYSTEM_DISK`, `AWS_*` | `local` locally; S3-compatible in prod |
| Channels | `WHATSAPP_*`, `MESSENGER_*`, `INSTAGRAM_*`, `META_*` | optional — admins connect channels in-app ([`CHANNELS.md`](CHANNELS.md)) |

## Project layout

```
app/
  Channels/         Channel connectors (WhatsApp/Messenger/Instagram) + ChannelManager
  Http/Controllers/ Page + API + Webhook controllers
  Http/Middleware/  Tenancy, locale, security headers, Inertia
  Jobs/             Queued work (inbound/outbound, broadcast send)
  Models/           Eloquent models (tenant-scoped via BelongsToWorkspace)
  Services/         WalletService, AutomationDispatcher, FlowValidator, FlowRuntime, ChannelOnboarder, Plans
  Support/          Tenancy holder, exceptions
resources/js/
  Pages/            Inertia React pages (Auth, Inbox, Contacts, Broadcasts, Settings, …)
  components/        Design-system primitives + shell + Brand
  hooks/ lib/        useTheme/useTranslations/useOnlineStatus, utils, metaSdk
routes/             web.php, api.php (incl. webhooks), console.php (scheduler), channels.php
database/           migrations + seeders
docs/               this folder
```

## Quality gates (run before pushing)

```bash
./vendor/bin/pint                 # format (PSR-12)
./vendor/bin/phpstan analyse      # static analysis — Larastan level 6
npx tsc --noEmit                  # TypeScript type-check
./vendor/bin/pest                 # tests
npm run build                     # production asset build
```

CI ([`.github/workflows/ci.yml`](../.github/workflows/ci.yml)) runs all of these
on every push.

## Tenancy model (important)

Every tenant-owned table has a `workspace_id`. The `BelongsToWorkspace` trait adds
a global scope so all queries are automatically filtered to the **active
workspace**, resolved by the `SetCurrentWorkspace` middleware from the logged-in
user. When you write code that runs **outside** a request (jobs, webhooks), set
the tenant explicitly:

```php
use App\Support\Tenancy;
Tenancy::set($workspace);   // … scoped work …
Tenancy::clear();
```

Webhooks resolve the tenant *before* auth using `withoutGlobalScopes()` to find
the matching `Channel`, then dispatch a job that re-scopes via `Tenancy::set()`.

## Common tasks

```bash
php artisan migrate:fresh --seed   # rebuild DB with demo data
php artisan tinker                  # REPL
php artisan route:list             # inspect routes
php artisan queue:work --stop-when-empty   # drain the queue once (as cron does)
```
