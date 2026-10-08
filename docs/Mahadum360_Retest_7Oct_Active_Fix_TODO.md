# Mahadum360 — active fix TODO after the 7 October retest

Created at Adebare’s request: create a TODO for the audited issues and exclude deferred items. “Differ items” is interpreted as deferred/held scope. The execution record below tracks subsequent fixes; the original audit observations remain unchanged.

**Scope: 28 grouped work items covering 58 active audit IDs.** The audit has 82 unresolved source rows. This list excludes 23 deferred/held IDs and leaves W15 as source reconciliation only: 82 − 23 − 1 = 58. Mixed rows B9/B18/B25 retain only current invitation/completion/review work; their deferred recording/protection portions stay excluded. Duplicate IDs may appear in more than one task when acceptance spans code and deployment. Counts are source-row coverage, not counts of distinct bugs.

Baseline: `codex/beta-feedback-20260903` at `876ebf88760c6a3a09c10210537a437194a99106`. Batches 1–5 are pushed; deployment and per-ID retest acceptance are not established by that fact. Prior local audit checks: 41 tests passed, 385 assertions. Existing tests do not establish missing new requirements.

Source: [full pending-fix audit](<C:/Users/adeba/.codex/.chatgpt-projects/g-p-6a3f2fbabbd88191b8ba56ff62fb709a/outputs/retest-audit-20261007/Mahadum360-Pending-Fix-Audit-7-Oct-2026.md>), based on the supplied retest workbook, retest report and account-role recommendation. Source observations remain separate from the documents’ proposed instructions. Original statuses are preserved in the coverage table below; no new dates or delivery promises are assigned here.

## Order of work

### Execution record — 7 October 2026

Checkboxes marked complete mean the code requirement was implemented and locally verified using reproduced accounts. They do not mean the changes are deployed or that historical production data has been repaired. No production account, balance, learner link, duplicate profile or invoice has been changed. This local fix batch is prepared for version control; production deployment remains pending.

