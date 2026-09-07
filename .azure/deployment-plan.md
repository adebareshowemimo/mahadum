# Azure Deployment Plan

> **Status:** Deployed to production

Generated: 2026-08-20

---

## 1. Project Overview

**Goal:** Move the existing MAHADUM.360 application to the existing empty Ubuntu 24.04 VM whose public IP is `20.151.177.171`.

**Path:** Modernize existing application for an Azure VM deployment.

No additional VM is planned. The supplied VM is `Standard_D4s_v3` (4 vCPUs, 16 GiB RAM).

### Explicit scope boundary

- Deploy a clean copy of the current application code to `20.151.177.171`.
- Create a new MySQL database and new application environment on that VM.
- Do **not** connect to, inspect, modify, stop, migrate from, or otherwise touch the old system.
- Do **not** change existing DNS records as part of this deployment.

---

## 2. Requirements

| Attribute | Value |
|-----------|-------|
| Classification | Fresh deployment for review; production cutover is separate |
| Scale | Proposed: Small initially (<1,000 active users) |
| Budget | Proposed: cost-optimized, using the existing VM |
| Compliance | Awaiting confirmation; application handles children and payment-related data |
| Subscription | Confirmed new Azure account: `Azure subscription 1` (`4212afa5-d96d-4717-a56d-1d34956599a6`) |
| Location | Canada Central |
| Domain | None for initial deployment; use `20.151.177.171` for review |
| Source data | Fresh MySQL database only; the old system and its data are out of scope |

### Azure access finding

The Azure CLI is authenticated to the new account as `iwelabiafricastudio@gmail.com`. The target subscription, VM, public IP, NIC, and region have been verified. No resources from any older Azure account are in scope.

---

## 3. Components Detected

| Component | Type | Technology | Path |
|-----------|------|------------|------|
| API and web entry point | API/server application | Laravel 13, PHP 8.3 | repository root |
| SPA | Frontend | React 18, Vite, TypeScript, Tailwind | `web/` |
| Queue worker | Background worker | Laravel database queue, systemd unit available | `deploy/systemd/` |
| Scheduler | Scheduled jobs | Laravel scheduler, one-minute cron | `routes/console.php` |
| Database | Relational data | MySQL in the current local environment | external/local MySQL |
| User media | Persistent files | Laravel public storage disk | `storage/app/public/` |
| Web server | Reverse proxy/static/PHP | Existing Apache configuration | `deploy/apache/` |

No Copilot SDK, Azure Functions, Docker, Terraform, Bicep, or existing AZD configuration was detected.

The repository already contains a repeatable deployment script, Apache virtual host, MySQL setup, queue service, staging environment example, health check, and code rollback behavior.

### Research summary

- Existing-VM deployment uses Azure CLI only for VM/network access and SSH for guest configuration; no new Azure resources are provisioned.
- The release is transferred as a snapshot of the current workspace, excluding local secrets, dependencies, build outputs, and Git metadata.
- Ubuntu uses Apache with PHP 8.3 FPM, local-only MySQL, a systemd queue worker, and a one-minute scheduler entry.
- Initial access is HTTP on the fixed IP. TLS is intentionally deferred until a domain is approved because a public certificate should not be issued for a bare IP in this workflow.
- Network exposure is limited to SSH and HTTP for initial review. SSH key authentication and host-level UFW rules provide defense in depth.
- Application secrets are generated on the VM and stored only in its protected `.env`; review credentials are randomized and stored in the admin user's mode-0600 file.

---

## 4. Recipe Selection

**Selected:** Azure CLI + SSH deployment to the existing VM.

**Rationale:** The target compute already exists, and the repository already has VM-native Apache/systemd/cron deployment assets. Provisioning a second compute platform with AZD would add cost and complexity without helping this migration.

---

## 5. Proposed Architecture

**Stack:** Single Azure VM, hardened LAMP-style deployment.

