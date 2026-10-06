# F33a local confirmation: Assign teacher validation

Ready local implementation on 6 October 2026, based on verified origin commit e183525efad014a0e75285781ac03d078ba61190 on codex/beta-feedback-20260903. This is a distinct follow-up to F24a. Adebare authorized its commit and origin push with "commit and push to origin"; manual local acceptance and live deployment are still pending. The exact commit SHA and remote verification result are reported in the task handoff. The user owns server deployment. Parent sends Deborah QA only after confirmed live deployment.

Source F33: assignment control subset, priority P1, intended date 7 October. Dependencies B13/B30/F29 remain open beyond this unblocker; no teacher invitation/onboarding or school-admin assignment creation access is added.

- [x] C - Separate update validation accepts teacher-only PUT/PATCH and preserves omitted name/level/teacher fields; creation still requires name and explicit invalid names are rejected.
- [x] T - 27 relevant backend tests passed (125 assertions). Full Pint and PHPStan passed. Full backend suite passed: 422 passed and one skipped out of 423; 2,270 assertions. The existing environment-dependent SendGrid/OpenSSL EC verification limitation remains recorded in the F24a baseline. Whitespace checks passed. Local /up and SPA /school returned HTTP 200. Browser assignment remains pending.
- [ ] L - Adebare's local acceptance.
- [x] Origin authorization - Adebare authorized this commit/push; verify remote delivery against the task handoff.
- [ ] D - User deployment and live release confirmation.
- [ ] Q - Deborah QA through parent after live deployment confirmation.

## Reproduction and scope

The existing school dashboard AssignTeacher control sends PUT /api/v1/classes/{id} with only teacher_user_id. The backend shared the create request and required name. Isolated authenticated endpoint regressions reproduced HTTP 422, "The name field is required". The new UpdateSchoolClassRequest reuses the existing field/membership rules and makes only name conditional on its presence. Class creation rules, policies, teacher reassignment restrictions and active same-school membership validation remain intact. Nine new tests cover the exact payload through assigned teacher class visibility and assignment creation; preservation of name/level/organization/roster; partial PATCH; missing create name; explicit null/blank/long names; inactive/foreign/non-teacher/non-member teacher selection; teacher restrictions; cross-school denial; and unauthenticated/parent denial.

No frontend changes; prior F24a frontend tests/build passed, but were not rerun for this backend-only candidate. Actual local school data was inspected read-only; the agent did not assign a teacher, create a class or mutate a role in the demo database. Browser confirmation is pending, not claimed passed.

## Local test

API port 8000 and SPA port 5173 were verified listening. Use your existing local demo credentials; no password or credential changes are needed.

1. Sign in locally as Shawna Goodwin / admin1@dev.mahadum360. Open http://127.0.0.1:5173/school and select Rath-Cronin Academy (organization 1), if prompted.
2. Choose New class. Create an empty local test class named F33a Local QA with a distinctive level such as Local QA. Leave optional Teacher blank. Do not invite learners or send email. This manual test creates only a local class; alternatively use your own disposable local test class.
3. On its dashboard card, select Granville Mann / teacher1@dev.mahadum360 from Assign teacher and click Assign. Expect the teacher name to update. Refresh; expect the class name and level to remain F33a Local QA / Local QA. No 422/name-required error should occur.
4. Sign in as that existing teacher. Open http://127.0.0.1:5173/classes; expect the test class to appear. Open its Assignments tab (or http://127.0.0.1:5173/assignments) and verify the existing teacher New assignment flow is available for that class. Keep reward at zero and the class empty if you choose to create a local test assignment; do not send messages, fund wallets or submit purchases.
5. Report F33a pass/fail and any error text. Local acceptance, push and production deployment are separate checklist states. F24a local/live/QA acceptance still remains pending even though its origin push is verified.

## Code and rollback

Files: app/Http/Controllers/School/SchoolClassController.php; new app/Http/Requests/School/UpdateSchoolClassRequest.php; new tests/Feature/ClassTeacherAssignmentTest.php. StoreSchoolClassRequest, policies, routes and frontend are unchanged. No migration or configuration change. Reverting just this candidate's controller/request restores prior behavior; no assignment or class records are deleted by a code rollback. Preserve F24a and independent data-store work. The agent does not deploy, seek host credentials or email Deborah.


## Candidate identity

SHA-256 of tested files:

- SchoolClassController.php: B18221D5FA99B00A2F9977340E7EEA20F83F968EDEA9A7BF73BBC77F9471E6AB
- UpdateSchoolClassRequest.php: EAC524D832BB4D69FE41B20901694E792413A4D70C17F2154CA7B1090D6DE2C9
- ClassTeacherAssignmentTest.php: EDADF771AD6ECAED72273C8B6126D7C63AA6F84979512953D4AF02F9047C1E68

F24a remote remains e183525efad014a0e75285781ac03d078ba61190; F33a commit/push is authorized; the final task handoff records the exact delivery result. No production deployment or email is performed by this task. Independent tmp/ and the hearts patch outside the repository are preserved.
