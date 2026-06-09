# Deploying ARKS Messages Platform to SiteGround — step by step

A hand-holding guide for developers doing a fresh deploy. For the condensed
reference, see [`/DEPLOYMENT.md`](../DEPLOYMENT.md). For local dev see
[`SETUP.md`](SETUP.md); for connecting channels see [`CHANNELS.md`](CHANNELS.md).

> **Why these steps are shaped this way.** SiteGround shared/Cloud hosting has
> **no long-running daemons**, **no Redis**, and **unreliable Node tooling**. So:
> queues run from **cron**, cache/session use **Memcached or database**, realtime
> uses **hosted Pusher**, media uses **external S3-compatible storage**, and Vite
> assets are **built in CI / locally and shipped** — never built on the server.

---

## 0. Prerequisites (once)

- A SiteGround plan — **Cloud** recommended for production; **GrowBig** is fine for staging.
- Local: PHP 8.3+, Composer, Node 22+, Git.
- Accounts for external services (can be added later, see [`CHANNELS.md`](CHANNELS.md)):
  - **Pusher** (or Ably) app — realtime.
  - **S3-compatible bucket** — Cloudflare R2 / DigitalOcean Spaces / Wasabi — media.
  - **Meta** app + WhatsApp/Facebook/Instagram assets — channels.

---

## 1. Provision the site

1. In **Site Tools → Websites**, create the site or subdomain (e.g. `app.yourbrand.com`).
2. **Site Tools → Devs → PHP Manager**: set PHP to **8.3** (or higher). Confirm later over SSH with `php -v`.
3. **Site Tools → Security → SSL Manager**: issue a **Let's Encrypt** certificate and enable **HTTPS Enforce**.

## 2. Create the database

1. **Site Tools → Databases → MySQL**: create a database and a user, then grant the user all privileges on it.
2. Note the **database name, username, password, host** (usually `localhost`) — you'll put these in `.env`.

## 3. Enable Memcached

1. **Site Tools → Speed → Caching → Memcached**: enable it.
2. Note the **host** and **port** (typically `127.0.0.1:11211`).

## 4. Enable SSH & add your key

1. **Site Tools → Devs → SSH Keys Manager**: create or import a key pair; add your public key.
2. Test: `ssh -p18765 USER@your-server` (SiteGround uses a non-standard SSH port shown in Site Tools).

## 5. Get the code onto the server

Pick **one** of the two deploy methods.

### Method A — GitHub Actions → rsync (recommended)

Assets are built in CI and shipped, so the server never runs Node.

1. Add repo **secrets** in GitHub (Settings → Secrets → Actions):
   `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_KEY` (private key), `DEPLOY_PATH` (e.g. `/home/USER/www/app.yourbrand.com`).
2. The CI workflow ([`.github/workflows/ci.yml`](../.github/workflows/ci.yml)) already runs Pint, Larastan, tsc, **`npm run build`**, and Pest. Add a deploy job that runs **after** CI passes on your deploy branch — example:

```yaml
  deploy:
    needs: build-and-test
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - uses: actions/setup-node@v4
        with: { node-version: '22', cache: npm }
      - run: composer install --no-dev --optimize-autoloader --no-interaction
      - run: npm ci && npm run build           # produces public/build
      - name: Rsync to SiteGround
        uses: burnett01/rsync-deployments@7.0.1
        with:
          switches: -avzr --delete --exclude='.env' --exclude='storage' --exclude='.git'
          path: ./
          remote_path: ${{ secrets.DEPLOY_PATH }}
          remote_host: ${{ secrets.SSH_HOST }}
          remote_port: ${{ secrets.SSH_PORT }}
          remote_user: ${{ secrets.SSH_USER }}
          remote_key:  ${{ secrets.SSH_KEY }}
      - name: Post-deploy (SSH)
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.SSH_HOST }}
          port: ${{ secrets.SSH_PORT }}
          username: ${{ secrets.SSH_USER }}
          key: ${{ secrets.SSH_KEY }}
          script: |
            cd ${{ secrets.DEPLOY_PATH }}
            php artisan migrate --force
            php artisan optimize        # config+route+view+event cache
            php artisan storage:link || true
```

> Because the build is shipped (`public/build`, `vendor/`), the server only
> migrates and rebuilds caches.

### Method B — Git push + build locally, then rsync

If you don't use Actions:

```bash
# On your machine, from the project root:
composer install --no-dev --optimize-autoloader
npm ci && npm run build
rsync -avzr --delete --exclude='.env' --exclude='storage' --exclude='.git' \
  -e "ssh -p PORT" ./ USER@HOST:/home/USER/www/app.yourbrand.com/
```

> Never run `npm run build` on SiteGround — Node tooling is unreliable there and
> CPU limits will kill it.

## 6. Point the document root at `public/`

The web root must serve Laravel's `public/` directory, with app code outside it.

