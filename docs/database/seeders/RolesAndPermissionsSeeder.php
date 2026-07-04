<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mahadum.360 — roles & granular permissions.
 *
 * Permissions are grouped by the *aspect of the platform* they govern (content,
 * billing, schools, finance/payouts, referrals, users, analytics, system).
 * Each of the 7 roles is granted only the slices it needs. super_admin gets
 * everything (also backed by a Gate::before bypass — see AuthServiceProvider).
 *
 * Roles are GLOBAL capabilities ("what can this role do") — spatie teams are
 * off. Tenant scope is enforced separately (IdentifyTenant membership check +
 * BelongsToTenant query scope + policy sameTenant/ownership checks), so a grant
 * does not need to carry an organization id. Assignment is simply:
 *
 *   $user->assignRole('teacher');
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Canonical permission catalogue, grouped by admin aspect.
     *
     * @var array<string, array<int, string>>
     */
    private array $groups = [
        // ── Content / CMS (content_owner) ──────────────────────────────────
        'content' => [
            'content.languages.manage',
            'content.courses.view',
            'content.courses.create',
            'content.courses.update',
            'content.courses.delete',
            'content.courses.publish',
            'content.lessons.manage',
            'content.quizzes.manage',
            'content.media.upload',
            'content.cultural.manage',
        ],

        // ── Learning oversight (teacher / parent / supervisor) ─────────────
        'learning' => [
            'learning.enrollments.manage',
            'learning.progress.view',
            'learning.submissions.review',
        ],

        // ── Gamification config ────────────────────────────────────────────
        'gamification' => [
            'gamification.badges.manage',
            'gamification.leagues.manage',
        ],

        // ── Family economy (parent) ────────────────────────────────────────
        'family' => [
            'family.manage',
            'family.wallet.view',
            'family.wallet.fund',
            'family.chores.manage',
            'family.chores.review',
            'family.reviews.handle',
        ],

        // ── Billing & subscriptions ────────────────────────────────────────
        'billing' => [
            'billing.plans.manage',
            'billing.subscriptions.view',
            'billing.subscriptions.manage',
            'billing.telco.view',
            'billing.telco.manage',
            'billing.databundles.manage',
            'billing.invoices.view',
            'billing.invoices.manage',
            'billing.health.view',
            'billing.webhooks.view',
        ],

        // ── Money / ledgers (settlement-grade) ─────────────────────────────
        'finance' => [
            'finance.wallets.view',
            'finance.wallets.adjust',
            'finance.ledger.view',
            'commissions.view',
            'commissions.manage',
            'payouts.view',
            'payouts.request',
            'payouts.approve',
            'settlements.view',
            'settlements.manage',
        ],

        // ── Referrals & promos ─────────────────────────────────────────────
        'referrals' => [
            'referrals.view',
            'referrals.manage',
            'referrals.fraud.review',
            'promocodes.manage',
        ],

        // ── School operations (school_admin / teacher / supervisor) ────────
        'schools' => [
            'schools.dashboard.view',
            'schools.roster.view',
            'schools.roster.import',
            'schools.classes.view',
            'schools.classes.manage',
            'schools.seats.view',
            'schools.seats.purchase',
            'schools.assignments.create',
            'schools.assignments.review',
            'schools.analytics.view',
        ],

        // ── Organizations / tenancy ────────────────────────────────────────
        'organizations' => [
            'organizations.view',
            'organizations.manage',
            'organizations.activate',
        ],

        // ── Users & access control ─────────────────────────────────────────
        'users' => [
            'users.view',
            'users.manage',
            'roles.view',
            'roles.assign',
        ],

        // ── Analytics ──────────────────────────────────────────────────────
        'analytics' => [
            'analytics.platform.view',
            'analytics.lesson.view',
            'analytics.class.view',
        ],

        // ── System ─────────────────────────────────────────────────────────
        'system' => [
            'audit.view',
            'support.handle',
            'system.settings.manage',
        ],
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        // 1) Create every permission.
        foreach (Arr::flatten($this->groups) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // 2) Create roles and attach their permission slices.
        foreach ($this->rolePermissionMap() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }

        $registrar->forgetCachedPermissions();
    }

    /**
     * Role → permissions. Use whole groups where a role owns an aspect,
     * cherry-pick where it owns only part of one.
     *
     * @return array<string, array<int, string>>
     */
    private function rolePermissionMap(): array
    {
        $g = fn (string ...$keys) => Arr::flatten(Arr::only($this->groups, $keys));

        return [
            // Everything (also wired via Gate::before — see docs).
            'super_admin' => Arr::flatten($this->groups),

            // Global curriculum authoring + lesson analytics.
            'content_owner' => array_merge(
                $g('content', 'gamification'),
                ['learning.progress.view', 'analytics.lesson.view']
            ),

            // School back-office: roster, classes, seats, invoices, org users.
            'school_admin' => array_merge(
                $g('schools'),
                [
                    'organizations.view',
                    'billing.subscriptions.view',
                    'billing.invoices.view',
                    'users.view', 'users.manage', 'roles.view', 'roles.assign',
                    'learning.progress.view',
                    'referrals.view',
                    'analytics.class.view',
                ]
            ),

            // Classroom: own classes, assignments, learner progress, commissions.
            'teacher' => [
                'schools.classes.view',
                'schools.assignments.create',
                'schools.assignments.review',
                'schools.analytics.view',
                'analytics.class.view',
                'learning.progress.view',
                'learning.submissions.review',
                'referrals.view',
                'commissions.view',
                'payouts.view',
                'payouts.request',
            ],

            // Read-only oversight across the org.
            'supervisor' => [
                'schools.dashboard.view',
                'schools.roster.view',
                'schools.classes.view',
                'schools.analytics.view',
                'analytics.class.view',
                'learning.progress.view',
                'organizations.view',
            ],

            // Family account holder: wallet, chores, reviews, own billing.
            'parent' => array_merge(
                $g('family'),
                [
                    'learning.enrollments.manage',
                    'learning.progress.view',
                    'learning.submissions.review',
                    'billing.subscriptions.view',
                    'billing.subscriptions.manage',
                    'billing.telco.view',
                    'billing.telco.manage',
                    'billing.databundles.manage',
                    'referrals.view',
                    'payouts.view',
                    'payouts.request',
                ]
            ),

            // Learner: self-access is governed by policies, not global permissions.
            'student' => [],
        ];
    }
}
