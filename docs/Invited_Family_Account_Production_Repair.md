# Production repair: Family account with a teacher invitation

This workspace runs the local application, not production. After deployment, run `php artisan accounts:repair-invited-families` on production to preview affected accounts. The command selects only users who owned a family before an accepted teacher invitation and whose sign-up type was changed to `teacher` or whose `parent` role is missing. It leaves the teacher role and school membership in place.

Check Lucy's user ID, family ownership, invitation acceptance and preview output. Run `php artisan accounts:repair-invited-families --user=<Lucy user ID> --execute` for her verified account. Then confirm `/me` reports `account_type=family`, both `parent` and `teacher` roles are present, and the Family page loads her existing children. Review any other previewed accounts individually before applying the repair to them. The command writes an audit record for each repaired user.
