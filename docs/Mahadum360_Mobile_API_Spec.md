# MAHADUM.360 — Mobile API Specification

Concrete contract for the iOS/Android clients. Complements the *Backend Architecture*
(endpoint catalogue) and *Content Model* (`/play` payload). Auth = **Laravel Sanctum
bearer tokens** (mobile) — the same API also serves the web SPA via cookie auth.

> The mobile app is **POST-MVP** to ship, but the API is built MVP-first and is
> mobile-ready from day one — so this spec drives the Laravel API now and the apps later.

---

## 1. Conventions

**Base URL** `https://api.mahadum360.com`
**Version** path-prefixed: `/api/v1/...` (breaking changes → `/api/v2`).

### Required headers
| Header | Example | Notes |
|---|---|---|
| `Authorization` | `Bearer 12|abc...` | Sanctum token (after login) |
| `Accept` | `application/json` | always |
| `Content-Type` | `application/json` | on POST/PATCH |
| `X-Client` | `ios` \| `android` | client platform |
| `X-App-Version` | `1.4.0` | drives force-update |
| `X-Device-Id` | `<uuid>` | stable per install (fraud + push) |
| `Accept-Language` | `en`, `ig` | UI locale |
| `X-Organization-Id` | `42` | only for school users w/ multiple orgs |
| `Idempotency-Key` | `<uuid>` | **required on money POSTs** (wallet, billing, payout) |

### Success envelope
```json
{ "data": { ... }, "meta": { ... } }
```
### Error envelope (RFC-7807-ish)
```json
{
  "error": {
    "code": "validation_failed",
    "message": "The phone number is invalid.",
    "status": 422,
    "details": { "phone": ["Invalid MSISDN format."] }
  }
}
```
| HTTP | When |
|---|---|
| 200/201 | ok |
| 401 | missing/expired token → re-auth |
| 403 | wrong role / cross-tenant |
| 409 | idempotency conflict / state conflict |
| 422 | validation |
| 426 | **upgrade required** (force-update) |
| 429 | rate limited (`Retry-After` header) |

### Pagination — cursor
`GET /lessons?cursor=eyJpZCI6MTB9&limit=20`
```json
{ "data": [ ... ], "meta": { "next_cursor": "eyJpZCI6MzB9", "has_more": true } }
```

### Rate limits
Per-token + per-IP. Headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`.
Auth endpoints throttled harder (e.g. 5/min). OTP: 3/10min per MSISDN.

---

## 2. Auth & token lifecycle (mobile)

Mobile uses **bearer tokens**, stored in **iOS Keychain / Android Keystore** (never plain prefs).
Each login mints a token **scoped to the device** with abilities and an expiry.

### 2.1 Sequence
```
register/login ──▶ {token, user, abilities, expires_at}
   store token in Keychain/Keystore
   POST /me/devices  (register push token)
every request ──▶ Authorization: Bearer <token>
   401 ──▶ try POST /auth/refresh ; if that 401s ──▶ send user to login
