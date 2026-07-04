import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  adminApi,
  type AdminOrgQuery,
  type AdminPayoutsQuery,
  type AdminTicketsQuery,
  type AdminUsersQuery,
  type UpdateTicketInput,
  type AssignRoleInput,
  type AuditLogQuery,
  type CreateCampaignInput,
  type CreateOrgInput,
  type CreatePromoInput,
  type EmailLogQuery,
  type InviteOrgAdminInput,
  type IncomeReportQuery,
  type OrgStatus,
  type CreatePlanInput,
  type SettingValue,
  type UpdateOrgInput,
  type UpdatePlanInput,
  type UserStatus,
} from '@/lib/api'

export const adminKeys = {
  metrics: ['admin-metrics'] as const,
  billingHealth: ['admin-billing-health'] as const,
  settlements: ['admin-settlements'] as const,
  organizations: ['admin-organizations'] as const,
  organizationsList: (params: AdminOrgQuery) => ['admin-organizations', params] as const,
  organization: (id: number) => ['admin-organization', id] as const,
  users: (params: AdminUsersQuery) => ['admin-users', params] as const,
  roles: ['admin-roles'] as const,
  payouts: (params: AdminPayoutsQuery) => ['admin-payouts', params] as const,
  incomeReport: (params: IncomeReportQuery) => ['admin-income', params] as const,
  growthReport: (params: IncomeReportQuery) => ['admin-growth', params] as const,
  subscriptionsReport: (params: IncomeReportQuery) => ['admin-subscriptions', params] as const,
  referralsReport: (params: IncomeReportQuery) => ['admin-referrals-report', params] as const,
  orgActivityReport: (params: IncomeReportQuery) => ['admin-org-activity', params] as const,
  renewalsReport: (params: IncomeReportQuery) => ['admin-renewals', params] as const,
  emailCampaigns: ['admin-email-campaigns'] as const,
  emailCampaign: (id: number) => ['admin-email-campaign', id] as const,
  contactLists: ['admin-contact-lists'] as const,
  contactList: (id: number, page: number) => ['admin-contact-list', id, page] as const,
  emailLog: (params: EmailLogQuery) => ['admin-email-log', params] as const,
  gateways: ['admin-gateways'] as const,
  audit: (params: AuditLogQuery) => ['admin-audit', params] as const,
  support: (params: AdminTicketsQuery) => ['admin-support', params] as const,
  settings: ['admin-settings'] as const,
  flaggedReferrals: ['admin-flagged-referrals'] as const,
  languages: ['admin-languages'] as const,
  plans: ['admin-plans'] as const,
}

export function useAdminMetrics() {
  return useQuery({ queryKey: adminKeys.metrics, queryFn: adminApi.metrics })
}

export function useBillingHealth() {
  return useQuery({ queryKey: adminKeys.billingHealth, queryFn: adminApi.billingHealth })
}

export function useSettlements() {
  return useQuery({ queryKey: adminKeys.settlements, queryFn: adminApi.settlements })
}

export function useAdminOrganizations(params: AdminOrgQuery = {}) {
  return useQuery({
    queryKey: adminKeys.organizationsList(params),
    queryFn: () => adminApi.organizations(params),
    placeholderData: (prev) => prev,
  })
}

export function useAdminOrganization(orgId: number) {
  return useQuery({
    queryKey: adminKeys.organization(orgId),
    queryFn: () => adminApi.organization(orgId),
    enabled: Number.isFinite(orgId),
  })
}

export function useActivateOrg() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (orgId: number) => adminApi.activateOrg(orgId),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: adminKeys.organizations })
      void qc.invalidateQueries({ queryKey: adminKeys.metrics })
    },
  })
}

export function useSetOrgStatus() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ orgId, status }: { orgId: number; status: OrgStatus }) =>
      adminApi.setOrgStatus(orgId, status),
    onSuccess: (_res, { orgId }) => {
      void qc.invalidateQueries({ queryKey: adminKeys.organizations })
      void qc.invalidateQueries({ queryKey: adminKeys.organization(orgId) })
      void qc.invalidateQueries({ queryKey: adminKeys.metrics })
    },
  })
}

export function useCreateOrg() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateOrgInput) => adminApi.createOrg(input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: adminKeys.organizations })
      void qc.invalidateQueries({ queryKey: adminKeys.metrics })
    },
  })
}

