# Family batch local retest — 4 November 2026

This is a local code and automated-test review. It is not a production delivery check.

| Item | Local status | Evidence and limit |
| --- | --- | --- |
| B22 | Fixed locally | Family API and page show each learner's completed lessons, streak, recorded speaking score and current weekly rank. `FamilyNextBatchTest` checks all three requested metrics. No speaking score appears until a submission is actually scored. |
| B24 | Verified locally | Parent-funded chore approval already debits existing parent coins and credits the child once. `Batch2FamilyFlowsTest` and `WalletRewardFundingTest` cover approval and insufficient funds. A live wallet card top-up was not performed. |
| B25 | Partly ready | Reviews already includes chores and assignments. Speaking review is present in code, but learner speaking submissions remain paused pending minor-data approval. |
| B26 | Verified locally | Coin transfers, family challenges and opt-in parent low-balance alerts are covered by `Batch4FamilyTest`. |
| B27 | Verified with fakes | Browser push, email and one selected text channel are exercised by `Batch4NotificationsTest`. Actual SMS, WhatsApp, email and push delivery require configured providers and production retests. |
| B46 | Verified locally | Parent-managed group coin pools conserve coins and reject cross-family access, covered by `Batch4FamilyTest`. |
| N1 | Verified locally | Add child enrolls in published courses for the chosen language; `FamilyNextBatchTest` confirms the selected language and enrollment. |
| N5–N6 | Fixed locally | Parents manage a per-child approved list of verified registered adults. The learner's practice picker uses it and rejects other recipients with the requested message. Invitation tests and `FamilyNextBatchTest` pass. |
| N10 | Fixed locally | The lesson player counts five visible minutes per child per day. A 10-coin reward transfers from the parent wallet to the child once; insufficient coins leave it pending and alert the parent. Parents can buy coins from their naira wallet at the admin-configured kobo-per-coin rate. Purchases debit cash, credit coins and record both ledgers with an idempotency key. The default price is zero, so an admin must set a rate before purchases appear. `FamilyNextBatchTest` covers the hold, payout, insufficient cash, retry and wallet report. The previously noted 10 coins for kam were traced to a chore in the October retest; they were not an unfunded study award. |
| W5 | Fixed locally | Parents can choose a starting course level before the child begins; rebuilding the path does not mark skipped lessons complete. `FamilyNextBatchTest` checks the path and the lock after progress. |

The four new migrations have been applied to the local database. This batch has not been pushed or deployed.