logout ──▶ DELETE /auth/token (revoke this device's token)
```

### 2.2 Endpoints

**POST `/api/v1/auth/register`**  (adult / parent; age-gate handled client+server)
```json
// request  (referral_code optional; X-Device-Id header used for fraud attribution)
{ "first_name":"Funmi", "last_name":"Adeyemi", "email":"funmi@mail.com",
  "password":"••••••••", "password_confirmation":"••••••••",
  "username":"funmi", "device_name":"Funmi iPhone" }
// 201
{ "data": {
  "token": "37|Hk9...",
  "token_type": "Bearer",
  "expires_at": "2026-07-26T10:00:00Z",
  "abilities": ["parent"],
  "user": { "id":101, "first_name":"Funmi", "last_name":"Adeyemi",
            "name":"Funmi Adeyemi", "roles":["parent"],
            "active_organization_id": null }
}}
```

**POST `/auth/login`**
```json
{ "login":"funmi@mail.com", "password":"••••••••", "device_name":"Funmi iPhone" }
// → same shape as register
```

**POST `/auth/google`** (native Google Sign-In → send the ID token)
```json
{ "id_token":"<google_id_token>", "device_name":"Funmi iPhone" }
// → token envelope
```

**POST `/auth/refresh`** (sliding expiry — mints a fresh token, revokes the old)
→ `{ "data": { "token":"...", "expires_at":"..." } }`

**DELETE `/auth/token`** — revoke current device token (logout). `204`.

**POST `/auth/password/forgot`** `{ "email":"..." }` → `202`.
**POST `/auth/password/reset`** `{ "token":"...","password":"..." }`.

**Telco OTP (authenticated; only at airtime opt-in — not for login):**
`POST /telco/otp/request` `{ "msisdn":"+2348012345678", "operator":"mtn" }` → `202 { "expires_at", "msisdn" }`. Rate-limited (6/min); code is delivered out-of-band, never returned in the response.
`POST /telco/otp/verify` `{ "msisdn":"...", "code":"123456" }` → `200 { "verified": true }`, else `422`. Six-digit code, 5-min TTL, ≤5 attempts.
The verified MSISDN is then enrolment-eligible for 15 min and consumed single-use by `POST /telco/subscribe` (which returns `403` if no fresh verification exists).

---

## 3. Bootstrap & device

**GET `/api/v1/config`** (call on launch — drives force-update, feature flags, CDN base)
```json
{ "data": {
  "min_supported_version": { "ios":"1.2.0", "android":"1.2.0" },
  "force_update": false,
  "feature_flags": { "ai_pronunciation": false, "telco_billing": true },
  "cdn_base": "https://cdn.mahadum360.com",
  "languages": [ {"code":"ig","name":"Igbo"}, {"code":"yo","name":"Yoruba"} ]
}}
```
> If `X-App-Version` < `min_supported_version` → API returns **426** on protected routes; client shows the update gate.

**GET `/me`** — current user, roles, abilities, families, profiles, active tenant.
```json
{ "data": {
  "user": { "id":101, "first_name":"Funmi", "last_name":"Adeyemi", "name":"Funmi Adeyemi", "roles":["parent"] },
  "families": [ { "id":7, "name":"Adeyemi Family",
    "learners": [ {"id":55,"display_name":"Chidi","target_language":"ig","is_child":true} ] } ],
  "organizations": [],
  "active_organization_id": null
}}
```

**POST `/me/devices`** — register/refresh push token.
```json
{ "device_id":"<uuid>", "platform":"ios", "push_token":"<fcm/apns>" }
```

**POST `/profiles/{learnerId}/switch`** — switch active child profile (parental PIN).
```json
{ "pin":"4821" }   // → { "data": { "active_learner_id": 55 } }
```

---

## 4. Onboarding & assessment

| Method | Path | Body → Response |
|---|---|---|
| POST | `/onboarding/goal` | `{learner_id, motivation}` → ok |
| POST | `/assessments` | `{learner_id, language, answers[]}` → `{result_level}` |
| GET | `/courses?language=ig` | list of courses |
| POST | `/enrollments` | `{learner_id, course_id}` → enrollment + initial path |

---

## 5. Learning (the core mobile loop)

**GET `/learners/{id}/path`** — the tree (nodes with state).
```json
{ "data": { "units":[ { "title":"Greetings",
  "nodes":[ {"lesson_id":451,"title":"Morning Greetings","state":"active","position":1},
            {"lesson_id":452,"title":"Family Titles","state":"locked","position":2} ] } ] } }
```

**GET `/lessons/{id}/play`** — ordered components, **signed media**, questions **without answers**
(full shape in *Content Model* §7). Mobile caches this for the session.

**POST `/lessons/{id}/progress`** — heartbeat / component completion.
```json
{ "component_id":1, "type":"video", "watched_seconds":92, "completed":true }
```

**POST `/components/{id}/answer`** — submit a quiz answer; **server grades**.
```json
// request
{ "question_id":5012, "answer":{ "option_id":1 }, "time_ms":2400 }
// response
{ "data": { "correct":true, "correct_answer":{"option_id":1},
            "explanation":"‘Ụtụtụ ọma’ = good morning.",
            "hearts_remaining":5, "xp_awarded":2 } }
```

**POST `/speaking-submissions`** — multipart audio upload (AI deferred → `needs_review`).
```
multipart: learner_id, component_id, audio(file)
→ { "data": { "id":900, "status":"needs_review" } }
```

**POST `/lessons/{id}/complete`** — finalise (server verifies all components).
```json
{ "data": {
  "lesson_score": 0.92, "xp_total": 23, "coins_awarded": 10,
  "streak": { "count": 15, "state":"active" },
  "badges_unlocked": [ {"code":"first_greeting","name":"First Greeting"} ],
  "next_node": { "lesson_id":452, "unlocked":true }
}}
```

---

## 6. Gamification

| Method | Path | Response |
|---|---|---|
| GET | `/learners/{id}/streak` | `{count, longest, state, frozen_until}` |
| POST | `/streak/shield` | spend coins/premium to arm a shield |
| GET | `/hearts` | `{current, refills_at}` |
| POST | `/hearts/refill` | rewarded-ad or coin refill (Rule 4 — never blocks learning) |
| GET | `/leagues/current` | league + `{rank, weekly_xp}` |
| GET | `/leaderboard?league={id}` | ranked members (cursor) |
| GET | `/learners/{id}/badges` | earned + locked |

---

## 7. Family & wallet (parent on mobile)

| Method | Path | Body |
|---|---|---|
| GET | `/family` | family + members + balances |
| POST | `/family/children` | add child profile (COPPA) |
| GET | `/wallet` | `{coin_balance, currency_balance}` |
| POST | `/wallet/fund` | `{amount, gateway}` → returns gateway checkout ref *(Idempotency-Key)* |
| POST | `/wallet/transfer` | `{to_learner_id, coins}` |
| GET | `/chores` · POST `/chores` | list / create |
| GET | `/reviews/pending` | speaking + chore + assignment submissions |
| POST | `/chores/{id}/review` | `{decision:"approve"}` → releases coins |

> `/wallet/fund` returns a gateway **checkout reference/URL**; the app opens the gateway SDK/webview.
> The server confirms via **webhook** (not the client) and the wallet updates — the app polls `/wallet` or receives a push.

---

## 8. Subscription & billing

| Method | Path | Body / Notes |
|---|---|---|
| GET | `/plans` | tiers + prices (NGN + diaspora) |
| POST | `/subscriptions` | `{plan_id, method:"card"}` → gateway checkout *(Idempotency-Key)* |
| POST | `/telco/otp/request` · `/telco/otp/verify` | MSISDN ownership proof — see §2 *(rate-limited)* |
| POST | `/telco/subscribe` | `{plan_id, msisdn, operator}` — requires a fresh verified OTP (else `403`) |
| GET | `/telco/status` | `{state:"active|grace|...", grace_until}` |
| POST | `/subscriptions/{id}/cancel` | also surfaces "text STOP to 3600" |
| GET | `/data-bundles` · POST `/data-bundles/purchase` | one-tap airtime data top-up |

**Mobile note:** Apple/Google may require **in-app purchase** for *digital* subscriptions on their
stores. Telco airtime + Flutterwave/Paystack are fine for web and for physical/family-funding,
but plan for IAP (StoreKit / Play Billing) on the subscription tier if shipping via the app stores —
flag for legal/commercial before mobile launch.

---

## 9. Referrals & notifications

| Method | Path |
|---|---|
| GET | `/referral-code` · `/referrals/summary` |
| POST | `/payouts/request` *(Idempotency-Key)* |
| GET | `/notifications?cursor=` · POST `/notifications/{id}/read` |
| POST | `/notifications/read-all` |

---

## 10. Offline & low-bandwidth (mobile-critical)

- **Resilient progress:** queue `progress` / `answer` writes locally; flush when online. Server is
  idempotent on `(learner_id, component_id, attempt)` so replays are safe.
- **Offline lesson packs** (premium, ≤5): `POST /lessons/{id}/download-pack` → returns a manifest of
  signed asset URLs (video renditions @ 240/360p, audio, questions JSON) to cache locally; expires on
  subscription lapse.
- **Video:** request 360p by default; `?quality=240p` on poor links; adaptive HLS where supported.
- **Sync:** `GET /sync?since={iso}` returns changed path/progress/wallet/streak deltas for fast resume.
- **Conflict rule:** last-write-wins on progress; money is **server-authoritative** (client never
  computes balances).

---

## 11. Security (mobile)

- Tokens in **Keychain/Keystore**; never in logs or shared prefs.
- **Certificate pinning** to the API domain.
- Short-ish token expiry + `/auth/refresh`; revoke on logout / password change / suspected compromise.
- No card data ever touches the app — gateway SDK/webview only (PCI).
- Biometric unlock optional for the parent PIN.

---

## 12. Laravel implementation pattern

**Routes — `routes/api.php`**
```php
Route::prefix('v1')->group(function () {
    // public
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login',    [AuthController::class, 'login']);
    Route::post('auth/google',   [AuthController::class, 'google']);
    Route::get('config',         [ConfigController::class, 'show']);

    // protected
    Route::middleware(['auth:sanctum', 'identify.tenant', 'min.app.version'])->group(function () {
        Route::get('me', [MeController::class, 'show']);
        Route::post('me/devices', [DeviceController::class, 'store']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::delete('auth/token', [AuthController::class, 'logout']);

        Route::get('learners/{learner}/path', [PathController::class, 'show'])
            ->middleware('can:view,learner');
        Route::get('lessons/{lesson}/play', [LessonPlayController::class, 'show']);
        Route::post('components/{component}/answer', [AnswerController::class, 'store']);
        Route::post('lessons/{lesson}/complete', [LessonController::class, 'complete']);

        // money endpoints require idempotency
        Route::middleware('idempotency')->group(function () {
            Route::post('wallet/fund', [WalletController::class, 'fund']);
            Route::post('subscriptions', [SubscriptionController::class, 'store']);
            Route::post('payouts/request', [PayoutController::class, 'store']);
        });
    });
});
```

**Token issuance (Sanctum, device-scoped, with abilities + expiry)**
```php
public function login(LoginRequest $r) {
    $user = User::where('email', $r->login)->orWhere('username', $r->login)->first();
    if (! $user || ! Hash::check($r->password, $user->password)) {
        return response()->json(['error' => ['code'=>'invalid_credentials','status'=>401]], 401);
    }
    $abilities = $user->getRoleNames()->all();
    $token = $user->createToken($r->device_name, $abilities, now()->addDays(30));
    $user->update(['last_login_at' => now()]);
    return response()->json(['data' => [
        'token'      => $token->plainTextToken,
        'token_type' => 'Bearer',
        'expires_at' => $token->accessToken->expires_at,
        'abilities'  => $abilities,
        'user'       => new UserResource($user),
    ]], 201);
}
```

**API Resource (stable JSON contract — decouples DB from payload)**
```php
class LessonPlayResource extends JsonResource {
    public function toArray($request): array {
        return [
            'lesson' => ['id'=>$this->id, 'title'=>$this->title, 'est_minutes'=>$this->est_minutes],
            'components' => $this->components->sortBy('position')->map(fn($c) => [
                'id'=>$c->id, 'type'=>$c->type, 'position'=>$c->position, 'xp'=>$c->xp_value,
                $c->type => $this->payloadFor($c),   // strips is_correct for quizzes
            ])->values(),
        ];
    }
}
```

**Middleware**
- `identify.tenant` — sets current org context (super-admin bypass; `X-Organization-Id` validated).
- `min.app.version` — returns **426** when `X-App-Version` < `config.min_supported_version`.
- `idempotency` — caches the response for an `Idempotency-Key` (24h) so retries don't double-charge.

**Versioning & deprecation:** additive changes stay on `v1`; breaking changes → `v2` with a sunset
window. Return `Deprecation` / `Sunset` headers on endpoints scheduled for removal.

---

## 13. Build checklist (API layer)

- [ ] Sanctum token guard + abilities; device-scoped tokens + expiry + `/auth/refresh`.
- [ ] `config` + `min.app.version` (426) force-update gate.
- [ ] Standard envelope + error handler + cursor pagination (base controller / middleware).
- [ ] `idempotency` middleware on all money POSTs.
- [ ] API Resources for every payload (never return raw models).
- [ ] Tenant middleware + policies on every protected route.
- [ ] Rate limiters (auth, otp, general); per-token throttling.
- [ ] Push device registration (FCM/APNs); notification list/read.
- [ ] Offline: idempotent progress writes; `download-pack`; `/sync`.
- [ ] OpenAPI/Swagger generated from the routes (for the mobile team + contract tests).
- [ ] Postman/Insomnia collection + contract tests in CI.
```

---

### Next steps
- Generate the **OpenAPI 3.1 (swagger.yaml)** from this spec so the mobile team gets typed clients + mocking.
- Scaffold the Laravel **controllers + form requests + API resources + route file** for M1 endpoints.
