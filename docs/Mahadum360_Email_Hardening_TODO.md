# MAHADUM.360 — Email System: Remaining / Hardening TODO

The email system (branded template, 10 transactional emails, campaigns, contact
lists + upload, scheduling, unsubscribe/suppression, SendGrid bounce webhook,
admin UI, email log) is **shipped and tested**
([`Mahadum360_Email_System_TODO.md`](Mahadum360_Email_System_TODO.md)). This doc
tracks the **deliberately-deferred optional items** so nothing is lost.

> **Status (2026-07-04):** §B, §C, §D, and the code parts of §E all **shipped**
> (176 backend tests, PHPStan L5 clean, web build green). Only §A (ops) and a few
> dependency-blocked bits (XLSX, resend, telco receipt) remain — see each section.

**Legend:** ✅ done · 🟡 partial · ⬜ not started
**Tags:** `[OPS]` config/ops, no code · `[BE]` backend · `[FE]` frontend ·
`[INFRA]` needs a new upstream event/flow first · `[COMPLIANCE]` legal/consent ·
`[HARDENING]` works today, this makes it sturdier

---

## A. ESP go-live `[OPS]` — the only thing blocking real inbox delivery

Nothing here is code; it's the deploy/ops checklist. Until it's done, mail goes to
the `log` driver, not inboxes.

- [ ] **Testing/staging → Mailtrap.** Set `MAIL_MAILER=smtp`,
  `MAIL_HOST=sandbox.smtp.mailtrap.io`, `MAIL_PORT=2525`, inbox `MAIL_USERNAME` /
  `MAIL_PASSWORD` (see `.env.example`). Send a test (`php artisan mail:preview` is
  render-only; use a real notification against Mailtrap to confirm delivery).
- [ ] **Production → SendGrid.** `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.sendgrid.net`,
  `MAIL_PORT=587`, `MAIL_USERNAME=apikey`, `MAIL_PASSWORD=<SENDGRID_API_KEY>`,
  `MAIL_SCHEME=tls`. Set `MAIL_FROM_ADDRESS` / `BRAND_SUPPORT_EMAIL` to the real
  domain, and a hosted PNG `BRAND_LOGO_URL`.
- [ ] **Sender-domain auth** — verify the domain in SendGrid and publish **SPF /
  DKIM / DMARC** DNS records. Without these, mail lands in spam.
- [ ] **Wire the bounce webhook** — set `SENDGRID_WEBHOOK_TOKEN` and point
  SendGrid's Event Webhook at `POST /api/v1/webhooks/sendgrid/{token}` (already
  built). Enable bounce / dropped / spamreport events.
- [ ] **Smoke test in prod** — one transactional (welcome) + one small campaign to
  a seed list; confirm inbox delivery, the email log rows, and that an unsubscribe
  click suppresses.

**DoD:** a real welcome email and a real campaign both land in an inbox, fully
branded; a bounce/unsubscribe shows up in the suppression list.

---

## B. Payment-failed / dunning email `[INFRA]` `[BE]`

The webhook flow (`PaymentService::process`) only handles `success` / `refund` /
`ignored` today — there is **no failed-charge event**, so there's nothing to
trigger dunning off. This needs the upstream event first.

- [x] ✅ `[BE]` **`kind = 'failed'` path** — `PaymentService::process` routes a failed
  charge to `fail()`, which alerts the payer without touching money. Paystack
  `charge.failed` + Flutterwave `status=failed` map to it in
  `PaymentWebhookController` (Monnify's decline event name is unverified — left as
  `ignored` until confirmed).
- [x] ✅ `[BE]` **`PaymentFailed` notification** — branded, `source=payment_failed`,
  retry CTA. *(Tested via `process(...,'failed',...)` → payer notified.)*
- [ ] **Card-expiring variant** ⬜ — optional pre-emptive nudge (needs the gateway to
  expose card expiry).

**DoD:** a declined renewal emails the payer a branded retry notice, logged as
`payment_failed`.

---

## C. Campaign send robustness `[HARDENING]` `[BE]`

Today `CampaignSender` marks each recipient `sent` optimistically at dispatch and
leans on the email log + bounce webhook for true state. Fine for MVP; this makes
large blasts exact and resilient.