| Component | Target | Notes |
|-----------|--------|-------|
| React SPA | Apache static assets | Built during deployment and served from Laravel `public/` |
| Laravel API | Apache + PHP 8.3 FPM | Same-origin API, storage, and health routes |
| MySQL | Local VM MySQL initially | Bind to localhost; final data migration choice is pending |
| Queue | systemd-managed Laravel worker | Persistent, restart-on-failure |
| Scheduler | system cron | Runs `php artisan schedule:run` every minute |
| Uploads | VM persistent disk | Backed up with the database; future Blob Storage migration remains optional |
| TLS | Let's Encrypt/Certbot | Requires the confirmed domain to point to the VM |
| Secrets | Production `.env` outside version control | Never copied into source control or command output |

### Security and reliability

- SSH key authentication; disable password/root SSH after access is verified.
- Restrict SSH in the NSG to the administrator's public IP where practical.
- Permit only HTTP/HTTPS publicly; use UFW as a host firewall.
- Keep MySQL bound to localhost and use a least-privilege application account.
- Configure automatic security updates, log rotation, database/upload backups, and restore verification.
- Take a source-data backup before migration and retain the old environment until acceptance.
- Run migrations in maintenance mode and validate the health endpoint, API, SPA, queue, scheduler, uploads, email, Google login, and Monnify callbacks.

---

## 6. Provisioning Limit Checklist

The current plan deploys application software onto an existing VM and creates **zero new Azure resources**. Azure resource quota is therefore not consumed by this deployment. Existing VM capacity and region will be verified after Azure re-authentication.

| Resource Type | Number to Deploy | Total After Deployment | Limit/Quota | Notes |
|---------------|------------------|------------------------|-------------|-------|
| New Azure resources | 0 | 0 | Not applicable | Existing VM, NIC, disk, and public IP are reused |

**Status:** No new-resource quota requirement. Existing-resource inspection is pending Azure re-authentication.

---

## 7. Proposed Execution Checklist

### Phase 1: Planning

- [x] Analyze workspace
- [x] Confirm review deployment, existing-VM scope, no domain change, and fresh source data
- [x] Confirm target subscription and existing VM location
- [x] Prepare resource inventory
- [x] Determine quota impact (zero new Azure resources)
- [x] Scan codebase
- [x] Select deployment recipe
- [x] Plan architecture
- [x] User approved this plan (2026-08-20)

### Phase 2: Preparation and migration

- [x] Research the existing-VM deployment components and security posture
- [x] Generate fresh-VM bootstrap and IP-only Apache configuration
- [x] Harden release handling, local database credentials, demo credentials, Apache headers, and UFW configuration
- [x] Build an immutable clean release snapshot from the current workspace
- [x] Re-authenticate Azure CLI and inspect VM, NIC, NSG, disks, and boot diagnostics
- [x] Verify SSH access and independently confirm the VM host key
- [x] Confirm source backup/restore is not applicable to the approved fresh-data deployment
- [x] Harden/update VM and install Apache, PHP 8.3 extensions, Composer, Node 22 LTS, MySQL, Git, and required utilities
- [x] Create a least-privilege local MySQL application user and application directories
- [x] Transfer the approved clean release snapshot and generate production secrets on the VM
- [x] Initialize a fresh database and randomized review credentials; no old data restored
- [x] Install queue service, scheduler, IP-only Apache virtual host, SSH hardening, and UFW
- [x] Run the deployment script and Laravel optimizations
- [x] Mark plan Ready for Validation

### Phase 3: Validation

- [x] Run local CI checks before release
- [x] Invoke the Azure validation workflow
- [x] All validation checks pass
  - [x] Azure CLI installation
  - [x] Authentication and exact subscription/region match
  - [x] Bicep compilation (not applicable: no infrastructure provisioning)
  - [x] ARM template validation (not applicable: no infrastructure provisioning)
  - [x] What-if preview (not applicable: no infrastructure provisioning)
  - [x] Docker build (not applicable: VM-native deployment)
  - [x] Azure Policy validation
  - [x] Existing VM/NIC/public-IP state validation
  - [x] Release archive contents, exclusions, and checksum validation
  - [x] Bootstrap/deployment shell syntax validation
  - [x] Application test/build/static-analysis validation
