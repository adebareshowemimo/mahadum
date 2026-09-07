# Learning access and September 4 feedback implementation

Approved by the product owner on September 7, 2026 in this task. This amendment supersedes the full-learning Free plan and no-heart-lock language in the original Business Requirements Document, implementation plan, and older feedback reviews.

## Access rules

- Free learners may browse every language and play the explicitly designated introductory Lesson 0. `lessons.is_free_preview` identifies this content independently of its title or display order. Existing content is initialized to the first published lesson per language; authors must review this selection before rollout.
- Active paid card/bank subscriptions unlock all published lessons. Individual, family-owner, and organization subscriptions are considered. Existing grace-period access continues until the subscription leaves active/grace status; cancellation/refund state changes restore Free access.
- Airtime subscriptions are Individual plans and permit content in course Level 1 (`course_levels.position = 1`) only. A qualifying card/bank subscription takes precedence over an airtime restriction.
- Free learners lose one heart per four answered quiz questions, regardless of correctness. Re-sending an answer in an open attempt does not increment the counter again. Paid learners have unlimited hearts.
- At zero hearts, playback, answers, progress, and lesson completion are locked for 12 hours. The API returns `423`, `hearts_exhausted`, and `details.locked_until`. Expiry, an eligible refill, or paid unlimited hearts restores access. The historical `practice_mode` response field now signals this lock.
- Quiz questions award one XP for a first correct answer. Quiz-component XP is not awarded again in the lesson-completion bonus. Lesson replays remain one-time XP under the previously recorded anti-farming decision; this is separate from the newly approved access restrictions.
- Learning badges require completed level content, not merely a lifetime-XP threshold. Lifetime XP continues to determine the leaderboard's level display; weekly XP determines weekly ranking.

## Implementation and rollout

Access is enforced in the shared learning-progress boundary, lesson-play controller, answer transaction, speaking/assignment submissions, and invitation creation. Authoring payloads containing answer keys require content permissions. Path and enrollment responses report content locks without permanently rewriting progress, so subscription changes take effect immediately.

The introductory lesson setting is available in the lesson builder. Pricing and billing share the Free-plan wording. Public terms and safeguarding copy explain the restrictions. The migration preserves existing progress and financial ledgers.

Rollout still requires applying pending migrations to the intended environment and reviewing the chosen Lesson 0 per language. No production accounts have been deleted. Data cleanup requires an environment-specific inventory and backup; FluentCRM/SES delivery testing requires the client's connection and mapping configuration.

The configured local database was inventoried on September 7: 45 users, zero matches for the four retained emails. Inventory: `storage/app/feedback-cleanup-inventory.json` (local, not committed). This is not the client cleanup target until confirmed. Coin refill previously restored hearts without a ledger debit; that unpriced method now returns 422 and is hidden. Verified rewarded-ad refills remain supported.

Local rollout completed after a MySQL dump backup (107 tables, 499869 bytes) and a phone preflight with zero duplicate or invalid phone rows. All five pending migrations were applied locally. The 45-user count is unchanged. Free introductory lesson IDs are Yoruba 1, Igbo 13, Hausa 25, and English 37. No production deployment or cleanup was performed.

Validation: 340 backend tests passed, with one SendGrid signature test skipped because OpenSSL EC key generation is unavailable. All 192 frontend tests passed. The final frontend build, PHPStan level 5, Pint, and git diff whitespace check passed.