| Task | Current evidence / remaining acceptance |
| --- | --- |
| T01 | School-scoped identity, reorder-safe replay, optional StudentId, ambiguity errors, deleted-profile protection and serialized imports implemented. Repeated StudentIds within CSV/inline imports now reject the whole batch instead of silently merging rows; corrected-file/replay checks preserve learners and seats. Replay/isolation tests pass; actual simultaneous MySQL retry acceptance remains. |
| T02–T03 | Whole-file preflight, independent split names, L0–L5 validation and course-level placement pass. Duplicate CSV headings and records with missing/extra columns are rejected before writes; physical starting-line errors remain accurate after quoted multiline fields. Placement does not unlock paid content or overwrite age data. |
| T04–T05 | Import class picker, refreshed directory and distinct total/in-class/unassigned counts implemented and tested. Adding an existing learner to a class now refreshes the already-loaded Students directory and school dashboard; class search displays roster placement rather than age band. The import → search → repeated add → directory/dashboard regression confirms one profile/seat, one course enrollment and distinct counts when joining two classes. Production deployment and historical count reconciliation remain. |
| T06 | Server readiness metadata filters wallet options; unavailable gateways fail before creating funding records. SPA funding retries now retain a reference per account/amount/provider across reloads in the same browser session, recovering a successful checkout whose response was lost. Success or a definite non-conflict 4xx rejection clears it; disabled storage retains in-memory safety. API replay checks confirm one provider initialization/funding record and one credit across repeated signed payment notifications. Live provider funding acceptance remains. |
| T07 | User confirmed parent-funded chore, study and assignment transfers. Chore and both assignment paths debit/credit atomically and remain pending when unfunded. Self-approval is now blocked for chores and uploaded assignments, matching class assignments. Chore approval checks the learner still belongs to the chore’s family; both assignment review paths recheck current learner ownership under a row lock before moving funds. Direct service tests include study source and repeat safety. There is no study timer/award caller in this checkout, so the reported historical 10-coin award is untraced. See the reward funding decision document. |
| T08–T09 | Adult direct-learner capabilities, protected Family route, friendly redirect, Home account type and shared admin/filter classifications implemented. Family owners retain Family identity when teaching; both role consoles remain. Minors, unknown-age and school learners receive no new adult grant. |
| T10 | Reproduced missing-household parent access. Existing idempotent admin Parent grant repairs all three pages and preserves existing/deleted-household safeguards. Six account-repair tests pass. Lucy’s actual production state is not established; no guessed relink was performed. |
| T11 | Add child creates published selected-language paths transactionally; paid content remains locked. Rebuilding the path creates no duplicate nodes. |
| T12 | Shared safe contacts for both invitation routes, household-owner inclusion for operated child profiles, adult/staff checks, player selector and readable errors implemented. Home invitations now select only published path lessons. Both private-link flows return a readable unavailable response for withdrawn/deleted lessons without marking them opened or accepted, avoiding stale content exposure and null-relation errors. Private-link and recipient tests pass; actual configured email delivery remains. |
| T13 | Prevented duplicate completion requests on StrictMode effect replay, retained the first award response and displayed badge names. Server completion locks the learner/progress and rechecks status before awarding XP. Completion, XP/event, path advancement, level sync, streak and badges now commit together under that lock; badge notifications dispatch after commit. Retry tests verify one XP award/First Steps/streak, a stale-request interleaving and rollback/retry after event or badge persistence failure. A failed second badge write rolls back the first award too, so the retry returns both original badges with no premature notification. Production BadgeSeeder state, actual concurrent MySQL requests and the reported learner completion remain unverified. |
| T14 | Approved +N coins, To do, Needs more and Rejected remain visible; adult profile copy is neutral. Full chore decision cycle and approved-history tests pass. |
| T16–T18 | Signup country selector reused for referrals with shared server normalization; duplicate/existing-account checks pass. Reported Login/Refer prose uses DISPLAY_NAME. Passive Watch label has no play symbol; coverage/seek gate tests still pass. |
| T15 | Implemented preview-first Firstname/Lastname/Email/Phone CSV invitations through the shared single-invite service. Whole-file validation, physical row errors, normalized phones, pending/accepted replay skips and tenant/membership guards pass. Verified acceptance preserves existing Family/phone/roles and populates the directory/picker. Actual mail delivery and school deployment remain part of T25. |
| T19 | Added an explicit frontend assertion that Apply promo never invokes payment. Existing fee/discount/no-stacking backend tests pass. Invoice checkout now rejects disabled/unconfigured providers before recording a checkout reference, preserving promo eligibility and invoice state. Configured checkout uses the discounted amount and only then locks promo application. Production invoice #12 and deployed versions remain unverified. |
| T20 | Reproduced seat purchase retry issuing a second allocation/invoice. Added a required Idempotency-Key, stored organization/user-scoped response and atomic allocation/invoice/audit transaction. Replay preserves original invoice/expiry; changed details conflict; intentional new purchases remain possible; failure rolls back everything. Pending SPA references now survive remount/reload in browser session storage, scoped to user, school and purchase details; successful purchases clear the reference. Disabled storage falls back to in-memory retry safety. Existing production invoices #11/#12 and actual simultaneous MySQL retries remain unverified. |
| T22 | Disabled false availability/verification in the unconfigured ad fallback and reverify before redemption, including previously shown placeholders. Tests prove unavailable ads cannot grant hearts or consume an impression. Real video/provider integration and the one-heart/full-refill decision remain pending; no banner/countdown is treated as provider proof. |
| T23 | Data-ad messaging now follows the active/viewed child profile and server financial capability. Parent-operated child views have no checkout link; eligible adult Individuals can browse Buy data. Billing, Buy data and Tasks remain excluded; premium/staff suppression retained. Deployed campaign/placement acceptance remains. |
| T25 | Full CSV invitation → verified acceptance → class teacher assignment → learner submission → teacher grade → funded parent review passes locally. Existing Family/roles preserved; approval before grading and teacher self-approval denied; insufficient parent funds leave review pending; retry releases one conserved transfer. Fixed teacher display of existing uploaded audio/video and added grading policy requiring current active teacher membership. Removed stale tenant binding between requests; inactive/removed/changed teacher membership cannot grade existing work. Deployment and real school/mail acceptance remain pending. No recording feature added. |
| T26 | Notification dispatch failures no longer turn committed chore/assignment submissions, teacher grades, funded parent approvals or earned badges into failed API responses; errors are reported and reward replay guards remain. Badge/level-up dispatch failure no longer hides First Steps in the completion response. The scheduled family-alert command continues after a failing family, reports attempt/failure totals and returns failure when any evaluation fails. Failed family-alert dispatch episodes reset for a later retry; successful families are not resent. This episode retry does not add automatic redispatch of failed badge/approval notifications. Failure-injection tests pass. Existing league/pool/challenge behavior and actual opted-in mail/text/push delivery still require deployment acceptance. |
| T28 | Fixed recovery from a failed uploaded file by selecting another available quality; required coverage remains locked. Quality changes retain the playhead on completed clips, including positions near the beginning/end, and clamp to the replacement file duration. Eleven video-player tests pass. Production source/rendition/caption inventory and actual asset playback acceptance remain pending; no transcoding or caption-authoring pipeline added. |
| T21, T24, T27 | Still pending provider, reviewed production-data or deployment acceptance. Deferred items remain excluded. |

