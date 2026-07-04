# MAHADUM.360 — Email System TODO

A build plan for a **single branded email template**, the **transactional email
content** for every major event, and an **admin broadcast / campaign** tool that
can email a segment of registered users *or* an imported list of arbitrary
contacts (registered or not), on demand or on a schedule.

**Legend:** ✅ done · 🟡 in progress / partial · ⬜ not started
**Tags:** `[MVP]` ships for launch · `[POST]` deferred · `[BE]` needs a backend seam first ·
`[COMPLIANCE]` legal/consent guardrail — not optional

> **Scope.** Spans the Laravel API (mailer, template, Mailables/Notifications,
> campaign + contacts models, scheduler) and the super_admin portal (compose /
> segment / schedule / contacts UI). Reuses the existing notification fan-out
> (`DeliversOverMessagingChannels`), the `database` queue, `AuditLogger`, and the
> admin-portal primitives (`AdminPageHeader`, `DataTable`, `AdminSubNav`).

---

## Current state (audit, 2026-07-03)

- **Mailer:** `MAIL_MAILER=log` — nothing reaches a real inbox yet. `from` =
  `hello@example.com` / `${APP_NAME}`. **No ESP configured.**
- **Queue:** `QUEUE_CONNECTION=database` — fine for batched/scheduled sends.
- **Templates:** none. The 3 existing notifications
  (`SubscriptionRenewalReminder`, `SubscriptionActivated`, `PayoutApproved`) use
  Laravel's stock `MailMessage` markdown → generic look, no logo/brand/footer.
- **Registration** fires `event(new Registered)` → Laravel's default
  `VerifyEmail` (unbranded). **No welcome / login-alert / receipt emails.**
- **No `app/Mail/` Mailables, no campaign/contacts/broadcast anything.**
- **Brand source:** `web/src/lib/brand.ts` — `WORDMARK = 'MAHADUM.360'`,
  `TAGLINE` (locked). Player identity is "Gilded Adire" (African-heritage: gold
  hero, adire lattice). Email design should echo that, not a generic template.

---

## 0. Foundations — provider + one global branded template `[MVP]` `[BE]` (do first)

- [x] 🟡 **Mail provider, swappable via env** — **decision: SendGrid (prod) +
  Mailtrap (testing/staging)**, both over SMTP, so no driver-specific code — just
  `MAIL_MAILER=smtp` with per-env host/credentials. `.env.example` documents both
  blocks (Mailtrap `sandbox.smtp.mailtrap.io:2525`; SendGrid `smtp.sendgrid.net:587`,
  username `apikey`). *(Remaining: drop in real creds per env, verify the SendGrid
  sending domain + SPF/DKIM/DMARC, and add the SendGrid Event Webhook → suppression
  in §5.)*
- [x] ✅ **One global branded template.** Published Laravel's markdown mail
  components (`vendor:publish --tag=laravel-mail`) + a **"Gilded Adire" theme**
  (`resources/views/vendor/mail/html/themes/mahadum.css`: ink header band, gold
  wordmark, gold card accent, adire-cream body, accessible ink CTA). Customised
  `header`/`message` to read brand config; footer carries tagline + address +
  support email + an `$unsubscribeUrl` slot for marketing. `config/mail.php`
  markdown theme → `mahadum`, so **every `MailMessage` notification renders
  branded with zero per-email markup** (the 3 existing notifications + Laravel's
  verify/reset inherit it automatically). *(Verified live in the browser.)*
- [x] ✅ **Branding source of truth** — `config/brand.php` (wordmark, tagline,
  logo URL, support email, address, gold/ink hex), env-overridable, mirrors
  `web/src/lib/brand.ts`.
- [x] ✅ **Preview harness** — `php artisan mail:preview [--out=]` renders a sample
  branded email to HTML for design review.
- [ ] **A11y + client testing** — spot-checked (semantic headings, alt text,
  ≥14px body, ink-on-white contrast). Full Gmail/Outlook/Apple-Mail pass still
  ⬜ (needs a real send once the ESP lands).

**Definition of done (§0):** a real send lands in an inbox, fully branded, with a
working unsubscribe footer on marketing mail; every existing notification renders
in the new shell with no bespoke markup.

---

## 1. Transactional email content — the major events `[MVP]`

Each is a Mailable/Notification using the §0 template. **Transactional** (account
/ security / receipts) **always sends** and ignores marketing unsubscribe;
**lifecycle nudges** respect preferences. **COPPA:** a learner under 13 has no
login — send to the **parent**, never the child (`[COMPLIANCE]`).

