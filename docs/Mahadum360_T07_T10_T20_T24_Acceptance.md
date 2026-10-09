# T07, T10, T20 and T24 review — 8 October 2026

This review uses the supplied `mahadum360-20261009-000521-45411.sql.gz` backup, parsed offline without executing its SQL or restoring it into an application database. SHA-256: `0a222c5964ff47a6841666dc431bc051ff34b94f833a813ee7942927415f411c`. Backup evidence describes that snapshot, not current production state. The user's instruction to preserve demo records and handle deployment remains in force.

## T07 — reward funding and the reported 10 coins

The backup identifies learner `kam` as profile 391, household 44. Wallet 131 has 10 coins. Its matching credit is transaction 29, source `chore`, reference chore 89, recorded 7 October at 07:05:36. Chore 89 was approved for 10 coins at that time. There is no matching parent debit for that reference, and the backup has no `study` coin transactions. This traces the observed credit to a historical chore award; it does not establish that a separate study award existed.

The approved policy remains parent-funded transfers for chore, study and assignment rewards. Current chore and assignment callers use the shared atomic transfer service. That service now reloads and locks the current learner before selecting a household; stale family links and soft-deleted learners cannot release coins. Insufficient funds leave rewards pending; replay does not transfer twice. Direct tests cover all three source labels. There is no study timer/award caller in this checkout, and no new reward cadence is introduced. Historical ledger entries are preserved under the demo-data waiver.

## T10 — Lucy's household access

Backup user 56 has Parent and Student roles but no owned household or household membership. Learner 312 belongs to school 15 and has no family link. This establishes the missing-household condition that causes the Family, Wallet and Reviews APIs to return 404.

The existing administrator Parent-grant action can repair a Parent account missing its household. Role, household, owner membership and audit now commit together; an audit failure rolls everything back. Repeating a successful grant reuses the household. Multiple owned households require review, and a deleted household is never silently replaced. Regression coverage reproduces the Parent+Student school-learner shape and verifies all three APIs while preserving school placement and the learner's null family link.

After deployment, an administrator must deliberately reapply Parent access to the verified account through the existing admin action. Deployment alone does not repair historical accounts. No production household was created or learner relinked during this task.

## T20 — purchase retries and invoices 11/12

Backup invoices 11 and 12 belong to school 7, are unpaid, and each total 12,900,000 minor units. Their issuance times are 30 September 14:01:48 and 14:06:45. Each contains student fees 7,000,000, registration 5,000,000 and VAT 900,000. Allocations 7 and 8 each contain 10 seats, term `2026/2027`, expiring 30 June 2027. Equal amounts and a five-minute interval do not prove that these were retries. No invoice or term was rewritten.

The existing durable server receipt preserves the original purchase response and prevents a repeated key from issuing another invoice/allocation. The SPA now clears only the successful request's own retry key. A late success cannot erase a newer uncertain purchase, including across remount. Failed or lost responses retain the key.

The local MariaDB verifier ran two separate PHP workers using the same school, user, purchase details and key. Both requests were observed contending on the school row before release. Both returned 201 with identical purchase data, leaving exactly one invoice, one new allocation, one durable request and one purchase audit. Five existing roster concurrency scenarios also passed. The verifier created and removed only its random local test database; no real payment or production write occurred.

## T24 — cleanup waived

The user confirmed the existing school records are demo data and waived historical reconciliation and cleanup. T24 is recorded as waived, not as a completed deletion. Existing schools, profiles, allocations, invoices and ledger history remain intact. Deferred report items remain excluded.

Validation: all 90 affected wallet, chore, assignment, parent-role, seat and admin-user backend tests passed (614 assertions). The final Parent-role test was expanded to match Lucy's school-learner state and passed all eight cases (47 assertions). All frontend tests passed, including four purchase-retry cases. TypeScript/production build, Pint, PHPStan level 5 and whitespace checks passed. The six local MariaDB concurrency scenarios passed on version 10.11.11.

Local implementation and tests do not establish deployment acceptance. The user owns deployment; no migration is added by this batch. This batch is prepared for origin; deployment remains pending.
