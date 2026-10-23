# Page load audit — 9 October 2026

## Production finding

The Families page is blank because Apache cannot serve the deployed browser files. The HTML returns HTTP 200, but the main script, both vendor scripts and stylesheet return HTTP 403. The Apache error log confirms permission denied on `public/assets`.

Read-only server inspection found:

- `public/assets` and `public/images` have mode `700`.
- All 142 bundled files lack read permission for other users.
- The `www-data` web-server account cannot traverse `public/assets`.

This affects all routes that need the blocked bundles, rather than a single Families component. Production has not been modified during this audit.

## Local verification

- All 105 lazy page declarations import the expected component successfully (106 checks including discovery).
- The existing frontend suite passes: 381 tests across 71 files.
- TypeScript and the production Vite build pass.
- The compiled build visibly renders these 18 pages in Chrome: `/`, `/families`, `/schools`, `/institutions`, `/about`, `/pricing`, `/contact`, `/child-safety`, `/accessibility`, `/privacy`, `/terms`, `/login`, `/register`, `/forgot-password`, `/reset-password`, `/v2`, `/v3`, `/v4`.
- The deployment asset check covers all published JavaScript and CSS, including lazy page chunks. A simulated forbidden Families chunk correctly fails deployment and invokes rollback.

Component import checks do not establish that every authenticated workflow works. Live rendering and authenticated page acceptance remain pending the production permission repair and deployment.

## Remediation

Commit `20a84dd`, already pushed to `codex/beta-feedback-20260903`, repairs application and published SPA permissions during deployment. This audit additionally extends deployment HTTP checks to every lazy page asset and adds the all-page import check.

For immediate repair of existing public files, run on the server:

```bash
cd /var/www/mahadum
sudo find public/assets public/images -type d -exec chmod 755 {} +
sudo find public/assets public/images -type f -exec chmod 644 {} +
```

Refresh Families after repair. Recheck live routes and their assets after deploying the intended branch.
