# School survey fix plan — 4 November 2026

Local implementation and verification plan. Production changes require a later deployment and retest.

| ID | Finding and plan | Local outcome |
| --- | --- | --- |
| B34 | Seat purchase and tier discounts existed; no actionable inactive-seat review. | Done: configurable 30-day inactivity review, last activity, classes, and school-admin release that frees a seat and removes class membership. Inactive/active and cross-school tests pass. |
| M9 | Class assignments were written/manual work, separate from course quizzes. | Done: teachers can link a published quiz lesson from a course assigned to the class. Learners open the course player; lesson completion records the quiz result in the assignment automatically. Written work and optional teacher grading remain. Feature test passes. |
| M12 | Class analytics had no attempt drill-down or per-quiz aggregate. | Done: assigned course outline, per-quiz class average and most-missed questions, and learner attempt answers, correctness, score and date. Other-class attempt denied in feature test. |
| M4 | Invitations could be sent, but class page did not list/manage pending invitations. | Done: pending name, email, sent date, status, resend and cancel; old/revoked links stop working. Feature tests pass. |
| M13 | Invitation lookup only returned whether an email existed. | Done: existing account name appears in the invitation explanation. Feature test passes. |
| N12 | Dashboard API already returns total, in-class and unassigned counts; frontend shows the breakdown. | Verified by the existing `RosterImportTest::test_dashboard_distinguishes_school_profiles_from_distinct_class_members`. |
| N14 | Consumer billing promo used preview without payment but allowed a second Apply; school invoice promo already applies without checkout and rejects a second code server-side. | Done: consumer billing accepts one code only, gives a readable second-code error, and shows a code-labelled discount and new total. Apply calls preview only; school invoice behavior remains protected. |
| N16, N17 | Roster import preflights the whole file and checks L0–L5. | Verified: first/last name errors show `Row N:` in UI; invalid rows prevent any writes. Template now samples every level L0–L5; instructions and validation permit only those values. Existing roster tests cover both cases. |
| P7 | Teacher profile lacked experience/language/state/dialects. | Done: editable teacher details, school teacher directory, and parent view limited to teachers of the child's school classes. Feature test passes. |
| P8 | Teacher navigation was filtered, but teacher sign-in opened Home. | Done: accepted teacher accounts land on My classes from Home; Family accounts with an added teacher role keep their Family account type and Home. Redirect test passes. |
| Q3 | School admin saw global content builders. | Done: hidden from school-admin navigation and guarded routes; assigned class course outlines remain available. |
| F32 | Only one-off class email invitations existed. | Done: active school admin can create, replace and disable a 30-day link. Existing or newly registered individuals join with their own learner profile; parents choose a child. Seat capacity, school scope and account type are preserved. Feature tests pass. |

Verification: full PHPUnit passed with 738 passing and one skipped test (5,379 assertions). The initial run exposed an old fixture expecting analytics for an unassigned course; that fixture was corrected. PHPStan level 5, Pint, frontend typecheck/build and the full 574-test Vitest run passed. Focused frontend tests added afterward also passed for teacher routing, school-admin navigation, school-join registration choices and route loading. Local migrations are applied. No production deployment in this pass.
