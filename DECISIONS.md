# Architecture Decision Record (ADR)

Decisions are append-only. Each records the choice, the rationale, and any spec conflict resolution.

## ADR-001 — Framework & language versions
- **Decision:** Laravel **12.x** (pinned via `^12.0`, currently 12.61), PHP **8.3+** floor (dev on 8.4).
- **Rationale:** The build prompt (§2) allows 12.x or 13.x. Laravel 13 was tried first, but the testing ecosystem (`pestphp/pest-plugin-laravel`) does not yet support Laravel 13. Pinning 12.x gives a fully green Pest + Larastan toolchain today. Revisit when the ecosystem catches up.

## ADR-002 — Frontend stack
- **Decision:** Inertia.js v2 + React 19 + TypeScript + **Tailwind CSS v4** (CSS-first `@theme`), built with Vite + `@vitejs/plugin-react`. Icons via `lucide-react`. `clsx` + `tailwind-merge` for class composition.
- **Rationale:** §2 mandates Inertia+React+TS+Tailwind. Tailwind v4's CSS-first token system maps cleanly onto the UX spec's design tokens (Part A) as CSS custom properties that resolve in light/dark and mirror automatically under RTL via logical properties.

## ADR-003 — Auth backend
- **Decision:** Laravel **Fortify** + **Sanctum** are installed, but auth is currently driven by custom Inertia controllers (`AuthController`) so registration can atomically create the Workspace + owner User. Fortify/passkeys route auto-discovery is disabled (`dont-discover`) to avoid duplicate `/login` routes during this phase.
- **Rationale:** §2 decides Fortify+Sanctum. The workspace-creating registration flow needs a custom action; migrating the backend onto Fortify's pipeline (with `CreateNewUser`, 2FA, password reset) is a Phase-1 hardening task. Sanctum remains the API/SPA session guard.

## ADR-004 — Multi-tenancy
- **Decision:** Single database, row-level scoping. `Workspace` tenant; `BelongsToWorkspace` trait adds a global scope + auto-fills `workspace_id`; `SetCurrentWorkspace` middleware resolves the active tenant; `App\Support\Tenancy` holds it for the request.
- **Rationale:** §2 mandates single-DB row scoping. Proven by `TenancyTest` (cross-tenant isolation + auto-fill).

## ADR-005 — SiteGround runtime constraints
- **Decision:** Database queue drained by a **cron-scheduled** `queue:work --stop-when-empty --max-time=50 --withoutOverlapping` (no daemon). Cache/session Memcached-or-database (no Redis). Realtime via hosted Pusher/Ably (no self-hosted socket). Media on S3-compatible storage. Vite assets built in **CI**, never on the server.
- **Rationale:** §3 verified hosting facts. Encoded in `routes/console.php`, `.env.example`, and the CI workflow.

## ADR-006 — Design language
- **Decision:** Neutral-led cool-gray ramp, a single calm **teal** accent (`#0d9488` light / `#14b8a6` dark), four semantic colours, Inter typeface. Border-led surfaces; shadows reserved for floating layers only; 6px controls / 10px cards.
- **Rationale:** UX spec Part A demands minimal, modern, content-first surfaces with rationed colour. Accent maps to ARKS Messages Platform's WhatsApp-adjacent green/teal brand without competing with content.

## ADR-007 — Inertia page directory
- **Decision:** Pages live in `resources/js/Pages` (PascalCase); `config/inertia.php` points the test page-existence check at that path with `tsx` extension.
- **Rationale:** Keeps component names (`Auth/Login`) aligned with file paths and lets `assertInertia` validate pages on a case-sensitive filesystem.
