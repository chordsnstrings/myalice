# Production Readiness Audit

**Date:** 2026-06-09 · **Scope:** ARKS Messages Platform (this branch) · **Method:** manual review + automated gates.

Verification baseline at audit time: **Pint clean · Larastan L6 (0 errors) · tsc clean · Pest 84 passing / 396 assertions · Vite build ok · boots under cached config/route/view/event**.

## Findings & resolutions

| # | Severity | Area | Finding | Resolution |
|---|----------|------|---------|------------|
| F1 | High | Auth | No rate limiting on `/login`, `/register`, `/forgot-password`, `/reset-password` — brute-force exposure | Added an `auth` rate limiter (5/min per **email+IP**, 20/min per IP) on the guest route group. Test: 6th failed login → 429. |
| F2 | High | Transport | Behind SiteGround's proxy, scheme/client-IP weren't trusted (breaks HTTPS detection + IP-keyed limits) | `trustProxies(at: '*')` for X-Forwarded-* ; `URL::forceScheme('https')` in production. |
| F3 | Med | Performance | N+1 in Contacts list (one `count` query per row) | Single grouped `count(*) group by contact_id` query. |
| F4 | Med | Headers | No security response headers | `SecurityHeaders` middleware: `X-Content-Type-Options`, `X-Frame-Options=SAMEORIGIN`, `Referrer-Policy`, HSTS on HTTPS. |
| F5 | Med | Deploy | Needed proof the app boots under the production cache suite | Verified `config|route|view|event:cache` then live boot (health 200, auth 200, guarded routes redirect). |

## Verified good (no change needed)

- **Secrets:** `.env` is git-ignored and untracked; `.env.example` holds placeholders only. No `dd()/dump()/ray()/var_dump` in app code.
- **Credentials at rest:** channel tokens stored via `encrypted:array` cast — confirmed the raw DB column is ciphertext and round-trips correctly.
- **Multi-tenancy:** global `WorkspaceScope` on all tenant models; route-model binding respects it (cross-tenant ids → 404); webhooks resolve tenant pre-auth with `withoutGlobalScopes` then re-scope. Isolation proven by tests.
- **AuthZ:** RBAC gates (`manage-billing/team/channels/api`) and plan gates (`use-automation`) enforced server-side; sensitive settings + channel-onboarding routes gated.
- **Webhooks:** `X-Hub-Signature-256` HMAC verification (timing-safe), verify-token handshake, idempotent dedupe, enqueue-only — never inline (SiteGround CPU).
- **API:** Sanctum auth + per-workspace rate limit; workspace-scoped resources.
- **Money integrity:** wallet debits atomic (`lockForUpdate`), never negative; broadcasts blocked/paused, not half-sent.
- **Input validation:** present on every mutating endpoint (auth, locale, broadcast send, channel connect, CSV import incl. mime/size).
- **SiteGround constraints:** no daemon (cron-driven `queue:work --stop-when-empty`), no Redis, no server-side asset build, queued heavy work.

## Residual risks / recommendations (tracked, not blocking)

- **CSP:** a full Content-Security-Policy is not yet set (only baseline headers) — add once asset/CDN origins are pinned.
- **Mass assignment:** `User.workspace_role` is fillable; safe today (no endpoint mass-assigns request input to it) but keep that invariant.
- **2FA / login lockout UX** (cooldown screen) — deferred (PUNCHLIST), throttle now covers brute force.
- **Credential-gated launch items** (live WhatsApp/Meta, Stripe, Shopify, SiteGround, Pusher/S3) remain in `BLOCKERS.md`.
- **Engine gaps** behind some UIs (commerce sync, analytics aggregation, billing charges, automation event sources, embeddable widget.js) — `PUNCHLIST.md`.

## Sign-off

The application is **secure and structurally production-ready** for deployment once the `BLOCKERS.md` credentials are supplied and the `DEPLOYMENT.md` runbook is followed. No High findings remain open.