export function useUpdateOrg() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ orgId, input }: { orgId: number; input: UpdateOrgInput }) =>
      adminApi.updateOrg(orgId, input),
    onSuccess: (_res, { orgId }) => {
      void qc.invalidateQueries({ queryKey: adminKeys.organizations })
      void qc.invalidateQueries({ queryKey: adminKeys.organization(orgId) })
    },
  })
}

export function useInviteOrgAdmin() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ orgId, input }: { orgId: number; input: InviteOrgAdminInput }) =>
      adminApi.inviteOrgAdmin(orgId, input),
    onSuccess: (_res, { orgId }) => {
      void qc.invalidateQueries({ queryKey: adminKeys.organization(orgId) })
    },
  })
}

export function useCreatePromo() {
  return useMutation({
    mutationFn: (input: CreatePromoInput) => adminApi.createPromo(input),
  })
}

export function useAdminUsers(params: AdminUsersQuery) {
  return useQuery({
    queryKey: adminKeys.users(params),
    queryFn: () => adminApi.users(params),
    placeholderData: (prev) => prev, // keep the table steady across filter/page changes
  })
}

export function useAssignUserRole() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ userId, input }: { userId: number; input: AssignRoleInput }) =>
      adminApi.assignUserRole(userId, input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['admin-users'] }),
  })
}

export function useSetUserStatus() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ userId, status }: { userId: number; status: UserStatus }) =>
      adminApi.setUserStatus(userId, status),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['admin-users'] }),
  })
}

export function useRolesMatrix() {
  return useQuery({ queryKey: adminKeys.roles, queryFn: adminApi.roles })
}

export function useAdminPayouts(params: AdminPayoutsQuery) {
  return useQuery({
    queryKey: adminKeys.payouts(params),
    queryFn: () => adminApi.payouts(params),
    placeholderData: (prev) => prev,
  })
}

export function useApprovePayout() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payoutId: number) => adminApi.approvePayout(payoutId),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['admin-payouts'] })
      void qc.invalidateQueries({ queryKey: adminKeys.settlements })
    },
  })
}

export function useRejectPayout() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payoutId: number) => adminApi.rejectPayout(payoutId),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['admin-payouts'] })
      void qc.invalidateQueries({ queryKey: adminKeys.settlements })
    },
  })
}

export function useIncomeReport(params: IncomeReportQuery) {
  return useQuery({
    queryKey: adminKeys.incomeReport(params),
    queryFn: () => adminApi.incomeReport(params),
    placeholderData: (prev) => prev,
  })
}

export function useGrowthReport(params: IncomeReportQuery) {
  return useQuery({
    queryKey: adminKeys.growthReport(params),
    queryFn: () => adminApi.growthReport(params),
    placeholderData: (prev) => prev,
  })
}

export function useSubscriptionsReport(params: IncomeReportQuery) {
  return useQuery({
    queryKey: adminKeys.subscriptionsReport(params),
    queryFn: () => adminApi.subscriptionsReport(params),
    placeholderData: (prev) => prev,
  })
}

export function useReferralsReport(params: IncomeReportQuery) {
  return useQuery({
    queryKey: adminKeys.referralsReport(params),
    queryFn: () => adminApi.referralsReport(params),
    placeholderData: (prev) => prev,
  })
}

export function useOrgActivityReport(params: IncomeReportQuery) {
  return useQuery({
    queryKey: adminKeys.orgActivityReport(params),
    queryFn: () => adminApi.orgActivityReport(params),
    placeholderData: (prev) => prev,
  })
}

export function useRenewalsReport(params: IncomeReportQuery) {
  return useQuery({
    queryKey: adminKeys.renewalsReport(params),
    queryFn: () => adminApi.renewalsReport(params),
    placeholderData: (prev) => prev,
  })
}

export function usePaymentGateways() {
  return useQuery({ queryKey: adminKeys.gateways, queryFn: adminApi.paymentGateways })
}

export function useTestGateway() {
  return useMutation({ mutationFn: (provider: string) => adminApi.testGateway(provider) })
}

export function useAuditLogs(params: AuditLogQuery) {
  return useQuery({
    queryKey: adminKeys.audit(params),
    queryFn: () => adminApi.auditLogs(params),
    placeholderData: (prev) => prev,
  })
}

export function useSupportTickets(params: AdminTicketsQuery) {
  return useQuery({
    queryKey: adminKeys.support(params),
    queryFn: () => adminApi.supportTickets(params),
    placeholderData: (prev) => prev,
  })
}