- [x] Verify HTTP SPA routes, API health, authentication, queue, scheduler, storage link, and safe disabled integration defaults
- [x] Confirm backup/restore is deferred until production data and uploads exist
- [x] Record validation evidence and mark the review deployment complete

### Phase 4: Cutover

- [ ] Obtain explicit cutover approval
- [ ] Lower DNS TTL and take a final source backup
- [ ] Restore the final data delta and switch DNS
- [ ] Smoke-test production and monitor logs
- [ ] Keep the old environment intact through the agreed rollback window
- [x] Mark the new IP-based review environment deployed; production cutover remains deferred

---

## 8. Validation Proof

### Course table-of-contents validation — 2026-09-07

- Azure validation workflow confirmed the expected subscription, Canada Central VM/IP, production marker `541320d`, five active services and successful HTTPS health check.
- Release `38a0030` is pushed to origin; frontend and documentation changes only. Three targeted regression tests, TypeScript, production build and diff checks passed. Deployment shell syntax passed.
- Archive SHA-256: `c50352401789bc2230eacdde43d5ac08f1794179ccf5a9db0e608b1a89285573`. Isolated frontend build, verified source/assets backup and atomic SPA entry-point replacement planned. No database, RBAC or infrastructure changes.

### Referral visibility release validation — 2026-09-07

- Azure validation workflow confirmed the expected subscription and running Canada Central VM at `20.151.177.171`. Production marker `553d9ba`, five active services, HTTPS health passed.
- Local verification: 8 backend regression tests / 61 assertions, 11 relevant frontend tests, production build, Pint, PHPStan level 5 and diff checks passed.
- Release `541320d` changes controllers and frontend only; no migration, dependency, infrastructure or RBAC change. Archive SHA-256 `f571a92ae4939af39d2e8a8ec5abb205f9a99355a36649bc3df6469d44f41f50`.
- Deployment builds separately, archives current source/assets, replaces controllers and SPA entry point atomically, and performs a read-only production referral-owner count/field check with rollback on failure.

### Worldwide calling-code release validation — 2026-09-07

- Azure validation workflow confirmed the existing subscription and running Canada Central VM at `20.151.177.171`; production marker is `2338b90` and all five runtime services are active. HTTPS health passed.
- Application delta to `553d9ba` contains frontend source, dependency lockfile, regression test and documentation only. No backend, database or infrastructure changes.
- Local frontend validation completed: 193 tests across 33 files passed; production build and `git diff --check` passed.
- Release delta archive SHA-256: `e2ee051a3460f26a1ca81c0aa3ec34c086c247c11d6919cdf15cf4a7d47232af`. Deployment builds in an isolated directory, backs up existing source/assets, retains old hashed assets and atomically replaces the SPA entry point.

### September 4 feedback release validation — 2026-09-07

- Azure validation workflow confirmed the existing subscription, Canada Central VM `mahadum`, and public IP `20.151.177.171` using `az account show` and `az vm show -d`.
- `az policy assignment list` returned the existing Security Center assignment; VM identity role assignments remain empty. No infrastructure or RBAC changes are needed.
- Azure VM Run Command verified Apache, PHP-FPM, MySQL and queue active, 114 GB free disk, and HTTPS `/up` returning 200.
- Local validation: PHPUnit 340 passed / 1 skipped (OpenSSL EC key generation unavailable), 1,492 assertions; Vitest 192 passed across 32 files; frontend production build passed; Pint and PHPStan level 5 passed.
- `git diff --check` and deployment shell syntax passed. Bicep/ARM/Docker checks are not applicable to this existing-VM application release.
- Production database/source backups and phone normalization preflight will run before migrations. No account cleanup is included.

