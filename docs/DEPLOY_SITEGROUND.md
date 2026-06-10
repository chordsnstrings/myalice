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

# Optional — AI sales agent (M13). Per-call LLM timeout in seconds; the only AI
# env knob (provider keys are per-workspace, set in-app — see the note below).
AI_HTTP_TIMEOUT=20

# Optional — Meta "Connect with Facebook" one-click onboarding (else admins paste
# tokens manually in Settings → Channels). See docs/CHANNELS.md.
META_APP_ID=
META_GRAPH_VERSION=v21.0
META_WA_CONFIG_ID=
META_MESSENGER_CONFIG_ID=
META_INSTAGRAM_CONFIG_ID=
VITE_META_APP_ID="${META_APP_ID}"
VITE_META_GRAPH_VERSION="${META_GRAPH_VERSION}"
```

> ⚠️ **`VITE_*` variables are baked into the JS bundle at _build time_, not read on
> the server.** They must be present in the environment that runs `npm run build`
> (your CI job or local machine), then the compiled `public/build` is shipped. Setting
> them only in the server `.env` has **no effect** on the frontend. If you change a
> `VITE_*` value, rebuild and redeploy the assets.

> Channel credentials (WhatsApp/Meta page tokens) are **not** required in `.env` —
> admins add them in the app (Settings → Channels), stored encrypted. See
> [`CHANNELS.md`](CHANNELS.md). Analytics, CSAT surveys and the reports need **no**
> extra configuration — they work from the database out of the box.

> 🤖 **AI sales agent (M13).** LLM provider API keys (Anthropic, OpenAI, Gemini,
> DeepSeek, or any OpenAI-compatible / self-hosted endpoint) are **not** in `.env`
> either — each workspace connects its own under **Settings → AI agent**, stored
> encrypted, verified with a live test call. The feature is plan-gated (Business
> for `ai_agents`; Enterprise for custom/self-hosted endpoints). `AI_HTTP_TIMEOUT`
> is the only related env value. **Egress:** the server makes outbound HTTPS calls
> to whichever providers your tenants connect (`api.openai.com`,
> `api.anthropic.com`, `generativelanguage.googleapis.com`, or a custom base URL).
> SiteGround allows outbound HTTPS by default; if you run a stricter egress policy,
> allow those hosts.

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
   - `queue:work --stop-when-empty --tries=3 --max-time=50` (`->everyMinute()->withoutOverlapping()`) — drains the DB queue (inbound/outbound messages, broadcasts, CSAT surveys, and **AI replies**).
   - `analytics:snapshot` (`->dailyAt('00:20')`) — rolls up daily metrics for the analytics trend lines (the dashboard/reports otherwise compute live + cache).
   - daily housekeeping (prune batches, clear password resets, prune Sanctum tokens).

The AI sales agent needs **no** extra cron or worker: when a customer message
arrives, `GenerateAiReply` is queued (debounced 8s) and drained by the same
`queue:work` tick. Its 35s wall-clock guard fits inside `--max-time=50`, and the
job runs `tries=1` so a retry can never double-message a customer.

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
- PWA: `curl -I https://app.yourbrand.com/manifest.webmanifest` → 200; in Chrome
  DevTools → Application, the manifest + service worker register and an **Install**
  prompt is offered. On mobile, the bottom tab bar + "More" sheet appear.
