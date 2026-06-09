# Punchlist — non-blocking improvements, deferred to a named phase

| Severity | Item | Owning phase |
|----------|------|--------------|
| low | Code-split the main Vite bundle (currently ~1MB; React+Inertia in one chunk) | P15 |
| med | Migrate custom auth onto Fortify pipeline (2FA, password reset, lockout cooldown) | P1 |
| med | Add component primitives still missing from A9 (Table, Tabs, Combobox, Date/time picker, Uploader, Emoji picker) | P2 |
| med | Offline banner + queued-write mechanism (A10.6) and presence/collision (A10.7) | P4 |
| low | Virtualize the conversation/contacts lists at scale (C-23) | P4/P5 |
| low | Wire Telescope (dev) and an in-app queue/failed-job monitor | P0/P15 |
| med | Externalize all UI strings for i18n (EN/AR/PT/ES) and full RTL parity sweep (C-21) | P14 |
| low | Replace seeded controller data in Inbox/Dashboard with live tenant-scoped queries | P4/P11 |
