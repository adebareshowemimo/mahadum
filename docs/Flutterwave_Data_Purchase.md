# Flutterwave Buy data integration

Flutterwave is an additional provider. Monnify remains available, and existing data orders default to Monnify through the provider migration. Each new order stores its payment and fulfillment providers so status checks always return to the provider used to create it.

## Configuration

The current flow uses Flutterwave Standard hosted checkout and the v3 Bills API. Configure all three Flutterwave API keys (public, secret, encryption) at `/admin/settings/gateways` or with `FLUTTERWAVE_PUBLIC_KEY`, `FLUTTERWAVE_SECRET`, and `FLUTTERWAVE_ENCRYPTION_KEY`. The current server-side checkout and Bills calls use the secret key; the other two are retained with the account configuration. Set a separate `FLUTTERWAVE_SECRET_HASH` to match the webhook secret hash configured in the Flutterwave dashboard. Admin-saved keys and hash are encrypted at rest and never returned by the API. Replacing the secret key without a new webhook hash clears the previous hash, so checkout cannot be enabled until a matching hash is saved. Set `PAYMENT_GATEWAY_LIVE=true` only in an environment intended to call its configured payment providers.

`FLUTTERWAVE_ENABLED` and `FLUTTERWAVE_BILLS_ENABLED` default to `false` and can be changed independently on the admin page. Flutterwave remains an additional choice; saving its configuration does not change the Monnify default. The admin page's "Test data catalogue" button makes only a read-only category request and reports bill access errors. Enable data purchases only after the account has Nigerian mobile data bill access, the source balance is funded, and the server's outgoing IP is allowlisted. The bill catalogue and purchase endpoints use the configured Flutterwave secret. Never copy live credentials into a test environment or commit credentials.

The admin page also saves a separate preferred mobile data provider (`DATA_BUNDLE_DEFAULT_PROVIDER` defaults to `monnify`). The Buy data page combines offers from providers whose read-only network catalogue responds, places preferred-provider offers first, and labels offers when more than one provider is available. Each offer retains its original provider, biller code, product code, and price. Checkout verifies the selected provider's current plan and price before creating an order; a paid order never switches providers automatically.

The webhook URL is `/api/v1/webhooks/flutterwave`. Flutterwave's `verif-hash` must match the configured secret hash. Payment confirmation still re-queries Flutterwave and checks the order reference, amount, currency, and successful status before starting a bill purchase. A bill request is sent once; the returned Flutterwave bill `tx_ref` is saved for later checks through `/v3/bills/{tx_ref}`. If bill submission times out before that reference is returned, the order is never resubmitted automatically; a signed bill webhook can supply it for requery. Uncertain or failed delivery remains a support/reconciliation case and is not automatically refunded.

## Validation before enabling purchases

1. Test read-only `MOBILEDATA` category, billers, and items with the **test** account from the deployment server. Confirm account permissions and server IP allowlisting.
2. Configure a test webhook secret hash in the Flutterwave dashboard and the app environment, then verify receipt of a signed event.
3. Verify checkout success, failed/cancelled payment, pending payment, mismatched amount/currency/reference, duplicate notifications, bill pending/success/failure, and timeout requery behavior.
4. Confirm a controlled live data purchase reaches the recipient before enabling live Buy data broadly. Check the Flutterwave bill reference, source balance, internal order, and customer receipt.

The supplied test secret returned HTTP 400 on a read-only category request on 21 October 2026 with the message “Please enable IP Whitelisting to access this service.” No bill or payment was initiated by that check.