| Event | Type | Status | Notes |
|---|---|---|---|
| **Verify email** (registration) | transactional | ✅ branded | Laravel's `VerifyEmail` now inherits the `mahadum` theme (signed link intact) |
| **Welcome** (post-verify) | transactional | ✅ **shipped** | `WelcomeEmail` notification, sent by `SendWelcomeEmail` on the `Verified` event; branded + tagged (`source=welcome`); COPPA-safe (only the verifying account holder) |
| **Password reset** | transactional | ✅ branded | Laravel's `ResetPassword` inherits the theme (SPA reset link intact) |
| **New-device / login alert** | transactional (security) | ✅ **shipped** | `NewDeviceAlert`, detected in `AuthController@login` via `X-Device-Id` vs known `devices` (only when ≥1 device known → no false alarms); "reset password" CTA |
| **Wallet top-up receipt** | transactional | ✅ **shipped** | `WalletFunded`, dispatched from `PaymentService@settleFunding` to the wallet owner (user or family owner). *(Subscription receipt already covered by `SubscriptionActivated`.)* |
| **Other purchase receipts** (telco / school invoice) | transactional | ⬜ | wire at their settle points, same pattern |
| **Subscription activated** | transactional | ✅ **branded + tagged** | `SubscriptionActivated` (`source=subscription_activated`) |
| **Renewal reminder** | transactional | ✅ **branded + tagged** | `SubscriptionRenewalReminder` (`source=subscription_renewal_reminder`) |
| **Payment failed / card expiring** | transactional | ⬜ | dunning: retry link before access lapses |
| **Promo redeemed** | transactional | ⬜ | confirmation of discount applied |
| **Payout approved** | transactional | ✅ **branded + tagged** | `PayoutApproved` (`source=payout_approved`) |
| **School invite / seat assigned** | transactional | ⬜ | ties to the org invite-admin flow + seat allocation |
| **Chore/assignment approved (coins released)** | lifecycle | ⬜ | parent-approval event; to the learner's parent |
| **Support ticket reply** | transactional | ✅ **shipped** | `SupportReply`, sent to the requester from `Admin\SupportController@addMessage` |

- [x] ✅ **Source-tagging** — `App\Notifications\Concerns\TagsEmail` stamps the
  `X-Mahadum-*` headers so each event's mail lands in the email log with the right
  `source`/`type`/`user_id`. Applied to the 3 existing notifications + `WelcomeEmail`.
- [x] ✅ Rebranded the 3 existing + verify/reset (inherit the theme) and shipped
  **Welcome**, **wallet receipt**, and **new-device login alert** — each full-path
  (branded → tagged → logged → event-wired). *(11 email feature tests: incl.
  Verified→welcome, funding→receipt, new-vs-known-vs-first device.)*
- [ ] Remaining ⬜ events above (telco/invoice receipts, dunning, promo, school-invite,
  chore-approved, support-reply) — each a small `TagsEmail` notification wired to its
  dispatch point.
  Dispatch points for the remainder: receipt ← `PaymentService`, login-alert ←
  `AuthController@login`, support-reply ← `Admin\SupportController`. Feature-test each
  (`Mail::fake()`/`Notification::fake()`), and confirm under-13 routes to the parent.

---

## 2. Admin broadcast to a user segment `[MVP]` `[BE]`

Super_admin composes an email and sends it to a **filtered set of registered
users**. `emails.campaigns.manage` (new, super-admin-only).

- [x] ✅ `[BE]` **`EmailCampaign` + `EmailCampaignRecipient` models** — subject,
  body (markdown → §0 template), audience_type + audience (json), status, counts,
  `scheduled_at`, `created_by`; audited on create/send.
- [x] ✅ `[BE]` **Audience** (`CampaignSender`) — **user segment** (role / status /
  organization filters) *or* a **contact list**, resolved at send time.
- [x] 🟡 `[BE]` **Queued send** — `CampaignMail` is `ShouldQueue`, so each send is
  queued (non-blocking); one `EmailCampaignRecipient` row per address with status
  (`sent`/`suppressed`). *(Optimistic per-recipient status; true Bus-batch + per-job
  sent/failed callbacks are the remaining hardening.)*
- [x] ✅ `[BE]` **Respect suppression** — campaigns are marketing; every recipient is
  checked against the global suppression list and skipped (`status=suppressed`).
- [x] ✅ **Test send** — `POST …/{c}/test` sends only to the admin (no recipient rows).
- [x] ✅ Endpoints: `GET/POST /admin/email-campaigns`, `POST …/{c}/test`,
  `POST …/{c}/send` (send-now or schedule), `GET …/{c}` (recipient stats). Audited,
  `emails.campaigns.manage`. **409 on re-send.** *(8 feature tests incl. segment +
  list send, suppression skip, double-send guard, signed unsubscribe, log capture.)*

