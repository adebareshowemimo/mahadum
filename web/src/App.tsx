import { Suspense, lazy } from 'react'
import { Navigate, Outlet, Route, Routes } from 'react-router-dom'
import { AdminRoute, GuestRoute, ProtectedRoute, RoleRoute, TeacherRoute } from '@/components/auth/ProtectedRoute'
import { AdminLayout } from '@/components/admin'
import { AppLayout } from '@/components/layout/AppLayout'
import { PaywallGate } from '@/components/billing/PaywallGate'
import { Spinner } from '@/components/ui/Spinner'
// ComingSoon stays static: it backs every not-yet-built nav destination in the
// .map() below, so lazy-loading it would mean one chunk request per placeholder.
import { ComingSoon } from '@/pages/ComingSoon'
import { allNavItems } from '@/lib/nav/navigation'

// Every real page is route-split. All page components are named exports, hence
// the `.then(m => ({ default: m.X }))` shim React.lazy requires.
const AssignmentsPage = lazy(() => import('@/pages/AssignmentsPage').then((m) => ({ default: m.AssignmentsPage })))
const BillingPage = lazy(() => import('@/pages/BillingPage').then((m) => ({ default: m.BillingPage })))
const ClassesPage = lazy(() => import('@/pages/ClassesPage').then((m) => ({ default: m.ClassesPage })))
const TeacherProfilePage = lazy(() => import('@/pages/TeacherProfilePage').then((m) => ({ default: m.TeacherProfilePage })))
const ComponentsPage = lazy(() => import('@/pages/ComponentsPage').then((m) => ({ default: m.ComponentsPage })))
const CoursesPage = lazy(() => import('@/pages/content/CoursesPage').then((m) => ({ default: m.CoursesPage })))
const CourseBuilderPage = lazy(() => import('@/pages/content/CourseBuilderPage').then((m) => ({ default: m.CourseBuilderPage })))
const CoursePreviewPage = lazy(() => import('@/pages/content/CoursePreviewPage').then((m) => ({ default: m.CoursePreviewPage })))
const LessonBuilderPage = lazy(() => import('@/pages/content/LessonBuilderPage').then((m) => ({ default: m.LessonBuilderPage })))
const MediaPage = lazy(() => import('@/pages/content/MediaPage').then((m) => ({ default: m.MediaPage })))
const EarningsPage = lazy(() => import('@/pages/EarningsPage').then((m) => ({ default: m.EarningsPage })))
const DashboardPage = lazy(() => import('@/pages/DashboardPage').then((m) => ({ default: m.DashboardPage })))
const FamilyPage = lazy(() => import('@/pages/FamilyPage').then((m) => ({ default: m.FamilyPage })))
const ForgotPasswordPage = lazy(() => import('@/pages/ForgotPasswordPage').then((m) => ({ default: m.ForgotPasswordPage })))
const AchievementsPage = lazy(() => import('@/pages/AchievementsPage').then((m) => ({ default: m.AchievementsPage })))
const AdminOverviewPage = lazy(() => import('@/pages/AdminOverviewPage').then((m) => ({ default: m.AdminOverviewPage })))
const LandingPage = lazy(() => import('@/pages/LandingPage').then((m) => ({ default: m.LandingPage })))
const LandingV1Page = lazy(() => import('@/pages/LandingVariantsPage').then((m) => ({ default: m.LandingV1Page })))
const LandingV2Page = lazy(() => import('@/pages/LandingVariantsPage').then((m) => ({ default: m.LandingV2Page })))
const LandingV3Page = lazy(() => import('@/pages/LandingVariantsPage').then((m) => ({ default: m.LandingV3Page })))
const LandingV4Page = lazy(() => import('@/pages/LandingExtendedVariantsPage').then((m) => ({ default: m.LandingV4Page })))
const LandingV5Page = lazy(() => import('@/pages/LandingExtendedVariantsPage').then((m) => ({ default: m.LandingV5Page })))
const PricingPage = lazy(() => import('@/pages/PricingPage').then((m) => ({ default: m.PricingPage })))
const AdminCoursesPage = lazy(() => import('@/pages/AdminCoursesPage').then((m) => ({ default: m.AdminCoursesPage })))
const AdminIncomePage = lazy(() => import('@/pages/AdminIncomePage').then((m) => ({ default: m.AdminIncomePage })))
const ReportsPage = lazy(() => import('@/pages/ReportsPage').then((m) => ({ default: m.ReportsPage })))
const GrowthReportPage = lazy(() => import('@/pages/GrowthReportPage').then((m) => ({ default: m.GrowthReportPage })))
const SubscriptionsReportPage = lazy(() => import('@/pages/SubscriptionsReportPage').then((m) => ({ default: m.SubscriptionsReportPage })))
const ReferralsReportPage = lazy(() => import('@/pages/ReferralsReportPage').then((m) => ({ default: m.ReferralsReportPage })))
const OrgActivityReportPage = lazy(() => import('@/pages/OrgActivityReportPage').then((m) => ({ default: m.OrgActivityReportPage })))
const RenewalsReportPage = lazy(() => import('@/pages/RenewalsReportPage').then((m) => ({ default: m.RenewalsReportPage })))
const EmailCampaignsPage = lazy(() => import('@/pages/EmailCampaignsPage').then((m) => ({ default: m.EmailCampaignsPage })))
const CampaignDetailPage = lazy(() => import('@/pages/CampaignDetailPage').then((m) => ({ default: m.CampaignDetailPage })))
const ContactListsPage = lazy(() => import('@/pages/ContactListsPage').then((m) => ({ default: m.ContactListsPage })))
const EmailLogPage = lazy(() => import('@/pages/EmailLogPage').then((m) => ({ default: m.EmailLogPage })))
const EmailTemplatesPage = lazy(() => import('@/pages/EmailTemplatesPage').then((m) => ({ default: m.EmailTemplatesPage })))
const AuditLogPage = lazy(() => import('@/pages/AuditLogPage').then((m) => ({ default: m.AuditLogPage })))
const FraudReviewPage = lazy(() => import('@/pages/FraudReviewPage').then((m) => ({ default: m.FraudReviewPage })))
const GatewaysPage = lazy(() => import('@/pages/GatewaysPage').then((m) => ({ default: m.GatewaysPage })))
const LanguagesPage = lazy(() => import('@/pages/LanguagesPage').then((m) => ({ default: m.LanguagesPage })))
const PlansPage = lazy(() => import('@/pages/PlansPage').then((m) => ({ default: m.PlansPage })))
const SettingsPage = lazy(() => import('@/pages/SettingsPage').then((m) => ({ default: m.SettingsPage })))
const SupportPage = lazy(() => import('@/pages/SupportPage').then((m) => ({ default: m.SupportPage })))
const ContactSupportPage = lazy(() => import('@/pages/ContactSupportPage').then((m) => ({ default: m.ContactSupportPage })))
const OrganizationsPage = lazy(() => import('@/pages/OrganizationsPage').then((m) => ({ default: m.OrganizationsPage })))
const OrganizationCreatePage = lazy(() => import('@/pages/OrganizationCreatePage').then((m) => ({ default: m.OrganizationCreatePage })))
const OrganizationDetailPage = lazy(() => import('@/pages/OrganizationDetailPage').then((m) => ({ default: m.OrganizationDetailPage })))
const PayoutsPage = lazy(() => import('@/pages/PayoutsPage').then((m) => ({ default: m.PayoutsPage })))
const PromoCodesPage = lazy(() => import('@/pages/PromoCodesPage').then((m) => ({ default: m.PromoCodesPage })))
const SchoolLeadsPage = lazy(() => import('@/pages/SchoolLeadsPage').then((m) => ({ default: m.SchoolLeadsPage })))
const RolesMatrixPage = lazy(() => import('@/pages/RolesMatrixPage').then((m) => ({ default: m.RolesMatrixPage })))
const SettlementsPage = lazy(() => import('@/pages/SettlementsPage').then((m) => ({ default: m.SettlementsPage })))
const UsersPage = lazy(() => import('@/pages/UsersPage').then((m) => ({ default: m.UsersPage })))
const LeaderboardPage = lazy(() => import('@/pages/LeaderboardPage').then((m) => ({ default: m.LeaderboardPage })))
const LearnPage = lazy(() => import('@/pages/LearnPage').then((m) => ({ default: m.LearnPage })))
const LessonPlayerPage = lazy(() => import('@/pages/LessonPlayerPage').then((m) => ({ default: m.LessonPlayerPage })))
const InvoicesPage = lazy(() => import('@/pages/InvoicesPage').then((m) => ({ default: m.InvoicesPage })))
const ReferralsPage = lazy(() => import('@/pages/ReferralsPage').then((m) => ({ default: m.ReferralsPage })))
const ReviewsPage = lazy(() => import('@/pages/ReviewsPage').then((m) => ({ default: m.ReviewsPage })))
const RosterPage = lazy(() => import('@/pages/RosterPage').then((m) => ({ default: m.RosterPage })))
const SchoolDashboardPage = lazy(() => import('@/pages/SchoolDashboardPage').then((m) => ({ default: m.SchoolDashboardPage })))
const SchoolReferralsPage = lazy(() => import('@/pages/SchoolReferralsPage').then((m) => ({ default: m.SchoolReferralsPage })))
const SeatsPage = lazy(() => import('@/pages/SeatsPage').then((m) => ({ default: m.SeatsPage })))
const CompetitionsPage = lazy(() => import('@/pages/CompetitionsPage').then((m) => ({ default: m.CompetitionsPage })))
const CompetitionDetailPage = lazy(() => import('@/pages/CompetitionDetailPage').then((m) => ({ default: m.CompetitionDetailPage })))
const CompetitionsAdminPage = lazy(() => import('@/pages/CompetitionsAdminPage').then((m) => ({ default: m.CompetitionsAdminPage })))
const WalletPage = lazy(() => import('@/pages/WalletPage').then((m) => ({ default: m.WalletPage })))
const LoginPage = lazy(() => import('@/pages/LoginPage').then((m) => ({ default: m.LoginPage })))
const RegisterPage = lazy(() => import('@/pages/RegisterPage').then((m) => ({ default: m.RegisterPage })))
const ResetPasswordPage = lazy(() => import('@/pages/ResetPasswordPage').then((m) => ({ default: m.ResetPasswordPage })))

