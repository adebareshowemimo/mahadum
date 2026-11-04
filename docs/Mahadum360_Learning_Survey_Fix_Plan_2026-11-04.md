# Learning survey review and fix plan — 4 November 2026

Scope: local code and automated tests. A code implementation is not a production acceptance test. Review each row after the proposed test with a new individual, family child, and school learner where relevant.

| ID | Local finding | Next action and acceptance check |
| --- | --- | --- |
| B4 | Implemented in code. Individual sign-up routes to the frontend learning-goal question; the answer is saved on the learner profile, configurable in Admin, and used in lesson suggestions and progress copy. | Retest a fresh individual sign-up and a parent choosing a child's goal in the browser. Confirm save, reload persistence, suggestions, and changed Admin wording. Fix any failure found. |
| B5 | Partial backend only. `POST /assessments` accepts a client-supplied score and sets A1/A2/B1; no learner placement test or language-selection handoff is present. | Build an authored audio and text assessment, grade answers on the server, map Beginner/Intermediate to the course start point, and test both result paths and access limits. |
| B9 | Components and playback for video, quiz, speaking, exercise, game, and assignment exist. Speaking recording/submission is already exposed, although the stated minors-data approval gate is unresolved; Invite someone is available after video. | Audit the editor's ordering and player sequence. Keep Invite someone as the interim speaking step for children; restrict child recording until recording retention, access, and parent/teacher review are approved and tested. Test the full ordered lesson. |
| B10 | Partial. Upload transcoding code generates 240p/360p/720p, the player offers a quality selector, and required uploaded videos track contiguous viewing. YouTube embeds cannot enforce the full-watch rule. Caption and duration publishing requirements are not enforced. | Test a real uploaded video through transcode and all renditions; test both caption languages, 60–180 second rule, and seek rejection. Make the required-watch rule explicit for external embeds or disallow them in required-watch lessons. |
| B11 | Implemented in code. Listen-and-respond and type-what-you-hear have authoring, prompt audio, playback, grading, and tests. | Author and complete one of each on the local UI with real audio; verify answer feedback and retry behavior. |
| B16 | Outstanding. Offline lesson download has no learner flow and the entitlement was disabled. Some promotional copy still promises offline lessons. | Build a premium-only, five-lesson offline cache with expiry, storage limits, revocation, and progress sync; test reconnect and plan expiry. Until accepted, remove the remaining offline marketing claim. |
| B18 | Partial. First completed lesson starts a 1-day streak; a seven-day badge exists. Telco billing has 48-hour payment grace, but streak protection is not linked to it. The shield endpoint arms protection without charging. Monthly tier is absent. | Add weekly/monthly displays, connect eligible telco grace to streak protection, charge a configured coin price atomically before arming a shield, and test missed-day, expiry, and insufficient-balance cases. Disable the free shield action until charged. |
| B19 | Implemented to the recorded 7 September decision: one heart per four answered questions, regardless of correctness; zero hearts locks learning for 12 hours; paid plans have unlimited hearts. The survey's four-WRONG rule conflicts with that decision. | Obtain a product decision before changing the deduction rule. Then update API, tests, Achievements copy, and BRD together; retest correct/wrong answers and paid unlimited hearts. |
| B21 | Implemented in code and tests: stable weekly cohorts of at most 30 distinct learners; family cheer is available. | Retest with 31+ local learners and a parent sending a private cheer; inspect cohort size, rank, and child visibility. |
| N4 | Fixed in current player: the noninteractive video section label reads “Watch” without the play symbol; the video itself has native controls. | Verify the label and playback affordance on desktop and mobile. |
| N8 | Implemented in code: first completed lesson awards the First Steps badge; the lesson result names it and Achievements shows the earned date. | Browser-test the first completion, notification/display, and reload persistence. |
| N9 | Partial. Verified rewarded video flow and unavailable message exist, but a completed ad refills all five hearts, not +1; available inventory depends on an enabled managed video. | Decide +1 versus full refill, update UI and API consistently, configure one approved active video, and test completed, unavailable, interrupted, duplicate, and child-ineligible cases. |
| P14 | New completions award First Steps and start a 1-day streak. No historical backfill found. | Reconcile existing completed lessons to missing First Steps badges and streak starts with a dry-run report and idempotent backfill. Test first lesson after backfill. |
| Q4 | Completion endpoint marks lessons complete only after required components; retries are idempotent. No historical repair found. | Audit learners with all required components/quiz complete but lesson_progress incomplete; dry-run and backfill those records, then verify Learn counts and achievements. |
| F13 | Current Achievements text matches the recorded four-answered-questions and 12-hour-lock rule. | Keep the text aligned with the final B19 decision and test paid unlimited hearts. |
| B47 | Ad-supported flow and remove-ads upsell exist in code; active ad inventory and production behavior are unverified. | Run an eligible adult free-tier ad and paid no-ads test, then verify impressions, reward, frequency, and unavailable handling. No new feature work unless this retest fails. |

