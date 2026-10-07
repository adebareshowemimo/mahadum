# Batch 5 verification — 7 October 2026

Continuation from the completed local Batch 4 candidate. Batches 1–3 were pushed (latest `a59e1bf`); Batch 4 remains uncommitted/unpushed. Batch 5 changes are also local only. No source project files, production records, account links, financial ledgers or credentials were modified. No messages or purchases were sent.

## Per-ID results

| ID | Local finding and delivered scope | Remaining evidence |
|---|---|---|
| F24 | Read-only exact-account ownership diagnosis added through `audit:batch-five --user=<exact ID or email>`. Existing parent-role/family regressions passed. The command lists owned families (including deleted status), learner IDs and school context without changing links. | Lucy's exact production account, family/member history, deployed version and failing requests are missing. No relink attempted. |
| F14 | Existing leaderboard membership regression passed; same-name profiles retain separate IDs and XP. New inventory reports duplicate names by ID and sums the append-only XP ledger. Local inventory contains no duplicate active learner names. | Live Lucy IDs and cumulative totals; never merge profiles merely because names match. |
| F16 | Local read-only inventory identified promo codes SCHOOL25 (ID 2, inactive) and TERM2024 (ID 4, active), both with zero local redemptions; four demo-named adverts. The three known placeholder school names and exact draft-level names were not found locally. Foreign-key dependency counts accompany candidates. | All six school names and production IDs are needed. These are candidates, not approved production deletion targets. Snapshot exact records and dependencies before any approved removal. |
| F28 | Same advert inventory as F16: IDs 1–4 locally, all active: Demo leaderboard; Demo inline; Demo family learning leaderboard; Demo culture lesson inline advert. No records deleted. | Verify production targets and preserve media assets or placements shared with legitimate campaigns. Capture a full rollback snapshot before approved removal. |
| B10 | Fixed required-upload seek-to-end bypass in player and API. Unique watched intervals persist across resumes and requests; repeating one section cannot cover a skipped section. Completion waits for the saved result and offers retry after a failed save. Paused/seeking events add no custom watch spans; native played ranges support throttled updates. Ready MP4 rendition and VTT caption records now reach the player; only actual quality files are offered. | Local DB has 48 video rows, zero source assets, zero ready renditions and zero `require_watch` flags. This local content cannot establish production asset readiness. Actual 240/360/720 encodes, caption assets, uploaded-video duration accuracy and cultural duration checks remain open. |
| B48 | Fresh, uncached Monnify sandbox authentication/catalogue inspection succeeded: four networks; MTN one plan, Glo three plans, Airtel and 9mobile no data products in this sandbox response. Existing checkout/payment-versus-vend/requery tests passed with HTTP fakes. | Production credentials and Bills Payment activation must be checked in the deployed environment. No payment initialized and no real carrier delivery attempted. |
| F23 | Shares B48 provider verification; the local configured sandbox does not reproduce the reported authentication error. A `--check-monnify` read-only diagnostic is available. | Same production and checkout/fulfillment evidence as B48. Catalogue success is not a fulfilled-purchase pass. |
| B45 | Inspected current code: new airtime enrollment is expressly deprecated and defaults off; existing billing schedule remains at 02:00 with grace/STOP regression coverage. No enrollment enabled or live charges performed. | User decision requested to keep retirement or restore the ₦50/day plan, then aggregator sandbox/contract evidence. Existing monthly-price-derived charge does not prove the requested ₦50/day tariff. |
| F20 | Existing Individual-only airtime enrollment and Level-1 entitlement tests passed. Retirement/default-off behavior remains intact. | Same product decision as B45; creation of a telco plan and live access acceptance remain pending. |

## Code and evidence

The required-video gate applies to uploaded video components configured with `settings.require_watch=true`. Already completed components remain complete. YouTube embeds retain the existing behavior because this player has no YouTube playback integration. Required uploads stay locked if a source fails; optional videos retain their skip behavior.