This section will be populated by the Azure validation workflow before deployment.

| Check | Command Run | Result | Timestamp |
|-------|-------------|--------|-----------|
| Azure CLI | `az version` | Pass: Azure CLI 2.84.0 | 2026-08-20T04:06:25Z |
| Azure identity | `az account show` | Pass: exact subscription and tenant | 2026-08-20T04:06:25Z |
| Target VM | `az vm show --show-details` | Pass: running/succeeded in Canada Central | 2026-08-20T04:06:25Z |
| Public IP | `az network public-ip show` | Pass: static Standard IP `20.151.177.171` | 2026-08-20T04:06:25Z |
| Policy assignments | `az policy assignment list` | Pass: zero assignments constrain deployment | 2026-08-20T04:06:25Z |
| Release integrity | `tar -tzf` + `Get-FileHash -Algorithm SHA256` | Pass: all required files, zero forbidden entries, checksum matched | 2026-08-20T04:06:25Z |
| Shell syntax | `bash -n deploy/bootstrap-ubuntu.sh deploy/deploy.sh` | Pass | 2026-08-20T04:06:25Z |
| Source integrity | `git diff --check` | Pass | 2026-08-20T04:06:25Z |
| Frontend | `npm test` + `npm run build` | Pass: 179 tests and production build | 2026-08-20T04:06:25Z |
| Backend | `php artisan test --compact` | Pass: 306 passed, 1 skipped, 1,254 assertions | 2026-08-20T04:06:25Z |
| PHP quality | `vendor/bin/pint --test` + `vendor/bin/phpstan analyse --level=5` | Pass: formatting and zero static-analysis errors | 2026-08-20T04:06:25Z |
| New VM bootstrap | `deploy/bootstrap-ubuntu.sh` on `mahadum` | Pass: Ubuntu stack, Node 22.23.2 checksum, build, migrations, fresh seed, worker, scheduler, and UFW | 2026-08-20T16:23:59Z |
| External HTTP | `Invoke-WebRequest` to `/`, `/up`, `/register`, `/learn`, and `/api/v1/config` | Pass: HTTP 200 for all endpoints | 2026-08-20T16:23:59Z |
| Review authentication/data | `deploy/verify-review.sh` | Pass: login HTTP 200; 45 users; 8 courses; English present; zero Pidgin languages | 2026-08-20T16:23:59Z |
| Runtime services | `systemctl is-active` | Pass: Apache, PHP-FPM, MySQL, and queue worker active | 2026-08-20T16:23:59Z |
| Host security | `sshd -T`, `ufw status`, `ss -ltnp` | Pass: password/root SSH disabled; only SSH/HTTP allowed; MySQL bound to loopback | 2026-08-20T16:23:59Z |
| Temporary access cleanup | SSH denial + Azure VM Run Command | Pass: both one-time keys rejected/removed; original GitHub key retained | 2026-08-20T16:23:59Z |

### Production update validation — 2026-09-03

| Check | Command Run | Result | Timestamp |
|-------|-------------|--------|-----------|
| Azure target | `az account show`; `az vm show`; `az network public-ip show` | Pass: confirmed subscription `4212afa5-d96d-4717-a56d-1d34956599a6`, running VM `mahadum`, Canada Central, static IP `20.151.177.171` | 2026-09-03T17:42:47Z |
| Azure Policy | `az policy assignment list` | Pass: Security Center built-in assignment present; this release provisions no Azure resources | 2026-09-03T17:42:47Z |
| Infrastructure validation | Bicep/ARM what-if and Docker checks | Not applicable: existing VM application-only deployment, with no Bicep, ARM, Terraform, AZD, or containers | 2026-09-03T17:42:47Z |
| Source integrity | `git diff --check`; `bash -n deploy/*.sh` | Pass | 2026-09-03T17:42:47Z |
| Frontend | `npm test`; `npm run build` | Pass: 182 tests and production Vite build | 2026-09-03T17:42:47Z |
| Backend | `composer ci` | Pass: Pint, PHPStan level 5, 316 tests passed, 1 skipped, 1,299 assertions | 2026-09-03T17:42:47Z |
| Referral regression | focused referral, Google auth, school referral, and admin referral suites | Pass: 26 tests, 104 assertions; Google referral attribution and production deep-link smoke coverage included | 2026-09-03T17:42:47Z |
| Existing runtime | Azure VM Run Command + public HTTP checks | Pass: Apache, PHP-FPM, MySQL, and queue worker active; `APP_ENV=production`; root, `/up`, and `/api/v1/config` return HTTP 200 | 2026-09-03T17:42:47Z |

