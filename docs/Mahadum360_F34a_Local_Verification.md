# October 6 local verification: F34a admin Individual labels

P2; intended date 7 October 2026. Local candidate on codex/beta-feedback-20260903, base/verified origin 6e7cedb9b6452143b319697e9f73a63bd7458c04. Adebare authorized committing/pushing the completed F34a candidate at 01:20 UTC on 7 October by replying yes commit it to the parent's explicit commit/push question. Exact delivery SHA/origin verification is recorded in the release handoff. Local human acceptance, deployment and Deborah QA remain pending; delivery approval does not establish those gates. Parent owns email; Adebare owns production. Independent tmp/ and all published audit fixes are preserved.

## Exact audit reference and scope

Workbook Mahadum360 Test Results - Fix by 7 Oct 2026.xlsx; Library libfile_d384814a74bc81918140bc576168a324; sheet Test Results (second tab); audit ID F34, extracted zero-based index 99. Physical Excel row UNVERIFIED: no certified extraction-to-cell mapping, and original workbook materialization is blocked by the Windows helper's os.setxattr requirement. Do not infer a physical row from the index.

F34 expressly asks to rename Single to Individual in admin. Sign-up already shows Individual; Users Type still showed Single, while Overview Users by type rendered single verbatim. The local repair changes both displayed labels to Individual. The existing type=single API filter, users_by_type.single value, counts, paging, family/school categories, registration and roles/memberships remain unchanged. The generic status chips only apply the label override to Users by type; organization/subscription/billing status groups retain their existing labels.

This is only F34a. Teacher/School signup separation, Institution classification and supervisor onboarding remain open. No new signup option or privilege/membership grant is included. No hearts, streak/tier, XP, money, teacher access or fulfillment rule changed. Deborah's exact production class-loading failure remains open pending sanitized request and membership/permission/version evidence.

## Verification

- Before repair: both new tests failed on absent Individual labels; eight existing Users page/detail tests passed.
- After repair: focused ten tests passed; full frontend 282/282 passed. Production build includes TypeScript validation and passed; whitespace check passed.
- Browser: actual local SPA in a fresh hidden browser with every /api/v1 request fulfilled by isolated fixtures. Selected Individual visibly appears in Users and sends type=single; Overview shows the existing fixture count 42 as 42 Individual while family, school and status counts remain unchanged. Both PNGs were inspected as pixels. This proves fixture rendering/requests, not authenticated production data or human acceptance. No application API request, user creation, role grant, purchase or email occurred.
- Backend code is unchanged; backend suite, Pint and PHPStan were not rerun for this presentation-only candidate. The preceding F33b release had 451 backend tests passed/one skipped and Pint/PHPStan passed; those are prior-release evidence.

## Adebare's local check

1. Open http://127.0.0.1:5173/admin/users using the existing local super@dev.mahadum360 account. Hard refresh. In Type, expect Individual rather than Single. Select it: the directory should filter normally; Family and Educator/School options stay available. No account creation is required.
2. Open http://127.0.0.1:5173/admin. In Users by type, expect the existing single-category count followed by Individual. Record the count before/after if comparing versions; it must not change because of the label repair. Other cards and categories retain their counts.
3. Optional isolated check from web/: npm test -- src/pages/UsersPage.test.tsx src/pages/AdminOverviewPage.test.tsx. Expect ten passing tests, including filtering with the retained single wire value.
4. Report F34a PASS/FAIL separately from the unresolved Teacher/School/Institution parts of F34. These manual checks have not been performed against an authenticated application account by this agent.

## Release and rollback

No migration or backend release is required for F34a. After the local/push/deployment gates, rebuild/release the SPA and reload cached clients. For rollback, restore only UsersPage.tsx and AdminOverviewPage.tsx from base 6e7cedb and rebuild; no record repair is needed. Do not revert the previously published F12/F33b changes or delete legitimate accounts.

Adebare reported latest-branch production deployment at 22:55 UTC on 6 October (6e7cedb, including F12/F33b). This is user-reported, not independently verified, and does not include F34a. The F12 production receipt migration was not directly observed. The parent is preparing QA communication; this agent has not sent mail or confirmed Deborah acceptance.

Artifacts: workspace f34a-before-tests.json, f34a-focused-tests.json, f34a-full-web-tests.json, f34a-browser/results.json with individual-filter.png and individual-overview.png; exact candidate hashes in f34a-handoff.json.