Coverage merges actual playback spans; it allows 0.25 seconds at each endpoint and joins adjacent spans separated by at most 0.05 seconds to absorb timing precision. Replays do not count as new coverage. The API uses the video's stored duration where available and locks each progress row inside a transaction while merging intervals. Completion with absent or incomplete coverage returns 422. Playback evidence is browser-reported, not cryptographic proof of human viewing. Existing completion records are not reset.

Quality records are filtered to ready MP4 files; the selector offers supported 240/360/720 records only. The UI resumes at the current playhead after switching files. It does not fabricate renditions or encode missing production assets. The browser fixture uses one disposable eight-second test-pattern MP4 for both selector entries to verify UI switching, not to claim different encode resolutions.

Read-only diagnostics:

```text
php artisan audit:batch-five
php artisan audit:batch-five --user=<exact account ID or email>
php artisan audit:batch-five --check-monnify
```

The default command makes no HTTP calls. The last option fetches only networks and products, bypassing the catalogue cache; it does not validate recipients, initialize payment or vend. Configuration output contains presence flags, environment and catalogue counts, never keys or secrets. The report contains private account relationships when `--user` is supplied and should remain internal.

Local inventory deliberately differs from production; local IDs are not production targets. Cleanup requires a fresh production inventory, exact record review, full before-state export and dependency review. Prefer audited reversible deactivation for records that need to stop appearing; any final permanent deletion requires confirmed targets and a tested restoration plan. No cleanup action was performed by this continuation.

## Validation

- Required-video API: four new feature tests passed (26 assertions): seek, repeat/middle-gap, resume/server duration and genuine rendition/caption payloads.
- Read-only inventory: two new tests passed (15 assertions): exact identity, conserved records, dependencies, secret omission and no outbound HTTP.
- Existing affected backend: 49 tests passed (295 assertions), covering video, Monnify, telco, leaderboard and parent-family behavior.
- Full frontend suite passed: 314 tests before two final focused additions. Final video/invitation suite passed: 11 tests, including save retry and paused playhead rejection.
- Final frontend TypeScript/production build passed.
- Browser fixture inspected: full playback unlocked Continue; seek-to-end at 8/8 seconds left it disabled; 240p selection rendered. The fixture was disposable, used no customer account and made no API writes.
- Full backend suite passed: 500 tests passed, one existing skip, 3,050 assertions. Final affected backend checks passed: 24 tests / 135 assertions; after the final read-only membership/draft inventory extension, its two tests / 15 assertions passed again. Final PHPStan (zero errors), Pint and whitespace checks passed.

Screenshots:

- `C:/Users/adeba/.codex/visualizations/2026/10/07/01a117a5-bbed-7ba2-a3b4-c398df12bd5e/batch5-video-preview.jpg`
- `C:/Users/adeba/.codex/visualizations/2026/10/07/01a117a5-bbed-7ba2-a3b4-c398df12bd5e/batch5-seek-blocked.jpg`

## Delivery and rollback

No Batch 5 schema migration is required. Revert the Batch 5 controller/request/player/API changes and remove its helper/diagnostic command to roll back. Stored watched ranges may remain as harmless JSON progress metadata. Preserve the independent Batch 4 candidate and unrelated `tmp/` content. This report does not authorize a commit/push, deployment, customer-account repair, deletion, provider activation or QA communication. Per-ID human acceptance remains pending.

## Delivery authorization — 7 October 2026

Adebare explicitly requested 'commit and push' for the Batch 4 and Batch 5 candidates in this chat. This supersedes the earlier pending commit/push statements above. It authorizes delivery to the existing codex/beta-feedback-20260903 branch; it does not record per-ID human acceptance or authorize production deployment. Production migrations, real notification/provider delivery, Lucy identity, cleanup, assets and the airtime decision retain their documented pending state. The final chat response records the pushed revision after origin verification.
