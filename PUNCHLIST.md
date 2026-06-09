# Punchlist — non-blocking items deferred to a named phase

Items here are buildable without external credentials but were scoped out of the
current pass. Credential-gated work lives in `BLOCKERS.md`.

| Severity | Item | Owning phase |
|----------|------|--------------|
| med | Migrate custom auth onto Fortify pipeline: **2FA**, lockout cooldown. Password reset is done. | P1 |
| med | **Queued analytics aggregation** (replace seeded dashboard metrics with rollups). | P11 |
| low | Skills-based routing + bot↔human handoff state (load-balanced auto-routing done). | P5 |
| low | **List virtualization** at extreme scale (100k+/500k+) — server pagination already in place. | P15 |
| low | Code-split the Vite bundle (currently ~1MB single chunk). | P15 |
| low | Identity **merge/unmerge** UI across channels (C-04). | P5 |
| low | Live **presence/collision** indicators in the inbox (C-07). | P4 |
| low | Full UI string externalization (mechanism + nav/shell done; pages pending). | P14 |
| low | Native mobile app or PWA delivering inbox parity (B13). | P14 |
| low | In-app queue/failed-job monitor (Filament or custom) + Telescope wiring. | P15 |

## Done since first pass (moved off the punchlist)
CSV import · offline banner + queued writes · scale-aware bulk confirm · broadcast
send engine + wallet ledger · automation dispatcher · flow validation + publish gate ·
RBAC gates · i18n + RTL + locale switch · realtime scaffolding.
