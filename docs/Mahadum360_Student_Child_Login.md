# Student & Child Access — How Login Works

Companion to the identity split described in `CLAUDE.md`: **`users`** (authenticatable
accounts) vs **`learner_profiles`** (the learner entity — a child under 13 has a profile
with **no login**, operated by a parent) vs **`families`** (household owned by a parent
`user`). This doc traces the actual code paths for both cases so the design decision
(COPPA-aware: children never hold credentials) stays discoverable.

## 1. A `student`-role user with their own account (teen / adult learner)

Registering with `accountType: 'learner'` assigns the `student` role directly — no
`Family` is created for this path (only `accountType: 'parent'` creates one):

- `POST /v1/auth/register` → `AuthController::register()`
  (`app/Http/Controllers/Auth/AuthController.php:27-68`) — `$role = $request->accountType()
  === 'learner' ? 'student' : 'parent'` (line 29).
- `POST /v1/auth/login` → `AuthController::login()` (lines 70-89) — looks up the user by
  email or username, checks the password hash, rejects `status !== 'active'` accounts.
- Both paths end in `tokenResponse()` (lines 186-200), which issues a **Sanctum bearer
  token** (`user->createToken(...)`, 30-day TTL) carrying the user's role names as token
  abilities.
- A `student` user can be linked to their own `LearnerProfile` via `learner_profiles.user_id`.
  `LearnerProfilePolicy::isSelf()` (`app/Policies/LearnerProfilePolicy.php:51-54`) grants
  them `view`/`update` on that profile directly — no parent or family relationship needed.
  This is why the `student` role carries no global permissions (see
  `docs/Mahadum360_Roles_Permissions.md`): self-access is authorized entirely through this
  policy check, not a permission grant.

Google OAuth (`AuthController::google()`, lines 112-159) only ever creates `parent`
accounts (with a `Family`), so it's not part of the student self-login path.

## 2. A child under a parent (`learner_profile`, no login of their own)

**The child never authenticates.** There is no child username/password, and no separate
child token. Every API call for the child is made using the **parent's own Sanctum
token**, with the specific child identified only as a `learner_profile_id` route/request
parameter.

### 2a. Backend authorization

`LearnerProfilePolicy` (`app/Policies/LearnerProfilePolicy.php`) is what stands in for a
child "login": the parent's token proves *who is calling*, and the policy proves *which
learner they may act as*.

- `isParentOwner()` (lines 56-59): `$profile->family->owner_user_id === $user->id` — the
  logged-in parent owns the family that the profile belongs to.
- `view()` (lines 18-23) also allows the learner's own `isSelf()` match, or org staff with
  `learning.progress.view` in the same tenant (teacher/supervisor/school_admin viewing a
  student's progress — not the child self-serving).
- `redeemReward()` (lines 46-49) is deliberately narrower — self or parent-owner only —
  so staff who can *view* progress still can't trigger reward redemption on a child's
  behalf.

Controllers that act on a specific child use the `ResolvesLearner` trait
(`app/Http/Controllers/Concerns/ResolvesLearner.php`), which loads the `LearnerProfile` and
runs `Gate::authorize('view'|'redeemReward', $learner)` against the parent's authenticated
user before doing anything else.

### 2b. The "switch profile" flow (PIN gate, not authentication)

This is UX, not a second auth system — it never issues a token of its own.

- `POST /v1/profiles/{learner}/switch` (`routes/api.php:138-139`) sits behind
  `auth:sanctum` (the parent's token) **and** `->can('view', 'learner')` (the policy check
  above) before the controller runs at all.
- `ProfileController::switch()` (`app/Http/Controllers/ProfileController.php:17-27`): if
  `$learner->parental_pin_protected` is true, it additionally requires a 4-digit PIN,
  checked against the family's hashed `parental_pin` via `pinMatches()` (lines 29-40:
  `Hash::check($request->input('pin'), $family->parental_pin)`). If the family has no PIN
  configured, any non-empty PIN is accepted as a placeholder gate.
- On success it returns `{ data: { active_learner_id } }` — just an id, no new credential.

### 2c. Frontend

- `ActiveProfileProvider` / `useActiveProfile()`
  (`web/src/lib/profile/ActiveProfile.tsx`) tracks which child is "active" in
  `localStorage` (`mahadum.active_learner`), sourced from `user.families[].learners` on
  the authenticated parent's `/me` payload. It clears the active learner on sign-out and
  if the stored id no longer matches a known learner once `/me` reloads.
- `ProfileSwitcher` (`web/src/components/layout/ProfileSwitcher.tsx`) is the topbar
  control: lists the family's learner profiles, and for a `pin_protected` one opens a
  `PinModal` (4-digit `CodeInput`) before calling `profileApi.switch(learnerId, pin)`
  (`web/src/lib/api/endpoints.ts`). On success it calls `setActiveLearner(learner.id)`
  and navigates to `/learn`. "Exit to parent" clears the active learner and returns to
  `/home`.
- `ActiveLearnerGate` (`web/src/components/learner/ActiveLearnerGate.tsx`) wraps
  learner-scoped pages/routes and prompts "choose a learner" when no profile is active.

## Summary

| | Own credential? | How the app identifies them | Where enforced |
|---|---|---|---|
| `student` (own account) | Yes — email/username + password → Sanctum token | Sanctum bearer token; self-access via `LearnerProfile.user_id` | `AuthController`, `LearnerProfilePolicy::isSelf()` |
| Child (`learner_profile`, no `user`) | No | Parent's Sanctum token + `learner_profile_id` param; optional 4-digit PIN as a local UX gate | `LearnerProfilePolicy::isParentOwner()`, `ResolvesLearner`, `ProfileController::switch()` |

The PIN is a **soft, family-local gate** (e.g. to stop one sibling opening another's
profile on a shared device) — it is not authentication and does not appear anywhere in
the Sanctum token/ability chain.