## Execution order

1. **Protect current users:** resolve the B19 heart-rule conflict, disable the uncharged B18 shield, and decide N9 reward amount. Keep child recording gated under B9 until the minors-data decision is recorded.
2. **Repair progression:** implement B5 placement; audit and backfill P14/Q4; retest B4 and N8 together.
3. **Complete learning media:** run real-content B10/B11 tests, fix any format or publish gaps, and verify B9 lesson order.
4. **Finish retention:** implement B18 weekly/monthly and telco-grace behavior; retest B21 and family cheer.
5. **Offline and ads:** deliver B16 before re-advertising it; configure and retest N9/B47. Verify N4 on the finished player.

Each item closes only after its automated test and the stated local browser/API acceptance check pass. Deployment and production acceptance follow separately.

## Local implementation update

The fixes below were made and tested locally after the initial review. Production has not been changed.

| IDs | Local result | Remaining acceptance work |
| --- | --- | --- |
| B4 | Learning-goal save, persistence, suggestions, and progress copy were confirmed with a local learner profile in the browser. | Retest with a fresh registration and child profile before production acceptance. |
| B5 | Server grades four authored questions (two audio, two text) and places Beginner or Intermediate. Course selection opens the placement page, including when a parent starts learning as themselves. If no suitable authored questions exist, the learner starts at Beginner. | The user asked to skip sourcing approved audio for now. Author and test the audio questions before claiming the assessment is available for each language. |
| B9 | Player orders video, quiz, speaking, exercise, game, assignment. Child recording is blocked server-side; Invite someone remains available. | Full minor recording workflow awaits the data-handling decision. |
| B10 | Creator can mark a cultural video, enter its duration, upload two caption languages, and process three qualities. Transcoding measures actual duration. Publishing requires 60–180 seconds, two caption languages, three ready qualities, and full-watch; required-watch YouTube is rejected. | Author and play an approved 60–180 second video with dual captions. |
| B11, B21, N4, N8, F13 | Existing code and focused tests supported the prior implementation findings. | Complete the browser checks in the table above with suitable test content/accounts. |
| B16 | Offline downloads and public plan promises stay off for launch, as requested. | No offline playback or progress sync is claimed. |
| B18 | A 30-day badge and weekly/monthly display were added. Eligible telco grace preserves the streak. Streak Shield costs 10 coins by default, is configurable by Admin, and debits the family coin wallet atomically. | Test the Shield purchase with an adult family wallet and a telco grace case on a local account. |
| B19, F13 | Retained the recorded four-answered-questions rule and matching Achievements copy. | A product decision is required before changing it to four wrong answers. |
| N9, B47 | A completed rewarded ad grants one heart, with replay protection and an unavailable message. | The user asked to skip sourcing an approved ad video; inventory remains unavailable until one is provided. |
| P14, Q4 | Added a dry-run-first, idempotent `learning:reconcile-progress` command to repair completed lessons, path progress, First Steps, missing streaks, and 30-day badges. Local dry run, apply, and second dry run were completed. | Run a dry run and review counts before any production repair. |
