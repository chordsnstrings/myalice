# Blockers — need human input

These goals are stubbed behind interfaces/feature flags so dependent work proceeds.
Clear them in batches by supplying the credentials/decisions below.

| ID | Goal | What's blocking | What unblocks it |
|----|------|-----------------|------------------|
| BLK-1 | G0.6 | SiteGround staging deploy + cron-queue + SSL round-trip | A SiteGround site/subdomain, SSH key access, MySQL DB credentials, and the document-root mapping to `…/public`. |
| BLK-2 | G0.6 / P4 | Realtime broadcasting | A hosted **Pusher** (or Ably) app: `PUSHER_APP_ID/KEY/SECRET/CLUSTER`. Until set, `BROADCAST_CONNECTION=log` and the UI degrades to no live updates. |
| BLK-3 | G0.6 / media | User media storage | S3-compatible bucket (Cloudflare R2 / DO Spaces / Wasabi): `AWS_*` + `AWS_ENDPOINT`. Until set, `FILESYSTEM_DISK=local`. |
| BLK-4 | P3 | WhatsApp Cloud API | Meta WABA + phone number ID + permanent token + app secret + webhook verify token (`WHATSAPP_*`, `META_APP_SECRET`). |
| BLK-5 | P6 | Store integration | A Shopify dev store + API key/secret (`SHOPIFY_*`) to verify catalog/order sync and chat-to-order. |
| BLK-6 | P10 | Billing | A payment processor decision/account (Stripe assumed pending confirmation) for subscriptions + wallet top-ups. |

No code currently hard-requires any of the above; absence degrades gracefully per the env defaults.