Validation: full backend run passed 516 tests with one skipped (3,198 assertions); full frontend run passed 321 tests. Subsequent affected backend run passed 50 tests/398 assertions, including final practice and reward fixtures; affected frontend run passed 41 tests. PHPStan level 5 and frontend production build passed. Pint applied style fixes. Two new roster migrations must be applied with deployment. Live gateway, data vend, mail/push and production account acceptance are not inferred from local tests.

Continuation deployment requirements: apply the teacher-invitation phone and seat-purchase receipt migrations as well as the preceding roster migrations. Deploy the updated SPA/API together: seat purchases now require an `Idempotency-Key` header, reused for retries of the same operation. The SPA retains pending keys across remounts/reloads within a browser session; a new session or disabled storage still requires checking the invoice list after an uncertain response. No existing invoice or term was rewritten. Unconfigured rewarded ads now return unavailable instead of granting placeholder rewards.

Continuation validation: full backend suite passed 530 tests with one skipped (3,318 assertions); all 330 frontend tests passed. PHPStan level 5, Pint style check, frontend production build and OpenAPI YAML parsing passed. Ten of 28 task checkboxes now record locally completed code requirements; deployment and source-status acceptance remain separate. This continuation is prepared for version control and has not been deployed.

Latest T25/T20 continuation validation: full backend suite passed 532 tests with one skipped (3,361 assertions); all 336 frontend tests passed. The focused teacher/assignment/tenant suite passed 33 tests with 255 assertions. PHPStan level 5, Pint and frontend production build passed. Teacher audio/video evidence, denied grading capability, purchase remount/account isolation and blocked browser-storage cases have frontend coverage. This latest continuation is prepared for version control and undeployed; T25 remains open for real school/mail acceptance, and T20 remains open for the historical production invoice investigation. No additional task checkbox is closed by these local checks.

T13/T28 continuation validation: 36 affected backend tests passed with 305 assertions, covering completion retries, gamification, video tracking/resume, access, parent self-learning, referral economy and xAPI. All 339 frontend tests passed, including 11 video coverage/quality/recovery cases. PHPStan level 5, Pint and the production build passed. The full backend suite was not rerun for this bounded continuation; the preceding full run remains the baseline. This continuation is prepared for version control and undeployed. T13/T28 remain open for their production learner and real-asset acceptance; deferred hearts/protection/transcoding/recording changes remain excluded.

T26 continuation validation: the combined affected backend regression run passed 67 tests with 604 assertions, including family alerts, pools/challenges/cheers, notification channels, assignments, teacher onboarding and the preceding learning/video changes. After adding badge-dispatch failure handling, 11 completion/gamification tests passed with 139 assertions. Earlier frontend/build results remain valid because this continuation adds backend changes only. The queued-notification failure tests use simulated dispatch failures; no live delivery is claimed. This work is prepared for version control and undeployed, and T26 remains open for its deployment/provider acceptance.

T01/T02 continuation — 8 October 2026: prevented repeated StudentIds from silently merging CSV/inline rows, rejected duplicate CSV headings and incorrect column counts, and corrected physical error lines after quoted multiline fields (LF/CRLF). Invalid imports leave profiles, identities, class memberships and seats unchanged; corrected-file imports and replay safety pass. The affected school/roster suite passed 41 tests with 301 assertions; all 339 frontend tests passed. Pint, PHPStan level 5, the frontend production build and OpenAPI YAML parsing passed. No frontend code or migration was needed. These changes remain undeployed; T01 remains open for actual concurrent MySQL acceptance. Task/source completion counts are unchanged.

T04/T05 continuation — 8 October 2026: reproduced stale directory/dashboard state after adding a class learner and age-band data incorrectly returned as school placement in class search. Both regression tests failed before the fix and pass after it. The affected school/roster suite passed 42 tests with 328 assertions, and the affected frontend suite passed 31 tests. Pint, PHPStan level 5 and the production build passed. No migration or production-data adjustment was needed. This continuation remains undeployed; T04/T05 remain open for deployment and historical count reconciliation. Task/source completion counts are unchanged.

