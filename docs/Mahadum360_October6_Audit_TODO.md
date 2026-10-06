# Mahadum360 October 6 audit implementation and release to-do

Source: Mahadum360 Test Results - Fix by 7 Oct 2026.xlsx, Library libfile_d384814a74bc81918140bc576168a324. Both tabs read completely through Library extracted text. Original bytes could not be materialized: the current Library helper requires os.setxattr, unavailable on Windows. No screenshot-dependent finding is claimed.

Gate order for EACH source ID: code/finding → tests → Adebare local confirmation → confirmed production deployment → Deborah live QA. The parent task owns QA emails. No deployment or email has happened. A grouped batch is not approval of every linked item. Dates are requested targets, not delivery promises. New features and consequential business-rule changes remain decision-gated.

Reconciliation: 125 detail rows = 55 Met + 41 Partial + 22 Not met + 7 Cannot verify. Summary reports 124/40 Partial because it omits the unlabelled streak row. B18-A below names Test Results index 17 (Excel row 19); activity start/tiers due October 7. B18 grace/Shield is October 15. B35 is absent and stays explicitly unresolved. Detail dates override the stale all-October-7 summary: 61 October 7, four October 15, six Phase 2, 54 Done.

Release handoff update (6 October): Adebare instructed "Just push to origin, I will deploy myself on the server." The F24a commit and origin push were authorized and completed; this is not blanket push authorization for later candidates. Local acceptance, user-performed production deployment and Deborah QA remain pending and are separate states. The agent will not deploy or seek host credentials. The parent sends Deborah QA only after live deployment is confirmed.

First local release candidate: F24 parent provisioning on Admin create/grant. It addresses a reproduced missing-family path, not a verified repair of live Lucy. Existing household ownership, learners, wallets and deleted households are preserved. No production record is automatically relinked.

Prepared hearts work is outside the release candidate: B19/F13 request wrong-answer deduction, while September 7 approval and repository instructions require all answers. Resolve the changed rule with Adebare before installing or releasing it. W15 preserves the previously approved “Discuss with a campus”; W5 stays unresolved. Minors recording and the separate CX/14-feature roadmap stay gated.

## Deduplicated work groups

- [ ] A01 · P1 · Family links and parent pages · source IDs: F24, B3, B22, B25 · targets: 2026-10-07, Phase 2
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A02 · P1 · School teachers, membership and identities · source IDs: B33, F33, F34 · targets: 2026-10-07
  Code/change: F33a teacher assignment subset ready locally; tests: 27 relevant backend tests passed, full backend: 422 passed / one skipped out of 423, 2,270 assertions; local confirmation: pending; origin push: authorized by Adebare; exact delivery result in task handoff; production: not deployed; Deborah: not requested. Other work in this group remains open.

- [ ] A03 · P1 · Roster email and validation · source IDs: B28, F30 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A04 · P1 · School join link · source IDs: F32 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A05 · P1 · Assignments and teacher analytics · source IDs: B13, B30, F29, B29 · targets: 2026-10-07
  Code/change: F33a teacher assignment subset ready locally; tests: 27 relevant backend tests passed, full backend: 422 passed / one skipped out of 423, 2,270 assertions; local confirmation: pending; origin push: authorized by Adebare; exact delivery result in task handoff; production: not deployed; Deborah: not requested. Other work in this group remains open.

- [ ] A06 · P1 · Buy data and coordinated adverts · source IDs: B48, F23, F22 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A07 · P1 · Retry XP and duplicate leaderboard · source IDs: F12, F14 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A08 · P1 · Hearts: decision on October audit versus approved September rule · source IDs: B19, F13 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A09 · P1 · Activity streak start and weekly/monthly tiers · source IDs: B18-A · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A10 · P2 · Leagues, cheer and badge verification · source IDs: B21, F8, B20 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A11 · P1 · Actual video completion and quality · source IDs: B10 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A12 · P2 · Unique email and phone verification · source IDs: F18 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A13 · P2 · Chores, reviews, coin transfer and pools · source IDs: B24, B25, B26, B46 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A14 · P2 · Automatic notifications · source IDs: B27 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A15 · P2 · School and platform analytics, seat inactivity threshold decision · source IDs: B32, B34, B41 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A16 · P2 · Telco and settlement configuration · source IDs: B42, B45, F20 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A17 · P2 · Referral activation and activity views · source IDs: B51, X1, X2, F2, X3, X4, F1, X5, F3 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A18 · P2 · Referral safeguards and share text · source IDs: B52, B53, X6 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A19 · Decision · Stories, Proverbs and Trivia new scope · source IDs: B14, X7, X8, X9, X10, X11, X12 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A20 · P2 · Inventory and reversible dummy cleanup · source IDs: F16, F28 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A21 · P2 · Browser/auth branding and RC footers · source IDs: W1, F26 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A22 · P2 · Free subtitle and school quote wording · source IDs: F6, W11 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A23 · Decision · Starting-level parent control · source IDs: W5 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A24 · Decision · Conflicting previously approved campus copy · source IDs: W15 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A25 · Decision · Built-in email versus Fluent CRM · source IDs: F21 · targets: 2026-10-07
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A26 · Later · Learning goals and placement · source IDs: B4, B5 · targets: 2026-10-15
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A27 · Later · Bilingual UI and accessibility · source IDs: B6 · targets: 2026-10-15
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A28 · Later · 48-hour telco grace and purchasable Shield · source IDs: B18 · targets: 2026-10-15
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A29 · Phase 2 · Minors recording, preserve interim invitation · source IDs: B9, B12 · targets: Phase 2
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A30 · Phase 2 · Audio-prompt quiz types · source IDs: B11 · targets: Phase 2
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A31 · Phase 2 · Offline lessons · source IDs: B16 · targets: Phase 2
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A32 · Phase 2 · Family per-member metrics · source IDs: B22 · targets: Phase 2
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A33 · Phase 2 · Captions and publish rules · source IDs: B39 · targets: Phase 2
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A34 · Reconcile · Literal sparkle label despite Met · source IDs: F17 · targets: Reconcile / regression
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A35 · Reconcile · Performance count definitions despite Met · source IDs: F25 · targets: Reconcile / regression
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

- [ ] A36 · Reconcile · Duplicate invoice investigation; missing B35 · source IDs: F31, B35 · targets: Reconcile / regression
  Code/change: pending; tests: not run; local confirmation: pending; production: not deployed; Deborah: not requested.

## Per-source evidence and tickable gates

Met is the audit author’s status, not newly verified completion. Preserve affected Met behavior and record a no-change finding when appropriate. X6 remains a verification task despite Met.