// Full-viewport fallback for chunks loaded before any shell exists.
function RouteFallback() {
  return (
    <div className="grid min-h-dvh place-items-center" aria-busy="true">
      <Spinner className="size-8 opacity-60" />
    </div>
  )
}

// Nested boundary used *inside* AppLayout, so a page chunk loading swaps only the
// content area — the sidebar/header stay mounted and nothing in the shell shifts.
function ShellSuspense() {
  return (
    <Suspense
      fallback={
        <div className="grid min-h-[60vh] place-items-center" aria-busy="true">
          <Spinner className="size-8 opacity-60" />
        </div>
      }
    >
      <Outlet />
    </Suspense>
  )
}

// Destinations that already have real screens; everything else in the nav
// resolves to a ComingSoon placeholder so links never dead-end.
const REAL_PAGES = new Set([
  '/home',
  '/components',
  '/family',
  '/wallet',
  '/reviews',
  '/learn',
  '/achievements',
  '/leaderboard',
  '/referrals',
  '/school',
  '/roster',
  '/seats',
  '/invoices',
  '/school/referrals',
  '/competitions',
  '/competitions/manage',
  '/admin',
  '/admin/settlements',
  '/admin/payouts',
  '/admin/reports',
  '/admin/reports/income',
  '/admin/reports/growth',
  '/admin/reports/subscriptions',
  '/admin/reports/referrals',
  '/admin/reports/org-activity',
  '/admin/reports/renewals',
  '/admin/emails',
  '/admin/emails/contacts',
  '/admin/emails/log',
  '/admin/emails/templates',
  '/admin/settings/gateways',
  '/admin/audit',
  '/admin/settings',
  '/admin/fraud',
  '/admin/support',
  '/support',
  '/admin/languages',
  '/admin/plans',
  '/admin/orgs',
  '/admin/orgs/new',
  '/admin/leads',
  '/admin/users',
  '/admin/roles',
  '/admin/courses',
  '/admin/promos',
  '/billing',
  '/classes',
  '/assignments',
  '/earnings',
  '/teacher/profile',
  '/courses',
  '/media',
])

