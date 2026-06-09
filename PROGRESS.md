# Build Progress Log

## 2026-06-10 — Granular team-performance analytics (P11)

- **Data capture:** conversation lifecycle timestamps (`first_response_at`, `assigned_at`, `resolved_at`, `awaiting_csat_at`) via `MessageObserver`/`ConversationObserver`; new `csat_ratings` and `metric_snapshots` tables; `csat_enabled` workspace toggle.
- **CSAT surveys (send + capture):** `SendCsatSurvey` job dispatched on resolve; numeric 1–5 reply captured in `ProcessInboundMessage` → `CsatRating` (no reopen); non-numeric replies reopen as normal.
- **AnalyticsService (cached, hybrid):** real KPIs, daily series, agent leaderboard, agent drill-down (response/resolution distribution, active-hours proxy, CSAT comments), sales/conversion, CSAT report, channel breakdown, response distribution. `analytics:snapshot` command + nightly schedule fill `metric_snapshots` (scale path).
- **Reports + filters:** rewrote the Dashboard to real data; added `/reports/{agents,agents/{id},sales,csat}` (manager-gated) with a shared **functional** date-range + channel + team `FilterBar`, dependency-free chart components, REPORTS nav group, and CSV export.

Verification: Pint clean · Larastan L6 0 errors · tsc clean · **Pest 111 passing / 494 assertions** · Vite build ok. New tests: AnalyticsService correctness + filters, lifecycle observers, CSAT survey send/capture, reports (manager gate + tenant isolation + CSV), snapshot idempotency/isolation.


## 2026-06-09 — Backend engines (money + automation + import)

- **Wallet + broadcast engine (P8):** `WalletService` (atomic debit/credit, no-negative, audit ledger); `SendBroadcast` debits up front and pauses on mid-send drain (C-03); server-side wallet gate on broadcast create.
- **CSV import (P5):** `ImportContacts` action — validate, dedupe-on-phone (merge), invalid flagging, summary; upload endpoint + wired Import button.
- **Automation dispatcher (P9):** guards for active/quiet-hours(cross-midnight)/frequency-cap(C-22)/wallet; wallet-empty = explicit skip+log+notify; `automation_sends` for capping.

Verification: Pint clean · Larastan L6 0 errors · tsc clean · **Pest 54 passing, 318 assertions** · build ok.


## 2026-06-09 — Breadth to all phases (P3, P12, P13, P14, RBAC, realtime, hardening)

- **Channels (P3):** ChannelConnector interface + WhatsApp connector (Graph API, stub mode), idempotent webhook (verify + dedupe + enqueue), queued inbound/outbound jobs.
- **Developer API (P12):** Sanctum REST endpoints (contacts/conversations), API resources, per-workspace rate limiter.
- **Entry points (P13):** web chat widget config with live preview + install snippet; QR/click-to-chat link generator (real QR + attribution).
- **RBAC (P1 G1.4):** Owner/Manager/Agent/Developer roles + permissions seeder; capability gates enforced on routes; capabilities shared to the UI so the settings nav hides denied items (C-17).
- **i18n + RTL (P14):** EN/AR/ES/PT translation files, SetLocale middleware, locale switcher, `useTranslations()` hook, Arabic RTL mirroring.
- **Real-time (P4 G4.5):** Echo client (graceful degrade), MessageCreated private-channel broadcast, inbox subscription; offline banner + online-status hook (C-16).
- **Compliance (P15):** `COMPLIANCE.md` maps Part C (C-01…C-24) and Part D to where each is handled.

Verification: Pint clean · Larastan L6 0 errors · tsc clean · **Pest 44 passing, 287 assertions** · Vite build ok.


## 2026-06-09 — Full feature breadth (P1, P4–P11, P14)

