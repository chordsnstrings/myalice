# Multi-Channel Broadcasts — Engineering Plan

> Status: **Planning approved, not yet implemented.** Branch: `claude/broadcasts`
> (built phase-by-phase, each merged to `main` only when green).

## Context

The app has a `Broadcast` model + `broadcasts`/`audiences`/`message_templates`
tables and a `BroadcastController`, but the actual sending is a **stub**:
`SendBroadcast` debits the wallet and sets `delivered = recipients` without ever
looping recipients or calling a channel. To ship a real WhatsApp / Messenger /
Instagram broadcast feature we need identity, consent, real template support,
a per-recipient send pipeline, delivery tracking, and per-message billing.

### Decisions (locked)
1. **Channel scope:** WhatsApp = full marketing broadcasts; Messenger/Instagram =
   **session sends only** (in-window or non-promotional message tags).
2. **Templates:** full lifecycle — create + submit for approval to Meta + sync status.
3. **Consent:** full per-channel opt-in/opt-out + suppression, enforced on every send.

## The platform reality (drives the whole UX)

| Channel | Marketing blast to a list? | Outbound mechanism |
|---|---|---|
| **WhatsApp** | ✅ Yes | Approved **HSM template** to **opted-in** numbers, anytime. |
| **Messenger** | ❌ No | Broadcast API deprecated. Outside 24h: **non-promotional message tags** only. |
| **Instagram** | ❌ No | 24h window + `HUMAN_AGENT` tag (non-promotional). |

The composer is **channel-first**: choosing the channel changes the capabilities and
shrinks the eligible audience. We must never let a user cold-blast Messenger/IG.

## Current-state gaps (from code audit)
- `SendBroadcast` is a stub (no per-recipient send, no connector calls).
- Connectors (`WhatsAppConnector`/`MessengerConnector`/`InstagramConnector`) are **text-only**.
- `messages` has **no `external_id`**; webhooks **discard** delivery/read/failed events.
- **No per-recipient tracking table.**
- `contacts` has a single `phone`/`channel`; **PSID/IGSID are wrongly stored in `phone`**; **no consent fields**.
- `message_templates` is **plain text** (no variables/components/media); read-only; no Meta sync.
- `audiences.filters` is **never evaluated**; `size` is manual; no builder UI.
- `schedule_at` bug (always `null`); scheduled broadcasts never trigger.
- Reusable & solid: `WalletService` (atomic debit/credit + ledger), `MetaWebhookController` (signature + idempotency), the cron-drained DB queue.

---

## Architecture overview

```
Audience (filters) ──► AudienceBuilder ──► eligible contact_channels (opted-in, in-policy, deduped, freq-capped)
                                              │
BroadcastLauncher: materialize broadcast_recipients · reserve wallet · schedule/dispatch
                                              │
SendBroadcastChunk (paced, idempotent, resumable) ──► Connector.sendTemplate()/sendTag()/send()
                                              │                         │ provider message id
                                              ▼                         ▼
                              debit wallet per success      messages.external_id + broadcast_recipients
                                              │
Meta webhooks (statuses[] / delivery / read) ──► reconcile recipient + message status + counters + error codes
                                              │
Finalizer: refund unused reserve · mark completed · analytics
```

All on the existing **SiteGround cron-drained queue** (`queue:work --stop-when-empty
--max-time=50`, no daemons) — so the sender must be **chunked, paced, and resumable**.

---

## Data model (all new/changed tables)

**Phase 0**
- **`contact_channels`** *(new)* — the spine. `id, workspace_id, contact_id, channel,
  external_id` (phone/PSID/IGSID), `opted_in_at, opt_in_source, opt_in_text,
  opted_out_at, opt_out_reason, last_inbound_at, window_expires_at, timestamps`;
  unique `(workspace_id, channel, external_id)`, index `(workspace_id, contact_id)`.
- **`messages.external_id`** *(alter)* — provider message id; index `(workspace_id, external_id)`.
- **`consent_events`** *(new, append-only)* — `workspace_id, contact_id, channel,
  type (opt_in|opt_out), source, raw_text, created_at`. Audit/proof.

