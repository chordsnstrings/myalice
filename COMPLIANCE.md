# Part C / Part D Compliance Matrix

How the UX spec's edge-case matrix (Part C) and "smooth" checklist (Part D) are
addressed in this build. ✅ implemented · 🟡 partial/scaffolded · ⛔ credential-gated.

## Part C — edge cases

| ID | Edge case | Status | Where |
|----|-----------|:------:|-------|
| C-01 | WhatsApp 24h window expires → template mode | ✅ | `Inbox/Index` composer state machine; server `window_open` |
| C-02 | Outbound to never-replied contact → template only | 🟡 | composer template state (server enforcement in P8) |
| — | Webhook signature verification (X-Hub-Signature-256) | ✅ | `MetaWebhookController::signatureValid()` (WhatsApp/Messenger/Instagram) |
| B9.2 | Channel onboarding panel (manual + Embedded Signup) | ✅ | `ChannelConnectionController` + `ChannelOnboarder`; Settings → Channels drawer |
| C-03 | Wallet hits zero mid-broadcast | ✅(UI) | `Broadcasts/Create` pre-flight gate, insufficient-funds block |
| C-04 | Same customer on multiple channels | 🟡 | channel dot disambiguation; merge/unmerge in PUNCHLIST |
| C-05 | Store token expired/disconnected | ✅ | context pane + `Commerce/*` "reconnect" degradation |
| C-06 | Message send fails | ✅ | thread failed-state + Retry; `SendOutboundMessage::failed()` |
| C-07 | Two agents on one conversation | 🟡 | presence design ready; live presence in PUNCHLIST |
| C-08 | Meta rate-limit/throughput | 🟡 | non-alarming copy patterns; queue throttling in P8 |
| C-09 | Template rejected/quality-downgraded | ✅ | `Broadcasts/Templates` + composer unselectable rejected |
| C-10 | Bot no-match / loops | 🟡 | builder fallback warnings; runtime guard in P7 |
| C-11 | Payment ok but store write fails | 🟡 | chat-to-order reconciliation design (P6) |
| C-12 | Order changed externally | 🟡 | conflict-detection design (P6) |
| C-13 | Bulk select-all across pages | ✅ | contacts/inbox bulk bar + scale-aware confirm |
| C-14 | Long/oversized/unsupported media | ✅ | thread clamp + neutral chips |
| C-15 | Concurrent flow editing | 🟡 | draft model; presence lock in PUNCHLIST |
| C-16 | Offline / connection drop | ✅ | `OfflineBanner` + `useOnlineStatus` + optimistic queue |
| C-17 | Feature/role not available | ✅ | gates + nav hides denied items; locked-feature lock icon |
| C-18 | Failed renewal / billing lapse | 🟡 | billing grace-state copy (enforcement in P10) |
| C-19 | CSV import bad/dup rows | 🟡 | contacts import entry point (pipeline in P5) |
| C-20 | DST / timezone | ✅ | workspace timezone shown in scheduling/hours |
| C-21 | RTL + long strings | ✅ | full RTL via logical properties + AR locale |
| C-22 | Opted-out/blocked targeted | ✅(UI) | broadcast exclusion breakdown |
| C-23 | Huge scale (100k+/500k+) | 🟡 | server pagination on API; list virtualization in PUNCHLIST |
| C-24 | Auth edges (enumeration, 2FA, lockout) | ✅ | non-enumerating login/reset; 2FA/lockout in PUNCHLIST |

## Part D — "smooth" checklist (shell + built screens)

- ✅ Every interactive element has default/hover/focus-visible/active/disabled (component library).
- ✅ Visible focus ring; ≥touch targets; icon-only buttons have tooltip + aria-label.
- ✅ Async actions: skeletons, button spinners (no layout shift), optimistic send with rollback.
- ✅ Lists: distinct first-use / filtered / error empties.
- ✅ Destructive: scale-aware confirm; danger not default focus.
- ✅ Money shown before spend (broadcast pre-flight, wallet).
- ✅ WhatsApp window/template enforced at the composer, not via failed send.
- ✅ Offline banner + queued writes.
- ✅ WCAG: keyboard-complete shell + ⌘K, ARIA on dialogs/menus/toasts, modal focus handling.
- ✅ Full RTL parity (AR) and dark/light themes; `prefers-reduced-motion` honored.
- ✅ Locale-correct dates/numbers/currency.
- 🟡 List virtualization at extreme scale (PUNCHLIST).

See `BLOCKERS.md` for credential-gated items and `PUNCHLIST.md` for deferred polish.
