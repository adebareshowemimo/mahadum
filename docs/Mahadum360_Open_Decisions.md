# Open `[BLOCK]` decisions — recommendations for sign-off

**Drafted 2026-07-18.** These are the 8 decisions still marked `[BLOCK]` in
`Mahadum360_Implementation_TODO.md`. Each carries a recommendation and the
reasoning behind it. **Nothing here is implemented.** Once you approve a row,
it moves into the Implementation TODO's "resolved" list and the dependent work
unblocks.

Two of these turn out to be **already decided in code and merely unrecorded** —
they need ratification, not deliberation. They're listed first because they're
free wins.

---

## Already decided in code — just ratify

### 1. Wordmark — `MAHADUM.360` (with the dot)

**Recommendation: ratify as-is. No code change.**

`web/src/lib/brand.ts:5` already declares `export const WORDMARK = 'MAHADUM.360'`
as the canonical value, with a comment stating it must stay consistent across
auth screens, landing, and meta. The codebase has a single source of truth and
already follows it. The doc simply never recorded the decision.

One caveat worth deciding *alongside* this: the repo directory is `mahamu360`
(note the **m**, not **d**) — a typo relative to the product name. That's cosmetic
and invisible to users, but if you ever publish the repo it will read as sloppy.
Renaming is cheap now and annoying later.

### 2. Max child profiles — 6

**Recommendation: ratify 6. No code change.**

The BRD conflict is real, but the implementation has already converged on 6 and
is internally consistent about it:

- `create_families_table.php:17` — `child_limit` defaults to **6**
- `DemoSeeder.php:45` and `DevSeeder.php:200` both seed **6**
- `FamilyController.php:53` enforces the limit against `$family->child_limit`

Because the limit is a per-family **column** rather than a constant, 6 is only
the default — you can already raise it for an individual household without a
deploy. That makes this a low-stakes decision, which is an argument for simply
confirming the existing behaviour rather than churning it. Choosing 5 would mean
a migration plus reseeding for no product gain I can see.

---

## Product decisions — these need you

### 3. Zero-hearts behaviour  *(blocks M4)*

**Recommendation: practice-to-refill as the primary path, rewarded ad as an
optional accelerator, and never a hard wait.**

This is the highest-stakes item on the list because it collides directly with
your locked Rule 4 (*never gate learning behind hearts or paywall*). A timed
wait is the standard Duolingo answer and it is the one option that plainly
violates that rule — it converts hearts from a pacing signal into a lock.

Concretely: at zero hearts the learner keeps full access to lessons, and hearts
regenerate by completing a short practice/review set. The rewarded ad
(`rewarded_heart`) stays as a shortcut for learners who'd rather watch than
practice — that plumbing is already built and server-verified, including the
COPPA filter. Premium's `unlimited_hearts` entitlement then reads as
"skip the friction", not "unlock the content", which is a much safer position
both for the product rule and for child-safety optics.

**Dependency:** this decision also determines whether the hearts UI needs a
"practice to refill" affordance built, so it gates real M4 frontend work.

### 4. Streak Shield — coins, not premium-only

**Recommendation: coins.**

`StreakService.php:51` already arms the shield and explicitly defers payment to
"the wallet slice" (`StreakController.php:44` says the same), so either choice
is a small change — the seam is neutral.

Coins is the better fit because the family economy is your differentiator and
the coin sink matters: coins are earned through parent-approved chores, so a
coin-priced shield turns a retention mechanic into a reason for kids to engage
with the chore loop and for parents to engage with approvals. Locking it behind
premium instead makes it a paywalled retention feature, which sits
uncomfortably close to Rule 4's spirit even though a streak isn't strictly
"learning".

### 5. Diaspora telco VAS — Nigeria-only for MVP

**Recommendation: yes, Nigeria-only.**

Telco VAS requires signed commercial contracts per operator (MTN, Airtel, Glo,
9mobile) — that's the single longest-lead item in the whole launch plan and it's
still unsigned. Extending to diaspora carriers multiplies that contract burden
against a segment that already has frictionless card payments via
Paystack/Flutterwave. Diaspora users are the *least* likely to need carrier
billing.

Ship cards for diaspora, telco for Nigeria, and revisit only if the data shows
diaspora card conversion underperforming.

### 6. Spaced repetition — SM-2

**Recommendation: SM-2, but note this is not MVP-blocking.**

First, a scoping correction: there is currently **no SRS implementation at all**
in the codebase — no `ease_factor`, `interval_days`, or `next_review` columns
anywhere. So this decision blocks a feature that hasn't been started, and it
should not hold up launch.

When you do build it, SM-2 is the right default. Leitner is simpler but its
fixed box intervals adapt poorly to per-item difficulty, which matters a lot for
tonal languages where a handful of items (tone-mark minimal pairs) are
dramatically harder than the rest. SM-2 is well-documented, has reference
implementations in every language, and its per-item ease factor handles exactly
that long tail. The extra complexity is two columns and roughly twenty lines of
scheduling code.

---

## Vendor selections — commercial, not technical

These three are the ones actually holding up shipped-but-stubbed features. All
have working swappable seams already, so the integration cost is bounded and the
decision is mostly about price and support.

### 7. Managed video vendor  *(blocks M2)*

**Recommendation: Cloudflare Stream.**

The deciding factor for this product is Nigerian and diaspora bandwidth.
Cloudflare's edge presence in Africa is the strongest of the three, and Stream's
pricing model (per minute stored + per minute delivered) is predictable in a way
that matters when you can't yet forecast viewing volume. Mux has better
analytics and a nicer API but is meaningfully more expensive at scale; Bunny is
the cheapest but has the thinnest transcoding feature set and weakest support.

This unblocks more than it looks: the M2 video pipeline (240/360/720 HLS,
posters, captions), the M3 low-bandwidth requirement (360p default, adaptive),
**and** the blocked video thumbnail/poster generation in Admin Portal §12 — one
decision closes three open items.

### 8. LRS vendor  *(blocks M3 analytics)*

**Recommendation: defer past MVP — or pick Learning Locker if you must choose now.**

Worth challenging the premise: xAPI statements are already being **stored
locally** and simply not pushed. Nothing about the learner experience depends on
an LRS, and your actual analytics needs at launch (the KPI dashboard: DAU/MAU,
retention, conversion, completion) are all answerable from your own database.
An LRS earns its keep when you need interoperability with external LMS
platforms — which is a B2B schools story for *after* you have schools.

Since statements are already accumulating, deferring costs you nothing: you can
backfill into whichever LRS you eventually pick. If a pilot school contractually
requires one, Learning Locker (self-hostable, open-source core, no per-statement
pricing) is the lowest-commitment choice.

### 9. Ad network  *(not currently tagged `[BLOCK]`, but gates real revenue)*

Listed here because it's the last stub in the monetisation path. `NullAdGateway`
always fills, so the entire ad flow — request, COPPA filter, server-verified
completion, hearts refill, and now the post-lesson interstitial — works
end-to-end today without showing a real ad.

**Recommendation: Google AdMob**, on the strength of its child-directed treatment
and COPPA/GDPR compliance tooling, which is the binding constraint given your
under-13 audience. Whatever you choose must support a child-directed flag; that
requirement rules out most smaller networks regardless of eCPM.
