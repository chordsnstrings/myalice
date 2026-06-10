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
AI_HTTP_TIMEOUT=20   (optional; per-call LLM timeout, kept under the 50s worker cap)
```
Trust proxies and force HTTPS. **`VITE_*` keys (e.g. `VITE_PUSHER_*`, `VITE_META_APP_ID`)
are baked in at build time** — set them in CI before `npm run build`, not just on the server.
Channel tokens are added in-app (Settings → Channels), not in `.env`.
**AI sales agent (M13):** provider API keys (Anthropic/OpenAI/Gemini/DeepSeek/any
OpenAI-compatible endpoint) are stored **per workspace, encrypted**, via Settings →
AI agent — never in `.env`. The only env knob is `AI_HTTP_TIMEOUT`. The server must
allow **outbound HTTPS** to whichever providers your tenants connect (e.g.
`api.openai.com`, `api.anthropic.com`, `generativelanguage.googleapis.com`, or a
self-hosted base URL); confirm your egress/firewall policy permits them.

## E. Scheduling & queue (the SiteGround-specific core)
10. Add **one cron entry** (Site Tools → Devs → Cron Jobs), every minute:
    ```
    * * * * * php /home/USER/path/artisan schedule:run >> /dev/null 2>&1
    ```
11. The scheduler (`routes/console.php`) already runs:
    `queue:work --stop-when-empty --tries=3 --max-time=50` (`->everyMinute()->withoutOverlapping()`),
    `analytics:snapshot` (`->dailyAt('00:20')`, analytics trend rollups),
    `ai:reengage` (`->hourly()->withoutOverlapping()`, ~23h in-window AI re-engagement),
    `templates:sync` (`->everyThirtyMinutes()`, pull WhatsApp template approval statuses),
    `broadcasts:launch-due` (`->everyMinute()`, launch scheduled broadcasts),
    plus daily housekeeping (prune batches, clear resets, prune Sanctum tokens).
    Broadcast sends ride the same queue as paced, resumable `SendBroadcastChunk`
    jobs — no daemon; wallet cost is reserved at launch and the unsent remainder
    refunded on completion/cancel.
    The **AI reply job** (`GenerateAiReply`) and **re-engagement job**
    (`SendAiReengagement`) ride this same queue — no extra daemon. The reply
    loop's 35s wall-clock guard fits inside `--max-time=50`, and `tries=1` ensures
    a retry can never double-message a customer. `ai:reengage` only does cheap SQL
    filtering before queuing work, so it's safe on shared CPU.
12. Verify PHP `max_execution_time` is `0` or `>60`.

## F. TLS, verify, operate
13. Issue **SSL** (Site Tools → Security → SSL Manager, Let's Encrypt); enforce HTTPS redirect.
    SSL also enables the **PWA** (service worker registers only over HTTPS). The PWA files
    (`public/manifest.webmanifest`, `public/sw.js`, `public/offline.html`, `public/icons/*`)
    are static and ship with the deploy — keep them reachable at the site root.
14. **Smoke test:** `/up` health route; enqueue + drain a job via cron; Pusher round-trip;
    S3 upload; `/manifest.webmanifest` → 200 + installable in Chrome.
15. **Backups & rollback:** DB backups; keep a previous-release directory for instant rollback.
16. **Staging → prod discipline:** deploy to staging, smoke, then promote. Separate DBs/credentials per env.
17. **Turn on the AI sales agent:** all in-app under **Settings → AI agent** (Business+ plan, owner/manager).
    Connect a model, pick an autonomy mode, optionally enable the discount ladder + 23h re-engagement.
    Full plain-language, click-by-click walkthrough: **[`docs/DEPLOY_SITEGROUND.md` §14](docs/DEPLOY_SITEGROUND.md#14-turn-on-the-ai-sales-agent-first-run-setup-in-plain-english)**.

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