T06 continuation — 8 October 2026: reproduced the SPA creating a fresh wallet funding key after a lost checkout response. Retained uncertain attempts in browser session storage, scoped to account/amount/provider, with in-memory fallback. Successful recovery clears the attempt for a later intentional top-up; definite non-conflict 4xx rejection also clears it so a corrected attempt does not replay a cached validation error. Four funding retry tests pass, including remount, account/provider/amount separation and blocked storage. API integration confirms one provider initialization and funding record on replay, changed-body conflict, no pre-payment credit and one wallet credit across repeated signed payment notifications. The affected backend suite passed 25 tests with 136 assertions; all 344 frontend tests passed. Pint, PHPStan level 5 and the production build passed. No API/migration or real payment/provider change was made. This uses the existing server response cache (24 hours); it does not establish durable or simultaneous-request idempotency, recovery after server-side initialization failures, or recovery in a new browser session. Changes remain undeployed; T06 remains open for live provider acceptance.

T07 continuation — 8 October 2026: reproduced self-approval of a chore and an uploaded assignment, plus an old chore debiting a learner’s new household after a family move. All three cases succeeded incorrectly before the fix and are now blocked without changing balances, pending status or the review decision. Chore approvals lock and validate the current learner/family relationship and deny the beneficiary; uploaded assignments also deny self-review, and both assignment review paths recheck the current learner under a row lock before releasing funds. The affected wallet/reward/assignment/onboarding/notification suite passed 25 tests with 196 assertions; the chore lifecycle/family regression suite passed another four tests with 76 assertions (29 tests, 272 assertions total). Pint and PHPStan level 5 passed. Frontend code is unchanged; the preceding 344-test frontend run and build remain the baseline. No migration, production relationship or historical ledger adjustment was made. Changes remain undeployed. T07 remains open for the historical study award trace and production acceptance; actual concurrent MySQL behavior was not exercised by these local tests.

T12 continuation — 8 October 2026: six new regression cases reproduced Home selecting unpublished lessons and existing course/tone invitations exposing withdrawn content or returning server errors after lesson deletion. Home now skips unpublished path lessons; if none remain, it rejects before invitation/mail creation. Both link open/accept endpoints return 410 with readable copy when the target lesson is withdrawn/deleted, preserving opened/accepted state and avoiding acceptance audit entries. All 21 affected practice-invitation, lesson-access and parent-learning tests passed with 124 assertions. Pint and PHPStan level 5 passed. Frontend code is unchanged; the preceding 344-test frontend run and build remain the baseline. No migration, media or production content was changed. This continuation is prepared for version control and undeployed; T12 remains open for actual configured mail delivery and deployment acceptance.

T13 continuation — 8 October 2026: reproduced a failure persisting the second earned badge after lesson/XP/streak and the first badge had already been saved. Lesson completion now commits progress, XP/event, path advancement, learning-level sync, streak and all earned badges in one transaction under the existing learner lock. Badge/level-up notifications run after commit and retain dispatch-failure reporting. Failure injection verifies the entire completion rolls back, sends no premature notification and retries with First Steps and Star Starter in the response; later replay adds no XP/badges and preserves the earned date. All 48 affected completion/gamification/learning/access/referral/xAPI/video/practice-invitation tests passed with 405 assertions. Pint and PHPStan level 5 passed. Frontend code is unchanged; the preceding 344-test frontend run and build remain the baseline. No migration or qualifying-activity, hearts, grace/Shield or tier policy changed. This continuation is prepared for version control and undeployed; T13 remains open for deployed seeder/learner acceptance and actual simultaneous MySQL requests. Automatic retry of failed notification delivery is not added.

T19/T20 continuation — 8 October 2026: reproduced three unavailable invoice checkout paths returning success: payments disabled, an explicitly selected unconfigured provider and an unconfigured default provider. Positive invoices now require a configured, enabled provider before initialization or checkout-reference mutation; rejection leaves status, amount and fee lines intact and allows a subsequent promo application. No alternate provider is selected silently. The configured-provider regression verifies Apply makes no payment request, Pay initializes the discounted amount, the invoice stays unpaid pending confirmation and further promo application is rejected after checkout starts. Existing zero-total settlement remains supported without a provider. All 48 affected billing/payment/promo/seat/school tests passed with 311 assertions; Pint and PHPStan level 5 passed. Frontend code is unchanged; the preceding 344-test frontend run and build remain the baseline. No migration, real payment or historical invoice/term change was made. This continuation is prepared for version control and undeployed; T19/T20 remain open for production acceptance and historical invoice investigation. Task/source completion counts are unchanged.

