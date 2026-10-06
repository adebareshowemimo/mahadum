# October 6 local verification: existing branding and copy

Distinct copy batch authorized for commit/push by Adebare on 6 October on codex/beta-feedback-20260903, reviewed base 1407fdf1e400d8a7bafe9599bdacf0fe274aed8d. User owns production deployment. No email or deployment is performed by this task. EACH source ID remains pending user local acceptance; the user instructed "push to origin and continue with next fix"; exact remote delivery is verified in the task handoff.

| Source ID | Reproduced defect and resulting text | Local check | Verification state |
|---|---|---|---|
| W1 | Browser titles used dotted/all-caps MAHADUM.360 and duplicated fallback. Static title and routed titles now use Mahadum360; fallback is one name. | Open /families, /login, /register and check tab titles. | Actual browser DOM inspected; local acceptance pending. |
| F26 | Both auth layout branches omitted registration number. Footer is now © Mahadum360, RC 9601595 · current year · Lagos, Nigeria. | Open /login and /register, scroll to bottom of form. | Actual pixels inspected for both; local acceptance pending. |
| W11 | Active /schools shared quote had Language & Culture club and competition entry. It now reads language and culture club and competition entry. | Scroll /schools to A clear, simple school quote. | Before/after actual pixels inspected; local acceptance pending. |
| F6 | Live local pricing API/card said Full learning, forever. Ad-supported. It now says Level 0 free, forever. Ad-supported. | Open /pricing signed out and read Free card subtitle. | Local API and actual card pixels inspected; local acceptance pending. |
| F17 | AI disclosure summary had literal JSX text backslash-u2728. It now contains the actual sparkle character ✨. | In an existing lesson editor, inspect quiz, flashcard or game Generate ... with AI control. | Source/compiler/full frontend checks passed; authenticated editor pixels not run; user confirmation pending. |

W1 and F26 share the same footer change, but retain separate audit acceptance states. F17's original audit Met/Done and No action needed wording are preserved; the embedded reported bug and explicitly authorized repair are reconciled here. Intended source dates: October7 for W1/F26/W11/F6; F17 remains the original Done/reconciliation row. Priorities: P2 public/auth copy, P3 sparkle regression.

## Reproduction and checks

Before changes, an isolated local browser rendered Sign In · MAHADUM.360, Create Account · MAHADUM.360 and MAHADUM.360 · MAHADUM.360 on fallback routes; auth footers lacked RC. The actual local pricing API supplied the wrong full-learning subtitle; the actual school quote rendered the old capitalization. After changes, the same guest routes rendered Sign In · Mahadum360, Create Account · Mahadum360, and Mahadum360 on fallback routes, corrected auth footers, Free subtitle and school quote. Before/after login/register/pricing/schools screenshots were inspected as actual pixels. The isolated browser was closed; user browser sessions were untouched. No authentication, form submit, new account, invitation, charge or database repair was performed.

Passed on this candidate: all 264 existing frontend tests (48 files); npm run build (TypeScript and Vite); two existing PricingTest tests (11 assertions, isolated SQLite); full Pint; full PHPStan (zero errors); whitespace checks. No new copy-mirroring unit tests were added. Full backend suite was not rerun for the one-line subtitle change; prior F33a full-suite result was 422 passed / one skipped out of 423 (2,270 assertions), historical evidence. F17 authenticated editor browser display is explicitly not run.

## Individual user checks

Use a signed-out or private local browser for public/auth routes; do not log out a session you need elsewhere.

1. W1: http://127.0.0.1:5173/families - tab title must be Mahadum360 once. http://127.0.0.1:5173/login - Sign In · Mahadum360. http://127.0.0.1:5173/register - Create Account · Mahadum360.
2. F26: On local login and register pages, scroll below the form. Both must include Mahadum360, RC 9601595 and the current year. Do not create an account or submit reset mail.
3. W11: http://127.0.0.1:5173/schools - scroll to A clear, simple school quote; the final list item must read language and culture club and competition entry. Quote bands, totals and slider behavior are unchanged.
4. F6: http://127.0.0.1:5173/pricing - the Free card subtitle must read Level 0 free, forever. Ad-supported. Paid prices and features remain unchanged. Do not purchase a plan.
5. F17: Sign in using your existing local author/Admin account; open an existing lesson through /courses. Inspect Generate ... with AI (ChatGPT / Claude) in quiz/flashcard/game controls. Expect a sparkle, not literal backslash-u2728. Do not import data, save content or invoke an external AI service during this display check. Route shape: /courses/{courseId}/lessons/{lessonId}.

Report PASS/FAIL separately for W1, F26, W11, F6 and F17. Adebare authorized this batch commit/push on 6 October. Local acceptance and production deployment remain separate pending states.

## Scope and earlier release states

Files: web/index.html; web/src/lib/brand.ts (new DISPLAY_NAME only); web/src/App.tsx; web/src/components/auth/AuthLayout.tsx; web/src/pages/LandingVariantsPage.tsx; web/src/pages/content/LessonBuilderPage.tsx; app/Http/Controllers/PricingController.php. The visual WORDMARK and locked TAGLINE remain intact. Only the active school quote/API subtitle paths are corrected; inactive legacy LandingPage content is not part of this candidate. No price, entitlement, free-level flag, hearts, W5 starting-level control or previously approved W15 Discuss with a campus rule changed. No migration or provider/security configuration changes. A code-only rollback restores the seven files; no user records need reversal. Independent tmp/ and held hearts files outside the repository are preserved.

F24a origin e183525 and F33a origin 1407fdf are verified. Adebare reported deploying both on 6 October; this is not independently live-verified. Parent confirms Deborah's controlled production QA email sent 6 October at 16:01 UTC from corporate Outlook, no CC; Deborah acceptance and the separate Lucy retest remain pending. User local manual acceptance was not inferred.

## Screenshot evidence

Evidence files are local workspace artifacts, not production assets:

- C:/Users/adeba/Documents/Codex/2026-10-06/task/copy-fix/after-login.png
- C:/Users/adeba/Documents/Codex/2026-10-06/task/copy-fix/after-register.png
- C:/Users/adeba/Documents/Codex/2026-10-06/task/copy-fix/after-pricing.png
- C:/Users/adeba/Documents/Codex/2026-10-06/task/copy-fix/after-schools.png
- Matching before-*.png and before-/after-browser-results.json preserve reproduction details.


## Candidate SHA-256

- web/src/lib/brand.ts: 87F99A35BE9D08C2B2CE49AF5BDEC1CEB2E3CC678631A9AB84EBF58A54247BB7
- web/index.html: BCEE3053D0F3EF6CD6E195246D92EB4B762DC0938A3273D9A691DA2D344D7792
- web/src/App.tsx: 6AF93277C9DA197B81D5D003332D789C6A744830C9ED0E19C6422C988B2FB9A7
- web/src/components/auth/AuthLayout.tsx: 90EF70DC303A7F73C44D1023901F8750F94F3F2FEFC34628C3E935652E0CA087
- web/src/pages/LandingVariantsPage.tsx: A6410BA003B11D908E975E04DC9EF2FF4048607984AFDEE476A85CB2C1043ADB
- web/src/pages/content/LessonBuilderPage.tsx: 474E877E683192DFDB18D54EDEB7C83644C4F81400B185BDFFF75D66BAC4484A
- app/Http/Controllers/PricingController.php: 5894CDF655FA7A5CC1C58F03752ACD6FAD528BF3B5EE85AEB28A83E246937051
