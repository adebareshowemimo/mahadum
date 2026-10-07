# Mahadum360: 38 active audit rows, easiest first in five batches

Requested by Adebare on 7 October 2026 at 02:02 UTC: group the 38 into five batches. His 02:05 UTC direction, "or let arrange into easy fix", supersedes feature-first grouping. This is an ordered plan based on inspected implementations/tests and known blockers, not invented hour estimates. Start with batch 1 after batch execution is authorized. Planning only: no code changes, commit/push, production action or email are performed by this request. Acceptance remains recorded per audit ID.

Source: Mahadum360 Test Results - Fix by 7 Oct 2026.xlsx; Library libfile_d384814a74bc81918140bc576168a324; sheet Test Results. Exact original item titles/statuses/dates are retained in the tables. Indexes are extracted zero-based references, not physical Excel rows. Physical Excel rows remain UNVERIFIED because extraction has no certified coordinate mapping and original XLSX materialization is blocked by the Windows os.setxattr helper limitation.

Companion record: [October 6 audit checklist](Mahadum360_October6_Audit_TODO.md). Verified planning base: codex/beta-feedback-20260903 at f1c6134f9e2ce9f12b45d4e15483a428ec6f8e2e. Independent tmp/ remains untouched. This plan leaves the original checklist, statuses and deadlines unchanged.

## Exact membership and count

125 original detail rows include 70 originally Partial/Not met/Cannot verify. Remove seven implemented source corrections (B28, F12, F26, F30, F6, W1, W11), 15 decision/scope-held rows and ten deferred rows: 70 - 7 - 15 - 10 = 38. The 38 consist of 34 other active rows plus four partially addressed parent IDs: F24, F14, F33 and F34. They are source-row counts, not 38 distinct code defects: duplicate audit reports share fixes and some rows need verification first.

All 38 retain the source target date 7 October 2026. Original statuses: 23 Partial, nine Not met, six Cannot verify. These are historical audit labels, not new findings or delivery promises. Ease means existing-code verification first, then bounded UI work, then broader features, with production/provider/asset-dependent work last. Financial fixture tests can be safe to run while financial code repairs still require careful review. Batch sizes reflect likely shared fixtures and dependencies, not equal effort; unknowns are explicitly named.

| Order | Batch | Priority | Audit IDs | Rows |
|---|---|---|---|---:|
| 1 | Quick checks of features already present | First: lowest-risk verification; recommended starting batch | F18, X5, F3, X3, X4, F8, B53 | 7 |
| 2 | Verify existing family, class and referral flows | Second: existing flows, broader fixtures and stronger ledger/access checks | B24, B25, B13, B29, B51, B52, X1, X2, F2 | 9 |
| 3 | Focused UI, reporting and advert changes | Third: bounded UI/reporting changes; medium or unknown until definitions are settled | B30, F29, B32, B41, B42, F22 | 6 |
| 4 | Teacher onboarding and new family/notification features | Fourth: larger product/workflow changes with explicit design dependencies | B33, F33, F34, B21, B26, B46, B27 | 7 |
| 5 | Account diagnosis, cleanup, video and provider work | Last: production/provider/data/asset evidence-dependent; effort unknown | F24, F14, F16, F28, B10, B48, F23, B45, F20 | 9 |
| | **Total** | | **38 unique IDs; no duplicates or omissions** | **38** |

Recommended first batch: seven verification-first rows with existing validators, columns and regression fixtures. Current code already contains some features the audit described as missing, so check the deployed version/responsive UI instead of adding duplicate code. The order guides work rather than forcing every row to wait: bounded school-admin assignment work in batch 3 can unblock batch 2 manual checks; harmless production/provider/asset inventory can start before batch 5. Unexpected failures are reclassified by risk and remain tied to their original ID. No batch is a promise that every included row will be closed without prerequisites.

## Shared execution and acceptance gates