**Validated by:** Azure validation workflow

**Validation timestamp:** 2026-09-03T17:42:47Z

### Beta-feedback release validation — 2026-09-03

| Check | Command Run | Result | Timestamp |
|-------|-------------|--------|-----------|
| Azure identity and target | `az account show`; `az vm list -d` | Pass: subscription `4212afa5-d96d-4717-a56d-1d34956599a6`; VM `mahadum` running in Canada Central at `20.151.177.171` | 2026-09-03T18:49:00Z |
| Infrastructure validation | Bicep/ARM what-if and Docker checks | Not applicable: existing VM application-only update; no infrastructure resources are created or changed | 2026-09-03T18:49:00Z |
| Runtime preflight | Azure VM Run Command; `systemctl is-active` | Pass: Apache, PHP-FPM, MySQL, and `mahadum-queue` active; `APP_ENV=production` | 2026-09-03T18:49:00Z |
| Release integrity | `git diff --check`; `bash -n deploy/*.sh`; `vendor/bin/pint --test` | Pass | 2026-09-03T18:49:00Z |
| Frontend | `npm test`; `npm run build` | Pass: 188 tests; production Vite build | 2026-09-03T18:49:00Z |
| Backend | `php artisan test`; `vendor/bin/phpstan analyse --level=5` | Pass: 323 passed, 1 skipped, 1,332 assertions; zero static-analysis errors | 2026-09-03T18:49:00Z |

**Validated by:** Azure validation workflow
**Validation timestamp:** 2026-09-03T18:49:00Z

### Approved product-rules and generated-avatar release validation — 2026-09-03

| Check | Command Run | Result | Timestamp |
|-------|-------------|--------|-----------|
| Azure identity and target | `az account show`; `az vm show`; `az network public-ip show` | Pass: subscription `4212afa5-d96d-4717-a56d-1d34956599a6`; VM `mahadum` running in Canada Central; static IP `20.151.177.171` | 2026-09-03T19:43:05Z |
| Azure Policy / infrastructure | `az policy assignment list`; plan inspection | Pass: Security Center built-in assignment present; application-only existing-VM release creates no Azure resources and changes no RBAC | 2026-09-03T19:43:05Z |
| Runtime preflight | Azure VM Run Command; local `/up` health check | Pass: production environment; Apache, PHP-FPM, MySQL, queue worker, scheduler, and Laravel runtime active | 2026-09-03T19:43:05Z |
| Release integrity | clean Git worktree before validation; `git diff --check`; `bash -n deploy/*.sh` | Pass | 2026-09-03T19:43:05Z |
| Frontend | `npm test`; `npm run typecheck`; `npm run build` | Pass: 188 tests plus focused avatar asset test; production Vite build | 2026-09-03T19:43:05Z |
| Backend | `composer ci`; focused product-rule suites | Pass: Pint, PHPStan level 5, 329 passed / 1 skipped in full suite; focused practice/level/hero/tone suite passed | 2026-09-03T19:43:05Z |

**Validated by:** Azure validation workflow
**Validation timestamp:** 2026-09-03T19:43:05Z

### Role assignment verification

- **Status:** Not applicable and verified
- **Reason:** This deployment provisions no Bicep/Terraform resources, managed identities, or RBAC assignments. It deploys application software to an already-authorized VM in the confirmed subscription.

