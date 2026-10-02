# Mahadum360 October 1 feedback — implementation and verification

## October 2 follow-up

The user authorized applying the four explicit corrections in Deborah's “Mahadum360 – Pending Items Update” attachment (`Mahadum360 feedback october 2.docx`): S03 now says “Out of data? Buy data on Mahadum360.”; S05 retains “Reliable connection” and uses “Low data won’t keep you from learning, buy data directly from the website and continue learning.”; I02 now says “Discuss with a campus”; A05's title is now “Naija Voices”, with the English language label and description retained. The existing rendered-copy regression checks were updated for these corrections.

These are local copy changes. Data fulfillment was not changed or verified by this follow-up. F05 (“Parents - adjust starting level”) remains pending clarification of whether it means Families-page wording or a parent control. The October 1 report below is a historical snapshot and its withheld-copy statuses are superseded for these four items. No deployment has been performed.

Validation: all 14 tests in `OctoberFeedback.test.tsx` passed; `npm run build` (TypeScript and production build) and `git diff --check` passed.

**21 checklist corrections are implemented and tested locally. Three items need clarification; two data-purchase items are blocked or partial. Nothing has been deployed. The CX PowerPoint remains on hold.**

Source: the eight-page `Oct 1 Document feedback.docx`, received with “independent feedback” on October 1, 2026. All document text was read and checked against the supplied checklist. Comparison with the original embedded screenshot pixels remains incomplete because the original document was not available locally and its page-image references could not be displayed. No image-dependent ambiguity was guessed.

## Local versus live status

Changes are applied to the existing Mahadum360 development branch, `codex/beta-feedback-20260903`, in repository `adebareshowemimo/mahadum`. They remain uncommitted. Unrelated existing work was preserved and the applied source was checked against the tested source.

No push, merge, deployment, production configuration change, purchase or test email was performed. No terms or privacy clauses were edited. No CX recommendation was implemented.

## Verification evidence

- Full frontend suite passed: **255 tests across 46 files**.
- After the final copy-consistency edits, all **51 affected frontend tests across five files** passed.
- Backend learning-access/pricing subset passed: **16 tests, 91 assertions**.
- Final TypeScript check, production frontend build and whitespace check passed.
- Final local browser checks covered Families, Schools, Institutions, Contact, About and Pricing at **1440×1000 desktop** and **390×844 mobile**.
- All 12 page/viewport cases had no horizontal page overflow, no broken images and no browser page errors. Screenshots of the changed sections were inspected for wrapping and clipping, including the conversation greeting, footer identity, role panel, longer paragraphs, contact button and all four paid-plan card variants.
- Browser checks used isolated local test contexts with external requests blocked. Pricing data was illustrative test data, not real prices. No forms were submitted and no purchases were attempted.
- Physical Android/device testing, original-screenshot comparison and production verification remain outstanding. The full backend suite, Pint and PHPStan were not rerun; this patch changes no PHP source.

## Changed files

| File | Purpose |
|---|---|
| `web/src/pages/PublicAudiencePages.tsx` | Family copy and institutional contact address |
| `web/src/pages/LandingExtendedVariantsPage.tsx` | School copy and Level 0 consistency |
| `web/src/pages/LandingVariantsPage.tsx` | Shared footer registration identity and school-quote headings |
| `web/src/pages/AboutPage.tsx` | Reviewed brand mentions, teenager phrase and free-access copy |
| `web/src/pages/PricingPage.tsx` | Pricing hero, Level 1 boundary and removal of unlimited-free implications |
| `web/src/lib/billing/planFeatures.ts` | Shared Free and paid-plan card wording |
| `web/src/pages/PublicTrustPages.tsx` | Contact recipients, button capitalization and safely encoded email drafts |
| `web/src/lib/betaFeedback.test.ts` | Shared-plan regression expectation |
| `web/src/pages/OctoberFeedback.test.tsx` | Item-specific rendered-copy, contact-routing and pricing regressions |

## Issue-by-issue results

“Tested” means the local source/rendered content passed automated checks and the relevant page sections were inspected at desktop/mobile widths. It does not mean deployed or compared visually against the original DOCX images.