Continue T01–T04 roster integrity, T06 wallet availability, T08–T14 account/practice/task defects, and T19–T21 billing/provider diagnosis against the remaining evidence above. T07 uses the confirmed parent-funded rule. P1 means current integrity/access or core-flow priority; P2 means follow-up/acceptance, not deferred scope.

Quick UI tasks T16–T18 can proceed independently. T05/T24 depend on roster diagnosis; T25 depends on teacher deployment and later T15 for CSV coverage. T22 needs the vendor/refill decision. An active task with a prerequisite remains on this list; it is not permission to invent that decision or alter production data.

## P1 — integrity, access and core flows

- [ ] **T01 · Prevent duplicate roster imports and repeated seat usage** — Code; source IDs: B28, N15.

  Use stable school-scoped learner/import identity, safe replay handling and explicit ambiguity errors for blank-email rows. Do not merge learners by name alone. Done when the same file submitted twice creates no extra profiles or seats, concurrent retries are safe, and two legitimate same-name learners remain distinct.

- [x] **T02 · Validate the complete roster before writing** — Code; source IDs: B28, N16.

  Require first and last name independently, preserve physical CSV row numbers, and validate the intended file before creating profiles/enrolments or changing seats. Done when any invalid row produces readable row errors and leaves the import unchanged; a corrected file imports successfully.

- [x] **T03 · Correct roster levels and their storage** — Code; source IDs: B28, N17.

  Replace A1/A2 samples with L0–L5, reject invalid values and map the selected level to the relevant course/enrolment instead of age_band. Done when L0–L5 follow the defined course mapping, invalid levels are rejected and age data is preserved.

- [ ] **T04 · Make imported students visible and assignable to classes** — Code + deploy; source IDs: B28, B33, N18.

  Deploy the existing Students directory and add a class picker on import or an Add to class action for unassigned students. Done when every imported learner appears once, has a visible class/unassigned state, and can join a same-school class without creating another profile or consuming another seat.

- [ ] **T05 · Explain school student and class totals** — Code + reconcile; source IDs: N12.

  Display total school profiles, learners assigned to classes and unassigned learners using consistent definitions. Reconcile inflated counts after T01–T04 and reviewed cleanup. Done when directory/dashboard totals reconcile and a learner in multiple classes is counted once in the school total.

- [ ] **T06 · Show only usable wallet payment options** — Code + provider check; source IDs: B23, N11.

  Filter wallet choices using configured/enabled gateway capability and show a helpful unavailable state. Done when unconfigured Paystack/Flutterwave cannot be selected and an enabled provider completes the authorized top-up/webhook flow with exactly one ledger credit.

- [ ] **T07 · Resolve parent/child reward funding and trace the 10 coins** — Investigate; funding decision first; source IDs: B24, N10, N19.

  Identify kam by exact profile and inspect ledger source/reference for the reported study award. Confirm whether chore, study and assignment rewards should issue coins or transfer existing parent funds. If parent-funded is confirmed, implement locked atomic parent debit/child credit, insufficient-funds hold/block and retry safety. Done when each applicable reward follows the confirmed rule and both balances/ledger entries reconcile. Any historic adjustment must use reviewed compensating entries; optional study progress bars are outside this list.

- [x] **T08 · Repair adult Individual navigation and protected pages** — Code + access checks; source IDs: N21, N22.

  Align adult Individual Billing, Refer & earn and Buy data menus with API capabilities and account ownership. Redirect ineligible /family access with a friendly message. Done when an eligible adult Individual can use the intended pages without 403/load errors and child profiles remain unable to use adult financial/referral controls. Define eligibility before changing grants; do not grant every student permission.

- [x] **T09 · Display account type separately from roles** — Code + identity clarification; source IDs: F34.

  Use consistent account-type labels on Home, admin lists/filters and relevant reports. Decide how a Family owner who also teaches should be classified before changing derived-type precedence. Done when Individual/Family/Teacher/School/Institution labels are consistent for the agreed model and parent+teacher keeps both consoles. Removing the existing Teacher signup choice or adding a new institution_admin role is outside this fix.

- [ ] **T10 · Restore Lucy’s Family, Wallet and Reviews access** — Account investigation; source IDs: F24.

  Obtain exact user ID/email, deployed revision and sanitized failing requests; inspect family ownership/member and learner links with the read-only diagnostic. Repair only an established relationship defect. Done when all three pages load for the intended account and cross-family access remains denied. Do not create a new household or guess a relink to manufacture a pass.