---

## 9. Files

| File | Purpose | Status |
|------|---------|--------|
| `.azure/deployment-plan.md` | Migration source of truth | Deployed for review |
| `deploy/deploy.sh` | Repeatable application deployment with release-snapshot support | Deployed |
| `deploy/bootstrap-ubuntu.sh` | Fresh Ubuntu stack, database, application, worker, scheduler, SSH hardening, and host firewall setup | Deployed |
| `deploy/apache/mahadum-ip.conf` | IP-only Apache virtual host for initial review | Deployed |
| `deploy/ssh/00-mahadum-hardening.conf` | Disable password, keyboard-interactive, and root SSH login | Deployed |
| `deploy/verify-review.sh` | Secret-safe authentication and fresh-data smoke test | Passed |
| `deploy/apache/mahadum-staging.conf` | Legacy/reference Apache virtual host | Existing; not used by this deployment |
| `deploy/systemd/mahadum-queue.service` | Existing queue worker | Existing |
| `deploy/env.staging.example` | Existing environment template | Existing; production/staging choice pending |

---

## 10. Remaining production-cutover work

1. HTTPS requires a confirmed domain pointed to this new VM; DNS and the old environment remain untouched.
2. Real email delivery and Monnify remain safely disabled until credentials are entered and tested through the new admin configuration screens.
3. Automated database/upload backups and restore drills should be configured before accepting production data.
4. The dependency audit reported known npm advisories; these should be triaged separately before production cutover without applying blind breaking upgrades.

---

## 12. Production deployment — 2026-09-03

### Video practice invitations release — 2026-09-07

- **Application release:** `3667752`, pushed to `origin/codex/beta-feedback-20260903` and deployed to https://mahadum360.com. Includes the existing course table of contents.
- **Validation:** three backend tests (19 assertions), three frontend tests, Pint, PHPStan and production build passed. Backend regression tests passed again immediately before deployment verification.
- **Result:** checksum-verified source delta, isolated production frontend build, integrity-checked backup, atomic PHP files and SPA entry point, PHP-FPM reload and queue restart signal completed. No migrations were required.
- **Verification:** current entry point `index-6179B2JL.js`; both editor bundles import `CourseContents-3LlwPj9A.js` with the table-of-contents label. `SlideDeck--b_d8PWk.js` includes Invite to Practice. Lesson URL, `/up`, `/api/v1/config` and inspected assets returned HTTP 200; Apache, PHP-FPM, MySQL, queue and cron active.
- **Backup:** `/var/backups/mahadum/practice-3667752-20260907T170040Z/source-assets.tar.gz`.
- **Limit:** authenticated production interaction and live invitation email delivery were not exercised. Notification tests used fakes.

### Course table-of-contents release — 2026-09-07

- **Application release:** `38a0030`, committed and pushed before deployment to https://mahadum360.com.
- **Result:** isolated production build, archive checksum and backup integrity passed; frontend published through atomic entry-point replacement. Old hashed assets retained; no maintenance mode, database or infrastructure changes.
- **Verification:** supplied lesson-editor route and `/up` returned 200. Served entry point is `index-drqyxvUZ.js`; both `CourseBuilderPage-XlhZB3Oc.js` and `LessonBuilderPage-Cw6WDViH.js` import `CourseContents-CdVIthvv.js`. Shared bundle contains contents headings, edit link and current-lesson marker. All five runtime services active. Authenticated editor interaction was not exercised during this deployment.
- **Backup:** `/var/backups/mahadum/contents-38a0030-20260907T162856Z/source-assets.tar.gz`.

### Referral visibility release — 2026-09-07

