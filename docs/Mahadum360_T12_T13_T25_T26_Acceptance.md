# T12–T13 and T25–T26 local acceptance — 8 October 2026

This continuation fixes reproduced gaps in the already implemented invitation, completion, school-assignment and family-alert paths. It adds no migration, changes no production or historical records, and leaves deployment with the user. Deferred recording, streak-protection redesign and expanded reward policies remain excluded.

## T12 — practice invitations

Both private invitation accept endpoints previously saved accepted/opened timestamps before recording the audit. A failed audit returned 500 while consuming the acceptance; retry skipped the missing audit. Both now lock the invitation and commit timestamps and audit in one transaction. A failed audit leaves the invitation unopened/unaccepted, and retry accepts it with exactly one audit. Recipient ownership, expiry and withdrawn-content checks remain enforced. Both Home and player flows retain the shared family/school contact policy.

Two new failure-injection cases failed before the change and pass afterward. Existing tests cover eligible contacts, unrelated recipients, private links, expiry and withdrawn/deleted lessons. Actual configured email delivery remains a deployment acceptance item; no message was sent to a real recipient.

## T13 — earned completion results

A referral activation exception after successful lesson completion previously returned 500 and hid First Steps and the streak from the learner. Referral evaluation failure is now reported while preserving the committed completion response. Retrying cannot issue XP or badges twice. This does not claim that a failed referral activation is automatically redispatched.

The deployment script now runs the existing idempotent BadgeSeeder alongside the permission seed. This ensures canonical achievement definitions are present when the user's next deployment runs; it does not modify historical earned timestamps or backfill historical awards.

The isolated MariaDB verifier now launches two separate PHP workers against one ready lesson and learner. Both are observed waiting on the learner row before release. Both complete successfully; XP responses are 0 and 13, with one XP ledger entry, one First Steps award, streak count one and one completion event. The verifier preserves the five existing profiles and roster totals and removes its random local database afterward. Production concurrency and actual learner acceptance remain separate.

## T25 — current school assignment membership

Assignment lists, details and completion rosters previously counted duplicate, deleted and moved learner memberships. They now use the existing current-school enrollment scope and display distinct current learners. Submission/graded counts use that same roster, preserving historical membership and submission rows.

An old class membership previously allowed a parent to submit work after the learner moved out of the school. Submission now validates current membership both before and inside the transaction. Submission and grading share the school lock used by roster changes. Grading reloads the class, rechecks teacher authority, and validates the current learner and class membership before recording a decision or locking reward coins. Moved or deleted learners cannot be graded through stale memberships.

Regression tests cover four stored memberships representing one current learner, preservation of historical rows, and denied submission/grading after a school move. The existing CSV invitation → verified acceptance → teacher assignment → learner submission → teacher grading → funded parent approval flow still passes, including existing Parent-role/household preservation and conserved reward retries.

## T26 — current family alert recipients

Family alert evaluation previously trusted cached owner and learner relationships. A moved learner could trigger an inactivity notice to the former household, and a cached active owner could still receive an alert after suspension. Evaluation now reloads the current family, owner and learners; deleted households stop evaluation. Parent verification, active status, opt-in preferences and episode deduplication remain enforced.

Both stale-relationship regressions failed before the fix and now pass. Existing family pool conservation, retry protection, non-rewarding challenges, private cheers, 30-learner league cohorts, channel routing, review notices and notification failure handling remain covered. Real opted-in provider delivery is not established by local fakes or simulated dispatch failures.

## Validation and handoff

All 99 affected backend tests passed with 779 assertions. Seven new regression cases failed before the implementation and passed afterward. PHPStan level 5, Pint, deployment-shell syntax and whitespace checks passed. All seven actual local MariaDB concurrency scenarios passed on MariaDB 10.11.11, including lesson completion and the prior seat-purchase/roster checks.

Frontend code is unchanged in this batch; the preceding full frontend test and production-build pass remains the baseline. No deployment was executed and no live mail, text, browser push or financial transaction was sent. T12, T13, T25 and T26 remain open for the stated deployment and real delivery/learner acceptance checks; their reproduced local code gaps are fixed. This verified batch is prepared for origin; deployment remains pending.