**Phase 1**
- **`message_templates`** *(alter)* — `meta_template_id, components (json),
  variable_count, variable_samples (json), status (draft|pending|approved|rejected|paused|disabled),
  header_media_handle`. Keep `category`, `language`, `quality`, `rejection_reason`.

**Phase 2**
- **`broadcasts`** *(alter)* — `channel, sending_channel_id, template_id, variable_map (json),
  category, timezone, quiet_hours (json), reserved_cost, spent_cost, approved_by`,
  expand `status` enum (`draft|scheduled|launching|sending|paused|completed|canceled|failed`).
- **`broadcast_recipients`** *(new)* — `broadcast_id, contact_id, channel, external_id,
  status (queued|sent|delivered|read|replied|failed|skipped), skip_reason,
  provider_message_id, error_code, cost, sent_at, delivered_at, read_at, replied_at`;
  unique `(broadcast_id, contact_id)`; indexes on `(broadcast_id, status)` and `provider_message_id`.

**Phase 4**
- `broadcast_variants` (A/B) and a retention/archival policy for `broadcast_recipients`.

---

## Phases & acceptance criteria

### Phase 0 — Foundations (identity, consent, delivery loop)
Nothing customer-facing; it makes everything else possible.
**Done when:** inbound messages create correct per-channel identities (no more PSID-in-phone);
`window_expires_at` is a real timestamp; a STOP/unsubscribe writes `opted_out_at` +
a `consent_event`; WhatsApp `statuses[]` and Messenger/IG delivery/read webhooks update
message status; outbound sends store `external_id`; existing contacts are backfilled into
`contact_channels`; full suite green.

### Phase 1 — Template management (create → submit → sync)
**Done when:** an admin can build an HSM template (header/body/footer/buttons, `{{n}}` vars +
samples, media upload to S3), submit it to the WABA, see status update via sync + webhook,
and only `approved`/non-`paused` templates are sendable.

### Phase 2 — WhatsApp broadcast pipeline (core value)
**Done when:** an admin builds an audience, maps template variables, previews per-channel
eligibility + per-message cost, test-sends, launches; recipients are materialized; the wallet
reserve→debit→refund works; chunked paced sender delivers within MPS + tier limits, idempotent
and resumable; delivery/read receipts flow back; pause/resume/cancel works; scheduling +
quiet-hours work; auto-pause on quality drop / template pause / low balance.

### Phase 3 — Messenger / Instagram session sends
**Done when:** the same pipeline restricts MSG/IG to in-window contacts (or non-promotional
tags), connectors send tags/media, and the composer enforces/explains the rules.

### Phase 4 — Analytics, attribution, polish
**Done when:** broadcast detail shows live progress + per-recipient status; metrics (delivery,
read, CTR, reply, opt-out, conversions within N days, cost); auto-pause on opt-out spike;
A/B variants; archival.

---

## Cross-cutting requirements (apply across phases)
- **Per-channel policy enforcement** (WA template/opted-in; MSG/IG window/tags). #1 ban-prevention.
- **Consent**: opt-in proof, immediate opt-out honoring, suppression enforced on every send,
  per-channel opt-out scope, block/report feedback lowers eligibility.
- **WhatsApp number messaging tiers** (250→1K→10K→100K→unlimited unique/24h) + **quality rating** —
  respect, ramp, monitor, auto-pause.
- **MPS pacing** across cron ticks; **exactly-once + resumable** chunks; **failure isolation** +
  per-recipient retry/backoff + dead-letter.
- **Per-message wallet** reserve → debit-on-success → refund-unused; mid-send insufficient funds → pause/resume.
- **Pricing** per country × category (WA); MSG/IG session free.
- **Media** via S3 → Meta media handle.
- **Multiple sender numbers/pages** per workspace.
- **Quiet hours / region blocks**, timezone-aware scheduling.
- **Frequency cap / dedup** across recent broadcasts (Meta marketing limits + anti-spam).
- **Replies & attribution** — broadcast sends create/append conversations (replies hit the inbox,
  open the window, AI/agents handle); conversions attributed back.
