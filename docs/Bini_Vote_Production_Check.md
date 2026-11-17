# Bini vote production check

Deploy the code and run migrations. The new migration renames stored `Benin` votes to `Bini` so the public count and admin reports use the agreed name.

Identify the reported test vote from the production database before removal:

```sql
SELECT v.id, v.language, v.created_at, u.email, u.email_verified_at
FROM language_votes v
JOIN users u ON u.id = v.user_id
WHERE v.language = 'Bini'
ORDER BY v.created_at;
```

Check the account and timestamp against the test record. Do not remove a genuine community vote merely because it was previously labelled `Benin`. Once the exact vote ID and account email are confirmed, preview and then remove only that row:

```bash
php artisan language-votes:remove-test <vote-id> <account-email>
php artisan language-votes:remove-test <vote-id> <account-email> --execute
```

Confirm `/vote`, `/languages`, sign-up, the website reports, and the CSV show `Bini`, and that the public count excludes the removed test vote. Clear production configuration and page caches as part of deployment.