- [x] **T11 · Carry Add child language choice into learning** — Code; source IDs: N1.

  Persist the chosen language through the child profile and enrolment/path setup using published content and current access rules. Done when a child created with Igbo opens the corresponding learning path without being asked to select it again; retries do not duplicate enrolment.

- [ ] **T12 · Restore practice invitations and align the two invitation flows** — Code + regression diagnosis; source IDs: B9, N5, N6, N7.

  Reproduce the failing endpoint and capture eligibility, active lesson, response and mail/queue state. Align Home and in-player candidate selection and use readable eligibility errors. Done when eligible family/school adults can be selected and receive/open the private expiring lesson link, unrelated recipients are denied consistently, and an empty list explains the next step. A new parent-managed per-child whitelist requires a defined consent/eligibility policy before implementation. Recording remains excluded from B9.

- [ ] **T13 · Fix missing completion streak and First Steps outcomes** — Completion investigation; source IDs: B18, N8.

  Trace the exact learner, required component state, final lesson-completion request, badge seed/evaluation and UI invalidation. Done when finalizing a first eligible lesson gives First Steps with earned date/pop-up and starts the streak once, including retry/resume cases. Retain the current qualifying-activity rule unless separately changed. Weekly/monthly tiers, grace/Shield changes and purchasing protection are excluded.

- [x] **T14 · Keep chore outcomes visible and make Tasks copy fit the learner** — Code + account retest; source IDs: B24, N13, N20.

  Diagnose the missing approved chore against the current endpoint, which already returns assigned chores. Render To do, Submitted, Needs more, Approved +N coins and Rejected clearly; choose copy from active learner age/relationship. Done when submit → more evidence → resubmit → reject → resubmit → approve remains visible and adult profiles do not see child-only “grown-up” copy.

- [ ] **T19 · Verify Apply promo never starts payment** — Deployed regression check; source IDs: B54, N14.

  Current code already calls a separate /promo endpoint. Check deployed SPA/API versions and invoice #12 request/error first; fix a reproduced remaining defect. Done when Apply shows the labelled discount/new total without any /pay request, a second code is rejected clearly, errors are readable and Pay remains a separate action.

- [ ] **T20 · Resolve the reported duplicate invoices and term state** — Invoice investigation; source IDs: B35.

  Inspect invoices #11/#12, issuance identities, fee lines, payment references and subscription term state. Fix duplicate issuance/retries only if established. Done when each payable obligation has the correct invoice/term state and any duplicate is handled through a reviewed auditable process. Equal date/amount alone does not justify deleting or hiding an invoice.

- [ ] **T21 · Restore and verify real data top-up** — Provider/configuration check; source IDs: B48, F23.

  Verify production Monnify environment, authentication and Bills Payment enablement; retest catalogue, authorized checkout, signed payment result, vend/requery and delivery status. Done when a controlled purchase reconciles payment and actual data delivery with no duplicate vend. A sandbox catalogue pass alone is insufficient.

- [ ] **T25 · Accept teacher onboarding and assignments end to end** — Deploy + school acceptance; source IDs: B13, B30, B33, F29, F33.

  Deploy the already-pushed email onboarding/directory and required migrations; run invite → verified acceptance → assign teacher → create assignment → learner submit → teacher grade → parent review. Done when the intended school flow completes, parent+teacher roles coexist and rewards release once under the confirmed funding policy. Include teacher CSV acceptance after T15; speaking recording is excluded.


## P2 — remaining implementation and acceptance

- [x] **T15 · Complete teacher CSV onboarding** — Code; import contract first; source IDs: B33, F33.

  Define and implement Firstname, Lastname, Email and Phone import using the same verified invitation/acceptance flow as single email invites. Validate and preview errors, avoid duplicate invites/memberships and preserve existing family/roles. Done when a school-admin import leads to accepted teachers in the directory/picker, with tenant isolation and readable row errors. Supervisor import and multicampus administration are excluded.

- [x] **T16 · Add international phone entry to referral invitations** — Code; source IDs: N2.

  Reuse signup country selection and canonical international normalization. Done when +234/local Nigerian formatting resolves consistently, other supported country codes work and duplicate/existing-account errors remain readable.

- [x] **T17 · Replace residual dotted prose with the display name** — Copy; source IDs: N3.

  Use DISPLAY_NAME for the identified Login and Refer & earn sentences and check equivalent prose occurrences. Done when those sentences show Mahadum360. Preserve the separately defined graphic WORDMARK and locked tagline.

