# Seed the public Guides library

The guide playlists and FAQs are not part of the default production seed. After
deploying the Guides code and migrations, run the deployment once with the
explicit guide flag:

```bash
BRANCH=codex/subscriptions-report-table-20261023 SEED_GUIDE_LIBRARY=1 ./deploy/deploy.sh
```

If the current release is already deployed and the guide migrations have run,
the same seed can be applied without rebuilding the site:

```bash
cd /var/www/mahadum
SEED_GUIDE_LIBRARY=1 php artisan db:seed --class='Database\Seeders\GuideLibrarySeeder' --force
```

The seed adds 24 videos to each of How-to and Sample and five FAQs. It preserves
admin edits to existing entries. Ordinary deployments leave guide entries alone;
using the flag again can recreate any seeded entry that an editor deleted.
Verify `/guides` shows video cards and FAQs after the seed completes.
