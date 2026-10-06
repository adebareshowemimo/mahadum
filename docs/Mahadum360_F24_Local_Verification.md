# F24a local confirmation: Admin parent-family provisioning

Ready locally on 6 October 2026. This is the first independently confirmable part of F24. It prevents an Admin-created Parent or newly granted Parent role from lacking the household required by Family, Wallet and Reviews. It does not establish the cause of Lucy's live-account problem or relink any production records.

- [x] C — Missing-family provisioning implemented atomically in Admin create/Parent grant; existing households reused; intentional deleted households require review.
- [x] T — Six new regressions pass within the 35-test relevant backend run (139 assertions). Pint and PHPStan pass. All 264 frontend tests pass; TypeScript and build pass; whitespace check passes.
- [x] Origin push - `e183525efad014a0e75285781ac03d078ba61190` verified on `origin/codex/beta-feedback-20260903`, 6 October; non-force push.
- [ ] L — Adebare confirms this specific local fix.
- [x] D — USER-REPORTED deployment on 6 October; not independently verified live.
- [x] QA email — Parent confirms sent 6 October, 16:01 UTC.
- [ ] Q — Deborah live acceptance pending, including Lucy retest.

## What was reproduced

Before implementation, the new six-test regression suite had three failures and one error; two controls passed. Admin-created parents had no family; repeated Parent grants did not repair a missing household; family/wallet/reviews returned 404. After implementation, relevant regressions pass, including non-admin denial, duplicate-grant prevention, existing learners/wallet preservation across parent → school → parent, and atomic refusal to replace a deliberately deleted household.

The current local database is MySQL `mahamu360` on 127.0.0.1, APP_ENV local. Lucy is absent. Actual local accounts/roles/family links were inspected read-only and not changed. Automated tests use isolated in-memory SQLite and fake/disabled messaging and payments. The 37-coin fixture is test data, not real money.

## Adebare's local test

API runs at http://127.0.0.1:8000 and SPA at http://127.0.0.1:5173. A dedicated browser tab is open at the demo teacher's Admin detail page. Its Teacher role is on and Parent is off. No credentials were changed or included here.

1. In the local Admin session, open http://127.0.0.1:5173/admin/users/9. Confirm it is **Granville Mann / teacher1@dev.mahadum360**, the pre-existing local demo account. Alternatively choose your own local test account with no family. Keep its existing roles.
2. Click **+ parent** once. Expect Parent to become selected. Do not change Super Admin or other roles. This action provisions a family for that local account.
3. Sign in as the selected demo/test account using your existing local credentials. Open http://127.0.0.1:5173/family, http://127.0.0.1:5173/wallet and http://127.0.0.1:5173/reviews. Expect a household, zero initial wallet balances and an empty review queue; no “couldn't load your family” error. Do not fund a wallet or submit a purchase.
4. As local Admin, turn Parent off and back on for this test account. Sign in again and revisit the three pages. Expect the same household and any previously existing children/balances, without a duplicate family. Teacher and school memberships should remain.
5. Report pass/fail for **F24a** and any error text. Local acceptance remains recorded separately from the authorized origin push; server deployment is owned by Adebare. F24 remains open for live Lucy reconciliation even if this local prevention fix passes.

Avoid **Create user** during manual QA unless sending its password-reset invitation is separately authorized. The automatic create-path test used a fake notification channel; no email was sent by this task.

## Source and release evidence

Base checkout: `e82c1bf8c39f00d47279bbefcb6139e7e7af1f2d`, branch `codex/beta-feedback-20260903`, origin `adebareshowemimo/mahadum`. Changed files are UserController.php, new ParentFamilyProvisioner.php and new ParentRoleFamilyTest.php; checklist and this verification note are documentation. Independent untracked tmp/ and the two data-store commits after 9b6cbc6 are preserved. This candidate has no migration, provider configuration or live payment changes. The agent does not deploy production or email Deborah; commit/push status is provided in the handoff.

On 6 October, Adebare instructed: "Just push to origin, I will deploy myself on the server." This authorizes committing and pushing this tested candidate without waiting for local acceptance. It does not mark local acceptance or production deployment complete. The user owns server deployment; the parent sends Deborah QA instructions only after the user confirms live deployment. SHA-256 of UserController.php: `5F75FE6CE594C018C581A81BD7AA2810F03F7C36ED3D934870837F227C64E10D`. ParentFamilyProvisioner.php: `E064CA15D22819F83ADDAAA30CB23BA209647ACCD7BB2CED1C0ED410756EE5CB`. ParentRoleFamilyTest.php: `F7C98C6D8192585BF01E118C9552F251FC8C9E7DDAFD4B0DF5DCB722AB8B3174`.

Read-only production inspection on 6 October confirmed the Azure subscription is enabled and VM `mahadum` in resource group `MAHADUM` is running in Canada Central at `20.151.177.171`, admin user `adebareshowemimo`. Repository `.azure/deployment-plan.md` and Apache configuration identify `https://mahadum360.com` and `/var/www/mahadum/public` (application `/var/www/mahadum`). Public `/up` returned HTTP 200. The existing SSH identity was rejected (`Permission denied (publickey,password)`), with strict host-key checking retained. Therefore current live code SHA, branch/worktree state, service status and available rollback backups remain unverified. No keys, credentials, network rules or server state were changed.

After explicit local confirmation and authorized host access, inspect the live checkout/release and backup inventory, preserve prior code and SPA assets, back up the database, then release only the confirmed candidate. Do not blindly deploy the script's default main branch or revert the independent data-store commits. Repository deploy/deploy.sh can restore prior code/SPA after failure but retains completed migrations; this candidate has no migration. Restoring prior code does not remove households created through Admin actions; any data reversal requires a separate ownership/usage review.

## Remaining checks

- Full backend suite passed: 413 passed, one skipped out of 414; 2,225 assertions. The SendGrid ECDSA verification test was skipped because OpenSSL EC key generation is unavailable in this environment.
- User-performed browser role grant and family-page checks: pending. Agent browser inspection covered the existing Admin detail page and actual pixels only.
- Lucy's live family ownership and account history: not inspected; no production relinking attempted.
- Compliant XLSX materialization: blocked by required Library helper's Windows `os.setxattr` incompatibility. Both tabs and all 125 audit detail rows were read through Library extracted text.
- Hearts changed rule, W5, W15 and other decision-gated roadmap work are outside this candidate.


CI handoff: no Actions runs, check runs or status contexts were attached to e183525 at verification. `.github/workflows/ci.yml` triggers pushes only on main/develop, plus pull requests. This is not a CI pass. Local acceptance is pending; deployment is user-reported on 6 October; independent live verification and Deborah acceptance remain pending. The follow-up F33a teacher assignment change is separate; Adebare has now authorized its commit and origin push. Its exact delivery result is recorded in the task handoff; local acceptance remains pending; deployment is user-reported, and live verification/Deborah acceptance remain pending.


Release/QA status update, 6 October: User reported deploying F24a and F33a on 6 October 2026. This is user-reported deployment, not independent live verification or local manual acceptance. Parent confirmed the corporate Outlook QA email to Deborah was sent on 6 October at 16:01 UTC, with no CC. It covers F24a/F33a controlled live checks and a separate Lucy retest. Deborah acceptance is pending. Parent-reported sent-message suffix: AAJ2zw1BAAA=.
