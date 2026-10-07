# Batch 1 verification — 6 October 2026 (America/New_York)

Scope: F18, X5, F3, X3, X4, F8 and B53, authorized by Adebare's instruction to start Batch 1. Baseline: `codex/beta-feedback-20260903` at `f1c6134`. Adebare subsequently authorized pushing this batch to origin and asked Atanda to email the reviewer. Original audit statuses and target dates remain historical source labels. Per-ID local acceptance, production deployment and Deborah QA remain pending.

Maps to the implementation plan's authentication, referral/admin fraud review and gamification work. Existing badge conditions, referral activation prerequisites, commission rates, escrow and tenant/role enforcement are retained.

## Per-ID results

| ID | Local result | Evidence and resulting change | Acceptance still pending |
|---|---|---|---|
| F18 | PASS — no production-code change | `AuthTest` rejects a duplicate email and duplicate Nigerian phone in alternate formats; added Canadian +1 / 001 regression also rejects the same subscriber without creating another user. `PhoneInput.test.tsx` covers country selection and shared calling codes. Browser confirms default Nigeria +234 and selection of Canada +1. | Adebare, deployed version and actual signup QA |
| X5 | PASS — repaired mobile visibility | Existing admin API fixture returns activated=3, email=2, phone=1, active=2, inactive=1. Desktop already showed the columns. At 390px, `hideOnMobile` removed both channel headers and cells. Removed those two flags; the existing horizontal table scroll now exposes both counts. `Batch1Verification.test.tsx` verifies populated values and column availability. | Adebare, deployed version and live admin data |
| F3 | PASS — same repair as X5 | Separate audit ID, sharing the admin rendering/data check above. Browser inspected both widths with populated fixtures. | Separate F3 acceptance by Adebare and Deborah |
| X3 | PASS — no production-code change | `ReferralInvitationTest` rejects an existing active account by email and Nigerian local phone with `account_exists`/422, creates no invitation and sends no notification. Existing frontend test and browser fixture show “That account already exists and can’t be referred.” | Adebare and live test-account rejection |
| X4 | PASS — no production-code change | API regressions verify populated activation dates, codes, both contacts, active/inactive/pending states, email/phone search, pagination and owner isolation. Frontend activity tests verify paging/search. Browser searches `ben@example.test` and `+2348011111111` and receives the expected individual rows; mobile uses the existing table scroll. | Adebare and actual activated referral data |
| F8 | PASS — repaired refresh and contrast | Real lesson-completion API test awards `tier_0` / Star Starter, records a non-null earned date and sends the correct Level 0 notification payload to the parent through the database notification channel (notification fake). Browser verifies all six tier names and Star Starter detail/date at desktop/mobile widths. Completion previously invalidated only the path; it now also invalidates this learner's badges, streak, hearts and league cache. Earned badge title now uses dark text on its fixed pale-gold tile, making it readable in dark mode. | Adebare, live completion and actual notification delivery |
| B53 | PASS — no production-code change | New `ReferralFraudTest` proves 15 recent signups plus an older signup remain active; 16 recent signups flag the code and appear in the admin queue. Flagged/frozen codes block new attribution, pending activation and commissions. Admin freeze/clear are audited, clear restores eligibility, and parents cannot list or change fraud codes. A free subscription cannot activate a referral despite completed learning; reused devices remain rejected even with paid learning prerequisites. Browser confirms flagged → frozen → cleared queue states. | Adebare, deployed scheduler/version and live review QA |

`flagged` is the automatic review hold: it already blocks eligibility. `frozen` is the explicit admin confirmation state. The velocity command preserves that existing distinction rather than automatically declaring fraud.

## Verification performed

