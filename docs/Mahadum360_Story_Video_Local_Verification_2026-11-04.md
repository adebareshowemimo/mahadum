# Stories and video: local verification (4 November 2026)

Scope: X8, X9, X10, X12 and B39. This records local code and test evidence. It does not mark editorial publication, live payment methods, or production delivery as accepted.

| Item | Local result | Remaining acceptance |
| --- | --- | --- |
| X8 | Story age filters and thumbnail shelves cover 0–5, 6–12, 13–17, 18+ and Folklore. Empty bands now show a prepared-content message. Local sample stories exist in all five bands. | Editorially approved first stories must be uploaded and published in each band. |
| X9 | One-time ₦500 purchases and the preview-end Unlock card are present. Page previews default to 12 for colouring/tracing; text defaults to chapter 1. Preview video uploads now require an inspectable clip no longer than 60 seconds. Payment settlement tests cover the supported gateways and prevent unconfirmed access. | Confirm card, bank transfer and USSD on the configured production hosted checkout with real small payments. |
| X10 | Adult stories are absent from under-18 and school learner catalogues and direct URLs. A Family account that also has a teacher role retains its own eligible adult family profile access. | Confirm with production accounts after deployment. |
| X12 | Learner page has age chips, price banner, featured story and band shelves; it now has per-learner Continue reading. Proverbs & Trivia has a daily proverb, proverb sets and trivia sets. Daily choice rotates through authored flashcards. | Visual acceptance with editorial content on all shelves. |
| B39 | WebVTT captions can be uploaded in the lesson editor and are returned by the learner player. An uploaded, text-free local test video produced 240p, 360p and 720p MP4 renditions using FFmpeg. Lesson and course publish actions both enforce ready video plus a valid quiz. Speaking remains deferred pending minors' recording approval. | Install/configure FFmpeg on the deployment host and confirm video processing capacity. Adaptive HLS/CDN and automatic background transcoding remain a separate production pipeline decision. |

Local checks: `php artisan test --filter='VideoMediaTest|StoryLibraryTest|ContentPublishTest'` passed 30 tests and 251 assertions. The full backend suite ran 716 tests: 715 passed, one skipped, 5,119 assertions. `vendor/bin/pint --test`, PHPStan level 5, the web build and all 567 web tests passed.
