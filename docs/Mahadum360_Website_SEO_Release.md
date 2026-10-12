# Mahadum360 website SEO release

Prepared 11 October 2026 against the SEO brief and fix list issued 8 October. This is a local release candidate. Adebare will deploy it. A code change or passing local test is not live acceptance.

## What changes

Public marketing pages contain their actual rendered text before JavaScript runs. React hydrates that content instead of replacing it with a loading screen. The build produces eleven rendered pages and an explicit list of valid application routes. Laravel serves the correct page metadata, real HTTP 404s for unknown routes, and permanent canonical redirects on the production domain.

The sitemap includes public pages and published guides with modification dates. Robots rules exclude application routes while leaving Families, Schools and the language pages crawlable. Social previews use absolute URLs and a 1200 × 630 JPEG. The official supplied PNG logo is used without modifying its file.

New Hausa, Igbo and Yorùbá landing pages, the guide section and the vote tally render on the server. The language pages support approved phrase audio, level descriptions, FAQs, playlists and click-to-load YouTube videos. Course, FAQ and video structured data follows the content actually displayed; missing approved FAQs or videos are not invented.

Registration preserves the language selected by the landing-page link. New email and Google accounts can store one language vote, first-arrival campaign tags and a sign-up event. Email and phone normalization prevent duplicate identities. First-lesson events are idempotent. Admin reports show sign-ups and first lessons by source, and an aggregate language-vote CSV can be downloaded.

## Deployment

The release is on `codex/beta-feedback-20260903` in `adebareshowemimo/mahadum`. Once the release commit is fetched, the existing server procedure is:

```bash
cd /var/www/mahadum
git fetch origin
git checkout codex/beta-feedback-20260903
git pull --ff-only origin codex/beta-feedback-20260903
BRANCH=codex/beta-feedback-20260903 ./deploy/deploy.sh
```

If the server checkout has local changes, review them before pulling; do not discard them to make deployment proceed. These are instructions for the user who is handling deployment, not commands already run against the server.

Use the prepared source changes with the existing deployment process; no server deployment has been performed in this chat. Confirm the intended release revision rather than deploying an unrelated branch. Take a database backup and retain the current release and built public assets before starting.

The existing `deploy/deploy.sh` builds `web/`, publishes all build directories, runs migrations, refreshes caches and checks browser assets. Its existing rollback keeps completed database migrations. This SEO release uses that publication mechanism: `web/dist/prerender/` must reach `public/prerender/`, and `web/dist/index.html` must reach `resources/spa/index.html`.

If deploying manually, the required sequence is:

1. Install the release source and its dependencies.
2. Run `npm ci` and `npm run build` inside `web/`. The output must end with eleven rendered public pages.
3. Publish the complete `web/dist/` assets, images, scripts and `prerender/` directory using the established deploy process. Publish its `index.html` into `resources/spa/index.html`, not a public static homepage that bypasses Laravel.
4. Run `php artisan migrate --force`. The new migration creates the publishing, vote and event tables and adds language/source fields to users. Do not run development seeders on production.
5. Refresh configuration and route caches and restart workers using the existing deployment procedure.
6. Confirm files and directories served by Apache retain the existing deployment permissions. All public scripts and styles must return HTTP 200.
7. Run the live checks below before recording completion.

The optional `GOOGLE_SITE_VERIFICATION` and `BING_SITE_VERIFICATION` environment values are verification tokens, not credentials. Use the owner-provided values if needed. DNS domain verification and owner invitations must still be completed in the respective accounts. `SEO_SHELL_PATH` and `SEO_PRERENDER_PATH` are local-preview overrides; leave them unset on production.

If reverting the code, keep the new database columns and tables until their data has been reviewed. A migration rollback would delete recorded votes, attribution, events and published content. Restore the prior source and matching browser assets together through the existing release rollback process.

## Live acceptance

- Download `/families`, `/schools`, `/pricing`, `/about` and `/contact` without executing JavaScript. Each must include the page heading, body text, unique title/description and a self-referencing canonical URL.
- `/sitemap.xml` must return XML and list the public marketing, language and guide pages. Draft articles and account routes must be absent.
- `/robots.txt` must point to the sitemap. `/schools` and `/families` must remain allowed. App routes use noindex as well as crawler exclusions.
- `/this-page-does-not-exist` and an unpublished guide must return HTTP 404. Existing lesson, class and account routes must continue to work. Check `/register?lang=hausa` preselects Hausa.
- HTTP and www requests must redirect permanently to the matching HTTPS apex-domain path. Confirm that the hosting/TLS setup handles www. If TLS terminates at a proxy, configure Laravel's trusted proxies correctly before accepting these checks.
- Inspect home, Families and each language link using sharing debuggers and a controlled WhatsApp preview. The image URL is `https://mahadum360.com/images/og-cover.jpg`.
- Verify Google Search Console and Bing owner access, submit `https://mahadum360.com/sitemap.xml`, and record acceptance of the submitted sitemap. Indexed-page counts and rankings require subsequent observation.
- Use controlled registration accounts to verify selected language, one vote, duplicate email/phone rejection and campaign tags. Download the admin export and compare it with the public count. Complete a first lesson twice and confirm one conversion event.
- Publish an approved test guide through Website content, verify its raw HTML and sitemap entry, then return it to draft. Do not publish placeholder phrases, recordings or video IDs used by automated tests.
- Validate structured data for a language page populated with approved content. Course and FAQ markup does not guarantee Google rich-result eligibility. Run mobile PageSpeed Insights on home, Families and every language page after deployment, retaining the actual results. Field Core Web Vitals and target rankings have not been established locally.