- [x] **T18 · Make the Watch affordance match its behavior** — UI; source IDs: N4.

  Connect the apparent play control to video playback, or remove the play symbol from the passive label. Done when mouse/touch/keyboard behavior matches the presentation and the required-video completion gate still works.

- [ ] **T22 · Provide a real rewarded-heart advert path** — Integration; vendor/rule decision first; source IDs: B47, N9.

  Select a supported rewarded-ad vendor or approve a managed verified-video design; settle +1 versus the existing full refill. Replace the Null-only path for live rewards and show a clear unavailable state. Done when only a completed verified reward grants the agreed hearts, each impression is redeemed once and empty/error states cannot mint rewards. Ordinary banner clicks are not reward proof.

- [ ] **T23 · Correct parent/child data-ad placements** — Deployment + placement check; source IDs: F22.

  Compare the deployed build/campaigns with the current targeted data slot; investigate the reported lesson banner on Billing/My tasks, which the current consumer slot excludes. Use active learner eligibility rather than student role alone for child messaging. Done when eligible pages show the intended data promotion, adult links reach Buy data, child views show the grown-up prompt and premium/staff suppression remains correct.

- [ ] **T24 · Review and clean exact test records after import repair** — Reviewed data cleanup; source IDs: F16, F28, N15.

  Inventory exact production placeholder schools, legacy promos, draft content, demo adverts and duplicate test profiles with dependency/seat effects. Prepare a before-state export and reviewed target list; then perform only authorized removals/deactivations. Done when approved test records no longer appear and seats/dependencies reconcile without losing real learners, financial history or shared media. Fix T01 first to prevent recurrence.

- [ ] **T26 · Accept the pushed family, league and notification features** — Deploy + family/notification acceptance; source IDs: B21, B25, B26, B27, B46.

  Verify 30-learner cohorts/private cheers, conserved pool/transfers and non-rewarding challenges; retest chore/assignment review routes. Configure the deployment queue/scheduler/browser push and chosen text/email channel. Done when actual opted-in delivery, overdraft/replay isolation and review behavior pass per ID. New speaking recording and expanded family metrics are excluded.

- [ ] **T27 · Accept existing referral rules, safeguards and signup uniqueness** — Referral/signup acceptance; source IDs: B51, B52, B53, X1, X2, X3, X4, X6, F2, F18.

  Retest existing-account rejection, normalized duplicate phones, activity/search/share text, paid+lesson+quiz activation, 5% qualifying first-month commission, escrow/refund/payout limits and velocity freeze. Done when controlled records and scheduler/provider events produce correct ledger/UI results under current approved windows. Any observed mismatch gets a bounded fix; do not redesign commission policy.

- [ ] **T28 · Accept the pushed video coverage and quality support** — Video/content acceptance; source IDs: B10.

  Deploy the uploaded-video coverage repair and inventory real source/rendition assets. Done when seek-to-end/repeated fragments cannot bypass required watch, resume and save retry work, and the selector offers only available 240/360/720 files with verified playback. Check cultural duration and existing dual-caption assets. Missing encodes remain a content prerequisite; building a new transcode/caption/publish pipeline is excluded under B39.

## Completion record

Mark a grouped task complete only after its scoped acceptance passes. Record code revision, affected tests, deployed API/SPA version, exact test profile/organization, expected/actual outcome and remaining dependencies. Keep source IDs separate: a partial parent row is not fully closed while an excluded portion remains deferred. Do not mark a source row Met from a local test alone.

For implementation changes, run affected regression checks and the repository’s required checks. No new tests are needed for this TODO-only change. Deployment, provider receipts and exact account/data evidence are required only for the tasks that depend on them. Cleanup and historic coin/invoice adjustments require concrete reviewed targets before execution.

## Excluded deferred/held scope — reference only

These are not execution tasks in this TODO. They remain in the audit/backlog and are not marked fixed.