- **Site Tools → Domains → (your domain) → Document Root**: set it to `.../app.yourbrand.com/public`.
- If you can't change the document root, SSH in and symlink:
  ```bash
  ln -s /home/USER/www/app.yourbrand.com/public /home/USER/www/app.yourbrand.com/public_html
  ```
- A SiteGround-correct `public/.htaccess` ships with Laravel; keep it.

## 7. Create the server `.env`

Never commit `.env`. Create it on the server (`nano .env`) using
[`.env.example`](../.env.example) as the template. Minimum production values:

```ini
APP_NAME="ARKS Messages Platform"
APP_ENV=production
APP_DEBUG=false
APP_KEY=                      # generate in step 8
APP_URL=https://app.yourbrand.com
ASSET_URL=https://app.yourbrand.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_pass

CACHE_STORE=memcached         # fallback: database
MEMCACHED_HOST=127.0.0.1
MEMCACHED_PORT=11211
SESSION_DRIVER=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto
AWS_BUCKET=...
AWS_ENDPOINT=https://<account>.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true

BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=...
PUSHER_APP_KEY=...
PUSHER_APP_SECRET=...
PUSHER_APP_CLUSTER=mt1
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

MAIL_MAILER=smtp              # configure your SMTP for password resets etc.
```

> Channel credentials (WhatsApp/Meta) are **not** required in `.env` — admins add
> them in the app (Settings → Channels). See [`CHANNELS.md`](CHANNELS.md). The
> only Meta env keys you may want here enable the one-click "Connect with
> Facebook" flow (`META_APP_ID`, `META_*_CONFIG_ID`, `VITE_META_APP_ID`).

## 8. First-time app initialization (SSH)

```bash
cd /home/USER/www/app.yourbrand.com
php artisan key:generate          # writes APP_KEY into .env (run ONCE)
php artisan migrate --force
php artisan db:seed --force       # OPTIONAL: demo data; skip for a clean prod
php artisan storage:link
php artisan optimize              # caches config, routes, views, events
```

Verify `max_execution_time` is `0` or `>60` for CLI (queue worker needs it):
```bash
php -i | grep max_execution_time
```
If it's too low, raise it in **Site Tools → Devs → PHP Manager → PHP Variables**.

## 9. The cron job (the SiteGround-specific core)

There are **no daemons**. One cron entry drives the scheduler, which both runs
maintenance and **drains the queue** in short, self-terminating bursts.

1. **Site Tools → Devs → Cron Jobs**, add (every minute):
   ```
   * * * * * php /home/USER/www/app.yourbrand.com/artisan schedule:run >> /dev/null 2>&1
   ```
2. The scheduler ([`routes/console.php`](../routes/console.php)) already runs:
   - `queue:work --stop-when-empty --tries=3 --max-time=50` (`->everyMinute()->withoutOverlapping()`)
   - daily housekeeping (prune batches, clear password resets, prune Sanctum tokens).

No `queue:work` daemon, no Horizon, no Supervisor — those will be killed.

## 10. Smoke test the deploy

```bash
curl -I https://app.yourbrand.com/up           # health → 200
```
Then in a browser:
- `/login` and `/register` render.
- Register a workspace, land in the inbox.
- Queue: trigger something async (e.g. schedule a broadcast) and confirm it
  processes within ~1 minute (the cron tick).
- Realtime: open the inbox in two tabs; a new message streams in (needs Pusher).
- Storage: upload an avatar/file and confirm it lands in your S3 bucket.

## 11. Backups & rollback

- **Backups:** Site Tools → Security → Backups (DB + files). Schedule daily.
- **Rollback:** keep the previous release directory (or a tagged Git ref). To roll
  back: repoint the document root / symlink to the previous release and run
  `php artisan optimize:clear && php artisan optimize`. Restore the DB backup only
  if a migration must be undone.

## 12. Staging → production discipline

- Deploy to a **staging** site first (separate DB + `.env`), run the smoke test,
  then promote the same artifact to production.
- Never point staging and production at the same database.

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| 500 on every page | Missing `APP_KEY` or stale cache | `php artisan key:generate` (once), then `php artisan optimize:clear && php artisan optimize` |
| Blank page / 404 assets | Doc root not on `public/`, or assets not shipped | Fix document root (step 6); ensure `public/build` was rsynced |
| `mix`/`vite` manifest not found | Assets not built/shipped | Build in CI/locally; rsync `public/build` |
| Jobs never run | Cron missing or path wrong | Re-check the cron entry path (step 9); run `php artisan schedule:run` manually to test |
| Wrong scheme in links (http) | Proxy not trusted | Already handled (`trustProxies('*')` + `forceScheme` in prod); ensure `APP_URL=https://…` |
| Webhooks rejected (403) | Signature/verify token mismatch | See [`CHANNELS.md`](CHANNELS.md) — verify token + `META_APP_SECRET` must match Meta |
| Login throttled (429) | Rate limiter (5/min per email+IP) | Expected anti-brute-force; wait a minute |
