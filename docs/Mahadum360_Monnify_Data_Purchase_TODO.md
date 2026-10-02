# Mahadum360 data purchase with Monnify

Prepared October 2, 2026. This is an implementation plan. No feature implementation, provider activation, configuration change, charge, merge or deployment is included.

Monnify is the selected provider. The existing code integrates Monnify payment collection, but it does not yet integrate Monnify data vending. Current official documentation supports using Monnify for both parts, subject to Bills Payment activation and account-specific availability. A separate fulfillment provider is not required on the published evidence; if Monnify cannot enable the required products for this merchant, return that blocker to the owner before choosing an alternative.

## Evidence and current state

The repository is `C:/xampp/htdocs/mahamu360`, branch `codex/beta-feedback-20260903`. Planning sources are the BRD `docs/MAHADUM360_Business_Requirements_Document.docx`, the master Implementation TODO, AGENTS.md and Roles/Permissions. BRD section 6.6, FR-6.4 requires explicit consent and idempotency for a carrier-billed data top-up. FR-6.2 describes signed, idempotent payment webhooks. FR-6.3 concerns recurring airtime subscription billing and must remain separate from one-off data purchases.

| Provider | Implementation | Selected computer configuration | Verification limit |
| --- | --- | --- | --- |
| Monnify | Hosted checkout, verification/refund methods, signed payment webhook, admin configuration | Default; outbound payment calls enabled; API key, secret and contract code present; endpoint environment is sandbox | Presence of credentials does not establish validity. No external transaction or real delivery checked. Bills Payment activation unknown. |
| Paystack | Hosted checkout and payment webhook | Secret present; not default | No provider ping or live transaction checked; key type not inferred. |
| Flutterwave | Hosted checkout and payment webhook | Secret and webhook hash present; not default | No provider ping or live transaction checked; key type not inferred. |
| Operator SDP | Subscription airtime charge and OTP adapter | Outbound disabled; URL/token absent; webhook secret present | Not a configured outbound telco integration; no data-vending method. |

The read-only runtime inspection included readable local database integration overrides, using an in-memory cache. No secret values were printed. These findings apply to this computer, not production.

`BillingPage.tsx` already has a Mobile data card and Buy data button at `/billing`. `DataBundleModal.tsx` displays a static catalogue and carrier-balance consent. `DataBundleController::purchase` only writes a pending purchase; it neither collects payment nor requests fulfillment. No handler updating `DataBundlePurchase` from delivery results was found in the real app/routes. The existing purchase route already uses idempotency middleware and `billing.databundles.manage`. The modal lacks a recipient phone-number field and currently promises credit shortly after a pending-only response. Those gaps must be closed before presenting the flow as working.

The master TODO marks the earlier data-bundle scaffold complete and the modal verified live. That is not evidence of real carrier delivery: distinguish scaffold verification from provider fulfillment in the implementation status.

## Website placement

- [ ] Keep `/billing` as the main purchase location and improve the existing Mobile data card and modal. Use an authenticated deep link such as `/billing?buy=data` to reopen the purchase flow after sign-in; validate the query rather than accepting arbitrary redirect targets.
- [ ] Add a permission-aware Buy data shortcut in the authenticated account navigation or dashboard. Hide purchase controls from child profiles and accounts without the required permission; enforce the same rules server-side.
- [ ] On the Schools/public page, link the approved data message to the authenticated flow only when the feature is available. Preserve return-to-learning navigation after checkout. A school visitor should not imply authorization for school-account purchasing; that role remains a separate policy decision.
- [ ] Show network, recipient number confirmation, product size and validity, total NGN price and fees, consent, and payment method before checkout. Mask the number in history. Provide payment pending, payment received, delivering, delivered, failed and refund pending/refunded states with a receipt/reference and support path.
- [ ] Replace the existing airtime-balance copy with wording matching the approved Monnify funding flow. Show delivery success only after provider-confirmed vending success. Keep data availability independent of subscription upsells.

## Implementation checklist

### 1. Confirm the commercial and product contract

- [ ] Confirm Monnify Bills Payment is activated for this merchant, available in the intended test/live environments, and exposes the required DATA products. Obtain account-specific terms, settlement funding requirements, fees, refund behavior and support escalation. The published catalogue includes MTN, Airtel, Glo and T2; actual account products still require discovery.
- [ ] Record the owner-approved change from BRD carrier-balance charging to Monnify collection followed by merchant-funded vending. Provider selection is confirmed; the funding model, markup/fee bearer, recipient rules and refund policy are still implementation decisions requiring review.
- [ ] Confirm merchant liquidity requirements: Monnify's settlement guide describes debiting the merchant account for vending. Customer collection and merchant vending are separate financial events; avoid charging customers when funding/product availability is known to be unavailable.
- [ ] Review consent, privacy, number retention, irreversible delivery and refund wording. Obtain legal/business approval for the final policy text without claiming legal compliance.

### 2. Add a data-vending service and order records

