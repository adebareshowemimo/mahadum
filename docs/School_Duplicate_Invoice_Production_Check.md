# Production check: invoices #000011 and #000012

The local application is not connected to production. After deploying the duplicate-purchase guard and cleanup command, run `php artisan billing:cancel-duplicate-invoice 11 12` on production. It previews the school, amount and unused duplicate allocation, and refuses a mismatch, a paid invoice, an initiated checkout, a missing purchase record, or an allocation already in use.

If the preview matches the reported duplicate ₦129,000 charge and production payment records show no payment against #000012, run `php artisan billing:cancel-duplicate-invoice 11 12 --execute` on production. This keeps #000011 payable, marks #000012 cancelled, removes only the unused duplicate seat allocation, points retries at #000011, and records an audit event. Confirm the school's unpaid total is ₦129,000 afterward. If any guard fails, inspect the two invoices and payment records manually; do not force a database update or remove a filled allocation.