| Source ID | Excluded scope |
|---|---|
| B4 | Deferred learning-goal onboarding (previously 15 Oct). |
| B5 | Deferred adaptive placement (previously 15 Oct). |
| B6 | Deferred bilingual UI/full accessibility work (previously 15 Oct). |
| B11 | Phase 2 audio-prompt quiz types. |
| B12 | Held minors recording/submission/review. |
| B14 | Held Stories/Folklore/Proverbs/Trivia scope. |
| B16 | Phase 2 offline download. |
| B19 | Held change from all-answer to wrong-answer hearts deduction. |
| B22 | Phase 2 family streak/rank/speaking dashboard expansion. |
| B34 | Held seat-inactivity threshold/release policy. |
| B39 | Phase 2 transcoding automation/caption-authoring/publish rules. |
| B45 | Held restoration of retired airtime enrolment/tariff. |
| X7 | Held Stories/Folklore/Proverbs/Trivia scope. |
| X8 | Held Stories/Folklore/Proverbs/Trivia scope. |
| X9 | Held Stories/Folklore/Proverbs/Trivia scope. |
| X10 | Held Stories/Folklore/Proverbs/Trivia scope. |
| X11 | Held Stories/Folklore/Proverbs/Trivia scope. |
| X12 | Held Stories/Folklore/Proverbs/Trivia scope. |
| F13 | Same held hearts-rule change as B19. |
| F20 | Same retired airtime decision as B45. |
| F21 | Held Fluent CRM/SES architecture choice. |
| F32 | Held school-wide join-link design. |
| W5 | Held starting-level control/copy clarification. |

Also excluded within retained IDs: new recordings in B9/B25; weekly/monthly streak expansion and grace/purchasable Shield changes in B18; new caption/transcode/publish automation associated with B39; optional study-time progress UI; new supervisor-invite/multicampus scope; removal of Teacher signup or new global privileged roles; any change to the locked wordmark/tagline.

**W15: reconcile only.** The requested and reported wording are both “Discuss with a campus” and current code matches. Confirm the intended expected result and correct the audit status if appropriate; there is no established copy defect to implement.

## Active source coverage

Physical rows refer to the supplied workbook’s `Test Results` sheet. This table verifies that every retained audit ID has at least one task; it does not change the source status.

| Source ID | Excel row | Source status | Tasks |
|---|---:|---|---|
| B9 | 10 | Partial | T12 |
| B10 | 11 | Partial | T28 |
| B13 | 14 | Partial | T25 |
| B18 | 19 | Partial | T13 |
| B21 | 22 | Partial | T26 |
| B23 | 24 | Partial | T06 |
| B24 | 25 | Partial | T07, T14 |
| B25 | 26 | Partial | T26 |
| B26 | 27 | Partial | T26 |
| B27 | 28 | Partial | T26 |
| B28 | 29 | Partial | T01, T02, T03, T04 |
| B30 | 31 | Partial | T25 |
| B33 | 34 | Partial | T04, T15, T25 |
| B35 | 36 | Partial | T20 |
| B46 | 47 | Partial | T26 |
| B47 | 48 | Partial | T22 |
| B48 | 49 | Not met | T21 |
| B51 | 52 | Can't verify | T27 |
| B52 | 53 | Partial | T27 |
| B53 | 54 | Partial | T27 |
| B54 | 55 | Partial | T19 |
| X1 | 56 | Can't verify | T27 |
| X2 | 57 | Can't verify | T27 |
| X3 | 58 | Can't verify | T27 |
| X4 | 59 | Partial | T27 |
| X6 | 61 | Can't verify | T27 |
| F2 | 69 | Can't verify | T27 |
| F16 | 83 | Not met | T24 |
| F18 | 85 | Partial | T27 |
| F22 | 89 | Not met | T23 |
| F23 | 90 | Not met | T21 |
| F24 | 91 | Partial | T10 |
| F28 | 95 | Partial | T24 |
| F29 | 96 | Partial | T25 |
| F33 | 100 | Partial | T15, T25 |
| F34 | 101 | Partial | T09 |
| N1 | 127 | Not met | T11 |
| N2 | 128 | Not met | T16 |
| N3 | 129 | Partial | T17 |
| N4 | 130 | Not met | T18 |
| N5 | 131 | Not met | T12 |
| N6 | 132 | Not met | T12 |
| N7 | 133 | Not met | T12 |
| N8 | 134 | Not met | T13 |
| N9 | 135 | Not met | T22 |
| N10 | 136 | Partial | T07 |
| N11 | 137 | Not met | T06 |
| N12 | 138 | Partial | T05 |
| N13 | 139 | Not met | T14 |
| N14 | 140 | Not met | T19 |
| N15 | 141 | Not met | T01, T24 |
| N16 | 142 | Not met | T02 |
| N17 | 143 | Not met | T03 |
| N18 | 144 | Not met | T04 |
| N19 | 145 | Not met | T07 |
| N20 | 146 | Not met | T14 |
| N21 | 147 | Not met | T08 |
| N22 | 148 | Not met | T08 |