### What was built
- **Data layer (§6):** 16 tenant-scoped models + migrations — Channel, Conversation, Message, Tag, QuickReply, BusinessHour, StoreConnection, Product, Order, MessageTemplate, Audience, Broadcast, Chatbot, AutomationRule, Subscription, WalletTransaction. Rich demo seeder populating a full workspace.
- **Inbox** now data-driven from tenant-scoped Conversation/Message records.
- **Auth (P1):** forgot-password + reset (real password broker, non-enumerating) and the **onboarding wizard** (B1.5).
- **CRM (P5/B4):** Contacts list (search, bulk-select bar) + Contact profile (tabs, orders, GDPR delete confirm).
- **Commerce (P6/B8):** Orders table + Product catalog (out-of-stock handling).
- **Broadcasts (P8/B6):** list, **guided composer with the wallet pre-flight gate** (recipient/exclusion breakdown, cost-before-commit, insufficient-funds block, C-03), and the template manager (approval/quality/rejection states, C-09).
- **Automations (P9/B7):** rule list with toggles + recipe gallery.
- **Chatbots (P7/B5):** bot list + a visual **flow-builder canvas** (palette, nodes, connectors, inspector, Test/Publish).
- **Settings (P14/B11):** all 9 sub-pages on a shared sub-nav — workspace, team & roles, channels, business hours, quick replies & tags, billing (plan cards), wallet (ledger + auto-recharge), developer (API keys + webhooks), profile & notifications.
- **Primitives added:** Table, Tabs, Switch, Page container, SettingsLayout.

### Verification (all green)
- `pint` clean · `phpstan` L6 0 errors · `tsc` clean · `pest` **30 passing, 227 assertions** (incl. a parameterized render test for all 20 authed pages + HTTP-layer tenant isolation on /contacts).
- `npm run build` succeeds; `migrate:fresh --seed` clean.


## 2026-06-09 — Foundation, design system, auth & Inbox UI

### What was built
- **Project scaffold:** Laravel 12 + Inertia v2 + React 19 + TypeScript + Tailwind v4 (Vite). Pint, Larastan (level 6), Pest, Sanctum, Fortify, spatie/permission installed.
- **Tenancy core (G0.3):** `Workspace` model, `BelongsToWorkspace` trait (global scope + auto-fill), `SetCurrentWorkspace` middleware, `Tenancy` holder. First tenant-scoped model `Contact` (M8).
- **Design system (G0.4, A3–A8):** CSS-first token system — neutral ramp, single teal accent, four semantics, channel dots — resolving in light/dark; Inter type scale; border-led surfaces; skeleton/fade/pulse motion utilities; reduced-motion + custom scrollbars.
- **Component library (G2.1/A9):** Button, Input/Textarea/Field, Card, Badge, Avatar+ChannelDot, Toast (provider + hook), Tooltip, Modal + scale-aware ConfirmModal, Skeleton, three Empty states, ErrorState.
- **App shell (G2.3/B2):** nav rail (active accent bar, badges, locked-feature lock), top bar (⌘K search trigger, wallet chip with low-balance warning, notifications, theme toggle, user menu), and a full **⌘K command palette** (keyboard-operable fuzzy nav + actions).
- **Auth (G1.1/G1.2, B1):** split-screen AuthLayout; Login (show/hide password, remember, generic non-enumerating error C-24); Register (live password strength, atomic workspace+owner creation).
- **Inbox centerpiece (P4/B3):** 3-pane layout — conversation list (filters, search, unread/SLA/channel cues), thread (in/out bubbles, delivery ticks, bot/system distinction, animate-in), **composer state machine** (free-text / template-required when 24h window closed C-01 / resolved), optimistic send with reconcile, and the customer context pane (identity, tags, orders, products, notes).
- **Dashboard (P11/B10.1):** KPI grid with SVG sparklines, revenue bar chart, recovered-cart card, agent leaderboard table — tabular numerals throughout.
- **Polished placeholders:** every nav destination renders a first-use empty (FirstUseEmpty) so navigation never dead-ends (C-17 spirit).
- **SiteGround plumbing (§3):** cron-driven queue scheduler in `routes/console.php`; `.env.example` with Memcached/Pusher/S3/WhatsApp/Shopify placeholders; CI workflow building assets off-server.

### Verification (all green)
- `pint` — clean (1 file auto-fixed: bootstrap/app.php).
- `phpstan` (Larastan level 6) — 0 errors across app/database/routes.
- `tsc --noEmit` — 0 errors.
- `pest` — 7 passed, 36 assertions (auth flow, C-24 non-enumeration, guest redirect, tenant isolation + auto-fill, authenticated Inbox render).
- `npm run build` — succeeds; migrations run clean forward on a fresh DB.

### Notes / next
- Live integrations (WhatsApp, store, realtime, billing) and the SiteGround staging deploy are **blocked on credentials** — see `BLOCKERS.md`. Stubs/feature-flags keep everything else buildable.
- Next goal: **G1.2** — forgot/reset password, email verification, then onboarding wizard (B1.5).