---

## 3. Contact lists + non-user email lists `[MVP]` `[BE]`

Email people who **aren't necessarily registered users** — an imported list of
addresses (prospects, event sign-ups, diaspora newsletter).

- [x] ✅ `[BE]` **`ContactList` + `Contact` + `EmailSuppression`** — models +
  migrations. Contact: email, name?, status (`subscribed`/`unsubscribed`/`bounced`),
  source, `unsubscribed_at`, `consent_at`; unique `(contact_list_id, email)`;
  global `email_suppressions` wins over any list (`EmailSuppression::suppresses()`).
- [x] ✅ `[BE]` **Email upload → mailing list** — `Admin\ContactListController`
  two-step **preview → import**. Accepts a **paste box** *and* a **CSV file** (first
  col email, optional name; header row skipped). Preview parses → normalises
  (trim/lowercase) → validates (RFC) → **dedupes** within input + against the list +
  against suppression → returns `{valid, duplicate, invalid, suppressed, total}`
  without storing. Import re-checks against live state and inserts only genuine new
  subscribers (`status=subscribed`, `source=upload`, `consent_at` stamped), audited
  `contacts.uploaded`. 20k-row cap. *(4 feature tests: classification counts, insert +
  in-payload/re-import dedup, suppression skip, super-admin-only guard.)*
  *(XLSX + the `UploadBatch` history/rollback table are the remaining nice-to-haves.)*
- [ ] `[BE]` **Campaigns can target a `ContactList`** (not just a user segment) —
  §2's `EmailCampaign.audience` becomes a union type: `user_segment | contact_list`.
- [ ] **Manual add / remove / edit** a contact (remove ✅ via `destroyContact`);
  per-contact unsubscribe.
- [ ] Endpoints: `GET/POST /admin/contact-lists`,
  `POST …/{list}/uploads` (stage → returns preview counts + batch id),
  `POST …/{list}/uploads/{batch}/commit`, `GET …/{list}/uploads` (history),
  `GET/POST/DELETE …/{list}/contacts`. Audited (`contacts.uploaded`, with counts).

---

## 4. Scheduling `[MVP]` `[BE]`

- [x] ✅ `[BE]` **`scheduled_at` + `emails:dispatch-scheduled`** command (scheduled
  every 5 min next to `subscriptions:remind`) picks up due campaigns and sends them.
  Idempotent — `CampaignSender` skips a `sending`/`sent` campaign, so a double tick
  can't double-send. *(Feature test: scheduled campaign holds until due, then sends.)*
- [ ] **Reschedule / cancel** a scheduled campaign before it fires (UI affordance).
- [ ] **Timezone** — the UI sends an ISO instant; a stated-tz picker (default
  Africa/Lagos) is a nicety.
- [x] ✅ Test: a due campaign sends on the next tick; a `sent` one is never re-picked.

---

## 5. Compliance & deliverability `[COMPLIANCE]` `[MVP]`

Non-negotiable — a broadcast tool without these is a legal/reputation risk.

- [x] ✅ **Unsubscribe** — every campaign email carries a signed, no-login
  `/email/unsubscribe/{email}` link + a `List-Unsubscribe` header. Clicking it adds
  the address to the global suppression list and marks matching contacts
  unsubscribed (idempotent); a tampered link is 403. *(Feature-tested.)*
- [x] ✅ **Global suppression list** (`email_suppressions`) — checked on every campaign
  send. Transactional mail (receipts/security) is unaffected; marketing is skipped.
  *(Bounce/complaint ingestion — the SendGrid Event Webhook — is the remaining feed.)*
- [ ] **Consent record** — store when/how a contact opted in (source + timestamp);
  don't blast imported lists that have no consent basis.
- [ ] **COPPA** — never email a learner under 13; route to the parent. Enforced in
  both transactional dispatch and any segment that could include child profiles.
- [ ] **Rate limiting / throttling** — cap send rate to protect sender reputation
  and stay within ESP limits.
- [x] ✅ **Bounce/complaint webhook** — `Webhooks\SendgridWebhookController`
  (`POST /webhooks/sendgrid/{token}`, token-guarded). Bounce / dropped / spamreport
  events add the address to the suppression list and update the recipient's latest
  email-log row; idempotent. *(3 feature tests: suppress + log-status, idempotency,
  wrong-token 403.)* *(Full ECDSA signed-event verification can replace the URL token
  as hardening.)*

---

## 6. Admin portal UI `[MVP]`

Under `/admin/*`, `AdminRoute`-guarded, in a new **"Email"** sub-nav group.