1. Reproduce each reported failure with disposable fixtures and record verification-only results before deciding code is needed. Correlate duplicate IDs to a shared fix/test, but retain separate PASS/FAIL/BLOCKED/NOT-RUN results for every row. No reproduced defect means a documented no-change finding rather than an invented repair.
2. Implement a reviewable batch on the existing checkout while preserving independent changes. Use isolated SQLite tests, fake outbound delivery/payment gateways and mock browser API data. Preserve ownership/tenant/RBAC, append-only ledgers, approved hearts/streak/tier rules and previously shipped fixes.
3. Run meaningful affected backend/frontend regressions and required repository checks for changed areas: PHP tests/Pint/PHPStan where applicable, frontend tests/TypeScript/build, and whitespace checks. Inspect actual rendered pixels for UI/video claims. Distinguish fixture success from actual-account and production verification; provider fakes cannot prove live fulfillment.
4. Present one local batch with exact audit IDs, local URLs/actions, rollback and per-ID evidence. Adebare confirms each included fix locally, even if tested during one grouped session. Partially confirmed IDs remain open. Existing test accounts or disposable fixtures only; no automatic customer relink, grants, funding or deletion to manufacture a pass.
5. Commit/push only when authorized for that concrete batch. Adebare deploys confirmed changes. Parent owns Deborah QA email, with CC info@mahadum360.com, after the deployment gate. No batch is closed until individual acceptance is recorded; a grouped email or deployment is not a blanket QA pass.

Release baseline: F24a/F33a, copy/roster, F14a, INV-PROMO-1 and F12/F33b deployment are user-reported; the latest report was 22:55 UTC on 6 October for 6e7cedb. Independent live verification and Deborah acceptance remain pending. F12 production receipt migration was not directly observed. F34a Individual labels are pushed in f1c6134, with production deployment unreported. Those shipped subfixes are regression guards and are not newly claimed fixes or completed F24/F14/F33/F34 parent IDs.

## Batch 1: Quick checks of features already present (7 rows)

Priority/order: First: lowest-risk verification; recommended starting batch.

Start with seven rows whose requested behavior has concrete existing code/test support. Verify signup uniqueness, referral channel columns, existing-account rejection/activity search, badge detail/notification and referral velocity handling. Reproduce actual UI/API failures before making small repairs; a passing check produces a no-change finding instead of an unnecessary patch.

