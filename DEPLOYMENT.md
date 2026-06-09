# ARKS Messages Platform — SiteGround Deployment Runbook

The architecture is shaped by verified SiteGround constraints (Build Prompt §3):
**no daemons, cron-driven queues, Memcached not Redis, hosted realtime, CI-built assets, external media.**

## A. Provision
1. Plan: **Cloud** for production (CPU headroom); GrowBig acceptable for staging/MVP.
2. Create the site/subdomain in Site Tools; set the **PHP version** to 8.3+ (confirm `php -v` over SSH).

## B. Access & services
3. Enable **SSH** (Site Tools → Devs → SSH Keys Manager); add your public key.
4. Create a **MySQL** database + user; grant privileges.
5. Enable **Memcached** (Site Tools → Speed → Caching); note host/port for `MEMCACHED_*`.
6. Provision off-platform: **S3-compatible storage** (R2/Spaces/Wasabi) and a **Pusher/Ably** app. Collect credentials.

## C. Code & document root
7. Deploy via **Git push** to a bare repo with a `post-receive` hook, **or** GitHub Actions → SSH/rsync.
   Server deploy step:
   ```
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan config:cache route:cache view:cache event:cache
   ```
8. Point the domain/subdomain **document root to `…/public`** (keep app code outside the web root).
9. **Assets are built in CI** (`npm ci && npm run build`) and shipped (`public/build`). **Never** `npm run build` on the server.

## D. Configuration (`.env` on server, never committed)
```
APP_ENV=production
APP_DEBUG=false
APP_KEY=<php artisan key:generate --show>
APP_URL=https://app.yourbrand.com
ASSET_URL=https://app.yourbrand.com
DB_CONNECTION=mysql  DB_* ...
CACHE_STORE=memcached  (fallback: database)   MEMCACHED_HOST/PORT
SESSION_DRIVER=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=s3   AWS_* + AWS_ENDPOINT
BROADCAST_CONNECTION=pusher   PUSHER_*
```
Trust proxies and force HTTPS.

## E. Scheduling & queue (the SiteGround-specific core)
10. Add **one cron entry** (Site Tools → Devs → Cron Jobs), every minute:
    ```
    * * * * * php /home/USER/path/artisan schedule:run >> /dev/null 2>&1
    ```
11. The scheduler (`routes/console.php`) already runs:
    `queue:work --stop-when-empty --tries=3 --max-time=50` (`->everyMinute()->withoutOverlapping()`)
    plus daily housekeeping (prune batches, clear resets, prune Sanctum tokens).
12. Verify PHP `max_execution_time` is `0` or `>60`.

## F. TLS, verify, operate
13. Issue **SSL** (Site Tools → Security → SSL Manager, Let's Encrypt); enforce HTTPS redirect.
14. **Smoke test:** `/up` health route; enqueue + drain a job via cron; Pusher round-trip; S3 upload.
15. **Backups & rollback:** DB backups; keep a previous-release directory for instant rollback.
16. **Staging → prod discipline:** deploy to staging, smoke, then promote. Separate DBs/credentials per env.

## Local development
```
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
npm install && npm run build      # or: npm run dev
php artisan serve
```
Demo login after seeding: **demo@myalice.test** / **password**.

### Quality gates (run before every commit)
```
./vendor/bin/pint
./vendor/bin/phpstan analyse --no-progress
npx tsc --noEmit
./vendor/bin/pest
```
