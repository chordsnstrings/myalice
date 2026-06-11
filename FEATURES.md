# ARKS Messages Platform — Features

A multi-tenant, multi-channel customer messaging & commerce platform with a built-in
AI sales agent. Optimized for SiteGround (cron-driven queue, no daemons).

## Channels & messaging
- WhatsApp Cloud API, Facebook Messenger, and Instagram DM connectors
- One-click channel onboarding (Meta Embedded Signup) or manual credential entry
- Inbound webhooks with signature verification, idempotency, and async processing
- Per-channel contact identities (phone / PSID / IGSID) with cross-channel resolution
- Outbound delivery with provider message-id tracking and delivery/read/failed receipts
- 24-hour service-window tracking per channel identity

## Unified inbox
- 3-pane workspace: conversation list, message thread, customer context
- Human reply — free text inside the 24h window; approved template required outside it
- Resolve / reopen conversations (auto CSAT survey on resolve)
- Assign / reassign conversations to teammates
- Real-time message streaming (Pusher / Laravel Echo)
- AI draft review cards (Send / Dismiss) and "AI handling / handed off" badges
- Auto-routing of new conversations to available agents

## AI sales agent
- Pluggable LLM providers: Anthropic, OpenAI, Google Gemini, DeepSeek, any
  OpenAI-compatible / self-hosted endpoint (per-workspace encrypted keys)
- Default provider + automatic fallback chain
- Tiered autonomy: off / suggest (drafts) / auto-reply / autopilot
- Sales methodologies (consultative SPIN, direct closer, lead capture), tone, goal,
  business profile, and custom instructions — all admin-tunable
- Tools: capture lead, create order (DB-priced), send payment link, hand off to human
- Layered pre-approved discounts (free shipping → cart % → service %) with hard caps,
  once-per-customer limits, and real expiry — server-enforced
- High-closure tactics (FOMO, scarcity, urgency, social proof, anchoring, assumptive
  close, authority) kept truthful
- ~23-hour automatic re-engagement of stalled, customer-started chats
- Live playground to test the agent without sending
- Per-workspace guardrails: max messages, auto-order cap, handoff keywords
- Prompt-injection mitigation; all money/effects validated server-side
- AI performance analytics (engaged, replies, leads, orders, close rate, discounts)

## Broadcasts
- WhatsApp marketing broadcasts via approved HSM templates to opted-in contacts
- Messenger / Instagram session sends (compliant: in-24h-window only)
- Per-recipient personalization from contact fields (template variables)
- Audience builder (tag / lifecycle filters) with live size + cost preview
- Consent enforcement (opt-in required, opt-out / STOP suppression)
- Frequency capping and exactly-once, resumable, paced sending
- Scheduling (with a due-launcher), plus pause / resume / cancel
- Test send and per-message wallet cost (reserve → debit → refund of unsent)
- Delivery/read/reply tracking, reply attribution, and broadcast analytics funnel

## Templates
- Meta HSM template builder (header text/media, body with variables, footer, buttons)
- Submit for approval to the WhatsApp Business API and sync statuses
- Async approval/rejection/pause updates via webhook
- Only approved (non-paused) templates are sendable

## CRM & contacts
- Contact records with tags, lifecycle stages, and per-channel consent
- CSV contact import
- Saved audiences / segments
- Consent audit log (append-only opt-in/opt-out proof)

## Chatbots & automation
- Visual chatbot flow builder with validation and publish/versioning
- Automation rules (incl. abandoned-cart recovery tracking)
- Quick replies & tags, business hours

## Commerce
- Product catalog (physical products and services)
- Orders (including AI/chat-created orders) with line items
- Store connection (Shopify and other platforms)

## Analytics & reporting
- Dashboard with KPIs, revenue trend, and agent leaderboard
- Reports: agent performance, sales conversion, CSAT
- AI assistant and broadcast performance cards
- CSAT surveys with ratings capture
- Daily metric snapshots for trend lines

## Billing & wallet
- Plans: Premium / Business / Enterprise with cumulative feature gating
- Prepaid wallet with atomic debit/credit and a transaction ledger
- Pre-flight balance checks (broadcasts, AI orders)

## Administration & settings
- Multi-tenancy (workspaces) with full data isolation
- Role-based access control: Owner / Manager / Agent / Developer
- Settings: workspace, team & roles, channels, business hours, quick replies/tags,
  web widget, QR & links, AI agent, developer/API, profile
- Developer REST API (Sanctum tokens, per-workspace rate limiting)

## Platform
- Installable PWA (offline support, mobile navigation)
- Internationalization (English, Arabic, Spanish, Portuguese)
- Real-time via hosted Pusher/Ably; graceful degradation when unset
- SiteGround-optimized: cron-driven database queue, Memcached cache, S3 media,
  CI-built assets — no long-running daemons