- [ ] Add a dedicated `DataBundleGateway` interface with a `MonnifyBillsGateway` and disabled/test implementation. Keep it distinct from subscription `TelcoGatewayManager` and payment collection `PaymentGatewayManager`.
- [ ] Use Monnify's published discovery, customer validation, vend and requery operations. Fetch DATA billers/products; use provider product codes, validity, availability and prices instead of matching solely by MB. Validate the recipient against the chosen product and honor the returned validation-reference requirement. Confirm exact endpoint/schema behavior against account documentation before coding.
- [ ] Extend the purchase schema with owner/family/tenant, recipient, product/biller codes, quoted cost, customer charge, currency, consent version/time, payment reference, unique vend reference, provider transaction reference, separate payment and delivery status, failure reason and refund reference. Store money as integer minor units; convert provider major-unit values with decimal-safe parsing.
- [ ] Create a server-owned quote with expiry. Reject client-supplied prices, stale products, wrong currencies and invalid recipients. Define allowed recipient selection without silently restricting or widening who parents can top up.
- [ ] Require `billing.databundles.manage` plus record ownership/tenant policy. Parent-operated children must not initiate a financial operation. Keep staff/school purchases disabled unless an explicit permission policy is approved. Audit sensitive transitions with masked identifiers.

### 3. Collect and confirm payment safely

- [ ] Create the order and Monnify hosted checkout under a client idempotency key. Bind that key to user, operation and payload; reject reused keys with different payloads. Do not turn platform coins into NGN funding.
- [ ] Extend payment correlation to the data order without settling it as a subscription or family-wallet purchase. Verify transaction status, expected amount, NGN currency, merchant/contract and both references on the server before scheduling fulfillment. Handle under/overpayment using an approved rule.
- [ ] Validate production webhooks against the raw body signature with constant-time comparison, deduplicate provider event/transaction identifiers, and acknowledge only after durable receipt. Queue fulfillment outside the request. Unknown/replayed/out-of-order events must not charge, vend or refund twice.
- [ ] Resolve sandbox webhook differences explicitly: official docs say sandbox notifications omit the production signature. Keep production checks strict; use isolated fixtures or a separately secured sandbox verification path rather than allowing unsigned events in production.

### 4. Vend, reconcile and handle failures

- [ ] After verified payment, enqueue one fulfillment operation. Use database uniqueness/locking and a durable outbox so payment and job dispatch cannot drift. Carry one immutable merchant vend reference per order and retain the provider transaction reference.
- [ ] Separate paid from delivered. On timeout/unknown result, requery the existing reference with bounded backoff; do not issue a second vend to resolve uncertainty. Confirm provider duplicate-reference guarantees before permitting any resend.
- [ ] Reconcile pending collection and vending states on a schedule. Define limits, alerts and an admin manual-review queue for unresolved results. Update delivered only on authoritative SUCCESS; do not confuse collection webhooks with data delivery.
- [ ] On definitive failure, run the approved refund workflow once, with refund pending/refunded/failed states. Do not refund an unknown vend before determining whether data was delivered. Reconcile payment, merchant debit, vending and refund ledgers; do not promise automatic reversal without the provider contract.
- [ ] Mask phone numbers and sensitive payloads in logs, restrict order/history access, encrypt where required by the retention design, protect credentials, rate-limit attempts, and preserve an auditable trail without storing card details.

### 5. Verify and release

- [ ] Add backend tests for permission/tenant isolation, consent, expired quotes, missing recipient, amount/currency/reference mismatch, forged webhooks, duplicate/concurrent events, mismatched idempotency payloads, timeout then SUCCESS, persistent IN_PROGRESS, failure/refund, refund failure, and disabled/unconfigured vending. Prove one charge settlement and one vend per order.
- [ ] Add frontend tests for recipient/network/product selection, truthful payment versus delivery states, refresh/revisit, checkout cancellation, low connectivity, parent permissions, keyboard/modal accessibility and mobile layouts. An API 201 pending response must not display delivered success.
- [ ] Run Pint, PHPStan, PHPUnit, frontend Vitest, TypeScript and Vite build. Verify migration upgrade/rollback and recovery after queue/worker restart. Check the existing CI workflow: this branch alone does not trigger CI; a reviewed PR is needed for automated required checks.
- [ ] With separate authorization, perform sandbox integration checks and reconcile references/statuses against the provider. Use approved production test purchases only after credentials, feature flags, liquidity, monitoring, support and policy review are ready.
- [ ] Release behind a distinct data-vending flag, initially off; use a small approved pilot. Rollback must stop new purchases while continuing reconciliation and refunds for paid orders. Production enabling and deployment require explicit authorization.

Definition of done: an authorized parent can select a real product and recipient, consent, complete a verified Monnify payment, receive a single provider-confirmed data delivery, and view an accurate receipt/status; every pending/failure/refund path remains traceable and recoverable. A mocked test, pending database row or enabled credential flag alone is insufficient.

## Official references checked October 2, 2026

- [Monnify data and airtime coverage](https://monnify.com/products/bills-payment)
- [Bills Payment activation and workflow](https://developers.monnify.com/docs/bills-payment/process-a-bill)
- [Merchant debit and commission settlement](https://developers.monnify.com/docs/bills-payment/settlement-process)
- [Server-side payment verification](https://developers.monnify.com/docs/collections/manage-payments/verify-transactions)
- [Webhook security and environment differences](https://developers.monnify.com/docs/webhooks/event-types)

Account activation, liquidity, exact vending schemas, sandbox behavior, commercial/refund policy and real delivery remain unverified. No substitute provider has been selected.
