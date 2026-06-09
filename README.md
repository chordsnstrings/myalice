# ARKS Messages Platform

A multi-tenant conversational support, sales and marketing platform for eCommerce/DTC brands —
one inbox that unifies WhatsApp, Instagram, Messenger, Telegram, Line, Viber and the web,
plugged into the brand's store, with automation and AI on top.

Built as a production **Laravel 12 + Inertia + React + TypeScript + Tailwind v4** application,
shaped to deploy on **SiteGround** (cron-driven queues, Memcached, hosted realtime, CI-built assets).

> Source of truth: `MyAlice_Software_Specification.md` (what to build) and
> `MyAlice_UX_UI_Specification.md` (how it looks/behaves). Build plan and loop protocol live in
> `MyAlice_Laravel_SiteGround_Build_Prompt.md`.

## Status

See **`build_state.json`** for the machine-readable backlog and **`PROGRESS.md`** for the running log.
In place and verified today: the foundation, the design system (Part A), the app shell + ⌘K palette,
auth (login/register with workspace creation), the **centerpiece 3-pane Inbox** with the composer
state machine, and the analytics dashboard. Live integrations and the SiteGround deploy are tracked
in `BLOCKERS.md` (credential-gated).

## Quick start

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
npm install && npm run dev      # in another shell: php artisan serve
```

Demo login: **demo@myalice.test** / **password**

## Quality gates

```bash
./vendor/bin/pint                       # format
./vendor/bin/phpstan analyse            # static analysis (Larastan level 6)
npx tsc --noEmit                        # type-check
./vendor/bin/pest                       # tests
```

## Design system

Tokens are defined once in `resources/css/app.css` (CSS-first Tailwind v4 `@theme`) and resolve in
light/dark and mirror under RTL automatically via logical properties. Components compose tokens —
never hardcoded hex. See `DECISIONS.md` (ADR-006) for the design language.

## Deployment

See **`DEPLOYMENT.md`** for the SiteGround runbook (cron-queue, Memcached, S3, Pusher, SSL).

## Documentation

Developer guides live in **[`docs/`](docs/)**:

- **[docs/SETUP.md](docs/SETUP.md)** — local setup, env reference, project layout, tenancy model.
- **[docs/DEPLOY_SITEGROUND.md](docs/DEPLOY_SITEGROUND.md)** — step-by-step SiteGround deploy.
- **[docs/CHANNELS.md](docs/CHANNELS.md)** — connect WhatsApp / Messenger / Instagram / Web widget, and add new channels.
