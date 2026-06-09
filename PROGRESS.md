# Build Progress Log

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