| Issue | Status | Result and remaining limitation |
|---|---|---|
| **F01** | Tested | Family hero uses **Mahadum360**, without the dot. Global branding and artwork were not changed. |
| **F02** | Tested | Hero says **“your home yours”**, without the comma. |
| **F03** | Tested | Conversation card says **“Ndewo nwa m!”** The original exclamation was retained; words and comma were corrected. This illustrative card has no audio binding. |
| **F04** | Tested | Children row now reads **“Playful, age-respectful free lessons in beginner Level 0 let you try the platform before committing.”** No unlimited-free promise or entitlement change. |
| **F05** | Needs clarification | Parents row and controls were preserved. The note “Parents - adjust starting level” does not identify a replacement sentence or a control. See decisions below. |
| **F06** | Tested | Speaking review says **“Hear submitted practice or choose a trusted adult to practise with the child.”** No new adult account permissions were granted. |
| **F07** | Tested | Footer text includes **“Mahadum360, RC 9601595.”** Copyright, year and location remain. RC was supplied by the sender, not independently authenticated. Logo artwork is unchanged. |
| **S01** | Tested | Hero bullet says **“Beginner Level 0 stays free.”** |
| **S02** | Tested | Learner bullet says **“Beginner Level 0 stays free.”** The contradictory heading now reads **“A playful path from free Level 0 to deeper learning.”** Role switching was checked. |
| **S03** | Blocked; copy withheld | **“Out of data? Buy on Mahadum360” was not inserted.** The existing learner bullet remains pending verified data-bundle fulfillment. |
| **S04** | Tested | Requested sentence uses lowercase **“language and culture.”** |
| **S05** | Partial; copy withheld | Heading is **“Reliable connection.”** The requested data-purchase supporting promise was not inserted. Existing supporting text remains pending fulfillment and copy review. |
| **S06** | Tested | Quote titles are **“A clear, simple school quote.”** and **“See annual subscription fees and the cost per roll.”** Calculator arithmetic was unchanged. |
| **I01** | Routing tested | Reviewed family, support, school, partnership, university, culture, government and telecom public contact branches use **Partnerships@Mahadum360.com**. Visible links, recipients and encoded draft content agree. No email was sent. |
| **I02** | Needs clarification | University CTA remains **“Discuss a campus.”** Its recipient is corrected. Confirm the intended label below. |
| **C01** | Tested | Contact button says **“Prepare email for the family & learning team.”** Accessible name and mobile wrapping were checked. |
| **A01** | Tested | Timeline says **“Mahadum360 opened its online school.”** |
| **A02** | Tested | Learning-circle paragraph begins **“Mahadum360 is designed…”** |
| **A03** | Tested | Teenager feature line uses **“Age respectful design.”** Remaining feature text is preserved. |
| **A04** | Tested | Access card now reads **“Level 0 remains free, so every child can learn basic conversations before choosing a paid plan for deeper learning.”** This preserves the supplied commercial meaning with clear grammar. |
| **A05** | Needs clarification | English label and **“Everyday English”** title remain. Confirm whether only the title should become **“Naija voices.”** |
| **A06** | Tested | Closing reads **“Start with five joyful minutes. Every child learns basic conversations. Let the language become part of everyday life again.”** |
| **P01** | Tested | Hero describes free Level 0, paid deeper learning and unlimited hearts; family tools are explicitly limited to Family plans. Related contradictions were corrected as described below. |
| **P02** | Tested | Free card says **“All L0 lessons and quiz free.”** Supplied notation is retained. |
| **P03** | Tested | Duplicate **“Level 0 in every language”** bullet removed. Speaking practice, XP/streaks/badges and ad-support bullets remain. |
| **P04** | Tested | **“Paid lessons start from Level 1 only”** appears on Individual/Family cards for both monthly and annual selections, including shared billing wording. Billing logic and amounts were unchanged. |

P01’s final paragraph:

> Start your learning journey and build basic language skills at no cost with Level 0. Only commit to a paid plan when you are satisfied. Unlock the paid curriculum for deeper learning and unlimited hearts, with family tools on Family plans.

Additional consistency corrections on the reviewed pages:

- Pricing overlay now says **“Upgrade to unlock Level 1 and beyond.”**
- Pricing comparison introduction now says **“Every household can begin with free Level 0. Paid plans unlock deeper learning.”**
- Pricing closing now begins **“No card needed to start with Level 0.”** The “No locked course” promise was removed.
- The school learner heading no longer says the next lesson never locks.

## Data-bundle fulfillment: why S03 and S05 remain open

Already implemented: a bundle catalogue, Buy data modal, network/bundle selection, consent checkbox, authenticated endpoint and creation of a **pending** purchase record.

Missing from the inspected flow: a carrier request provisioning the selected bundle, a bundle-specific delivery/completion handler, and evidence that purchasing actually credits data. Existing telco gateway/webhook code handles subscription charging and OTPs, not bundle delivery.

Accordingly, **both new data-purchase marketing promises were withheld**. Only S05’s heading was changed. No provider, credential, payment flow or production configuration was altered. Closing these items requires confirmed working fulfillment or an approved integration specification if it is absent.

## Decisions needed

**F05 — Parents:** Current row is “One grown-up view for progress, recordings, consent, chores and rewards—without giving children payment controls.” Its button is **“See parent controls”**, linking to child safety. Does the feedback mean replace this sentence, add a parent starting-level control, or fix an existing control? Supply replacement copy or the control’s route/reproduction and intended authority. No control was changed.

**I02 — Universities:** Current CTA is **“Discuss a campus”**, opening the university contact topic with the corrected partnerships recipient. Should the label become **“Discuss with a campus,”** or was the note only identifying the wrong-email issue?

**A05 — English:** Current language is **English**, title **Everyday English**, description “Build confident speaking, listening and connection through everyday English.” Confirm whether to keep the language label and replace only the title with **“Naija voices.”** No curriculum/audio rename was made.

## Policy, legal and scope limits

Free Level 0 remains the public starting point; paid plans unlock deeper learning. Existing manager permissions allow additional levels to be designated free. This behavior was preserved without promising extra free levels to everyone. No prices, backend access policy or manager permissions were changed. Card/bank and airtime entitlement distinctions remain.

Contact consolidation was limited to the reviewed public enquiry experience. Child-safety, accessibility, privacy, referral, transactional and backend addresses were not indiscriminately replaced. Mailbox ownership and actual email delivery were not tested.

Global brand configuration, artwork and technical identifiers were preserved. Older landing variants outside the reviewed routes may still contain broad free-learning claims or dotted narrative branding; they were not changed in this follow-up.

The lawyer concern remains **blocked**: the supplied feedback contains no legal redlines, clause references, lawyer report or approved replacement terms. Obtain counsel’s actual review and approved wording. No legal-compliance conclusion is made, and no legal text was published.

The CX PowerPoint remains **on hold**. Its “Every lesson is free” suggestion was not adopted. No onboarding changes, tutorials, surveys, guest lessons, widgets or other CX recommendations were implemented.

## Remaining work

1. Resolve F05, I02 and A05.
2. Confirm or specify data-bundle fulfillment for S03/S05.
3. Obtain counsel’s terms review and approved wording.
4. Complete original-screenshot comparison and physical-device/production verification when authorized.

**These are local development results. Production remains unchanged.**