- **Application release:** `541320d`, deployed to https://mahadum360.com/referrals from the pushed beta-feedback branch.
- **Result:** isolated production build and PHP syntax checks passed; controllers and frontend published with rollback backups; PHP-FPM reloaded. No database migration or referral data changes.
- **Live data verification:** read-only controller checks matched all 31 referrals across 19 existing personal code owners against both dashboard and admin-profile totals. Required fields and pending status passed.
- **Public verification:** `/referrals`, `/up`, and `ReferralsPage-Dc3Ebjzc.js` returned 200; activity labels and pagination verified in the served bundle. All five runtime services active. Authenticated browser interaction was not exercised in this deployment.
- **Backup:** `/var/backups/mahadum/referrals-541320d-20260907T155654Z/source-assets.tar.gz`, archive integrity verified before publication.

### Worldwide calling-code release — 2026-09-07

- **Application release:** `553d9ba`, from `origin/codex/beta-feedback-20260903`, deployed to https://mahadum360.com.
- **Result:** checksum-verified frontend delta built successfully in an isolated VM directory; existing source/assets backed up; SPA entry point switched atomically with old hashed assets retained. No database migrations or maintenance downtime required.
- **Backup:** `/var/backups/mahadum/countries-553d9ba-20260907T140702Z/source-assets.tar.gz`, archive integrity verified before publication.
- **Verification:** registration and health returned HTTP 200; Apache, PHP-FPM, MySQL, queue and cron active. Live Chrome registration form showed all 245 country/territory options (Nigeria through Zimbabwe), Nigeria selected by default, and separate Canada/United States entries. No account was created.
- **Access:** deployment used authenticated Azure VM Run Command; no temporary SSH credentials were added.

### September feedback release — 2026-09-07

- **Application release:** `2338b90`, pushed to `origin/codex/beta-feedback-20260903` before deployment.
- **Target:** existing Azure VM `mahadum`, resource group `MAHADUM`, Canada Central; https://mahadum360.com.
- **Archive SHA-256:** `c81c64bf2a7b2c38813502e41c61c8c92332ea2eec132f0c6fea9ee44b78d77a`, verified after transfer.
- **Result:** Composer install, production frontend build, all three September 6–7 migrations, RBAC seeding, cache rebuild, queue restart and PHP-FPM reload succeeded. Maintenance mode cleared.
- **Database:** 52 users before and after. Two duplicate phone associations and one invalid phone were cleared by the documented normalization migration; originals are retained in protected database backups. Unique phone index verified; no duplicate groups remain. Free introductory lessons were pinned to published Igbo and Yoruba lessons. Heart cadence and quiz-XP columns verified.
- **Public checks:** root, `/up`, `/api/v1/config`, `/pricing`, referral route, and all four entry-point JS/CSS assets returned HTTP 200. Live LessonAccess source hash matches the committed local source.
- **Runtime:** Apache, PHP-FPM, MySQL, queue worker and cron active; `/etc/cron.d/mahadum-scheduler` runs every minute.
- **Backups:** `/var/backups/mahadum/sept7-2338b90-20260907T134644Z` contains integrity-checked source/assets and database archives, including a maintenance-time database snapshot. No database restore was needed or tested.
- **Deployment corrections:** two early attempts restored the previous source/assets before migrations. The successful invocation supplied `LOCK_FILE=/run/lock/mahadum-deploy.lock`, `COMPOSER_HOME=/root/.config/composer`, and `NPM_CONFIG_CACHE=/root/.npm` for Azure Run Command. Temporary SSH access was removed; the original authorized key remains.
- **Remaining operational work:** account cleanup and FluentCRM/SES configuration were not performed by this release. Live email and payment-provider flows were not exercised.

