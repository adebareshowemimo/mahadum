# Batch 2 verification — 6 October 2026 (America/New_York)

Scope: B24, B25, B13, B29, B51, B52, X1, X2 and F2, authorized by Adebare's instruction to continue to Batch 2. Baseline: `codex/beta-feedback-20260903` at `48a5b14794cc1150ee26038da64b875b9601ee73`. This is a local candidate. Original audit statuses and 7 October target dates remain unchanged. No Batch 2 commit, origin push, deployment or reviewer email has been performed; per-ID local and live acceptance remain pending.

Maps to implementation milestones 3 (learning), 5 (family economy), 7 (referrals) and 8 (school operations). The repository's parent-approval rule overrides the historical teacher-only coin release described in the July teacher plan.

## Per-ID evidence

| ID | Local result | Evidence and resulting behavior | Remaining acceptance or limitation |
|---|---|---|---|
| B24 | PASS — repaired | Added `/tasks` and checkbox chore submission. A disposable family wallet is funded with 100 fixture coins. Submissions reach Reviews; ask-for-more/reject release zero coins and allow resubmission; approval credits 15 exactly once. Approve-before-submission, duplicate approval/submission and foreign-family access fail. Decisions are audited. Desktop/mobile interactions inspected. | Adebare and live test-family QA. Existing reward issuance is preserved; chore approval does not debit the funded family wallet. |
| B25 | PARTIAL — chore/assignment PASS; speaking NOT RUN | Queue contains only submitted, undecided chores. Existing lesson-assignment review has a transaction-time replay guard. New class reward queue shows written work, teacher feedback and any existing uploaded media; parent approval releases locked coins once, rejection releases none. Teacher grading alone releases zero. `AssignmentFlowTest` also passes. | Speaking review remains deferred; no new minor recording is enabled. Adebare and live queue QA. |
| B13 | PASS — repaired assignment delivery | Enrolled learners/parents can read instructions and submit written work through `/tasks`; the owning teacher sees the answer and grades it. Already graded work cannot be reset by resubmission. Submission and grading lock the submission row to prevent stale concurrent updates. Staff read access does not grant learner submission authority. | Existing flashcard/game behavior preserved and covered by the frontend regression suite; new manual acceptance focuses on assignments. School-admin assignment management remains Batch 3. |
| B29 | PASS — added drill-down | Clicking a student's name in class Analytics opens lessons, quiz attempts, existing speaking metadata and class assignment feedback. API requires class view/analytics permissions plus enrollment; foreign-tenant access and non-enrolled learners are denied. Existing same-school staff read policy is preserved. Latest 50 records per section; scores render in their correct units. Desktop/mobile inspected. | No speaking recording or speech scoring was added. Existing speech metadata only; live teacher/data acceptance pending. |
| B51 | PARTIAL — existing rule verified with fakes | User rewards and school renewal attribution use existing 5% rules. `SchoolReferralTest` verifies organization renewal commission and webhook replay without duplicate credit. | Provider sandbox settlement NOT RUN. A renewal after the 30-day activation window earns none under the recorded design; unrestricted ongoing school renewal commissions are not implemented. |
| B52 | PASS — escrow boundary repaired | Escrow now clears at its exact expiry (`<=`), not only afterward. Tests cover 14 days minus one second, exact expiry, refund clawback/replay, ₦5,000 floor, exact ₦50,000 individual monthly cap, over-cap refusal and next-month availability. Payout requests are idempotent. | Fake payments/refunds and request ledgers only; actual payout transfer/provider settlement and production scheduler acceptance pending. |
| X1 | PASS — existing rule verified | Paid subscription plus one completed lesson plus one quiz are all required. Learning with no paid subscription stays pending; confirmed paid webhook activates after learning, and payment before learning waits for the learning requirements. | Provider sandbox payment NOT RUN; actual account and live activation acceptance pending. |
| X2 | PARTIAL — existing rule verified; timing limit recorded | After activation, an eligible 100,001-minor-unit purchase earns 5,000 minor units (integer rounding); webhook replay adds no duplicate. Exact day 30 qualifies; one second later does not. | The initial purchase made before learning/activation earns no retroactive commission. This follows the recorded architecture; changing this monetary policy was not part of this repair. Provider settlement NOT RUN. |
| F2 | PARTIAL — shared activation/commission evidence | Shares X1/X2 fixtures, with separate acceptance. `ReferralInvitationTest` verifies existing-contact rejection without sending invitations. No duplicate reward path is added for this duplicate audit report. | Same initial-purchase timing limitation as X2, plus live acceptance/provider payment still pending. |

