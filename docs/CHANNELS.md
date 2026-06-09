# Connecting channels

How to add every messaging channel to ARKS Messages Platform. Most channels are
connected **in the app** at **Settings → Channels** — no redeploy needed.

| Channel | Status | How to connect |
|---|---|---|
| WhatsApp | ✅ Supported | Settings → Channels (Quick or Manual) |
| Messenger | ✅ Supported | Settings → Channels (Quick or Manual) |
| Instagram | ✅ Supported | Settings → Channels (Quick or Manual) |
| Web widget | ✅ Config ready | Settings → Web widget (install snippet) — see note |
| Telegram / Line / Viber | 🔧 Connector to be added | Code change — see "Adding a new channel" |
| Email | ⛔ Coming soon | Roadmap |

Two ways to connect the Meta channels, **admin's choice** in the connect drawer:

- **Quick connect ("Connect with Facebook")** — one-click Meta Embedded Signup.
  Requires app-level Meta config in `.env` (below). No copying tokens.
- **Manual** — paste a token + ids; the app verifies them against the Graph API,
  saves them **encrypted**, and shows the **webhook URL + verify token** to paste
  into Meta.

Credentials are stored **encrypted at rest** on the `Channel` record (per
workspace), never in `.env`.

---

## A. One-time Meta setup (shared by WhatsApp / Messenger / Instagram)

1. Go to **developers.facebook.com → My Apps → Create App** (type: *Business*).
2. Note the **App ID** and **App Secret** (App → Settings → Basic).
3. Add the products you need: **WhatsApp**, **Messenger**, and/or **Instagram**.
4. Put the App Secret on the server so inbound webhook **signatures are verified**:
   ```ini
   META_APP_SECRET=your_app_secret
   ```
   (Without it the app still works but won't verify `X-Hub-Signature-256`.)

### Enable "Connect with Facebook" (optional, for Quick connect)

If you want the one-click flow, also set:
```ini
META_APP_ID=your_app_id
META_GRAPH_VERSION=v21.0
META_WA_CONFIG_ID=...            # Embedded Signup config id (WhatsApp)
META_MESSENGER_CONFIG_ID=...     # Facebook Login for Business config id
META_INSTAGRAM_CONFIG_ID=...     # Facebook Login for Business config id
VITE_META_APP_ID="${META_APP_ID}"
VITE_META_GRAPH_VERSION="${META_GRAPH_VERSION}"
```
Create the config ids under **App → WhatsApp/Facebook Login → Configurations**.
Add your domain to **App → Settings → Basic → App Domains** and the
**Valid OAuth Redirect URIs**. If these are unset, the panel simply hides Quick
connect and shows Manual.

> After changing `VITE_*` values you must **rebuild assets** (`npm run build`) and
> redeploy, because they're compiled into the frontend bundle.

---

## B. WhatsApp

### What you need from Meta
- A **WhatsApp Business Account (WABA)** with a **phone number** added.
- A **permanent access token** (System User token with `whatsapp_business_messaging`
  + `whatsapp_business_management`) — *App → WhatsApp → API Setup*.
- The **Phone number ID** (shown next to the number in API Setup).
- (Optional) the **WABA ID**.

### Connect in the app
1. **Settings → Channels → WhatsApp → Connect.**
2. **Quick connect:** click **Connect with Facebook** and pick the WABA/number.
   *(Requires the Meta config from section A.)*
3. **Manual:** paste **Permanent access token**, **Phone number ID**, and
   optionally **WABA ID**, then **Test & connect**. The app calls the Graph API to
   verify and saves on success.

### Point Meta's webhook at the app
In the connect drawer (Manual tab) copy the two values, then in
**App → WhatsApp → Configuration → Webhook**:
- **Callback URL:** `https://your-domain/api/webhooks/whatsapp`
- **Verify token:** the value shown in the drawer (set via `WHATSAPP_VERIFY_TOKEN` in `.env`)
- **Subscribe** to the `messages` field.

```ini
# .env — used for the webhook handshake + (optional) env-based sending
WHATSAPP_VERIFY_TOKEN=choose-a-random-string
# Optional fallback if you prefer env over the panel:
WHATSAPP_API_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_WABA_ID=
```

### Verify it works
- Send a WhatsApp message to your number → it appears in the **Inbox** within a few seconds.
- Reply from the inbox → it's delivered (free-form inside the 24-hour window; an
  approved **template** is required outside it — the composer enforces this).

---

## C. Facebook Messenger

### What you need from Meta
- A **Facebook Page**.
- A **Page access token** (App → Messenger → API Setup → generate token for the Page).

### Connect in the app
1. **Settings → Channels → Messenger → Connect.**
2. **Quick connect** (with Meta config) *or* **Manual**: paste the **Page access
   token** → **Test & connect** (the app fetches the page id via `/me`).