### B1 · Delivery BRD · Account · Done
Audit status: Met. Tested: Username + password, Google login, password reset.
Audit evidence: Sign-in has email/username, Continue with Google and Forgot password. Reset email reaches a real inbox (tested by Ifeoma, 6 Oct).
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B2 · Delivery BRD · Account · Done
Audit status: Met. Tested: Age gate: adult self-serve vs under-13 under a parent (COPPA/NDPA consent).
Audit evidence: Sign-up asks for date of birth first. Anyone under 13 is blocked with a message that only users above 13 can sign up (confirmed by Ifeoma); children join as profiles a parent creates in a Family account.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B3 · Delivery BRD · Account · Done
Audit status: Met. Tested: Family accounts up to 6 profiles; profile switching with parental PIN.
Audit evidence: The Family plan says up to 6 profiles and a Switch profile control exists. Family profiles and profile PINs work (confirmed by Ifeoma). The tested Lucy account failed to load its family, which is a separate bug (feedback row 24).
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B4 · Delivery BRD · Account · 2026-10-15
Audit status: Not met. Tested: Identity goal-setting (grandparents, travel, school, business).
Audit evidence: Not seen in sign-up or on the learner home. Meant as an onboarding question asking why the learner is learning, e.g. "To talk with my grandparents", "For travel back home", "For school", "For business or work". The answer would shape suggested lessons, progress messages ("You can now greet Grandma!") and reminders.
Requested action: Add an onboarding question after sign-up: 'Why are you learning?' (grandparents, travel, school, business). Save the answer to the profile and use it for lesson suggestions and progress messages.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B5 · Delivery BRD · Account · 2026-10-15
Audit status: Not met. Tested: Adaptive placement assessment → Beginner/Intermediate.
Audit evidence: Not seen. The landing page has a 1-minute taster quiz, not a placement test.
Requested action: Add a short placement test (audio + text) after language selection that places the learner at Beginner or Intermediate and unlocks the matching start point.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B6 · Delivery BRD · Account · 2026-10-15
Audit status: Partial. Tested: Bilingual UI; WCAG 2.1 AA; light/cream theme.
Audit evidence: Course content is bilingual, and there are light/dark modes and an Accessibility page. There is no UI language switch, and accessibility was not audited.
Requested action: Add a UI language switch (English + selected Nigerian language) and run a WCAG 2.1 AA check (contrast, keyboard access, alt text, labels); fix what it finds.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B7 · Delivery BRD · Account · Done
Audit status: Met. Tested: Phone OTP only at telco opt-in, not login.
Audit evidence: Phone is optional at sign-up ("used for airtime billing and account recovery"), and login has no OTP.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B8 · Delivery BRD · Learner · Done
Audit status: Met. Tested: Learning tree with progressively unlocking nodes.
Audit evidence: Lessons show Active / Locked in order within each level.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B9 · Delivery BRD · Learner · Phase 2
Audit status: Partial. Tested: Lesson flow: Video → Quiz → Speaking → Exercise → Game → Assignment (Speaking temporarily covered by "Invite someone", BRD note 6 Oct).
Audit evidence: Practice exercises and assignments are loaded as quiz steps after each practice video (confirmed by Ifeoma). Speaking step (record and submit): pending resolution. It is held back on purpose while data handling for minors (recordings of under-13s) is settled. Interim workaround: the "Practice with me / Invite someone" button, which pairs the learner with a family member or classmate to practise aloud.
Requested action: Keep 'Invite someone' as the interim speaking step. Once minors' data handling is approved, add a Speaking step (record, submit, parent/teacher review) to the lesson editor and player.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B10 · Delivery BRD · Learner · 2026-10-07
Audit status: Partial. Tested: Cultural videos 60–180s, dual captions, 240/360/720.
Audit evidence: Tested as Tosin: the first Yorùbá video is 2:42, within 60–180s, and the dual language is built into the video. Gaps: one MP4 with no 240/360/720 quality choice, and jumping to the end of the video counts as "watched".
Requested action: Encode each video at 240p/360p/720p with a quality selector, and block 'Continue' until the video has actually played to the end (no seek-to-end skip).
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B11 · Delivery BRD · Learner · Phase 2
Audit status: Partial. Tested: Quiz types (MCQ, true/false, listen, match, word-bank, fill-blank, type-what-you-hear, complete-the-chat).
Audit evidence: Delivered (confirmed by Ifeoma): MCQ, true/false, match-pairs, word-bank, fill-blank and complete-the-chat. Pending under the BRD: listen-and-respond and type-what-you-hear.
Requested action: Build the two pending quiz types: listen-and-respond and type-what-you-hear (audio prompt + answer check).
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B12 · Delivery BRD · Learner · Phase 2
Audit status: Can't verify. Tested: Speaking challenges: record, submit, parent review. Temporary workaround (BRD note, 6 Oct): "Practice with me / Invite someone" replaces recording until data handling for minors is resolved.
Audit evidence: Pending resolution: recording is paused until data handling for minors is settled. Interim workaround: "Invite someone" to practise together. The parent Reviews queue also failed to load.
Requested action: On hold: approve the consent, storage and deletion policy for minors' recordings, then switch on record-and-submit with parent review. Keep 'Invite someone' until then.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B13 · Delivery BRD · Learner · 2026-10-07
Audit status: Partial. Tested: Flashcards, games, assignments.
Audit evidence: Flashcards and games are live in the editor. Assignments are blocked because no teacher can be assigned.
Requested action: Unblock assignments by fixing teacher assignment (see Feedback #33); then confirm learners can see and submit an assignment.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B14 · Delivery BRD · Learner · 2026-10-07
Audit status: Not met. Tested: Cultural content: proverbs, folktales, festivals, songs.
Audit evidence: The Stories & Folklore and Proverbs & Trivia tabs (spec of 28 Sep) are not built yet.
Requested action: Build the Stories & Folklore and Proverbs & Trivia tabs per the 28 Sep spec (see Expanded BRD #7–12).
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B15 · Delivery BRD · Learner · Done
Audit status: Met. Tested: Completion / reward screens.
Audit evidence: Live (confirmed by Ifeoma). Not seen during this review because no lesson was completed.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B16 · Delivery BRD · Learner · Phase 2
Audit status: Not met. Tested: Offline download (premium, up to 5).
Audit evidence: Still outstanding: offline download is not built. It was taken off the plan descriptions (1 Sep feedback) until it is live.
Requested action: Still outstanding: build premium offline lesson download (up to 5 lessons), then add it back to the plan descriptions.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B17 · Delivery BRD · Learner · Done
Audit status: Met. Tested: Progress score = % completion (BRD changed 6 Oct; was weighted 30% video / 20% quiz / 25% speaking / 15% assignment / 10% engagement).
Audit evidence: A beaded progress line at the top of each lesson fills in as video and quiz steps are completed (step progress). This matches the changed requirement. The parent's child page also shows lessons completed. No numeric % is displayed; add one beside the beads if a number is wanted.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B18-A · Delivery BRD (unlabelled continuation) · Gamification · 2026-10-07
Audit status: Partial. Tested: Multi-tier streaks; 48-hour grace; Streak Shield.
Audit evidence: "0 Day Streak" and a "Protect streak" button are shown. Weekly and monthly tiers and the 48-hour grace period were not seen. Bug: the rule (confirmed by Ifeoma) is that the streak becomes "1 Day Streak" as soon as the learner completes any activity (a video, a quiz, etc.), but after Tosin watched a video and completed two quizzes on 6 Oct, Achievements still showed "0 Day Streak".
Requested action: 1.Start the streak at '1 Day Streak' as soon as any activity is completed (video, quiz, etc.). Add weekly and monthly streak tiers
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B18 · Delivery BRD · Gamification · 2026-10-15
Audit status: Partial. Tested: Multi-tier streaks; 48-hour grace; Streak Shield.
Audit evidence: "0 Day Streak" and a "Protect streak" button are shown. Weekly and monthly tiers and the 48-hour grace period were not seen. Bug: the rule (confirmed by Ifeoma) is that the streak becomes "1 Day Streak" as soon as the learner completes any activity (a video, a quiz, etc.), but after Tosin watched a video and completed two quizzes on 6 Oct, Achievements still showed "0 Day Streak".
Requested action: 1.48-hour telco grace period and a purchasable Streak Shield; show them on Achievements.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B19 · Delivery BRD · Gamification · 2026-10-07
Audit status: Partial. Tested: Hearts gate learning on the Free tier (Rule 4, updated 6 Oct); paid plans have unlimited hearts.
Audit evidence: BRD updated by Ifeoma: Free-tier hearts gate learning (1 heart per 4 questions, 12-hour lock at zero, "See you in 12 hours, or upgrade for unlimited hearts"). Tested as Tosin (Free): hearts fell 5→0 over two quiz attempts. At zero the lesson locked with "See you in 12 hours, or upgrade for unlimited hearts. Access resumes 10/6/2026, 7:51 PM" and an upgrade link. Bug: the rule is 1 heart per 4 wrong answers (confirmed by Ifeoma), but the site takes a heart every 4 questions answered, so a correct answer cost a heart at question 12.
Requested action: Change heart deduction to 1 heart per 4 WRONG answers (correct answers never cost a heart). Update the Achievements text to 'four wrong answers'. Test paid-plan unlimited hearts.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B20 · Delivery BRD · Gamification · Done
Audit status: Met. Tested: XP + cultural badges.
Audit evidence: XP, Level 0–5 badges, Family Hero and others are on Achievements.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B21 · Delivery BRD · Gamification · 2026-10-07
Audit status: Partial. Tested: Weekly leagues (30 users) + family cheer.
Audit evidence: The weekly leaderboard exists. League size could not be seen because only one learner was in the league.
Requested action: Group learners into 30-user weekly leagues and add the family-cheer element; seed test users to confirm league size.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B22 · Delivery BRD · Parent · Phase 2
Audit status: Partial. Tested: Family dashboard.
Audit evidence: Loads on the working family account: members, profile count (1 of 6), wallet coins, PINs, and each child's coins and lessons completed. Missing: streaks, speaking scores and ranks per member. Still fails on the Lucy account (family API 404).
Requested action: Add each member's streak, speaking score and rank to the family dashboard.(Speaking score comes with phase 2)
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B23 · Delivery BRD · Parent · Done
Audit status: Met. Tested: Parent wallet (Flutterwave/Paystack), coin balance, billing history.
Audit evidence: Wallet loads on the working family account: coin and cash balance, Add money with Monnify, Paystack or Flutterwave, and Send coins to a child. Billing history is on Billing.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B24 · Delivery BRD · Parent · 2026-10-07
Audit status: Partial. Tested: Chore-to-Coin.
Audit evidence: "New chore" sets the title, child, coin reward and due date. The submit, approve and coin release steps could not be run because the wallet has 0 coins.
Requested action: Fund a test wallet (sandbox) and run the full chore flow: child submits evidence, parent approves/rejects/requests more, coins release only on approval.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B25 · Delivery BRD · Parent · 2026-10-07
Audit status: Partial. Tested: Review queue (speaking, assignment, chores).
Audit evidence: Reviews loads on the working family account ("Approve chores and check what your children have submitted"). Speaking submissions are paused, and assignments are blocked.
Requested action: Confirm chore submissions appear in Reviews; add speaking and assignment submissions once those features are live.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B26 · Delivery BRD · Parent · 2026-10-07
Audit status: Partial. Tested: Coin transfer, family challenges, low-balance alerts.
Audit evidence: Send coins to a child is present, but it can't be used with 0 coins. Family challenges and low-balance alerts were not seen.
Requested action: Test coin transfer with a funded wallet; add family challenges and low-balance alerts.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B27 · Delivery BRD · Parent · 2026-10-07
Audit status: Partial. Tested: Notifications (push, SMS, WhatsApp, email).
Audit evidence: Working (confirmed by Ifeoma): SMS, WhatsApp and email for sharing referral codes; email for practice invites and for inviting a child to a class. Not yet seen: push notifications, and the automatic alerts for inactivity, achievements, low balance and review needed.
Requested action: Add push notifications, then trigger and verify the automatic alerts (inactivity, achievement, low balance, review needed) on push, SMS, WhatsApp and email.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B28 · Delivery BRD · Teacher · 2026-10-07
Audit status: Partial. Tested: Class roster + bulk CSV import.
Audit evidence: CSV import with a template exists (school admin). There is no email column, and row validation was not tested.
Requested action: Add an Email column to the roster CSV template and import; test row-level validation with a file containing errors.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B29 · Delivery BRD · Teacher · 2026-10-07
Audit status: Can't verify. Tested: Speech/quiz analytics grid with drill-down.
Audit evidence: A class Analytics tab exists, but no teacher account could reach it.
Requested action: Once a teacher can be assigned, verify the class Analytics tab shows the speech/quiz grid with drill-down per student.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B30 · Delivery BRD · Teacher · 2026-10-07
Audit status: Not met. Tested: Assignment creation wizard.
Audit evidence: There is no create button. Assignments need an assigned teacher, and none can be assigned.
Requested action: Add a 'Create assignment' button and wizard for the class teacher (and school admin), after fixing teacher assignment.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B31 · Delivery BRD · Teacher · Done
Audit status: Met. Tested: Referral hub + earnings ledger.
Audit evidence: The same referral hub works across all profiles (confirmed by Ifeoma). It has a code, share links, referral activity, available balance, commissions and payouts, as seen on the parent and school accounts.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B32 · Delivery BRD · School admin · 2026-10-07
Audit status: Partial. Tested: Dashboard KPIs (avg quiz/speaking score, completion, active seats, top classes/students).
Audit evidence: Shows classes, students, seats, unpaid invoices and subscription. No quiz or speaking averages, completion or top performers.
Requested action: Add average quiz and speaking scores, completion rate, and top classes/students to the school dashboard.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B33 · Delivery BRD · School admin · 2026-10-07
Audit status: Partial. Tested: Student and teacher rosters; class enrolment.
Audit evidence: Learners can be added or invited by email per class. There is no student list page, no teacher list and no teacher invite.
Requested action: Add Students and Teachers list pages under the school console, with teacher invite by email.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B34 · Delivery BRD · School admin · 2026-10-07
Audit status: Partial. Tested: Seat allocation, tier discounts, inactivity review.
Audit evidence: The Seats page shows allocations and Buy seats, and pricing has size tiers. No inactivity review.
Requested action: Add an inactivity review for seats (learners inactive for X days) so schools can reassign seats.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B36 · Delivery BRD · School admin · Done
Audit status: Met. Tested: School referral code distribution.
Audit evidence: School code with Copy link, WhatsApp and SMS.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B37 · Delivery BRD · Content owner · Done
Audit status: Met. Tested: Course → Level → Lesson → Component authoring.
Audit evidence: Course structure with levels, lessons and steps, plus Preview, Edit and Delete.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B38 · Delivery BRD · Content owner · Done
Audit status: Met. Tested: Quiz builder: all types, correct-answer config.
Audit evidence: Done and working (confirmed by Ifeoma). It covers the delivered quiz types, with answer settings and AI question generation. Existing quizzes have 10 questions each.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B39 · Delivery BRD · Content owner · Phase 2
Audit status: Partial. Tested: Video upload, transcode, captions; publish rules.
Audit evidence: Video upload is done (confirmed by Ifeoma). Current lesson videos already carry both languages, so learners see dual-language text today. The platform's own caption feature is not met. It needs testing with a video that has no built-in captions. The "video + quiz + speaking" publish rule is not tested, and it depends on the paused Speaking step.
Requested action: Test caption upload on a video without built-in text; confirm transcoding and the publish rule (video + quiz required, speaking once live).
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B40 · Delivery BRD · Content owner · Done
Audit status: Met. Tested: Draft/published versions; drop-off analytics.
Audit evidence: Draft/Published status, Re-publish, and an Insights button on each lesson.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B41 · Delivery BRD · Super Admin · 2026-10-07
Audit status: Partial. Tested: Platform metrics (revenue, users, language/AI/billing analytics).
Audit evidence: Shows users by type, revenue, orgs, subscriptions and billing health. No language or AI analytics, and revenue is ₦0.
Requested action: Add language and AI analytics to the platform overview; confirm revenue populates once payments run.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B42 · Delivery BRD · Super Admin · 2026-10-07
Audit status: Partial. Tested: Settlements: referral payout %, commissions, telco share, billing success.
Audit evidence: Shows telco revenue, clawback, commissions and payouts. No payout % setting was seen.
Requested action: Add the referral payout % setting to Settlements and confirm telco revenue-share and daily billing success figures.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B43 · Delivery BRD · Super Admin · Done
Audit status: Met. Tested: Promo codes; org activation; content/language control; fraud queue.
Audit evidence: All present (Promo codes, Organizations, Languages, Fraud review).
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B44 · Delivery BRD · Monetisation · Done
Audit status: Met. Tested: Tiers: Free / Individual / Family / School.
Audit evidence: All four tiers are on Pricing and in Admin › Plans, with annual options.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B45 · Delivery BRD · Monetisation · 2026-10-07
Audit status: Not met. Tested: Telco VAS ₦50/day (02:00 charge, grace, STOP→3600).
Audit evidence: No telco plan in Admin › Plans, and telco billing shows 0/0 attempts.
Requested action: Set up the telco plan (N50/day, 02:00 charge, grace, soft-downgrade, reactivation, STOP to 3600) and test with the aggregator sandbox.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B46 · Delivery BRD · Monetisation · 2026-10-07
Audit status: Partial. Tested: Coin economy, parent wallet, group coin pools.
Audit evidence: The parent wallet and child coin balances work. Group coin pools were not seen.
Requested action: Add group coin pools for families.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B47 · Delivery BRD · Monetisation · Done
Audit status: Met. Tested: Ad-supported free tier, rewarded-heart ads, remove-ads upsell.
Audit evidence: Ads show on free accounts, and paid plans are ad-free. A "Watch ad" option to refill hearts shows on Achievements, plus the upgrade upsell when hearts run out.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B48 · Delivery BRD · Monetisation · 2026-10-07
Audit status: Not met. Tested: In-app data top-up.
Audit evidence: Buy data fails with "Monnify authentication failed".
Requested action: Fix the Monnify credentials on Buy data so plans load and a test purchase completes.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B49 · Delivery BRD · Monetisation · Done
Audit status: Met. Tested: Landing page advert banners + management.
Audit evidence: Admin › Adverts. A banner is live on the landing page.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B50 · Delivery BRD · Referrals · Done
Audit status: Met. Tested: Unique codes for users and schools.
Audit evidence: Both have codes with share links.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B51 · Delivery BRD · Referrals · 2026-10-07
Audit status: Can't verify. Tested: User rewards; school commissions on renewals.
Audit evidence: Needs paid transactions.
Requested action: Run a sandbox paid subscription through a referral and a school renewal; confirm rewards and commissions post.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B52 · Delivery BRD · Referrals · 2026-10-07
Audit status: Partial. Tested: 14-day escrow, chargeback, ₦5k floor / ₦50k cap.
Audit evidence: The ₦5,000 payout floor is stated and escrow appears in content performance. The others could not be tested.
Requested action: Test 14-day escrow, chargeback cancellation and the N50k individual cap with sandbox payments.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B53 · Delivery BRD · Referrals · 2026-10-07
Audit status: Partial. Tested: Fraud controls (fingerprinting, velocity >15/24h, payment gate).
Audit evidence: The fraud review queue cites the velocity guard. It could not be triggered.
Requested action: Trigger the velocity guard in test (>15 sign-ups/24h on one code) and confirm the code is frozen and appears in Fraud review.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B54 · Delivery BRD · Referrals · Done
Audit status: Met. Tested: Promo codes single-use per institution; fraud-alert screen.
Audit evidence: Promo codes have max redemptions and an "applies to" setting, and the Fraud review screen exists.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### X1 · Expanded BRD · Referrals (1 Sep) · 2026-10-07
Audit status: Can't verify. Tested: Code activates only after the referred person has a paid subscription and has finished 1 lesson + 1 quiz.
Audit evidence: Needs a paid test subscription.
Requested action: Run a sandbox paid sign-up via a referral code; confirm the code only activates after payment + 1 lesson + 1 quiz.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### X2 · Expanded BRD · Referrals (1 Sep) · 2026-10-07
Audit status: Can't verify. Tested: Referrer earns 5% of the referred person's purchase value for the first month.
Audit evidence: Needs a paid test subscription.
Requested action: Confirm the referrer is credited 5% of the first-month purchase after activation.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### X3 · Expanded BRD · Referrals (1 Sep) · 2026-10-07
Audit status: Can't verify. Tested: Referring an already-active account shows "account already exists".
Audit evidence: Not tested, because it would send a real invite to an existing user.
Requested action: Invite an existing test account's email and confirm the 'account already exists' prompt.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### X4 · Expanded BRD · Referrals (1 Sep) · 2026-10-07
Audit status: Partial. Tested: Referrer dashboard: activation date, code, via email, via phone, status; search by email or phone.
Audit evidence: Referral activity list and a "Search by email or phone" box are live. The columns can't be confirmed until a referral exists.
Requested action: After one test referral activates, confirm the dashboard shows activation date, code, via email, via phone and status, and that search works.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### X5 · Expanded BRD · Referrals (1 Sep) · 2026-10-07
Audit status: Partial. Tested: Super Admin view per code: count activated, via email, via phone, active, inactive.
Audit evidence: Shows Code, Activated, Active, Inactive. The Via Email and Via Phone columns are missing.
Requested action: Add 'Via Email' and 'Via Phone' columns to Admin › Referral codes.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### X6 · Expanded BRD · Referrals (1 Sep) · 2026-10-07
Audit status: Met. Tested: Share message: "[Name] invited you to a Nigerian language lesson on Mahadum360! Free trial: [link]".
Audit evidence: Copy link, WhatsApp, SMS and email invite are present. The message text was not opened.
Requested action: Set the share text to: '[Name] invited you to a Nigerian language lesson on Mahadum360! Free trial: [link]' and verify on WhatsApp, SMS and email.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### X7 · Expanded BRD · Stories & Trivia (28 Sep) · 2026-10-07
Audit status: Not met. Tested: Every course has three tabs: Lessons, Stories & Folklore, Proverbs & Nigeria trivia.
Audit evidence: Courses show levels and lessons only, with no tab bar.
Requested action: Add a tab bar to each course: Lessons, Stories & Folklore, Proverbs & Nigeria trivia.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### X8 · Expanded BRD · Stories & Trivia (28 Sep) · 2026-10-07
Audit status: Not met. Tested: Stories grouped by age band (0–5, 6–12, 13–18, 18+, Folklore) as thumbnail cards.
Audit evidence: Not built.
Requested action: Build Stories & Folklore with age-band levels (0–5, 6–12, 13–18, 18+, Folklore) and thumbnail cards.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### X9 · Expanded BRD · Stories & Trivia (28 Sep) · 2026-10-07
Audit status: Not met. Tested: Each story ₦500 one-time, with a free preview (12 pages, 1 minute or chapter 1) ending in an "Unlock · ₦500" card.
Audit evidence: Not built. No story pricing in Admin › Plans.
Requested action: Add N500 one-time story pricing with free preview (12 pages / 1 min / chapter 1) ending in an 'Unlock · N500' card; pay by card, transfer or USSD.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### X10 · Expanded BRD · Stories & Trivia (28 Sep) · 2026-10-07
Audit status: Not met. Tested: 18+ titles hidden for under-18 and school accounts.
Audit evidence: Not built.
Requested action: Hide 18+ titles for under-18 and school accounts.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### X11 · Expanded BRD · Stories & Trivia (28 Sep) · 2026-10-07
Audit status: Not met. Tested: Creator view: tab bar on the course, and "+ Add" changes to Add level / Add band / Add section.
Audit evidence: The course editor shows "Add level" only.
Requested action: In the course editor, make '+ Add' change per tab: Add level / Add band / Add section.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### X12 · Expanded BRD · Stories & Trivia (28 Sep) · 2026-10-07
Audit status: Not met. Tested: Learner view: band filter chips, ₦500 banner, featured story, shelf per band; proverb of the day, proverb sets, trivia quiz sets.
Audit evidence: Not built. My learning shows lessons only.
Requested action: Build the learner Stories shelves (band chips, N500 banner, featured story, Continue) and the Proverbs & Trivia tab (proverb of the day, proverb sets, trivia quiz sets).
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F1 · Open feedback · Feedback · Done
Audit status: Met. Tested: Referrer dashboard with search by email or phone (4 Sep, #1–2).
Audit evidence: Refer & earn shows the code, WhatsApp/SMS/email invites, a Referral activity list and a "Search by email or phone" box. No referral data yet to confirm the columns.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F2 · Open feedback · Feedback · 2026-10-07
Audit status: Can't verify. Tested: Referral activation rules: paid plan, 1 lesson + 1 quiz, 5% first month, block existing accounts (4 Sep, #1).
Audit evidence: Needs a paid subscription to test.
Requested action: Retest with a sandbox paid subscription (see Expanded BRD #1–3).
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F3 · Open feedback · Feedback · 2026-10-07
Audit status: Partial. Tested: Super Admin referral view per code (1 Sep).
Audit evidence: Admin › Referral codes shows Code, Activated, Active, Inactive. The Via Email and Via Phone columns from the sample are missing, although the page description promises them.
Requested action: Add 'Via Email' and 'Via Phone' columns to Admin › Referral codes.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F4 · Open feedback · Feedback · Done
Audit status: Met. Tested: Super Admin can assign roles (1 Sep, #2).
Audit evidence: Each user's page has role toggles (super_admin to student). The Roles page itself is a view-only permission matrix.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F5 · Open feedback · Feedback · Done
Audit status: Met. Tested: Course header edit and table of contents (1 Sep #4; 4 Sep #3).
Audit evidence: "Edit course details" and an auto-built Table of contents with "Edit table of contents" are live.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F6 · Open feedback · Feedback · 2026-10-07
Audit status: Partial. Tested: Pricing wording (1 Sep #5; 4 Sep #3).
Audit evidence: Fixed: "All L0 lessons and quiz free", "All Free plan benefits", "All Individual plan benefits", offline downloads removed. Still wrong: the Free card subtitle says "Full learning, forever", which contradicts Level 0 only.
Requested action: Change the Free card subtitle 'Full learning, forever' to match Level 0 only (e.g. 'Level 0 free, forever').
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F7 · Open feedback · Feedback · Done
Audit status: Met. Tested: Individual option at sign-up (1 Sep).
Audit evidence: Individual, Family, Educator/School and Institution are all offered.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F8 · Open feedback · Feedback · 2026-10-07
Audit status: Partial. Tested: Badges: Level 0–5 names, award and notification, badge detail with date (4 Sep #4).
Audit evidence: Achievements lists Star Starter to Culture Master, plus First Steps, Week Warrior, Sharp Shooter and Family Hero. The award, the notification and the badge detail (date earned) could not be seen because no badge has been earned on the tested accounts.
Requested action: Complete Level 0 on a test learner; confirm the Star Starter badge is awarded, the notification shows, and the badge detail shows the date earned.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F9 · Open feedback · Feedback · Done
Audit status: Met. Tested: "0 Day Streak" wording (4 Sep).
Audit evidence: Shows "0 Day Streak".
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F10 · Open feedback · Feedback · Done
Audit status: Met. Tested: Tone practice: invite another user (4 Sep #5).
Audit evidence: My learning shows a "Practice with me" card with "Invite someone".
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F11 · Open feedback · Feedback · Done
Audit status: Met. Tested: Tone shows "Coming soon" (1 Sep #9).
Audit evidence: Seen as "Tone pop — Coming soon" in the game editor. Not every screen was checked.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F12 · Open feedback · Feedback · 2026-10-07
Audit status: Partial. Tested: Quiz XP: 1 XP per correct answer, XP on repeat, score per quiz, separate quizzes (4 Sep #6).
Audit evidence: Tested as Tosin: 4 of 10 correct gave +4 XP (1 per correct answer). The results screen shows the score per quiz (40%) with Review lesson video and Retry. Bug: retries should earn XP (rule confirmed by Ifeoma, 4 Sep), but a retry with 2 correct answers added 0 XP. Total XP stayed at 4.
Requested action: Award XP on retries (1 XP per correct answer each attempt). Retest: a retry with correct answers must add to total XP.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F13 · Open feedback · Feedback · 2026-10-07
Audit status: Partial. Tested: Hearts: 1 heart per 4 questions, 12-hour lock, message, unlimited on paid plans (4 Sep #6).
Audit evidence: The rule is stated on Achievements ("One heart per four quiz answers. At zero hearts, learning pauses for 12 hours."). Enforced: tested to zero hearts, then a 12-hour lock with the exact message and an upgrade link. Bug: hearts are taken every 4 questions answered instead of every 4 wrong answers. Paid-plan unlimited hearts were not tested.
Requested action: Deduct 1 heart per 4 wrong answers only; update the Achievements text. Test unlimited hearts on a paid plan.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F14 · Open feedback · Feedback · 2026-10-07
Audit status: Partial. Tested: Leaderboard shows cumulative XP (4 Sep).
Audit evidence: The leaderboard now has a "total XP" field next to weekly XP. With the family account, the leaderboard shows weekly and total XP for 4 learners. Bug: Lucy Okafor appears twice (16 total XP each). Adding up across attempts could not be confirmed because retries earned no XP.
Requested action: Remove the duplicate Lucy Okafor leaderboard entry; after the retry-XP fix, confirm totals add up across attempts.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F15 · Open feedback · Feedback · Done
Audit status: Met. Tested: Free tier: Level 0 open, Level 1+ locked (4 Sep).
Audit evidence: Level 0 is marked Free in every language. Level 1 onward shows "Upgrade To Unlock".
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F16 · Open feedback · Feedback · 2026-10-07
Audit status: Not met. Tested: Dummy data cleanup (4 Sep #7).
Audit evidence: Still present: 6 placeholder schools (e.g. Reilly-Hickle Academy, Kiehn Inc Academy, Fahey PLC Academy), legacy promo codes with odd values (SCHOOL25 ₦0.07, TERM2024 ₦0.19), a stray "level 1 / hello" draft level at the end of the Yorùbá course, and a "Demo family learning" advert still served on the parent pages.
Requested action: Delete the 6 placeholder schools, legacy promo codes (SCHOOL25, TERM2024, etc.), the stray 'level 1 / hello' draft in the Yorùbá course, and the demo adverts.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F17 · Open feedback · Feedback · Done
Audit status: Met. Tested: Games and flashcards: CSV template + AI generation (4 Sep #8).
Audit evidence: Both have "Download CSV template", "Import completed CSV" and "Generate … with AI (ChatGPT / Claude)". Small bug: the button label shows the raw code "\u2728" instead of a sparkle icon.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F18 · Open feedback · Feedback · 2026-10-07
Audit status: Partial. Tested: Sign-up: country code dropdown, unique email and phone (4 Sep #8).
Audit evidence: The country code dropdown is live (defaults to Nigeria +234). Phone uniqueness was not tested because that needs creating duplicate accounts.
Requested action: Enforce unique phone and email at sign-up; test by registering the same phone twice.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F19 · Open feedback · Feedback · Done
Audit status: Met. Tested: Export users and subscription report (4 Sep #9).
Audit evidence: Admin › Reports has "Export users report" (CSV with plan, status, dates and payment status).
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F20 · Open feedback · Feedback · 2026-10-07
Audit status: Partial. Tested: Telco: default Individual, Level 1 only (4 Sep #10).
Audit evidence: Site copy says airtime plans include Level 1 only. No telco plan appears in Admin › Plans, and Telco billing shows 0/0 attempts.
Requested action: Create the telco plan and default telco accounts to Individual with Level 1-only access.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F21 · Open feedback · Feedback · 2026-10-07
Audit status: Partial. Tested: Email marketing: Fluent CRM + Amazon SES (4 Sep #11).
Audit evidence: Amazon SES SMTP is configured and delivery is enabled. Campaigns run from a built-in email module, not Fluent CRM. Password reset emails arrive in a real inbox (tested by Ifeoma, 6 Oct).
Requested action: Confirm with Ifeoma whether the built-in email module replaces Fluent CRM; if not, integrate Fluent CRM with Amazon SES.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F22 · Open feedback · Feedback · 2026-10-07
Audit status: Not met. Tested: Parent and child adverts should promote data (1 Sep #11).
Audit evidence: The parent dashboard still shows the "Hear your child say it in your language" lesson banner.
Requested action: Replace the lesson banner on parent and child pages with the Buy data / top-up advert.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F23 · Open feedback · Feedback · 2026-10-07
Audit status: Not met. Tested: Buy data checkout.
Audit evidence: Billing › Buy data shows "Monnify authentication failed" and loads 0 plans.
Requested action: Fix the Monnify credentials on Buy data so plans load and checkout works.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F24 · Open feedback · Feedback · 2026-10-07
Audit status: Partial. Tested: Family, Wallet and Reviews load for every parent (raised during this review).
Audit evidence: On the Lucy account, all three pages fail ("We couldn't load your family"). The family API returns 404 and Home shows "No families yet". The family link seems to be lost when a profile is changed. A working family account (Family Study) loads Family, Wallet and Reviews normally, so the bug is limited to affected accounts.
Requested action: Fix the family link lost after a profile change (family API 404 on the Lucy account); relink existing affected accounts.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F25 · Open feedback · Feedback · Done
Audit status: Met. Tested: Content owner sees performance per course (14 Aug).
Audit evidence: Content › Performance lists each course with subscriptions, referral revenue, pending earnings and attributed revenue. The figures disagree, though: Yorùbá shows "0 active" but "22 subscribers".
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F26 · Open feedback · Feedback · 2026-10-07
Audit status: Partial. Tested: Website copy corrections (Document feedback).
Audit evidence: Most are done: Level 0 wording, "Out of data? Buy data on Mahadum360", school quote text, Speaking review text, "Mahadum360, RC 9601595" in the footer. Remaining: sign-in and register pages still show the old "© MAHADUM.360 · 2026" line without the RC number.
Requested action: Add 'Mahadum360, RC 9601595' to the sign-in and register page footers.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F27 · Open feedback · Feedback · Done
Audit status: Met. Tested: Annual plans "Get 2 months free" (2 Aug).
Audit evidence: Shown on both annual plans in Billing.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F28 · Open feedback · Feedback · 2026-10-07
Audit status: Partial. Tested: Landing page advert banners with management suite (BRD).
Audit evidence: Admin › Adverts manages banners, and one shows on the landing page. Inactive demo adverts still exist in the list.
Requested action: Delete the inactive demo adverts from Admin › Adverts.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F29 · Open feedback · Feedback · 2026-10-07
Audit status: Not met. Tested: Teacher can create assignments (2 Aug, 14 Aug).
Audit evidence: No one can create assignments. The class Assignments tab has no create button and says "The assigned teacher has not created an assignment", but no teacher can be assigned (row 33), and both Educator/School accounts tested got only the school_admin role.
Requested action: Add a 'Create assignment' button for class teachers (and school admins); depends on #33.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F30 · Open feedback · Feedback · 2026-10-07
Audit status: Partial. Tested: Roster template: Firstname, Lastname, Level, plus email (14 Aug; raised during this review).
Audit evidence: The template is now Firstname, Lastname, Level, as asked on 14 Aug. It has no email column, although email is the account identifier everywhere else, so imported students can't be matched to logins or parents.
Requested action: Add an Email column to the roster template (Firstname, Lastname, Email, Level) and match imported students to accounts by email.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F31 · Open feedback · Feedback · Done
Audit status: Met. Tested: School invoice split; balance before payout (2 Aug).
Audit evidence: Invoices show Student School Fees, Registration Fees, VAT 7.5% and Total, with Download PDF. School referrals shows the available balance before payout. Check: invoices #000011 and #000012 are identical (₦129,000 each, issued 30 Sep), so the dashboard reports ₦258,000 unpaid.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F32 · Open feedback · Feedback · 2026-10-07
Audit status: Not met. Tested: Shareable school sign-up link (raised during this review).
Audit evidence: The school only has a referral code, which earns commission. Learners can be invited one at a time by email from inside a class (confirmed by Ifeoma). There is no school join link or code that a school can send to students and parents.
Requested action: Add a shareable school join link/code that students and parents use to sign up into the school.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F33 · Open feedback · Feedback · 2026-10-07
Audit status: Not met. Tested: Create or invite teachers, and assign one after the class is made (raised during this review).
Audit evidence: There is no Teachers page and no way to invite a teacher. Teacher is optional in "New class", and once skipped, the class shows "Teacher: Not assigned" with no control to assign one.
Requested action: Add a Teachers page with invite by email, and an 'Assign teacher' control on each class.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### F34 · Open feedback · Feedback · 2026-10-07
Audit status: Not met. Tested: Sign-up account types match admin profiles (raised during this review).
Audit evidence: Sign-up offers Individual, Family, Educator/School and Institution. The admin users filter and overview only have Single, Family and School: "Individual" is called "Single", and Institution sign-ups have no user type. Educator/School does not separate a teacher from a school admin: both accounts tested (Ifeoma, ngozi) received school_admin, so no teacher profile exists in practice. The supervisor role has no sign-up path.
Requested action: Split Educator/School sign-up into Teacher and School; rename 'Single' to 'Individual' in admin; add Institution as a user type.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W1 · Website copy (1 Oct) · Families · 2026-10-07
Audit status: Partial. Tested: "Mahadum360" with no dots.
Audit evidence: Page text is fixed. The browser tab title still reads "MAHADUM.360 · MAHADUM.360", and the sign-in and register pages show "© MAHADUM.360 · 2026".
Requested action: Change the site title tag and the sign-in/register footer to "Mahadum360".
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W2 · Website copy (1 Oct) · Families · Done
Audit status: Met. Tested: "the languages that make your home yours" (no comma).
Audit evidence: Shows "home yours".
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W3 · Website copy (1 Oct) · Families · Done
Audit status: Met. Tested: "Ndewo nwa m" (no comma, correct word).
Audit evidence: Shows "Ndewo nwa m!".
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W4 · Website copy (1 Oct) · Families · Done
Audit status: Met. Tested: Children: free lessons in beginner Level 0.
Audit evidence: "Playful, age-respectful free lessons in beginner Level 0 let you try the platform before committing."
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W5 · Website copy (1 Oct) · Families · 2026-10-07
Audit status: Not met. Tested: Parents: adjust starting level.
Audit evidence: The Parents row still reads "One grown-up view for progress, recordings, consent, chores and rewards…" with no mention of adjusting the starting level.
Requested action: Add "adjust the starting level" to the Parents row, and build the parent control if it doesn't exist yet.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W6 · Website copy (1 Oct) · Families · Done
Audit status: Met. Tested: Speaking review: "…or choose a trusted adult to practise with the child".
Audit evidence: "Hear submitted practice or choose a trusted adult to practise with the child."
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W7 · Website copy (1 Oct) · Families · Done
Audit status: Met. Tested: Footer: "Mahadum360, RC 9601595.".
Audit evidence: "© Mahadum360, RC 9601595. · 2026 · Lagos, Nigeria"
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W8 · Website copy (1 Oct) · Schools · Done
Audit status: Met. Tested: "Complete learning stays free" → "Beginner Level 0 stays free".
Audit evidence: Shows "Beginner Level 0 stays free".
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W9 · Website copy (1 Oct) · Schools · Done
Audit status: Met. Tested: "Complete course on Free" → "Beginner Level 0 stays free".
Audit evidence: Both places now read "Beginner Level 0 stays free".
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W10 · Website copy (1 Oct) · Schools · Done
Audit status: Met. Tested: "Low-bandwidth activities" → "Out of data? Buy on Mahadum360".
Audit evidence: "Out of data? Buy data on Mahadum360."
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W11 · Website copy (1 Oct) · Schools · 2026-10-07
Audit status: Partial. Tested: "language and culture" in lower case.
Audit evidence: Lower case in the clubs section. The school quote list still says "Language & Culture club and competition entry".
Requested action: Change it to "language and culture club and competition entry".
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W12 · Website copy (1 Oct) · Schools · Done
Audit status: Met. Tested: Reliable connection: "Low data won't keep you from learning…".
Audit evidence: Matches the correction.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W13 · Website copy (1 Oct) · Schools · Done
Audit status: Met. Tested: School quote: "See annual subscription fees and the cost per roll.".
Audit evidence: Matches the correction.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W14 · Website copy (1 Oct) · Institutions · Done
Audit status: Met. Tested: All emails go to Partnerships@Mahadum360.com.
Audit evidence: The only email link is Partnerships@Mahadum360.com.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W15 · Website copy (1 Oct) · Institutions · 2026-10-07
Audit status: Not met. Tested: "Discuss with a campus".
Audit evidence: Still shows "Discuss with a campus".
Requested action: Replace with the agreed wording (e.g. "Talk to our partnerships team"); confirm the text with Ifeoma.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W16 · Website copy (1 Oct) · Institutions · Done
Audit status: Met. Tested: "Prepare email for family" with a lower-case f.
Audit evidence: No capitalised "Family" left in the email text.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W17 · Website copy (1 Oct) · About · Done
Audit status: Met. Tested: "Mahadum360" with no dot (both places).
Audit evidence: "Mahadum360 is designed for the different moments…"
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W18 · Website copy (1 Oct) · About · Done
Audit status: Met. Tested: "Age respectful".
Audit evidence: "Age respectful design · clubs · challenges · performance"
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W19 · Website copy (1 Oct) · About · Done
Audit status: Met. Tested: "Every learning lesson remains available on Free" → Level 0 wording.
Audit evidence: "Level 0 remains free, so every child can learn basic conversations before choosing a paid plan for deeper learning."
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W20 · Website copy (1 Oct) · About · Done
Audit status: Met. Tested: English course name: "Naija Voices".
Audit evidence: Shows "English · Naija Voices".
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W21 · Website copy (1 Oct) · About · Done
Audit status: Met. Tested: Closing line: "Start with five joyful minutes. Every child learns basic conversations…".
Audit evidence: Matches the correction.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W22 · Website copy (1 Oct) · Pricing · Done
Audit status: Met. Tested: Intro: "Start your learning journey… at no cost with Level 0…".
Audit evidence: Matches, with a small wording difference: "…unlimited hearts, with family tools on Family plans".
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W23 · Website copy (1 Oct) · Pricing · Done
Audit status: Met. Tested: Free: "All L0 lessons and quiz free".
Audit evidence: Shows on the Free card.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W24 · Website copy (1 Oct) · Pricing · Done
Audit status: Met. Tested: Free: remove the line "Level 0 in every language".
Audit evidence: Removed from the Free card.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### W25 · Website copy (1 Oct) · Pricing · Done
Audit status: Met. Tested: Paid plans: "Paid lessons start from Level 1 only".
Audit evidence: Shows on all four paid plans.
Requested action: No action needed.
- [ ] C — code/change or documented no-change finding
- [ ] T — automated/visual verification; evidence: not run
- [ ] L — Adebare local confirmation; pending
- [ ] D — production deployment; not deployed
- [ ] Q — Deborah live QA; not requested

### B35 · Missing source requirement

- [ ] Recover/clarify the subscription/invoice requirement; revised workbook omission is not completion.
- [ ] Reconcile F31 invoices #000011 and #000012 before any data removal.

## Release and rollback

Verified local repository C:/xampp/htdocs/mahamu360, branch codex/beta-feedback-20260903, origin https://github.com/adebareshowemimo/mahadum.git, F24a base e82c1bf; F33a reviewed base e183525 (verified F24a origin push). Independent untracked tmp/ preserved. No .agents or nested AGENTS.md found; root AGENTS.md read.

Read-only production inspection on 6 October confirmed the Azure subscription is enabled and VM `mahadum` in resource group `MAHADUM` is running in Canada Central at `20.151.177.171`, admin user `adebareshowemimo`. Repository `.azure/deployment-plan.md` and Apache configuration identify `https://mahadum360.com` and `/var/www/mahadum/public` (application `/var/www/mahadum`). Public `/up` returned HTTP 200. The existing SSH identity was rejected (`Permission denied (publickey,password)`), with strict host-key checking retained. Therefore current live code SHA, branch/worktree state, service status and available rollback backups remain unverified. No keys, credentials, network rules or server state were changed.

After explicit local confirmation and authorized host access, inspect the live checkout/release and backup inventory, preserve prior code and SPA assets, back up the database, then release only the confirmed candidate. Do not blindly deploy the script's default main branch or revert the independent data-store commits. Repository deploy/deploy.sh can restore prior code/SPA after failure but retains completed migrations; this candidate has no migration. Restoring prior code does not remove households created through Admin actions; any data reversal requires a separate ownership/usage review.

Provider credentials, live purchases/payouts, recipient messages, intentional data deletion and new business thresholds are not implicit test permissions. Tests stay marked blocked/not run when prerequisites are missing.

## First candidate status — F24a

- [x] Origin push: F24a `e183525efad014a0e75285781ac03d078ba61190` verified on codex/beta-feedback-20260903; CI not triggered on this branch push.
- [x] Local implementation: Admin-created/newly granted Parent provisions one family.
- [x] Relevant tests: 35 backend tests, 139 assertions; Pint/PHPStan; 264 frontend tests and build passed.
- [ ] Adebare's local confirmation.
- [ ] Production deployment.
- [ ] Deborah live QA.

See Mahadum360_F24_Local_Verification.md for reproducible local URLs and test steps. The overall F24 checkbox remains open: live Lucy ownership/relinking has not been examined. Full backend suite passed: 413 passed and one skipped out of 414; 2,225 assertions. The skipped SendGrid ECDSA test requires unavailable OpenSSL EC key generation. All 264 frontend tests and the frontend build, Pint, PHPStan and whitespace checks passed.


## Second candidate status - F33a: Assign teacher validation

Source: F33 assignment control subset; dependent chain B13, B30 and F29. Priority P1; intended audit date 7 October 2026. Teacher onboarding/invite, signup roles and school-admin assignment authoring remain open; no full source row is closed by this subset.

- [x] C - Local implementation: dedicated UpdateSchoolClassRequest permits omitted name on PUT/PATCH, while explicit names remain required/valid and StoreSchoolClassRequest stays unchanged.
- [x] T - 27 relevant backend tests / 125 assertions passed, including the exact dashboard payload, class/roster preservation, assigned teacher class visibility and assignment creation, inactive/foreign/non-teacher rejection, authorization and tenant isolation. Full Pint/PHPStan passed. Full backend suite passed: 422 passed / one skipped out of 423, 2,270 assertions. Frontend unchanged; previous F24a frontend checks are historical evidence, not rerun for this candidate. Local /up and SPA /school both returned HTTP 200; authenticated browser assignment remains pending.
- [ ] L - Adebare confirms F33a locally.
- [x] Origin authorization - Adebare instructed "commit and push to origin" for this distinct F33a candidate on e183525; exact commit/remote result is recorded in task handoff. Local acceptance, live deployment and QA remain separate pending states.
- [ ] D - User deploys the pushed candidate and confirms the live release.
- [ ] Q - Parent emails Deborah only after live deployment confirmation; QA not requested.

Authenticated reproduction before the fix returned 422, "The name field is required", for the dashboard teacher-only PUT and partial level PATCH. Authorization controls passed. The new cross-school test initially expected 404, but the existing route correctly denies with 403; the expectation was corrected without changing production authorization.

Implementation files: app/Http/Controllers/School/SchoolClassController.php; new app/Http/Requests/School/UpdateSchoolClassRequest.php; new tests/Feature/ClassTeacherAssignmentTest.php. No frontend, policy, membership rule, schema, provider, credential or live-data change. Automated tests use isolated SQLite and disabled outbound gateways. Actual local demo accounts/classes were inventoried read-only; browser assignment has not been performed by the agent.

See Mahadum360_F33a_Local_Verification.md for specific local steps. Existing teacher onboarding, school-admin authoring permissions and the held hearts/business-rule changes remain separate.