- Initial affected backend regressions: 50 tests, 268 assertions, passed.
- Expanded affected backend regressions: 56 tests, 327 assertions, passed.
- Full backend suite: 458 tests reported; 457 passed, one skipped; 2,662 assertions. The environment-dependent SendGrid EC-signature test skips when OpenSSL cannot generate an EC key; the skip is not a pass.
- Full final frontend suite: 55 files, 286 tests passed.
- Final frontend production build, including TypeScript: passed.
- Repository-wide Pint check: passed. PHPStan level 5: passed, zero errors.
- Whitespace check: passed.
- Actual browser pixels inspected at 1280×900 and 390×844. The admin channel omission was reproduced before the repair; earned-title contrast was observed and corrected.

Backend tests used SQLite in memory, array mail and disabled live messaging/payment gateways. Browser populated views used a disposable API on `127.0.0.1:8001` behind a separate Vite instance on `127.0.0.1:5181`, serving the real SPA with synthetic contacts/counts/badges. These screenshots demonstrate rendering and interactions; they do not demonstrate real payment settlement, email/SMS receipt, or production data. Original local API/Vite servers and independent `tmp/` contents were left intact.

The fixture preview and browser tab were stopped after verification. Screenshots and the reproducible fixture API are retained locally in `C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch1-evidence/`. Selected evidence:

- [Mobile referral counts](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch1-evidence/referral-codes-mobile.jpg)
- [Mobile country selector](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch1-evidence/signup-country-mobile.jpg)
- [Existing-account prompt](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch1-evidence/existing-account-mobile.jpg)
- [Star Starter detail and date](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch1-evidence/badge-detail-mobile.jpg)
- [Readable earned badge title](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch1-evidence/badges-mobile.jpg)
- [Lesson completion badge message](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch1-evidence/lesson-complete-desktop.jpg)
- [Flagged fraud queue](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch1-evidence/fraud-flagged-desktop.jpg)
- [Frozen fraud code on mobile](C:/Users/adeba/.codex/visualizations/2026/10/07/01a11421-01f8-70b0-ace5-b79cd71d4e19/batch1-evidence/fraud-frozen-mobile.jpg)

## Local review steps

Use the normal local app on `http://127.0.0.1:5173` with its API on port 8000. The UI route is `/admin/referrals`; `/admin/referrals/codes` is the API suffix, not a SPA page.

1. **F18:** Open `/register`, inspect the country selector. Duplicate signup enforcement is reproducible with `AuthTest` without creating accounts in the local database.
2. **X5 and F3:** Open `/admin/referrals` as super admin. Inspect Via email/Via phone counts, then narrow to mobile width and scroll the table horizontally. Confirm each ID separately.
3. **X3:** As a referrer, open `/referrals` and invite an existing active test contact; expect the account-exists prompt. The isolated regression verifies rejection without creating an invitation.
4. **X4:** On `/referrals`, inspect populated activity and search by its email and phone. Use an existing activated test referral or the fixture evidence; do not grant subscriptions or alter customer associations to produce a result.
5. **F8:** Open `/achievements` for a test learner, then complete their Level 0 content. Return immediately and check that the new Star Starter tile is shown. Open it to inspect the earned date; inspect its title in dark mode. Actual notification delivery is a separate live QA check.
6. **B53:** Review `/admin/fraud` and the isolated `ReferralFraudTest` for threshold, blocking, freeze/clear, audit and permission checks. Run the threshold only in disposable fixtures; do not flag customer codes to manufacture a pass.

## Rollback and release

Production behavior edits are limited to `ReferralCodesAdminPage.tsx`, `LessonPlayerPage.tsx` and `AchievementsPage.tsx`. Rollback consists of reverting this batch's edits to those files and the associated tests/documentation. There are no migrations or customer-data changes in this batch. Avoid a blanket checkout/reset because the workspace contains independent untracked planning material and `tmp/`.

Adebare authorized this concrete batch's push with: "okay push to origin and @Atanda should send an email to update the reviewer". Commit the intended files and verify the origin revision. Atanda owns the requested reviewer status update, which must distinguish pushed/local verification from deployment and live acceptance. Production deployment remains unconfirmed; a local PASS or status email does not close a production audit row.