- [x] ✅ **Campaigns** (`/admin/emails`) — `DataTable` list + **Compose** modal
  (subject, Markdown body, audience picker = user-segment role *or* a contact list),
  per-row **Test** + **Send** (send-now or schedule via a datetime). *(Verified live.)*
- [ ] **Campaign detail** — richer recipient stats + cancel-if-scheduled (the
  `GET …/{c}` stats endpoint exists; the drill-down page is the remaining bit).
- [x] ✅ **Contacts** (`/admin/emails/contacts`) — lists, create, per-list contact
  table, and the **email upload** panel (paste box + CSV) with a **staged preview**
  (valid / duplicate / invalid / suppressed) before import. *(Verified live — screenshot;
  caught + fixed a response-shape mismatch that crashed the detail view.)*
- [x] ✅ **Email log** (`/admin/emails/log`) — filterable `DataTable` (recipient /
  type / status / source). *(Verified live.)*
- [ ] **Template preview** in-app (the `mail:preview` command covers design iteration).
- [x] ✅ Frontend plumbing: `adminApi` + `lib/admin/queries.ts` hooks + `lib/api/types.ts`;
  `/admin/emails*` in `REAL_PAGES`, an **Email** `AdminSubNav` group + left-nav items.

---

## 7. Email log & audit — every outbound message `[MVP]` `[BE]`

A single, searchable record of **every email the system sends** — transactional
*and* campaign — so support can answer "did they get it?", compliance can prove
what went where, and admins can debug delivery. Distinct from §2's per-campaign
recipient rows (this spans *all* mail, including one-off transactional sends).

- [x] ✅ `[BE]` **`EmailLog` model + migration** — recipient (`to_email` +
  `user_id`/`contact_id`), `type` (transactional | marketing), `source` (event key
  or `campaign:{id}`), subject, status
  (`queued`/`sent`/`delivered`/`bounced`/`complained`/`failed`), `error`,
  `message_id` (ESP id, for webhook correlation), `queued_at`/`sent_at`,
  `created_by`. Indexed on recipient + `[type, created_at]` + source + message_id.
- [x] ✅ `[BE]` **Central capture** — `App\Listeners\RecordSentEmail`
  (auto-discovered, on `MessageSent`) writes one row per recipient for **every**
  send, no per-email boilerplate; `source`/`type`/`user_id` read from optional
  `X-Mahadum-*` metadata headers a Mailable/Notification may set. *(5 feature
  tests: every send logged, one row per recipient, header capture, branded render,
  preview command.)* Bounce/complaint webhooks (§5) will update rows by
  `message_id` — the correlation column is already captured.
- [x] ✅ `[BE]` `GET /admin/email-log` — paginated + filterable (recipient search,
  type, status, source, date range) + a distinct-sources list. Guard
  `emails.log.view`. *(Feature-tested.)*
- [ ] **Retention** `[COMPLIANCE]` — a configurable prune window (metadata-only by
  default; no body stored today).
- [x] ✅ **Admin UI** (`/admin/emails/log`) — filterable `DataTable` (recipient /
  type / source / status). *(Verified live.)* *(Row-detail modal + resend-failed
  are the remaining niceties.)*
- [ ] Test: sending any mail writes exactly one `EmailLog` row with the right
  type/source/status; a bounce webhook flips its status.

---

## 8. Tracking & analytics `[POST]`

- [ ] Delivery/open/click tracking (ESP webhooks or tracking pixel + link wrap),
  per-campaign open/click rates, a small reports-hub card.
- [ ] A/B subject testing. Segmentation saved as reusable audiences.

---

## Suggested order

1. **§0 foundations** (ESP + branded template + preview) — unblocks everything;
   nothing else is testable without it.
2. **§1 transactional content** — rebrand the 3 existing + verify/reset, then add
   receipt / welcome / login-alert (highest-value new ones).
3. **§7 email log** — land the central capture listener *with* §1 so every send is
   recorded from day one (retro-fitting a log is painful).
4. **§5 compliance core** (unsubscribe + suppression + COPPA routing) — must land
   *with* the first broadcast, not after.
5. **§2 user-segment broadcast** → **§3 contact lists + email upload** → **§4 scheduling**.
6. **§6 UI** grows alongside §2–§4; **§8** is post-launch.

## Definition of done (per email / campaign)
Renders in the one branded template · inline-CSS, client-tested · transactional
vs marketing correctly classified · marketing carries a working unsubscribe +
respects suppression · under-13 routed to parent · **every send writes an
`EmailLog` row** (§7) · batched/queued send is idempotent · sensitive actions
audited · admin UI guarded by `AdminRoute` + `emails.campaigns.manage` · feature
tests assert dispatch + suppression + COPPA routing + log capture · secrets (ESP
keys) env-only, never returned to the client.
