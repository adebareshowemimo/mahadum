# Parent-funded rewards — confirmed 7 October 2026

Adebare confirmed: “Yes—parent-funded transfers for all three” in response to whether chore, study and assignment rewards must transfer existing coins from the parent wallet and hold when funds are insufficient. This supersedes any earlier reward issuance assumption.

All applicable reward releases must debit the household wallet and credit the learner wallet atomically. Parents approve chore and assignment releases. Insufficient funds must leave the review and reward pending so approval can be retried after funding. Repeated processing must never transfer twice. Coin ledgers remain append-only; historical credits must not be silently rewritten or balanced by overdrawing a parent.

Implemented callers: chores, uploaded lesson assignments and teacher-graded class assignments. Each uses `WalletService::rewardFromParent`, which locks both wallets in ID order and checks the existing reference entries before transferring.

The supplied backup traces kam's 10 coins to learner 391, wallet 131, transaction 29: a chore credit referencing approved chore 89. No matching parent debit exists for that historical award, and there are no study-source entries in the backup. The checked-out application contains no study timer or study-time reward award route. Any restored study award must use this same parent-funded transfer rule; the report is not evidence of an approved new cadence or amount. The shared service reloads and locks the current learner and rejects stale household links or deleted learners before any transfer. Historical corrections are waived for the demo data; no ledger adjustment or production change has been made. See [the evidence and acceptance record](Mahadum360_T07_T10_T20_T24_Acceptance.md).

Reproduction uses isolated test households, following the instruction to replicate production features without requiring Lucy’s or kam’s exact account details. Local tests establish behavior in this checkout, not the deployed revision or historical balances.