- **Audit log** of who sent what to whom; **data-volume** handling (batched inserts, indexes, archival).
- **WABA readiness** — handle workspaces without a verified Business / approved WABA gracefully.

---

## Phase 0 — concrete build checklist (start here)

**Migrations**
- [ ] `create_contact_channels_table` (columns + unique + indexes as above).
- [ ] `add_external_id_to_messages` (+ index).
- [ ] `create_consent_events_table`.
- [ ] `backfill_contact_channels` — data migration: one row per existing contact from
      `contacts.phone`/`channel` (assume opted-in with source `legacy`); idempotent.

**Models**
- [ ] `App\Models\ContactChannel` (`BelongsToWorkspace`, casts for the timestamps, `belongsTo Contact`).
- [ ] `App\Models\ConsentEvent` (`BelongsToWorkspace`, append-only; `const UPDATED_AT = null`).
- [ ] `Contact::channels()` hasMany; helper `channelIdentity(string $channel): ?ContactChannel`.
- [ ] `Message`: add `external_id` to `$fillable`.

**Services**
- [ ] `App\Support\Consent` (or `ConsentService`): `recordOptIn()`, `recordOptOut()`,
      `isSuppressed(contact, channel)`, `eligible(channel)` query scope. STOP keyword set centralized.

**Inbound rework**
- [ ] `ProcessInboundMessage`: resolve/create `ContactChannel` by `(channel, external_id)`,
      attach to (or create) a `Contact`; stamp `last_inbound_at` + `window_expires_at`; detect
      STOP/opt-out → `recordOptOut()` (+ `consent_event`) and suppress AI.
- [ ] Connectors: `normalizeInbound` already returns `from`; ensure it carries the raw channel id.

**Delivery receipts**
- [ ] Connectors gain `normalizeStatuses(array $payload): array` — parse WhatsApp
      `entry[].changes[].value.statuses[]` and Messenger/IG `entry[].messaging[].delivery/read`.
- [ ] `MetaWebhookController`: route status events → a `ReconcileDeliveryStatus` job that maps
      `provider_message_id` → message/recipient and updates status + error_code.
- [ ] `SendOutboundMessage`: persist `external_id` from the connector's return value.

**Tests (Pest)**
- [ ] Inbound creates a `ContactChannel` with the correct identity per channel (WA phone, MSG PSID, IG IGSID); no PSID-in-phone.
- [ ] Second message from the same identity reuses the same channel/contact (dedup).
- [ ] STOP/unsubscribe → `opted_out_at` set + `consent_event` row + AI suppressed.
- [ ] WhatsApp `statuses[]` webhook → message status delivered/read; Messenger delivery → status updated.
- [ ] Outbound send stores `external_id`; a later status webhook reconciles it.
- [ ] Backfill migration produces one channel row per existing contact.
- [ ] Full suite + Pint + PHPStan + tsc green.

**Acceptance:** identity + consent + delivery-status loop work end-to-end with no regression;
this unblocks Phases 1–2.

---

## Verification strategy (all phases)
`Http::fake` the Graph API for template/media/tag sends; `Queue` tests for chunked / paced /
resumable dispatch and exactly-once; webhook tests for delivery/read/error → recipient status;
consent-enforcement tests (suppressed & un-opted-in excluded; STOP suppresses); pricing tests
per country/category; wallet reserve/debit/refund tests; MSG/IG window-only eligibility tests.
Gated by `create-broadcasts` + `manage-bots`; runs on the cron-drained queue.

## Risks & assumptions
- Requires a verified Meta Business + approved WABA per workspace for real WA template sends;
  until then, stub-mode keeps the pipeline testable.
- Meta may **re-categorize** submitted templates (changes pricing) — surface the actual category.
- Template review latency (minutes–24h); template creation is rate-limited.
- Opt-out scope (per-channel vs global) assumed **per-channel**; revisit if legal wants global.
- Click tracking (button/URL CTR) needs wrapped links or Meta analytics — Phase 4.

## Branching
Develop on `claude/broadcasts`; open a PR per phase; merge to `main` only when that phase is
green (Pint · PHPStan L6 · tsc · Pest · build). Keep `main` releasable throughout.
