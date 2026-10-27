# Stories, Proverbs & Trivia rules

These decisions resolve the open items in `Mahadum360_Stories_Trivia_Spec_2.docx` and describe the implemented experience.

## Stories

- Premium plans do not include stories. A complete story costs ₦500 (50,000 kobo) per item. A translation published as a separate story item requires its own purchase.
- A free preview contains the first 12 pages of a colouring or tracing story, the first chapter of a reading story, or a separately uploaded preview clip for an animated story. The remaining content is stored privately and requires a paid purchase.
- A parent purchases for a learner in their family. The same story item then unlocks for all learner profiles in that family. An adult learner can buy for their own profile. Purchases remain valid when a course subscription ends and restore automatically from the account's paid purchase record when the family signs in again.
- A completed full refund or chargeback revokes the purchased entitlement and returns the item to preview access. A partial refund is flagged for review and does not automatically change access. Retried and duplicate gateway notifications must not create a second entitlement or referral commission.
- Story publication is independent of course publication; a published story remains available if its associated language course is unpublished. Courses with story records cannot be deleted until those records are resolved.
- Age bands are 0–5, 6–12, 13–17, and 18+. Age 18 belongs only to the adult band. Adult stories are unavailable through school profiles and to teachers, school administrators, and supervisors.

## Proverbs & Trivia

- Proverbs and Trivia are ordinary lessons, tagged by kind. Authors create them within an existing course level using the standard lesson editor. Parents and learners have a separate browse page that opens the standard lesson player.
- Their free or paid access follows the containing level and the learner's existing plan rules. There is no separate item charge for a Proverb or Trivia lesson.
- The author configures the quiz pass percentage, maximum attempts (or unlimited), hearts, question shuffle, seconds per question (or untimed), and XP per correct answer. Correct-answer percentage determines the pass result. The existing lesson system records answers, progress, retries, XP, and hearts. A timed-out question records an unanswered, incorrect response.

## Local sample content

Run `php artisan db:seed --class=StoriesTriviaSampleSeeder` in a local environment to add one published Proverb lesson, one timed Trivia lesson, and six published stories in a dedicated `[Sample] Yoruba Stories & Wisdom` course. The stories cover colouring, tracing, animation, teen reading, adult reading, and all-ages Folklore. Generated covers and book pages are included, plus short silent MP4 clips for the animated preview and full-video flow. Re-running the seeder does not duplicate records. No sample payment or entitlement is created; use the free preview and normal checkout to test purchases.