The optional referral-policy question received no answer during this work. The recorded rule remains: **eligible purchases occur after activation and within 30 days of it**. A purchase that precedes learning completion is not backfilled. No commission rate, cap, escrow duration or school renewal eligibility policy was changed.

## Checks performed

- Full backend regression: 465 tests reported; 464 passed, one existing environment-dependent SendGrid EC-key test skipped; 2,770 assertions. The last referral timing regression was added during that run and is independently covered by the affected run below.
- Final affected backend regression: 36 tests passed, 242 assertions, covering family, class, lesson assignments, referrals, school renewals and invitations.
- Full frontend regression: 56 files, 291 tests passed. Final affected frontend run: 11 tests passed, including the later existing-media preview check.
- Frontend production build including TypeScript: passed.
- Repository Pint check and PHPStan level 5: passed, zero static-analysis errors. Whitespace check: passed.
- Additive migration applied successfully to the confirmed local MySQL database (`127.0.0.1`, `mahamu360`, environment `local`, no connection-URL override). All five new fields exist; historical submissions have zero non-null parent-review statuses and zero positive locked rewards. No backfill or balance change was performed.
- Actual browser pixels inspected at 1280×900 and 390×844: learner submission, parent request-more/resubmission/approval, empty queue after approval, teacher written work and student analytics modal.

Backend tests use isolated SQLite databases, fake notifications and payment/refund payloads. The real SPA was rendered against a disposable fixture API on port 8001 through a separate Vite preview on 5181. Browser evidence proves rendering/interactions; isolated backend tests prove the authorization and ledger assertions. Neither proves production data, external delivery or provider settlement. No customer wallet was funded, learner relinked, subscription granted, payment taken or email sent. Temporary preview processes/tab were closed; original servers on 8000/5173 and independent `tmp/` were preserved.

Evidence files and the reproducible fixture API are retained outside the repository:

- [Learner tasks, desktop](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch2-evidence/tasks-desktop.png)
- [Submitted work, mobile](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch2-evidence/tasks-submitted-mobile.png)
- [Parent review queue, mobile](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch2-evidence/reviews-mobile.png)
- [Queue after approval, mobile](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch2-evidence/reviews-approved-mobile.png)
- [Student analytics, mobile](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch2-evidence/analytics-detail-mobile.png)
- [Student analytics, desktop](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch2-evidence/analytics-detail-desktop.png)
- [Teacher sees written work](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch2-evidence/teacher-work-desktop.png)

## Local review and migration

The additive migration `2026_10_07_040000_add_parent_review_to_class_assignment_submissions.php` is required before serving the new application code. It adds written answers, locked coins and parent decision fields. It ran successfully in isolated tests and the confirmed regular local development database. Normal local review is available at `http://127.0.0.1:5173` with API port 8000. Production has not been migrated; apply the migration there as part of the later authorized deployment before serving this code.

1. B24/B25: Create a chore for an existing disposable family child in `/reviews`; select that learner and open `/tasks`. Submit completion, request more, resubmit, reject/resubmit and finally approve. Check the child's balance increases only once on approval.
2. B13/B25: As the assigned teacher, create written work in a class. The enrolled child's parent opens `/tasks`, reads instructions and submits an answer. Teacher opens the assignment, checks the answer, passes it and confirms zero coins are released. Parent opens `/reviews` and approves the reward; verify exactly one credit. Decline a second disposable reward and verify no credit. A school learner without a family parent retains locked rewards; do not grant staff parent authority to bypass the rule.
3. B29: Open the class Analytics tab as authorized staff, click a learner's name and inspect known lesson/quiz scores and assignment feedback. Repeat at mobile width. Confirm existing speech metadata only.
4. B51/B52/X1/X2/F2: Review the isolated referral tests and known amounts above. Live provider sandbox transactions remain a separate acceptance step. Confirm the initial-payment and late-school-renewal limitations explicitly for each relevant ID.

Historical graded submissions retain null parent-review status and zero new locked coins. They are not backfilled into the parent queue or paid again. New grading and parent reward decisions remain distinct audited operations. Wallet ledgers remain append-only.

Rollback: revert this batch's application/test/documentation changes selectively, preserving independent files. Additive columns can remain during a code rollback. Before dropping them after use, preserve submitted work and approval/locked-reward records and reconcile the ledger; dropping columns discards that evidence and must not be used to undo paid rewards. Do not reset balances or remove ledger entries. Code rollback also restores historical teacher release behavior, so review its conflict with the parent-approval rule before reopening reward-bearing grading.

Adebare subsequently authorized this concrete Batch 2 commit and origin push with: "commit and push." The verification snapshot above records the pre-delivery state; the verified origin revision is reported in the delivery response. Per-ID local confirmation, production deployment/migration evidence and Atanda/Deborah communication and QA gates remain pending.
