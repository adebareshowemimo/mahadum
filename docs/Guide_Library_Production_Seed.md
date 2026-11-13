# Seed the public Guides library

The first normal production deployment containing migration
`2026_11_13_000001_seed_initial_production_guides` adds the guide videos and FAQs
once. Run the normal deployment for the intended branch:

```bash
BRANCH=codex/subscriptions-report-table-20261023 ./deploy/deploy.sh
```

If that migration was already recorded as run without data, the guide content
can still be seeded explicitly:

```bash
cd /var/www/mahadum
SEED_GUIDE_LIBRARY=1 php artisan db:seed --class='Database\Seeders\GuideLibrarySeeder' --force
```

The seed adds 24 videos to each of How-to and Sample and five FAQs. It preserves
admin edits to existing entries. Subsequent ordinary deployments leave guide
entries alone; explicitly reseeding can recreate an entry an editor deleted.
Verify `/guides` shows video cards and FAQs after the seed completes.