- **Release:** `7f8c1a017ded91cbfec10ff6890523d263229cb6`
- **Release branch:** `codex/referral-production-20260903`
- **Target:** existing VM `mahadum`, Canada Central, static IP `20.151.177.171`
- **Public endpoint:** `https://mahadum360.com`
- **Result:** deployment script completed; referral migrations ran; RBAC permissions reseeded; config/routes/views cached; queue restarted; maintenance mode cleared.
- **Verification:** `/up`, `/api/v1/config`, and `/r/LZBE7XN5` returned HTTP 200. The deployed `index-BPFt4oC7.js` contains `/r/:code`, and a real browser redirected the supplied link to `/register?ref=LZBE7XN5` with the referral banner visible.
- **Authenticated smoke test:** passed login, referral deep-link bundle validation, user/course queries, and English/Pidgin data checks.
- **Runtime:** Apache, PHP 8.3 FPM, MySQL, and `mahadum-queue` all active; scheduler cron present.
- **Rollback artifact:** pre-release source/build archive retained at `/var/backups/mahadum/pre-7f8c1a0.tar.gz` (application secrets and persistent uploads excluded).

### Beta-feedback release `63c0101`

- **Release branch:** `codex/beta-feedback-20260903`
- **Target:** existing VM `mahadum`, resource group `MAHADUM`, Canada Central.
- **Result:** snapshot release deployed; Composer install and Vite build passed; migration `2026_09_03_000004` ran; RBAC reseeded; caches rebuilt; queue restarted; maintenance cleared.
- **Verification:** `https://mahadum360.com`, `/up`, `/api/v1/config`, `/pricing`, `/r/LZBE7XN5`, and a deployed avatar asset returned HTTP 200. Browser smoke redirected the referral to `/register?ref=LZBE7XN5`. Runtime services were active and paid personal plans reported zero enabled offline-download flags.
- **Rollback artifacts:** source snapshot and two database dumps retained under `/var/backups/mahadum/beta-63c0101-20260903T185453Z`.
- **Live role verification:** VM system identity has no Azure role assignments. This is expected: the application uses local MySQL/disk and provisions no Azure data-plane resources or managed-identity dependencies.

### Approved product-rules and generated-avatar release `27f852d`

- **Release branch:** `codex/beta-feedback-20260903`
- **Target:** existing VM `mahadum`, resource group `MAHADUM`, Canada Central, static IP `20.151.177.171`.
- **Result:** immutable snapshot deployed; Composer install and Vite production build passed; migration `2026_09_03_000005` ran; RBAC reseeded; caches rebuilt; queue restarted; maintenance cleared.
- **Features:** 12-hour zero-heart practice mode, lifetime-XP levels, daily Family Hero awards, privacy-safe tone-practice invitations, free core-learning regression guard, and normalized generated avatar artwork.
- **Verification:** application root, `/up`, `/api/v1/config`, `/pricing`, `/r/LZBE7XN5`, and `avatar-cultural-24.png` returned HTTP 200. Browser smoke redirected the referral to `/register?ref=LZBE7XN5`. Tone-invitation routes, Family Hero schedule, migration, avatar source/assets, and all runtime services were verified on the VM.
- **Rollback artifacts:** source and database backups passed archive integrity checks at `/var/backups/mahadum/product-rules-27f852d-20260903T194448Z`.
- **Operational correction:** the immutable archive staging directory initially copied a `0700` mode to the application root. Apache returned 403 until the root/public traversal permissions were restored to `0755`; local SNI health and all public checks then passed.

---

## 11. Functional Verification

- **Status:** Verified locally on 2026-08-20
- **Frontend:** 29 files / 179 tests passed; production Vite build passed
- **Backend:** 307 tests discovered, 306 passed, 1 skipped; 1,254 assertions
- **Static checks:** Pint passed; PHPStan level 5 passed with zero errors
- **Deployment assets:** Bash syntax and `git diff --check` passed
- **Release snapshot:** `mahadum-release-20260820-000442.tar.gz`, 20,219,677 bytes, SHA-256 `729DB2349914E16FAF2BBD2EA6D7EE79E66D3CF1F1C11A5EE42C982CC360E245`
- **Review deployment:** Live at `http://20.151.177.171`; fresh database only, with randomized credentials stored mode `0600` on the VM
- **Release correction:** The original archive's over-broad `vendor` exclusion omitted tracked Laravel mail views. Those tracked files were added to the new VM snapshot, the exclusion was root-anchored, and the database was recreated before the successful final seed.