export function App() {
  return (
    <Suspense fallback={<RouteFallback />}>
      <Routes>
      {/* Public marketing (redirects signed-in users away). */}
      <Route path="/" element={<LandingPage />} />
      <Route path="/v1" element={<LandingV1Page />} />
      <Route path="/v2" element={<LandingV2Page />} />
      <Route path="/v3" element={<LandingV3Page />} />
      <Route path="/v4" element={<LandingV4Page />} />
      <Route path="/v5" element={<LandingV5Page />} />
      <Route path="/pricing" element={<PricingPage />} />

      {/* Public auth screens — redirect away if already signed in. */}
      <Route element={<GuestRoute />}>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route path="/forgot-password" element={<ForgotPasswordPage />} />
        <Route path="/reset-password" element={<ResetPasswordPage />} />
      </Route>

      {/* Authenticated app — everything renders inside the role-aware shell. */}
      <Route element={<ProtectedRoute />}>
        {/* Chrome-free, immersive screens — rendered outside the authoring shell. */}
        <Route path="/learn/lessons/:lessonId" element={<LessonPlayerPage />} />
        <Route path="/courses/:courseId/preview" element={<CoursePreviewPage />} />
        <Route path="/courses/:courseId/levels/:levelId/preview" element={<CoursePreviewPage />} />

        <Route element={<AppLayout />}>
          <Route element={<ShellSuspense />}>
          <Route path="/home" element={<DashboardPage />} />
          <Route path="/learn" element={<LearnPage />} />
          <Route path="/achievements" element={<AchievementsPage />} />
          <Route path="/leaderboard" element={<LeaderboardPage />} />
          <Route
            path="/family"
            element={
              <PaywallGate feature="family_dashboard">
                <FamilyPage />
              </PaywallGate>
            }
          />
          <Route
            path="/wallet"
            element={
              <PaywallGate feature="family_dashboard">
                <WalletPage />
              </PaywallGate>
            }
          />
          <Route
            path="/reviews"
            element={
              <PaywallGate feature="family_dashboard">
                <ReviewsPage />
              </PaywallGate>
            }
          />
          <Route path="/billing" element={<BillingPage />} />
          <Route element={<TeacherRoute />}>
            <Route path="/classes" element={<ClassesPage />} />
            <Route path="/assignments" element={<AssignmentsPage />} />
            <Route path="/earnings" element={<EarningsPage />} />
            <Route path="/teacher/profile" element={<TeacherProfilePage />} />
          </Route>
          <Route path="/courses" element={<CoursesPage />} />
          <Route path="/courses/:courseId" element={<CourseBuilderPage />} />
          <Route path="/courses/:courseId/lessons/:lessonId" element={<LessonBuilderPage />} />
          <Route path="/media" element={<MediaPage />} />
          <Route path="/referrals" element={<ReferralsPage />} />
          <Route path="/school" element={<SchoolDashboardPage />} />
          <Route path="/roster" element={<RosterPage />} />
          <Route path="/seats" element={<SeatsPage />} />
          <Route path="/invoices" element={<InvoicesPage />} />
          <Route path="/school/referrals" element={<SchoolReferralsPage />} />
          {/* Language & Culture competition — browse + vote open to all; organiser
              console gated to super_admin + content_owner. */}
          <Route path="/competitions" element={<CompetitionsPage />} />
          <Route element={<RoleRoute roles={['super_admin', 'content_owner']} />}>
            <Route path="/competitions/manage" element={<CompetitionsAdminPage />} />
          </Route>
          <Route path="/competitions/:competitionId" element={<CompetitionDetailPage />} />
          {/* Global-admin portal — super_admin only; grouped sub-nav via AdminLayout. */}
          <Route element={<AdminRoute />}>
            <Route element={<AdminLayout />}>
            <Route path="/admin" element={<AdminOverviewPage />} />
            <Route path="/admin/settlements" element={<SettlementsPage />} />
            <Route path="/admin/payouts" element={<PayoutsPage />} />
            <Route path="/admin/reports" element={<ReportsPage />} />
            <Route path="/admin/reports/income" element={<AdminIncomePage />} />
            <Route path="/admin/reports/growth" element={<GrowthReportPage />} />
            <Route path="/admin/reports/subscriptions" element={<SubscriptionsReportPage />} />
            <Route path="/admin/reports/referrals" element={<ReferralsReportPage />} />
            <Route path="/admin/reports/org-activity" element={<OrgActivityReportPage />} />
            <Route path="/admin/reports/renewals" element={<RenewalsReportPage />} />
            <Route path="/admin/emails" element={<EmailCampaignsPage />} />
            <Route path="/admin/emails/contacts" element={<ContactListsPage />} />
            <Route path="/admin/emails/log" element={<EmailLogPage />} />
            <Route path="/admin/emails/templates" element={<EmailTemplatesPage />} />
            <Route path="/admin/emails/:campaignId" element={<CampaignDetailPage />} />
            <Route path="/admin/settings/gateways" element={<GatewaysPage />} />
            <Route path="/admin/audit" element={<AuditLogPage />} />
            <Route path="/admin/settings" element={<SettingsPage />} />
            <Route path="/admin/fraud" element={<FraudReviewPage />} />
            <Route path="/admin/support" element={<SupportPage />} />
            <Route path="/admin/languages" element={<LanguagesPage />} />
            <Route path="/admin/plans" element={<PlansPage />} />
            <Route path="/admin/orgs" element={<OrganizationsPage />} />
            <Route path="/admin/orgs/new" element={<OrganizationCreatePage />} />
            <Route path="/admin/orgs/:orgId" element={<OrganizationDetailPage />} />
            <Route path="/admin/leads" element={<SchoolLeadsPage />} />
            <Route path="/admin/users" element={<UsersPage />} />
            <Route path="/admin/roles" element={<RolesMatrixPage />} />
            <Route path="/admin/courses" element={<AdminCoursesPage />} />
            <Route path="/admin/promos" element={<PromoCodesPage />} />
            </Route>
          </Route>
          <Route path="/support" element={<ContactSupportPage />} />
          <Route path="/components" element={<ComponentsPage />} />
          {allNavItems()
            .filter((item) => !REAL_PAGES.has(item.to))
            .map((item) => (
              <Route
                key={item.to}
                path={item.to}
                element={<ComingSoon title={item.label} icon={item.icon} />}
              />
            ))}
          </Route>
        </Route>
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Suspense>
  )
}
