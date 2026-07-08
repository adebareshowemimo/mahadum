import { Navigate, Route, Routes } from 'react-router-dom'
import { AdminRoute, GuestRoute, ProtectedRoute, RoleRoute, TeacherRoute } from '@/components/auth/ProtectedRoute'
import { AdminLayout } from '@/components/admin'
import { AppLayout } from '@/components/layout/AppLayout'
import { PaywallGate } from '@/components/billing/PaywallGate'
import { AssignmentsPage } from '@/pages/AssignmentsPage'
import { BillingPage } from '@/pages/BillingPage'
import { ClassesPage } from '@/pages/ClassesPage'
import { ComponentsPage } from '@/pages/ComponentsPage'
import { CoursesPage } from '@/pages/content/CoursesPage'
import { CourseBuilderPage } from '@/pages/content/CourseBuilderPage'
import { CoursePreviewPage } from '@/pages/content/CoursePreviewPage'
import { LessonBuilderPage } from '@/pages/content/LessonBuilderPage'
import { MediaPage } from '@/pages/content/MediaPage'
import { EarningsPage } from '@/pages/EarningsPage'
import { ComingSoon } from '@/pages/ComingSoon'
import { DashboardPage } from '@/pages/DashboardPage'
import { FamilyPage } from '@/pages/FamilyPage'
import { ForgotPasswordPage } from '@/pages/ForgotPasswordPage'
import { AchievementsPage } from '@/pages/AchievementsPage'
import { AdminOverviewPage } from '@/pages/AdminOverviewPage'
import { LandingPage } from '@/pages/LandingPage'
import { PricingPage } from '@/pages/PricingPage'
import { AdminCoursesPage } from '@/pages/AdminCoursesPage'
import { AdminIncomePage } from '@/pages/AdminIncomePage'
import { ReportsPage } from '@/pages/ReportsPage'
import { GrowthReportPage } from '@/pages/GrowthReportPage'
import { SubscriptionsReportPage } from '@/pages/SubscriptionsReportPage'
import { ReferralsReportPage } from '@/pages/ReferralsReportPage'
import { OrgActivityReportPage } from '@/pages/OrgActivityReportPage'
import { RenewalsReportPage } from '@/pages/RenewalsReportPage'
import { EmailCampaignsPage } from '@/pages/EmailCampaignsPage'
import { CampaignDetailPage } from '@/pages/CampaignDetailPage'
import { ContactListsPage } from '@/pages/ContactListsPage'
import { EmailLogPage } from '@/pages/EmailLogPage'
import { EmailTemplatesPage } from '@/pages/EmailTemplatesPage'
import { AuditLogPage } from '@/pages/AuditLogPage'
import { FraudReviewPage } from '@/pages/FraudReviewPage'
import { GatewaysPage } from '@/pages/GatewaysPage'
import { LanguagesPage } from '@/pages/LanguagesPage'
import { PlansPage } from '@/pages/PlansPage'
import { SettingsPage } from '@/pages/SettingsPage'
import { SupportPage } from '@/pages/SupportPage'
import { ContactSupportPage } from '@/pages/ContactSupportPage'
import { OrganizationsPage } from '@/pages/OrganizationsPage'
import { OrganizationCreatePage } from '@/pages/OrganizationCreatePage'
import { OrganizationDetailPage } from '@/pages/OrganizationDetailPage'
import { PayoutsPage } from '@/pages/PayoutsPage'
import { PromoCodesPage } from '@/pages/PromoCodesPage'
import { RolesMatrixPage } from '@/pages/RolesMatrixPage'
import { SettlementsPage } from '@/pages/SettlementsPage'
import { UsersPage } from '@/pages/UsersPage'
import { LeaderboardPage } from '@/pages/LeaderboardPage'
import { LearnPage } from '@/pages/LearnPage'
import { LessonPlayerPage } from '@/pages/LessonPlayerPage'
import { InvoicesPage } from '@/pages/InvoicesPage'
import { ReferralsPage } from '@/pages/ReferralsPage'
import { ReviewsPage } from '@/pages/ReviewsPage'
import { RosterPage } from '@/pages/RosterPage'
import { SchoolDashboardPage } from '@/pages/SchoolDashboardPage'
import { SchoolReferralsPage } from '@/pages/SchoolReferralsPage'
import { SeatsPage } from '@/pages/SeatsPage'
import { CompetitionsPage } from '@/pages/CompetitionsPage'
import { CompetitionDetailPage } from '@/pages/CompetitionDetailPage'
import { CompetitionsAdminPage } from '@/pages/CompetitionsAdminPage'
import { WalletPage } from '@/pages/WalletPage'
import { LoginPage } from '@/pages/LoginPage'
import { RegisterPage } from '@/pages/RegisterPage'
import { ResetPasswordPage } from '@/pages/ResetPasswordPage'
import { allNavItems } from '@/lib/nav/navigation'

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
  '/admin/users',
  '/admin/roles',
  '/admin/courses',
  '/admin/promos',
  '/billing',
  '/classes',
  '/assignments',
  '/earnings',
  '/courses',
  '/media',
])

export function App() {
  return (
    <Routes>
      {/* Public marketing (redirects signed-in users away). */}
      <Route path="/" element={<LandingPage />} />
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

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