## Content team guide

Sign in with an existing verified `content_owner` or `super_admin` account. Open Content → Website content, or `/content/website`.

For an existing page, choose its address, update its search title (maximum 60 characters), description (maximum 155), main heading or introduction, and save. Leaving a core page's heading or introduction blank retains the current copy. Published editorial changes are reflected in raw HTML and preserved when the browser becomes interactive. Other layout sections remain in the website source.

For a language page, add the approved phrases, meanings, pronunciation and HTTPS audio links using individual entries. Add level descriptions, approved FAQ answers, the playlist and each YouTube video's ID, title, description and original publication date. Save, open the public page and verify the recordings and video buttons. Use the correct Yorùbá tone marks, Hausa hooked letters and the approved Igbo spelling. Never add unapproved language teaching material.

For a guide, select New guide, set an address such as `/guides/hausa-greetings`, and complete the heading, introduction, article text, author and related language. Add phrase audio and videos as appropriate. Save as a draft while reviewing. Check Publish this guide only after content approval; saving a published guide updates the sitemap immediately. Unticking publication returns it to draft and removes it from the public list and sitemap.

Super admins can use Export language votes and the source report on the same screen. Exports contain aggregate counts, not account passwords or private learner details. Human CMS acceptance and the full handover still require owner sign-off.

## Per-item status

Local verification: the full frontend run passed 493 tests across 73 files (including 107 pre-existing local lazy-route import checks). The full backend run passed 618 tests with one pre-existing skipped test; a separate fresh run of the changed SEO/smoke tests passed all 9 checks, including the additional editorial-copy case. The production build, style check and static analysis passed. Changes to final pricing wording were also rechecked in 33 focused frontend tests. Browser previews verified the home and Families pages and the new Hausa page; the homepage was checked at phone width without horizontal overflow. This does not establish production PageSpeed or third-party rich-result acceptance.

| IDs | Local state | Required before live sign-off |
| --- | --- | --- |
| W1, W2, W4–W9 | Implemented; local checks cover rendered content, XML, robots, canonical redirects, 404s and metadata | Deploy and retest on production |
| W3 | Verification-token support prepared | Owner access, DNS verification, Google/Bing submission and account evidence |
| W10, W11 | Preview metadata/image and stable language-focused home headline prepared | Ifeoma's copy/artwork approval and real sharing-preview checks |
| W12–W14, W16, W18 | Landing-page framework, audio/video/FAQ support, schema and language preselection implemented | Approved 6–10 phrase/audio sets, 5 FAQ answers, 2–4 videos, levels and playlists; live retest |
| W15, W17, W20, W21 | Language navigation, organization/site schema, social and WhatsApp links implemented | Production page/link/schema checks |
| W19 | Publishing UI, server-rendered guides, author/date, audio/video template and automatic sitemap updates implemented | Content-owner account, approved first article and team acceptance |
| W22 | Single-vote storage, signup UI, public counts and admin export implemented | Ifeoma's final 15-language shortlist and confirmation of launch wording; live account checks |
| W23 | First-arrival attribution and idempotent signup/first-lesson records and admin report implemented | Analytics-account decision/integration and production conversion check |
| W24 | Rendered HTML/hydration avoids the empty shell; videos load on click; phone-width layout checked | Actual production PageSpeed and field LCP/CLS/INP evidence; further fixes if measured issues remain |
| W25 | Existing alt text retained, official logo has alt text | Continue alt text on approved new images |
| W26 | Plain-text `/llms.txt` implemented | Deploy and verify response type |
| H1–H4 | Current repository/deploy instructions identified; owner questions emailed | Actual code/hosting/domain/service owner access and transfers |
| H5 | Secure handover questions and variable guidance prepared | Owner's password manager, verified inventory and provider-key rotation |
| H6 | Existing deploy runbook plus this SEO release guide available | A second developer's successful deploy/rollback exercise |
| H7 | Hosting access belongs to the user's deployment workflow | Owner-controlled daily database/file backups and documented restore test |
| H8 | Publishing/editor guide provided | Ifeoma's team performs and accepts a test edit/publication |
| H9 | This per-item open-issues list provided | Review remaining BRD obligations separately and obtain owner acceptance |
| H10 | Support questions emailed | Written dates, scope, response time and rate agreed by the parties |

Outstanding questions were sent as a reply to “Mahadum360 SEO test results” to `mahadum360@gmail.com`, copying `deboraho@agunfoninteractivity.com` and `info@mahadum360.com`. No credential, ownership transfer, backup restore or support agreement has been completed by sending that email.