| Audit ID | Exact original item title | Original status | Original fix date | Extracted index | Approach now |
|---|---|---|---|---:|---|
| F18 | Sign-up: country code dropdown, unique email and phone (4 Sep #8) | Partial | 2026-10-07 | 83 | Verification first: canonical phone normalization and unique email/phone validation already exist; duplicate-phone regression exists. |
| X5 | Super Admin view per code: count activated, via email, via phone, active, inactive | Partial | 2026-10-07 | 58 | Verification first: Via email/Via phone UI columns and per-channel API regression already exist; inspect versions and responsive visibility. |
| F3 | Super Admin referral view per code (1 Sep) | Partial | 2026-10-07 | 68 | Same already-present referral columns as X5; shared verification, separate acceptance. |
| X3 | Referring an already-active account shows "account already exists" | Can't verify | 2026-10-07 | 56 | Verification first: existing-account API rejection already has email/phone fixtures; suppress outbound delivery. |
| X4 | Referrer dashboard: activation date, code, via email, via phone, status; search by email or phone | Partial | 2026-10-07 | 57 | Verification first: populated activity/contact/search fixtures already exist; inspect actual UI with fixture data. |
| F8 | Badges: Level 0–5 names, award and notification, badge detail with date (4 Sep #4) | Partial | 2026-10-07 | 73 | Verification first: Star Starter, earned-date API and badge/notification fixtures exist; check the player/detail UI without changing badge rules. |
| B53 | Fraud controls (fingerprinting, velocity >15/24h, payment gate) | Partial | 2026-10-07 | 52 | Verification first: 16-signup velocity flag fixture exists; confirm freeze/review behavior, not just command completion. |

Evidence/decisions before dependent work: Inspected RegisterRequest and AuthTest include canonical phone uniqueness and duplicate-number rejection. ReferralCodesAdminPage already renders Via email/Via phone, with AdminReferralCodesTest covering counts. ReferralInvitationTest covers existing-account rejection and searchable/populated activation contacts. GamificationTest covers Star Starter/earned_at and level notification; ReferralTest covers a 16-signup velocity flag. These are inspected implementations/fixtures, not newly executed batch results or production acceptance. Check deployed revision and responsive hidden columns before calling X5/F3 a current missing-feature defect. No production evidence or real payment/message is required to start isolated verification.

Shared regressions: Use focused AuthTest, AdminReferralCodesTest, ReferralInvitationTest, GamificationTest and ReferralTest cases with SQLite and outbound fakes. Inspect the signup, referral-code/activity, badge-detail and fraud-review UI with populated fixtures at desktop/mobile widths. Add or repair tests only for an observed gap; maintain original permissions, badge rules and referral thresholds.

Local/manual QA: /register (isolated form/validation), /admin/referrals, referral activity, /achievements and Admin fraud review, using existing test accounts or intercepted fixtures. Record PASS/FAIL/BLOCKED per ID; X5/F3 share one rendering/data check but remain two audit rows. No real invitation or signup email is sent.

Execution record: [Batch 1 local verification](Mahadum360_Batch1_Verification.md), 6 October 2026 America/New_York. Seven local PASS results; X5/F3 mobile visibility and F8 cache/contrast repairs. Adebare subsequently authorized this batch's origin push and requested Atanda's reviewer status email. Origin verification is recorded with the delivery result; deployment and per-ID acceptance remain pending. These results do not close the production acceptance gates below.

- [x] Per-ID reproduction or no-change evidence recorded.
- [x] Bounded code changes and applicable regression/static/build checks complete.
- [x] Actual UI pixels inspected where relevant; blocked prerequisites explicitly recorded.
- [ ] Adebare local confirmation recorded separately for every included fix.
- [ ] Concrete batch commit/push authorized and origin SHA verified.
- [ ] Confirmed changes deployed by Adebare; migration/version evidence recorded if required.
- [ ] Parent Deborah QA communication and per-ID acceptance recorded.

## Batch 2: Verify existing family, class and referral flows (9 rows)

Priority/order: Second: existing flows, broader fixtures and stronger ledger/access checks.

Exercise existing chore/review, learner assignment/analytics and referral activation/commission flows with controlled seeded records. This batch is mainly verification and investigation, with repairs only for reproduced failures. It needs more setup than batch 1 and its financial assertions deserve careful review, even though fixture tests do not touch real balances.

| Audit ID | Exact original item title | Original status | Original fix date | Extracted index | Approach now |
|---|---|---|---|---:|---|
| B24 | Chore-to-Coin | Partial | 2026-10-07 | 24 | Verification first: funded disposable wallet, chore evidence and parent-controlled release. |
| B25 | Review queue (speaking, assignment, chores) | Partial | 2026-10-07 | 25 | Verify chore/assignment review queues; fix observed gaps. Speaking-recording dependency remains deferred. |
| B13 | Flashcards, games, assignments | Partial | 2026-10-07 | 12 | Verification after teacher assignment; preserve existing flashcards/games. |
| B29 | Speech/quiz analytics grid with drill-down | Can't verify | 2026-10-07 | 29 | Verification first: existing class analytics access/grid and student drill-down; permitted existing speech data only. |
| B51 | User rewards; school commissions on renewals | Can't verify | 2026-10-07 | 50 | Verification first: rewards and school renewal commissions with simulated paid transactions. |
| B52 | 14-day escrow, chargeback, ₦5k floor / ₦50k cap | Partial | 2026-10-07 | 51 | Verification first: existing escrow, chargeback and payout-limit behavior. |
| X1 | Code activates only after the referred person has a paid subscription and has finished 1 lesson + 1 quiz | Can't verify | 2026-10-07 | 54 | Verification first: payment plus lesson plus quiz activation conditions. |
| X2 | Referrer earns 5% of the referred person's purchase value for the first month | Can't verify | 2026-10-07 | 55 | Verification first: existing first-month 5% credit and ledger calculation. |
| F2 | Referral activation rules: paid plan, 1 lesson + 1 quiz, 5% first month, block existing accounts (4 Sep, #1) | Can't verify | 2026-10-07 | 67 | Duplicates X1-X3 requirements; share the transaction fixture, retain separate acceptance. |

Evidence/decisions before dependent work: WalletTest already covers transfers/overdraft and chore-only-on-approval; ClassAssignmentTest and ClassPage analytics scaffolding exist; ReferralTest covers payment+lesson+quiz activation, first-month commission and refunds/escrow, with SchoolReferralTest for school commissions. Full per-student drill-down and assignment review visibility still need actual UI checks. Use a canonical active-school teacher fixture rather than waiting for or mutating Deborah's account. B25 speaking-recording work remains deferred; it cannot enable excluded Phase 2 minors recording. Reuse existing approved financial settings; flag any source/implementation mismatch instead of changing rates/caps.

Shared regressions: Chore submit/reject/request-more/approve, exactly-once coin release and family isolation; learner assignment visibility/submission and teacher review; analytics tenant scoping/drill-down. Simulated subscription/renewal/payment/refund events prove activation prerequisites, first-month 5% attribution, escrow, floor/cap and replay behavior. F2 shares X1/X2/X3 contracts; do not duplicate rewards. Financial code failures may move to a later, explicitly reviewed repair rather than remaining labelled easy.

Local/manual QA: /chores, /reviews, class Assignments/Analytics and referral/payout views with isolated funded wallets and simulated transactions. Check a teacher fixture plus learner submission and known referral ledger totals. No real purchase, payout, notification, account relink or funding of customer wallets. Batch 3 may be needed for school-admin UI acceptance; keep that scenario blocked until its policy/visibility defect is resolved.

Execution record: [Batch 2 local verification](Mahadum360_Batch2_Verification.md), 6 October 2026 America/New_York. Learner tasks/submissions, parent-only class reward release, review replay guards, per-student drill-down and exact escrow expiry repaired. Full suites and final affected/static/build checks passed. Speaking review and provider sandbox settlement remain NOT RUN; the recorded post-activation 30-day referral rule excludes earlier initial purchases and later school renewals. The additive submission migration passed isolated tests and was applied to the confirmed local development database without historical reward backfill. Production migration remains pending. Adebare subsequently authorized the Batch 2 commit and origin push with "commit and push"; remote verification is recorded in the delivery response. Deployment/email and per-ID acceptance remain pending.

- [x] Per-ID reproduction or no-change evidence recorded.
- [x] Bounded code changes and applicable regression/static/build checks complete.
- [x] Actual UI pixels inspected where relevant; blocked prerequisites explicitly recorded.
- [ ] Adebare local confirmation recorded separately for every included fix.
- [ ] Concrete batch commit/push authorized and origin SHA verified.
- [ ] Confirmed changes deployed by Adebare; migration/version evidence recorded if required.
- [ ] Parent Deborah QA communication and per-ID acceptance recorded.

## Batch 3: Focused UI, reporting and advert changes (6 rows)

Priority/order: Third: bounded UI/reporting changes; medium or unknown until definitions are settled.

Repair the reproduced school-admin assignment entry gap under existing policy; add missing school/platform reporting from verified data; expose the already-configurable referral setting in the requested location if authorized; prepare the data advert with honest checkout availability. Pair B30/F29 as one fix and retain acceptance for both.

| Audit ID | Exact original item title | Original status | Original fix date | Extracted index | Approach now |
|---|---|---|---|---:|---|
| B30 | Assignment creation wizard | Not met | 2026-10-07 | 30 | Bounded UI repair candidate: teacher create entry already exists; current ClassPage hides management from school-admin-only accounts. Review API policy before repair. |
| F29 | Teacher can create assignments (2 Aug, 14 Aug) | Not met | 2026-10-07 | 94 | Same assignment visibility/policy chain as B30; teacher flow exists, school-admin behavior requires bounded reproduction. |
| B32 | Dashboard KPIs (avg quiz/speaking score, completion, active seats, top classes/students) | Partial | 2026-10-07 | 32 | Audit-reported school KPI gaps; verify aggregates with the batch 1 assignment/learning fixtures. |
| B41 | Platform metrics (revenue, users, language/AI/billing analytics) | Partial | 2026-10-07 | 40 | Inspected metrics API exposes aggregate languages/revenue only; detailed language/AI analytics scope and data sources still need definition. |
| B42 | Settlements: referral payout %, commissions, telco share, billing success | Partial | 2026-10-07 | 41 | Settings gap candidate: Settlements UI lacks a rate control while ReferralTest covers the existing admin commission setting. Preserve the approved rate policy. |
| F22 | Parent and child adverts should promote data (1 Sep #11) | Not met | 2026-10-07 | 87 | Audit-reported data-advert gap; coordinate honest advert behavior with Buy Data availability. |

Evidence/decisions before dependent work: ClassPage currently uses hasRole(teacher) to expose assignment management although the audit also asks for school admins; teacher create already exists, so do not build a duplicate wizard. Inspect assignment API policy before changing the UI. Metrics definitions/denominators and AI data sources are not yet fully known; B41 is not a guaranteed quick fix. Settlements UI omits a setting control but existing referral-rate settings/tests exist; no new rate is imposed. F22 must not imply working delivery before the provider-dependent batch succeeds; use an honest unavailable state where necessary.

Shared regressions: Own-class/same-tenant permissions, school-admin/teacher create and learner submissions; school and platform metric denominators with known quiz/assignment/payment fixtures; actual data for language/AI metrics; protected existing settings without financial-policy changes; advert destination/availability. Preserve previously shipped F33a/F33b/F34a and household guards.

Local/manual QA: Class Assignments, school dashboard, /admin, Settlements and parent/child banners. Inspect actual pixels and known-data totals; show per-ID acceptance and any metric-definition/provider dependency. B32 speaking averages use only permitted existing data, with no new minors recording.

Execution record: [Batch 3 local verification](Mahadum360_Batch3_Verification.md), 7 October 2026 America/New_York, on Batch 2 origin baseline `943d4499e26a810635df9cdbe4aa2649be6763a7`. B30/F29/B32/F22 local PASS; B41 PARTIAL (deferred AI and unavailable historical subscription receipts), B42 PARTIAL (approved telco split absent). Recorded-data definitions, desktop/mobile evidence, required receipt migration and targeted data-ad seeder are documented. Local migration/seed completed. Adebare subsequently authorized this concrete Batch 3 commit and origin push; remote verification is recorded in the delivery response. Production deployment and reviewer email remain pending. Original row labels and individual acceptance gates remain.

- [x] Per-ID reproduction or no-change evidence recorded.
- [x] Bounded code changes and applicable regression/static/build checks complete for implemented scope; B41/B42 limitations remain explicit.
- [x] Actual UI pixels inspected where relevant; blocked prerequisites explicitly recorded.
- [ ] Adebare local confirmation recorded separately for every included fix.
- [ ] Concrete batch commit/push authorized and origin SHA verified.
- [ ] Confirmed changes deployed by Adebare; migration/version evidence recorded if required.
- [ ] Parent Deborah QA communication and per-ID acceptance recorded.

## Batch 4: Teacher onboarding and new family/notification features (7 rows)

Priority/order: Fourth: larger product/workflow changes with explicit design dependencies.

Complete school Students/Teachers directories and legitimate invite/setup paths, the remaining Teacher/School signup and Institution classification, weekly league size/family cheer, family challenges/pools and automatic notifications. These need more cross-feature code and policy review than existing-flow verification.

| Audit ID | Exact original item title | Original status | Original fix date | Extracted index | Approach now |
|---|---|---|---|---:|---|
| B33 | Student and teacher rosters; class enrolment | Partial | 2026-10-07 | 33 | Audit-reported Students/Teachers directory and invite gaps; complete under established school authority. |
| F33 | Create or invite teachers, and assign one after the class is made (raised during this review) | Not met | 2026-10-07 | 98 | Partial fix shipped: assignment validator and class controls. Teacher onboarding and live class-loading diagnosis remain open. |
| F34 | Sign-up account types match admin profiles (raised during this review) | Not met | 2026-10-07 | 99 | Partial fix pushed: Individual labels. Teacher/School distinction and Institution classification still need a bounded design. |
| B21 | Weekly leagues (30 users) + family cheer | Partial | 2026-10-07 | 21 | Verify existing 30-member league grouping; reproduce the reported family-cheer gap. |
| B26 | Coin transfer, family challenges, low-balance alerts | Partial | 2026-10-07 | 26 | Verify existing coin transfer; reproduce reported family-challenge/low-balance gaps before implementation. |
| B46 | Coin economy, parent wallet, group coin pools | Partial | 2026-10-07 | 45 | Audit-reported missing group coin pools; define ownership, conservation and approval behavior before implementation. |
| B27 | Notifications (push, SMS, WhatsApp, email) | Partial | 2026-10-07 | 27 | Reported push/automatic-alert gaps; verify existing channels with delivery fakes, not real sends. |

Evidence/decisions before dependent work: F33a/F33b assignment controls are shipped subfixes; teacher onboarding and Deborah's exact class-loading cause remain open. F34a Individual labels are pushed with deployment unreported; Teacher/School/Institution behavior is not complete. Agree verified school ownership/invitation acceptance and account classification without auto-granting roles or broadening membership. LeagueService explicitly leaves 30-learner bucketing to later scheduling work; family-cheer readiness is unverified. Missing pool/challenge ownership and absent low-balance/notification thresholds need definitions or approved existing settings. Keep approved hearts/streak/tier rules intact and do not import excluded B34 inactivity thresholds.

Shared regressions: Parent+Teacher and parent-school-parent transitions retain family/learner IDs; active/inactive/foreign-school boundaries; invite acceptance/replay and class assignment. League grouping/rank integrity, conserved pool/transfer balances, parent-only approval, notification trigger/idempotence/unsubscribe and captured delivery. Extend existing flows without replacing verified controls or inventing automatic privilege grants.

Local/manual QA: School rosters/teacher setup, /classes and signup/admin classifications; family challenge/pool/cheer surfaces and notifications using isolated fixtures. Deborah's failed /classes status/error, X-Organization-Id, /me context and deployed revision are required for her live diagnosis. Release independent confirmed sub-scopes separately if that evidence is still pending; do not mark full F33/F34 closed.

**7 October local candidate:** [Batch 4 verification](Mahadum360_Batch4_Verification.md) records all seven IDs. Adebare confirmed parent-selected, initially disabled alert thresholds and parent-managed, conserved-coin pools. Directory/invite, Teacher/School/Institution, weekly cohorts/cheers, challenges/pools and notification code/fakes are implemented locally. F33 production diagnosis and B27 real provider/browser/device delivery remain unverified. Earlier dependency statements above record the starting assessment; they are superseded for the implemented local scopes, not production acceptance.

- [x] Per-ID reproduction or no-change evidence recorded.
- [x] Bounded code changes and applicable regression/static/build checks complete.
- [x] Actual UI pixels inspected where relevant; blocked prerequisites explicitly recorded.
- [ ] Adebare local confirmation recorded separately for every included fix.
- [ ] Concrete batch commit/push authorized and origin SHA verified.
- [ ] Confirmed changes deployed by Adebare; migration/version evidence recorded if required.
- [ ] Parent Deborah QA communication and per-ID acceptance recorded.

## Batch 5: Account diagnosis, cleanup, video and provider work (9 rows)

Priority/order: Last: production/provider/data/asset evidence-dependent; effort unknown.

Investigate affected Lucy household/leaderboard identities; prepare an exact reversible demo-record cleanup; fix actual video-watch completion and real rendition availability; verify Buy Data and approved telco behavior through genuine provider contracts/sandbox evidence. Begin harmless inventories earlier if useful, but avoid promising easy implementation or production success.

| Audit ID | Exact original item title | Original status | Original fix date | Extracted index | Approach now |
|---|---|---|---|---:|---|
| F24 | Family, Wallet and Reviews load for every parent (raised during this review) | Partial | 2026-10-07 | 89 | Partial prevention fix shipped; affected Family/Wallet/Reviews ownership diagnosis and Lucy reconciliation remain open. |
| F14 | Leaderboard shows cumulative XP (4 Sep) | Partial | 2026-10-07 | 79 | Partial deduplication fix shipped; verify learner IDs and cumulative totals without merging same-name profiles. |
| F16 | Dummy data cleanup (4 Sep #7) | Not met | 2026-10-07 | 81 | Read-only dummy-data inventory first; prepare a reversible, explicitly identified cleanup. |
| F28 | Landing page advert banners with management suite (BRD) | Partial | 2026-10-07 | 93 | Same advert cleanup inventory as the advert portion of F16; preserve legitimate adverts. |
| B10 | Cultural videos 60–180s, dual captions, 240/360/720 | Partial | 2026-10-07 | 9 | Playback-end gate uses ended; actual watch evidence and video rendition readiness need deeper reproduction/asset inspection. Full effort unknown. |
| B48 | In-app data top-up | Not met | 2026-10-07 | 47 | Local DataBundleService contains catalog/checkout/validate/vend/requery code; provider authentication and real contract/fulfillment remain unverified. |
| F23 | Buy data checkout | Not met | 2026-10-07 | 88 | Same provider-dependent Buy Data investigation as B48; local service code is not proof of working carrier delivery. |
| B45 | Telco VAS ₦50/day (02:00 charge, grace, STOP→3600) | Not met | 2026-10-07 | 44 | Audit-reported missing telco plan/configuration; prove supported sandbox charge/STOP/renewal behavior. |
| F20 | Telco: default Individual, Level 1 only (4 Sep #10) | Partial | 2026-10-07 | 85 | Verify telco Individual classification and Level 1 access without changing household roles/ownership. |

Evidence/decisions before dependent work: F24a prevention and F14a learner-membership deduplication are shipped; actual Lucy IDs/ownership/history remain unknown and cannot be auto-relinked or name-merged. Cleanup needs exact demo IDs, dependencies and rollback, preserving real accounts/ledgers/content. Video uses ended to unlock while counting contiguous deltas; seek bypass and genuine quality assets need deeper reproduction. Local DataBundleService has catalog/checkout/validation/vend/requery paths, but no provider authentication, sandbox contract or carrier delivery was verified here; previous planning docs alone do not prove fulfillment. Credentials/security configuration and live charges retain the existing approval boundary. B45/F20 reuse approved telco grace/access, without importing excluded B18's new 48-hour requirement.

Shared regressions: Read-only ownership/learner identity comparison; no membership/profile deletion or same-name merge; cleanup dry-run and reversible target isolation; video seek/pause/resume/watch coverage plus server acceptance and genuine rendition switching. Catalog/error handling, payment-versus-delivery separation, signed/idempotent events, retries/timeouts and no false fulfillment. Approved telco access/STOP/sandbox schedules preserve existing household roles and ownership.

Local/manual QA: Affected family/leaderboard diagnosis and cleanup preview; actual lesson-player pixels/assets; /billing/data, telco billing and Plans with authorized sandbox evidence. Record catalog loaded, payment verified and delivery confirmed separately. Missing production evidence/assets/provider contracts mean BLOCKED or NOT-RUN, never a fabricated pass; no real purchase, deletion, account repair or credential change during planning/tests.

**7 October local continuation:** [Batch 5 verification](Mahadum360_Batch5_Verification.md) records all nine IDs. Required-upload watch coverage and real-rendition/caption selection are repaired and tested locally. Read-only account, ledger, demo-record/dependency, asset and provider diagnostics are available. Fresh Monnify sandbox catalogue access succeeded. Lucy production identity, exact cleanup targets, missing encoded assets, payment/carrier delivery and the conflict with retired airtime enrollment remain open. No deletion, relink, production write, credential change, purchase, commit or push occurred.

- [ ] Per-ID reproduction or no-change evidence recorded.
- [ ] Bounded code changes and applicable regression/static/build checks complete.
- [ ] Actual UI pixels inspected where relevant; blocked prerequisites explicitly recorded.
- [ ] Adebare local confirmation recorded separately for every included fix.
- [ ] Concrete batch commit/push authorized and origin SHA verified.
- [ ] Confirmed changes deployed by Adebare; migration/version evidence recorded if required.
- [ ] Parent Deborah QA communication and per-ID acceptance recorded.

## Excluded rows and follow-ups

The following are not members of any of the five batches. Dependencies may be mentioned as constraints or regression guards; that does not pull their work into this plan.

- **15 decision/scope-held rows:** B19, F13 (hearts conflict); B18-A (streak activity/tiers); B14, X7, X8, X9, X10, X11, X12 (Stories/Proverbs/Trivia scope); W5 (starting-level control); W15 (previously approved campus wording conflict); F21 (email-module/Fluent CRM decision); F32 (school-wide join ownership design); B34 (seat inactivity threshold).
- **Ten deferred rows:** October 15: B4, B5, B6, B18. Phase 2: B9, B11, B12, B16, B22, B39. Keep the interim practice invitation and do not enable minors recordings or a new 48-hour streak rule through another row.
- **Seven implemented source corrections awaiting acceptance:** B28, F12, F26, F30, F6, W1, W11. Reuse as regression guards; do not count them among the 38.
- **Other reconciliation work outside the 38:** F17 corrected but awaiting authenticated/manual acceptance; F25/F31/X6 follow-ups; missing B35 clarification; additional INV-PROMO-1 regression/acceptance. Original source/reconciliation count remains 126 checklist sections.

## Validation of this planning artifact

- [x] Derived exact active set from all 125 extracted source rows and the prior reconciliation exclusions.
- [x] Five ordered batches contain 7 + 9 + 6 + 7 + 9 = 38 unique original IDs.
- [x] Every active ID appears exactly once; no excluded/deferred/implemented ID was added.
- [x] Exact original titles, source statuses, dates and extracted indexes preserved.
- [x] Existing audit checklist unchanged; no application code, commit/push, production or email action.