- AI agent (Business+ plan): **Settings → AI agent**, connect a provider (the
  "Test & connect" step verifies the key with a live call), then use the
  **playground** — a methodology-driven reply confirms outbound egress works.
  For the full click-by-click setup, see **[Section 14](#14-turn-on-the-ai-sales-agent-first-run-setup-in-plain-english)**.

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

## 13. PWA & service worker

The app is an installable PWA. No build step is required beyond the normal asset
build — the PWA files are **static and committed**, so they ship with every deploy:

- `public/manifest.webmanifest`, `public/sw.js`, `public/offline.html`, `public/icons/*`.

Operational notes:

- **HTTPS is mandatory** for the service worker to register (it self-skips on plain
  HTTP). Since step 13/1 enforces SSL, installability works in production; on a
  staging subdomain ensure SSL is issued too.
- **Updates:** navigations are network-first, so users always get fresh HTML; hashed
  `public/build` assets are content-addressed, so new deploys are picked up
  automatically. If you change the SW's own caching logic, bump the `CACHE`
  constant at the top of `public/sw.js` to invalidate old caches.
- **Don't** put `manifest.webmanifest`, `sw.js`, or `/icons` behind auth or a
  rewrite — they must be reachable at the site root.

---

## 14. Turn on the AI sales agent (first-run setup, in plain English)

This section is for the person configuring the app in the browser **after** it's
deployed. No coding — every setting lives in the admin panel. Do the steps in
order; each one builds on the last.

> **Before you start, two requirements:**
> 1. **Plan.** The workspace must be on the **Business** plan or higher. On Premium
>    the "AI agent" menu is hidden. (Enterprise unlocks one extra thing: connecting
>    your *own* self-hosted model — see step 14.2.)
> 2. **Your role.** You must be an **Owner** or **Manager**. Agents can't see these
>    settings.
>
> You'll find everything under **Settings → AI agent** in the left sidebar.

### 14.1 Get an AI model API key (one-time, outside the app)

The AI needs a "brain" — a Large Language Model from a provider. Pick **one** to
start (you can add more later as backups):

- **OpenAI** — sign up at platform.openai.com, create an API key.
- **Anthropic (Claude)** — console.anthropic.com.
- **Google Gemini** — aistudio.google.com.
- **DeepSeek** — platform.deepseek.com (cheapest of the four).

Copy the key somewhere safe for a minute. **You only paste it once** — the app
encrypts it and never shows it again.

### 14.2 Connect the model

1. Go to **Settings → AI agent**.
2. Under **Models**, click **Connect** on the provider you chose.
3. Paste your **API key**. (Optionally change the **Model** name if you want a
   specific version. Leave it as-is if unsure.)
4. Click **Test & connect**. The app sends one tiny test message to the provider
   to make sure the key works *before* saving. If the key is wrong you'll get a
   clear error and nothing is saved.
5. The first model you connect automatically becomes the **Default** (the one the
   AI uses). Connect a second provider if you want an automatic **fallback** for
   when the first is down — use the **star** icon to choose which is default.

> **Enterprise only — your own model.** If you run your own model (e.g. Llama via
> Ollama/vLLM) or a niche provider, choose **Self-hosted** and enter its **Base
> URL** (e.g. `http://10.0.0.5:11434/v1`). This requires the Enterprise plan and
> the server must be able to reach that address.

### 14.3 Choose how independent the AI should be (autonomy mode)

Still on **Settings → AI agent**, in the **Agent** card, pick a **mode**. Start
cautious and increase trust over time:

| Mode | What it does | Good for |
|---|---|---|
| **Off** | The AI never replies. | Pausing everything. |
| **Suggest** | Writes a **draft** reply; a human reviews and clicks Send in the inbox. | Week 1 — watch what it writes. |
| **Auto-reply** | Replies on its own. Can capture leads and hand off to a human. | Most teams. |
| **Autopilot** | Also creates **orders**, sends **payment links**, and applies **discounts** by itself. | When you trust it to close deals. |

**Recommended path:** start in **Suggest** for a few days, read the drafts, then
move to **Auto-reply**, and finally **Autopilot** once you're happy.

### 14.4 Tell the AI about your business

In the same card:

- **Goal** — what success means: **Sale** (drive to a completed order), **Lead**
  (collect and qualify contacts), or **Support** (answer questions).
- **Tone** — Friendly / Professional / Playful / Formal. (Friendly converts best
  for most DTC brands.)
- **Sales methodology** — how it sells: **Consultative** (asks questions first),
  **Direct closer** (leads with the offer), or **Lead capture** (focus on getting
  contact details).
- **Business profile** — a short paragraph: what you sell, who you serve, shipping
  and returns, what makes you different. The more you write, the smarter it sounds.
- **Custom instructions** — anything specific: promos to mention, phrases to avoid.

Your live product catalog (prices and stock) is added automatically — the AI can
**only** quote real prices from it and can never invent a discount.

### 14.5 Set the safety guardrails

In the **Guardrails** box:

- **Engage new conversations** — on = it greets brand-new chats automatically.
- **Max messages before handoff** — after this many back-and-forths it passes the
  chat to a human instead of looping forever. (12 is a sensible default.)
- **Auto-order cap** — the biggest order total it's allowed to create on its own;
  anything larger goes to a human. **Leave blank to never auto-create orders.**
- **Handoff keywords** — words that instantly route to a human (e.g. `refund`,
  `human`, `complaint`).

### 14.6 (Optional) Turn on high-closure tactics

In the **Closing tactics** card, tick the persuasion techniques you're comfortable
with. Each is kept **honest** by the system:

- **FOMO / Urgency** — only tied to a *real* deadline or low stock.
- **Scarcity** — only when catalog stock is genuinely low.
- **Social proof, Anchoring, Assumptive close** — standard sales moves.
- **Authority** ("I checked with my manager and secured a one-time approval…") —
  the AI may only use this line **after** it has actually been granted a real
  discount (see next step), so it never lies.

### 14.7 (Optional) Set up the discount ladder

This lets the AI offer **pre-approved** discounts — but only when a customer is
clearly interested yet hesitating, and **one layer at a time** (never your best
deal first). You stay in full control of the limits.

1. In the **Discount strategy** card, turn it **on**.
2. Add **layers** in the order they should be offered, e.g.:
   1. Free shipping
   2. 5% off
   3. 10% off
3. Set the **hard maximum discount %** — an absolute cap the AI can never exceed,
   even if it misbehaves.
4. **Service discount %** — a separate rate for service items (see 14.8).
5. **Minimum order value** — orders below this don't qualify.
6. **Offer valid for (minutes)** — makes the urgency real; the discount actually
   expires.
7. **One discount per customer** — stops people farming discounts across chats.

> To have the AI **apply** a discount and create the discounted order by itself,
> it must be in **Autopilot** mode. In Auto-reply it can *mention* a discount but a
> human finalises the order.

### 14.8 (Optional) Mark which catalog items are services

If you sell services and want the **service discount %** to apply to them:

1. Go to **Commerce → Products**.
2. On each item, use the **Product / Service** dropdown to label it.

### 14.9 (Optional) Turn on 23-hour re-engagement

This sends **one** friendly, personalised follow-up to people who asked a real
question and then went quiet — timed just before the 24-hour WhatsApp window
closes, so it's always allowed.

1. In the **Closing tactics** card, turn on **Auto re-engage at ~23h**.
2. Set **Min. customer messages to qualify** (1 is fine).

> This relies on the **cron job from step 9**. If the cron isn't running, no
> follow-ups are sent. It also needs a connected model (step 14.2).

### 14.10 Try it before customers do (Playground)

Scroll to the **Playground** on the same page. Type a message as if you were a
customer ("do you have blue mugs?", then "hmm, a bit pricey"). The AI replies live
and shows little chips for any actions it would take (like `offer_discount` or
`create_order`). **Nothing here is saved or sent to a real customer** — it's a safe
sandbox. Tune your settings until the replies feel right.

### 14.11 Go live and watch it work

Once you switch to **Auto-reply** or **Autopilot**, real conversations are handled
automatically. To monitor:

- **Inbox** — chats the AI is handling show an **"AI handling"** badge; if it hands
  a chat to a human you'll see **"AI handed off"**. In **Suggest** mode, drafts
  appear as an amber card with **Send** / **Dismiss** buttons.
- **The AI always backs off the moment a human teammate replies** — so your agents
  can jump into any chat at any time and the AI won't fight them.
- **Dashboard** — the **AI assistant** card shows conversations engaged, orders
  created, close rate, discounts offered, discount spend, and how many re-engaged
  customers came back and bought.

That's it — the AI is live. Revisit **Settings → AI agent** any time to adjust the
tone, raise autonomy, or change the discount ladder.

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
| Realtime/“Connect with Facebook” silently off | `VITE_*` not set at build time | Set `VITE_PUSHER_*` / `VITE_META_APP_ID` in CI, rebuild, redeploy `public/build` |
| “Install app” never appears | Not HTTPS, or already installed, or manifest 404 | Ensure SSL + `/manifest.webmanifest` reachable; check DevTools → Application |
| Stale UI after deploy | Old service-worker cache | Reload; if persistent, bump `CACHE` in `public/sw.js` and redeploy |
| Dashboard trends flat | `analytics:snapshot` never ran | Confirm the cron tick; run `php artisan analytics:snapshot` once to backfill |
| AI "Test & connect" fails with a valid key | Outbound HTTPS to the provider blocked | Allow egress to the provider host (e.g. `api.openai.com`); for self-hosted, check the base URL is reachable from the server |
| AI never replies to inbound messages | Plan/mode/cron | Plan must include `ai_agents` (Business+); agent mode ≠ Off in Settings → AI agent; confirm the cron tick drains `GenerateAiReply` |
| AI menu missing in Settings | Plan or role | `ai_agents` is Business+; the page needs the **manage-bots** capability (owner/manager) |
