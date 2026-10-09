# Managed rewarded video

Approved on 8 October 2026: managed video completion refills all five hearts. Coin refills and the existing consent-age, answered-question deduction and timed refill rules are unchanged.

## Configure after deployment

1. Deploy the API and SPA together and run `php artisan migrate --force`. The new migration adds nullable playback state and expiry to existing impressions; legacy impressions cannot earn managed rewards.
2. Upload the reviewed MP4/WebM video in Media. Keep its file on the configured public storage disk. External embeds and missing files are unavailable.
3. In System settings → Rewarded video, search and select the uploaded video by name. Load more results when needed. For uploads without duration metadata, enter the actual video length in seconds (1–300). The player checks this against media metadata when it loads.
4. Enable rewarded video. It defaults off. Only the rewarded-heart placement is supported; post-lesson network inventory remains unavailable.
5. Accept the flow using a free learner above the existing digital-consent age: reduce hearts, play the video fully, verify all five hearts and cleared lock, and confirm replay cannot grant another reward. Also test missing media, early close and real pause/resume on the supported browsers. No live content, setting or deployment is changed by the implementation alone.

## Verification and limits

The authenticated request binds the selected media, duration and fingerprint to an impression with a 30-minute expiry. The browser reports monotonically increasing playback positions approximately every two seconds. The server locks the impression, accepts only small contiguous advances (at most five seconds) within elapsed server time, and stores progress durably. Waiting without progress, seeking to the end or replaying earlier fragments cannot satisfy completion. Closing or network/media failure grants no reward. Expired, disabled, replaced or missing content and a changed consent-age eligibility fail verification again at redemption.

Completion sets `shown_at` only after verified coverage and minimum elapsed time. It can be retried after a lost response without changing the original shown date. Heart refill keeps the existing learner/impression locks and one-time `consumed_at` guard. The UI waits for the actual refill response before claiming success; if that response is lost, check the refreshed heart balance before starting a new session.

Eligibility is rechecked during progress, completion and redemption using the locked current learner. A former household cannot redeem after the learner moves. An upgrade to an ad-free plan or a new staff role stops the old reward session without consuming it or changing hearts. Staff suppression includes supervisors, including accounts that also have Parent access, and the banner audience uses the same role list. These changes add no migration and do not alter historical impressions or balances.

This is managed browser playback telemetry checked by the server, not independent ad-network attestation or proof that a person watched the pixels. A modified client can simulate plausible playback reports over real time. Hidden-tab pause and playback-rate handling are browser controls. No third-party impression revenue or external provider verification is claimed. Real browser/media acceptance and simultaneous MySQL execution remain deployment checks.