export function useUpdateTicket() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ ticketId, input }: { ticketId: number; input: UpdateTicketInput }) =>
      adminApi.updateTicket(ticketId, input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['admin-support'] }),
  })
}

export function useReplyTicket() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ ticketId, body }: { ticketId: number; body: string }) => adminApi.replyTicket(ticketId, body),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['admin-support'] }),
  })
}

export function useFlaggedReferrals() {
  return useQuery({ queryKey: adminKeys.flaggedReferrals, queryFn: adminApi.flaggedReferrals })
}

export function useReviewReferral() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ codeId, action }: { codeId: number; action: 'clear' | 'freeze' }) =>
      action === 'clear' ? adminApi.clearReferral(codeId) : adminApi.freezeReferral(codeId),
    onSuccess: () => void qc.invalidateQueries({ queryKey: adminKeys.flaggedReferrals }),
  })
}

export function useAdminPlans() {
  return useQuery({ queryKey: adminKeys.plans, queryFn: adminApi.plans })
}

export function useUpdatePlan() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ planId, input }: { planId: number; input: UpdatePlanInput }) =>
      adminApi.updatePlan(planId, input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: adminKeys.plans })
      void qc.invalidateQueries({ queryKey: ['me'] }) // entitlements may shift
    },
  })
}

export function useCreatePlan() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreatePlanInput) => adminApi.createPlan(input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: adminKeys.plans }),
  })
}

export function useAdminLanguages() {
  return useQuery({ queryKey: adminKeys.languages, queryFn: adminApi.languages })
}

export function useSetLanguageActive() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ languageId, isActive }: { languageId: number; isActive: boolean }) =>
      adminApi.setLanguageActive(languageId, isActive),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: adminKeys.languages })
      void qc.invalidateQueries({ queryKey: ['config'] })
    },
  })
}

export function useReorderLanguages() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (ids: number[]) => adminApi.reorderLanguages(ids),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: adminKeys.languages })
      void qc.invalidateQueries({ queryKey: ['config'] })
    },
  })
}

export function useSettings() {
  return useQuery({ queryKey: adminKeys.settings, queryFn: adminApi.settings })
}

export function useUpdateSettings() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (values: Record<string, SettingValue>) => adminApi.updateSettings(values),
    onSuccess: (data) => {
      qc.setQueryData(adminKeys.settings, data)
      void qc.invalidateQueries({ queryKey: ['config'] })
    },
  })
}

// ── Email: campaigns ──
export function useEmailCampaigns() {
  return useQuery({ queryKey: adminKeys.emailCampaigns, queryFn: adminApi.emailCampaigns })
}

export function useCreateEmailCampaign() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateCampaignInput) => adminApi.createEmailCampaign(input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: adminKeys.emailCampaigns }),
  })
}

export function useTestEmailCampaign() {
  return useMutation({ mutationFn: (id: number) => adminApi.testEmailCampaign(id) })
}

export function useSendEmailCampaign() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, scheduledAt }: { id: number; scheduledAt?: string }) => adminApi.sendEmailCampaign(id, scheduledAt),
    onSuccess: () => void qc.invalidateQueries({ queryKey: adminKeys.emailCampaigns }),
  })
}

// ── Email: contact lists ──
export function useContactLists() {
  return useQuery({ queryKey: adminKeys.contactLists, queryFn: adminApi.contactLists })
}

export function useCreateContactList() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: { name: string; description?: string }) => adminApi.createContactList(input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: adminKeys.contactLists }),
  })
}

export function useContactList(id: number, page = 1) {
  return useQuery({
    queryKey: adminKeys.contactList(id, page),
    queryFn: () => adminApi.contactList(id, page),
    placeholderData: (prev) => prev,
    enabled: id > 0,
  })
}

export function useImportContacts() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, contacts }: { id: number; contacts: { email: string; name: string | null }[] }) =>
      adminApi.importContacts(id, contacts),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['admin-contact-list'] })
      void qc.invalidateQueries({ queryKey: adminKeys.contactLists })
    },
  })
}

export function useDeleteContact() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ listId, contactId }: { listId: number; contactId: number }) => adminApi.deleteContact(listId, contactId),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['admin-contact-list'] }),
  })
}

// ── Email: log ──
export function useEmailLog(params: EmailLogQuery) {
  return useQuery({
    queryKey: adminKeys.emailLog(params),
    queryFn: () => adminApi.emailLog(params),
    placeholderData: (prev) => prev,
  })
}