- [x] ✅ **Bus-batched send** — `CampaignSender` now creates recipient rows then
  dispatches a `Bus::batch` of `App\Jobs\SendCampaignEmail` jobs; the batch's
  `finally` recomputes counts + marks the campaign `sent`. `CampaignMail` is no
  longer `ShouldQueue` (the job owns queueing).
- [x] ✅ **Per-recipient failure capture** — each job records `sent` / `failed` from
  the real transport result.
- [x] ✅ **Retry / resume** — a job skips a recipient not still `queued`, so a
  mid-batch crash is resumable without double-sending. *(Tested.)*

**DoD:** campaign counts reflect real per-recipient transport outcomes and a
mid-send crash is safely resumable.

---

## D. Compliance & retention `[COMPLIANCE]`

- [x] ✅ **Email-log retention pruning** — `emails:prune-log` (scheduled daily 04:00)
  deletes `email_logs` older than `email.log_retention_days` (admin-editable in
  `config/settings.php`, default 365; 0 disables). *(2 tests.)*
- [x] ✅ **ECDSA webhook signature** — `SendgridWebhookController` verifies SendGrid's
  Signed Event Webhook (ECDSA public key in env) when configured, with the URL token
  as fallback. *(Test skips only where openssl can't gen an EC key locally; the
  verify path is exercised where it can.)*
- [x] 🟡 **Consent surfacing** — `Contact.source` (upload/manual/signup) shows in the
  contacts table; `consent_at` is stored. *(A "why did they get this?" join in the
  log is the remaining nicety.)*

**DoD:** logs self-prune on schedule; the bounce webhook verifies a real signature.

---

## E. Admin UI niceties `[FE]`

- [x] ✅ **Cancel a scheduled campaign** — `POST …/{c}/cancel` (scheduled → draft,
  audited, 409 otherwise) + a "Cancel schedule" button on the detail page. *(Tested.)*
- [x] ✅ **Campaign recipient drill-down** — `GET …/{c}/recipients` (paginated,
  status filter) + a recipients table on the detail page. *(Tested.)*
- [x] 🟡 **Contact management** — per-contact **edit** (status subscribe/unsubscribe)
  + a **manual add** form (validated, dedup + suppression), both wired in the UI.
  *(Tested.)* *(Upload-history/rollback `UploadBatch` still ⬜.)*
- [x] ✅ **Email-log row detail** — click a log row → modal with the full record.
  *(Resend ⬜: we store metadata only, not the rendered body, so a true resend needs
  body retention first.)*
- [ ] **XLSX upload** ⬜ — needs a spreadsheet dependency (`openspout` /
  `phpoffice/phpspreadsheet`); not installed. CSV + paste cover the common case.
- [ ] **In-app template preview** ⬜ — the `mail:preview` command covers iteration.

**DoD:** an admin can cancel a scheduled blast, inspect who a campaign went to,
edit/import/rollback contacts, and preview an email — all in the browser.

---

## F. Remaining transactional emails `[BE]` — low value / weak fit

- [ ] **Telco (airtime) + school-invoice receipts** — same pattern as the wallet
  receipt (`WalletFunded`): a `TagsEmail` notification wired at the telco-bill and
  invoice-paid settle points. (Subscription + wallet receipts already ship.)
- [ ] **Chore/assignment-approved (coins released)** — *deliberately skipped:* the
  **parent** performs the approval, so emailing them their own action is redundant,
  and the learner (under 13) has no email per COPPA. Revisit only if a distinct
  recipient (e.g. a supervising teacher) is identified.

**DoD:** every money-in event to a consumer emails a branded receipt.

---

## Suggested order

1. **A — ESP go-live** (unblocks *everything* real; pure ops).
2. **C — Bus-batched send** + **E cancel-scheduled + recipient drill-down** (make
   the campaign tool production-grade for large lists).
3. **D — retention pruning + ECDSA** (compliance/security).
4. **B — dunning** (needs the failed-charge event first).
5. **E — contact edit / XLSX / log-detail** and **F — extra receipts** as demand
   warrants.

## Definition of done (per item)
Branded (transactional emails) · tagged so it lands in the email log with the
right `source`/`type` · marketing respects suppression + unsubscribe · sensitive
actions audited · admin surfaces guarded by `AdminRoute` + the right `emails.*`
permission · feature-tested · secrets env-only.