### Webhook (App → Messenger → Settings → Webhooks)
- **Callback URL:** `https://your-domain/api/webhooks/messenger`
- **Verify token:** value from the drawer (`MESSENGER_VERIFY_TOKEN` in `.env`)
- **Subscribe** the Page to the `messages` field.

```ini
MESSENGER_VERIFY_TOKEN=choose-a-random-string
MESSENGER_PAGE_TOKEN=          # optional env fallback
```

---

## D. Instagram

Instagram messaging runs through the **linked Facebook Page** (same Graph Send
API as Messenger).

### What you need from Meta
- An **Instagram Professional account** linked to a Facebook Page.
- A **Page access token** with `instagram_basic` + `instagram_manage_messages`.
- Instagram messaging enabled on the IG account (Settings → Messaging → Connected tools).

### Connect in the app
1. **Settings → Channels → Instagram → Connect.**
2. **Quick connect** *or* **Manual**: paste the **Page access token** → **Test & connect**.

### Webhook (App → Instagram → Webhooks, or the app's Webhooks product)
- **Callback URL:** `https://your-domain/api/webhooks/instagram`
- **Verify token:** value from the drawer (`INSTAGRAM_VERIFY_TOKEN` in `.env`)
- **Subscribe** to the `messages` field.

```ini
INSTAGRAM_VERIFY_TOKEN=choose-a-random-string
INSTAGRAM_PAGE_TOKEN=          # optional env fallback
```

---

## E. Web chat widget

1. **Settings → Web widget**: set the greeting and accent, then copy the
   **install snippet** and paste it before `</body>` on your site (a one-click
   Shopify install is offered).
2. Conversations from the widget land in the unified inbox.

> **Note:** the dashboard generates the snippet and config today; the embeddable
> `widget.js` runtime served from the CDN is on the punchlist
> ([`PUNCHLIST.md`](../PUNCHLIST.md)). Hosting that script is the remaining step to
> make the widget live on a storefront.

---

## F. Telegram / Line / Viber (adding a new channel)

These aren't wired yet, but the architecture makes them small additions. Each
channel is just a `ChannelConnector` plus a webhook route:

1. **Connector** — create `app/Channels/TelegramConnector.php` implementing
   `App\Channels\ChannelConnector`:
   - `type()` → `'telegram'`
   - `isConfigured()` → has a bot token
   - `send($to, $payload)` → call the provider's send API
   - `normalizeInbound($payload)` → return `[['external_id','from','type','body','sent_at'], …]`
2. **Register** it in `app/Channels/ChannelManager::$connectors`.
3. **Webhook** — add a controller (Telegram isn't Meta, so it won't extend
   `MetaWebhookController`; verify the provider's secret instead) and routes in
   `routes/api.php` (`GET`/`POST /api/webhooks/telegram`). Validate → dedupe via
   `WebhookEvent` → `ProcessInboundMessage::dispatch(...)`.
4. **Onboarding** — add the manual fields for it in the Channels connect drawer.

The inbound→conversation pipeline (`ProcessInboundMessage`), outbound queue,
routing, and inbox UI are already channel-agnostic, so you only add the adapter.

---

## How inbound/outbound actually flows (reference)

- **Inbound:** Meta → `POST /api/webhooks/{channel}` →
  `MetaWebhookController` verifies the `X-Hub-Signature-256` signature, dedupes on
  the provider event id (`webhook_events` table), resolves the tenant `Channel`
  by the receiving id, then dispatches `ProcessInboundMessage` (queued). The job
  creates/links the contact + conversation, stores the message, auto-routes it,
  and broadcasts it to the inbox in real time.
- **Outbound:** the inbox/queue calls the channel's `ChannelConnector::send()`,
  which uses the **panel-saved encrypted credentials** first, falling back to
  `.env`. If neither is present it runs in **stub mode** (logs, no real send) so
  non-production environments work without credentials.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Webhook verification fails in Meta | Verify token mismatch | Make the Meta "Verify token" equal the `*_VERIFY_TOKEN` in `.env` |
| Inbound returns 403 | Bad/missing signature with `META_APP_SECRET` set | Ensure the webhook is from the same app whose secret is in `.env` |
| "Couldn't verify these credentials" on Manual connect | Token/id invalid or expired | Regenerate the token in Meta; check the Phone number ID / page |
| Quick connect not shown | Meta app/config ids unset | Set `META_APP_ID` + `META_*_CONFIG_ID` + `VITE_META_APP_ID`, rebuild assets |
| Messages send but nothing arrives in Meta | Running in stub mode | Confirm the channel shows **Connected** in Settings → Channels |
| Duplicate inbound messages | (shouldn't happen) | Dedup is automatic via `webhook_events`; check that table isn't being cleared |
