# Sign-up, vote and competitions: fix and verification plan

Date: 2026-11-04. Scope: survey items F18, M2, M6, M7, M11 and M8. This is a plan based on the local code; it does not claim a fresh browser retest or a production result.

| Item | Current code finding | Work and local acceptance check |
| --- | --- | --- |
| F18 — country code, unique phone and email | `PhoneInput` is used on sign-up. `RegisterRequest` lowercases email, normalizes phone and validates both as unique. The users table has a unique email index and the 2026-09-06 migration adds a unique phone index. `AuthTest` covers duplicate Nigerian and diaspora numbers and duplicate email. | Run the focused authentication tests and sign up through the UI twice with the same number in different formats and a different email. Confirm the second attempt shows a phone error, creates no user, and a duplicate email also fails. If the local database has not applied the phone migration, apply it before UI retesting. Fix only a reproduced gap. |
| M2 — show/hide passwords | Shared `Input` supports an accessible show/hide button. Sign-in, sign-up password and sign-up confirmation enable it. Reset password and reset confirmation do not. | Enable `passwordToggle` on both reset fields. Check all four field locations with keyboard and pointer: show/hide changes only visibility, preserves the value, and does not submit the form. |
| M6 — count votes after verification | Sign-up stores a vote immediately, but the public `/vote` count joins users and includes only rows with `email_verified_at`. `PublicSeoTest` already checks count 0 before verification and 1 afterward. | Run the focused test, then register a new local account with a vote, inspect `/vote` before verifying, verify via the normal email flow, and confirm the count increases exactly once. Do not insert a second vote during verification. |
| M7 — existing user voting | Authenticated `/api/v1/language-votes` requires verified email and allows one vote per account. `/vote` includes a signed-in voting flow. `PublicSeoTest` checks unverified rejection, first vote and duplicate conflict. | Retest with a verified account that did not vote at sign-up. Vote on `/vote`, confirm one count increase and a clear duplicate message on a second attempt. Also check expired-session and unverified-account messages. Fix any UI mismatch found. |
| M11 — visible vote link | `/vote` is linked from the footer and `/languages`, but not the shared primary menu or landing hero. | Add “Vote for the next language” to `ConceptHeader` primary navigation and a hero action on `LandingPage`. Check desktop and mobile menus, keyboard access, and that the link opens `/vote`. |
| M8 — public competition page | `/competitions/:id` and its API are behind sign-in. Admin has a “Public page” link, but it is not public or copyable. The model stores description and two closing dates; it lacks structured rules, prizes, eligibility and entry/judging opening dates. The admin create form exposes only title, season and activity days. A family entry currently requires a parent-owned learner profile; school entry requires school membership. | Add public read-only listing and detail routes/API by stable slug, keeping drafts private and entry/vote actions authenticated. Add admin-editable description, entry and judging dates, rules, prizes and eligibility, with validation and a migration for missing fields. Show these on the public page and add a copy-link control in admin, plus Competition links on the public website and existing app menu. Enforce parent/guardian approval server-side for under-18 entries before they become eligible or public; prevent self-approval and expose approval status to the submitting parent. Retest as guest, adult, child profile, parent, school and admin: guests can read an open competition, drafts are hidden, only permitted accounts can enter/vote, and under-18 entries cannot bypass approval. |

## Implementation order

1. Verify F18, M6 and M7 against the current local build. Record any reproduced failures and fix only those failures.
2. Complete M2 and M11, then run their focused UI checks.
3. Build M8 in small steps: data/admin editor, public read API, public page and links, parent approval workflow, then role and privacy tests.
4. Run `php artisan test` and, in `web/`, `npm test` and `npm run build`. Apply any new M8 migration locally before end-to-end checks. Report each item's pass/fail status with evidence before deployment.

## Release conditions

- M8 needs approved competition copy and dates entered by an organiser before a real competition is opened. The software can be tested with local sample content, but sample content must not be published as an official competition.
- The F18 phone index migration already exists. M8 adds `2026_11_04_000001_add_public_competition_details.php`; run it during deployment after local verification.

## Implementation update (2026-11-04)

- F18: existing email and phone uniqueness protections verified by focused `AuthTest`, including duplicate numbers in different formats and a duplicate email.
- M2: reset password and confirmation now use the shared visibility control; UI tests cover both fields. Sign-in and sign-up already used it.
- M6 and M7: existing verification and one-vote behavior passed `PublicSeoTest`; the local vote page was checked in the browser.
- M11: the current home hero and public navigation now link to `/vote`; both were checked in the local browser, including navigation to the vote page.
- M8: public listing/detail by slug, admin content editor and copy link, publish validation, dates, parent approval and privacy gates are implemented. API and UI tests pass. Local database migration `2026_11_04_000001_add_public_competition_details` has run. The local browser listing is empty because no competition has been published there; the detail page was verified with automated tests.
