import { api } from './client'
import { deviceName } from './storage'
import type {
  AddChildInput,
  AnswerResult,
  AppConfig,
  AuthSession,
  AssignmentDecision,
  Chore,
  ChoreDecision,
  BadgesInfo,
  CompleteResult,
  CourseSummary,
  CreateChoreInput,
  FamilyOverview,
  HeartsInfo,
  LeaderboardRow,
  LeagueStanding,
  LearnerPath,
  LessonAnalytics,
  LessonPlay,
  QuizImportResult,
  AddComponentInput,
  AdminCoursesQuery,
  AdminMetrics,
  AdminPayout,
  AdminPayoutsQuery,
  AdminLanguage,
  AuditLogPage,
  AuditLogQuery,
  FlaggedReferral,
  SettingsResponse,
  SettingValue,
  GatewayStatus,
  GatewayTestResult,
  GrowthReport,
  IncomeReport,
  IncomeReportQuery,
  OrgActivityReport,
  RenewalsReport,
  ReferralsReport,
  SubscriptionsReport,
  AdminOrgDetail,
  AdminOrgList,
  AdminOrgQuery,
  AdminPlan,
  AdminTicketsPage,
  AdminTicketsQuery,
  AdminUserRow,
  CreatePlanInput,
  CreateTicketInput,
  SupportTicket,
  UpdateTicketInput,
  UpdatePlanInput,
  AdminUsersQuery,
  AssignRoleInput,
  CampaignRecipientRow,
  ContactListDetail,
  ContactListRow,
  CreateCampaignInput,
  CreateOrgInput,
  EmailCampaignDetail,
  EmailCampaignRow,
  EmailLogPage,
  EmailLogQuery,
  EmailTemplateContent,
  EmailTemplateDetail,
  EmailTemplatePreview,
  EmailTemplateSummary,
  ImportPreview,
  UploadBatchRow,
  InviteOrgAdminInput,
  OrgStatus,
  Paginated,
  RolesMatrix,
  UpdateOrgInput,
  UserStatus,
  AuthorComponent,
  AuthorLesson,
  AuthorLevel,
  BillingHealth,
  ClassAssignmentDetail,
  ClassAssignmentRow,
  CreateClassAssignmentInput,
  CreateClassInput,
  GradeSubmissionInput,
  GradeSubmissionResult,
  RequestTeacherCompensationPayoutInput,
  SchoolReferralSummary,
  TeacherCompensationSummary,
  CreateCourseInput,
  CreateLessonInput,
  CreateLevelInput,
  CreatePromoInput,
  CreateSubscriptionInput,
  PromoPreview,
  DataBundle,
  MediaAsset,
  MediaQuery,
  Payout,
  Plan,
  PurchaseSeatsInput,
  PayInvoiceResult,
  PurchaseSeatsResult,
  ReferralCode,
  ReferralSummary,
  RequestPayoutInput,
  RosterImportResult,
  ClassAnalytics,
  SchoolClassDetail,
  SchoolClassRow,
  SchoolDashboard,
  SchoolInvoice,
  SeatInfo,
  Settlements,
  StreakInfo,
  SubscriptionHistoryRow,
  TelcoOperator,
  TelcoStatus,
  LoginInput,
  Me,
  RefreshedToken,
  RegisterInput,
  ReviewQueue,
  WalletBalance,
  CompetitionSummary,
  CompetitionDetail,
  SubmitEntryInput,
  MyCompetitionEntry,
  AdminCompetition,
  AdminCompetitionDetail,
  CreateCompetitionInput,
  CompetitionStatus,
  EntryModeration,
  PricingInfo,
} from './types'

function idempotencyKey(): string {
  return typeof crypto !== 'undefined' && 'randomUUID' in crypto
    ? crypto.randomUUID()
    : `idem-${Date.now()}-${Math.random().toString(36).slice(2)}`
}

// Every endpoint returns the payload already unwrapped from the API's
// `{ data: ... }` envelope, so callers work with domain objects directly.

export const authApi = {
  async login(input: LoginInput): Promise<AuthSession> {
    const { data } = await api.post('/auth/login', { ...input, device_name: deviceName() })
    return data.data
  },

  async register(input: RegisterInput): Promise<AuthSession> {
    const { data } = await api.post('/auth/register', { ...input, device_name: deviceName() })
    return data.data
  },

  /** Exchange a Google ID token (from Google Identity Services) for a session. */
  async google(idToken: string): Promise<AuthSession> {
    const { data } = await api.post('/auth/google', { id_token: idToken, device_name: deviceName() })
    return data.data
  },

  async refresh(): Promise<RefreshedToken> {
    const { data } = await api.post('/auth/refresh')
    return data.data
  },

  /** Revokes the current token server-side. Returns 204. */
  async logout(): Promise<void> {
    await api.delete('/auth/token')
  },

  async me(): Promise<Me> {
    const { data } = await api.get('/me')
    return data.data
  },

  async resendVerificationEmail(): Promise<void> {
    await api.post('/email/verification-notification')
  },

  async forgotPassword(email: string): Promise<void> {
    await api.post('/auth/password/forgot', { email })
  },

  async resetPassword(input: {
    email: string
    token: string
    password: string
    password_confirmation: string
  }): Promise<void> {
    await api.post('/auth/password/reset', input)
  },
}

export const configApi = {
  async get(): Promise<AppConfig> {
    const { data } = await api.get('/config')
    return data.data
  },
}

export const pricingApi = {
  async get(): Promise<PricingInfo> {
    const { data } = await api.get('/pricing')
    return data.data
  },
}

export const familyApi = {
  async overview(): Promise<FamilyOverview> {
    const { data } = await api.get('/family')
    return data.data
  },

  async addChild(input: AddChildInput): Promise<{ id: number; display_name: string }> {
    const { data } = await api.post('/family/children', input)
    return data.data
  },

  async setPin(pin: string): Promise<{ pin_set: boolean }> {
    const { data } = await api.put('/family/pin', { pin })
    return data.data
  },

  async wallet(): Promise<WalletBalance> {
    const { data } = await api.get('/wallet')
    return data.data
  },

  /** Start a gateway top-up; the wallet is credited later by the webhook. */
  async fundWallet(input: { amount: number; gateway: 'flutterwave' | 'monnify' | 'paystack' }): Promise<{
    funding_id: number
    status: string
    gateway: string
    gateway_ref: string
    checkout_url: string | null
  }> {
    const { data } = await api.post('/wallet/fund', input, {
      headers: { 'Idempotency-Key': idempotencyKey() },
    })
    return data.data
  },

  async transfer(input: { to_learner_id: number; coins: number }): Promise<{
    family_balance: number
    learner_balance: number
  }> {
    const { data } = await api.post('/wallet/transfer', input, {
      headers: { 'Idempotency-Key': idempotencyKey() },
    })
    return data.data
  },

  async chores(): Promise<Chore[]> {
    const { data } = await api.get('/chores')
    return data.data
  },

  async createChore(input: CreateChoreInput): Promise<{ id: number; status: string }> {
    const { data } = await api.post('/chores', input)
    return data.data
  },

  async reviewChore(
    choreId: number,
    decision: ChoreDecision,
  ): Promise<{ chore_id: number; status: string; coins_released: number }> {
    const { data } = await api.post(`/chores/${choreId}/review`, { decision })
    return data.data
  },

  async pendingReviews(): Promise<ReviewQueue> {
    const { data } = await api.get('/reviews/pending')
    return data.data
  },

  async reviewAssignment(
    submissionId: number,
    decision: AssignmentDecision,
  ): Promise<{ submission_id: number; status: string; coins_released: number }> {
    const { data } = await api.post(`/assignment-submissions/${submissionId}/review`, { decision })
    return data.data
  },
}

export const learningApi = {
  async path(learnerId: number): Promise<LearnerPath> {
    const { data } = await api.get(`/learners/${learnerId}/path`)
    return data.data
  },

  /** Published courses available to enroll into. */
  async courses(): Promise<CourseSummary[]> {
    const { data } = await api.get('/courses')
    return data.data
  },

  async enroll(learnerId: number, courseId: number): Promise<{ enrollment_id: number }> {
    const { data } = await api.post('/enrollments', { learner_id: learnerId, course_id: courseId })
    return data.data
  },

  async play(lessonId: number, learnerId?: number): Promise<LessonPlay> {
    const { data } = await api.get(`/lessons/${lessonId}/play`, {
      params: learnerId ? { learner_id: learnerId } : undefined,
    })
    return data.data
  },

  async answer(input: {
    componentId: number
    learnerId: number
    questionId: number
    answer: Record<string, unknown>
    timeMs?: number
  }): Promise<AnswerResult> {
    const { data } = await api.post(`/components/${input.componentId}/answer`, {
      learner_id: input.learnerId,
      question_id: input.questionId,
      answer: input.answer,
      time_ms: input.timeMs ?? 0,
    })
    return data.data
  },

  /**
   * Heartbeat for non-graded components. Doubles as the video tracker: pass an
   * `event` + deltas (xAPI Video Profile). Only fields that are provided are
   * sent, so a heartbeat never accidentally marks the step complete.
   */
  async progress(input: {
    lessonId: number
    learnerId: number
    componentId: number
    completed?: boolean
    watchedSeconds?: number
    event?: 'played' | 'paused' | 'seeked' | 'heartbeat' | 'completed'
    watchedDelta?: number
    playDelta?: number
    positionSeconds?: number
    durationSeconds?: number
  }): Promise<{ component_id: number; status: string }> {
    const body: Record<string, unknown> = {
      learner_id: input.learnerId,
      component_id: input.componentId,
    }
    if (input.completed != null) body.completed = input.completed
    if (input.watchedSeconds != null) body.watched_seconds = input.watchedSeconds
    if (input.event) body.event = input.event
    if (input.watchedDelta != null) body.watched_delta = input.watchedDelta
    if (input.playDelta != null) body.play_delta = input.playDelta
    if (input.positionSeconds != null) body.position_seconds = input.positionSeconds
    if (input.durationSeconds != null) body.duration_seconds = input.durationSeconds

    const { data } = await api.post(`/lessons/${input.lessonId}/progress`, body)
    return data.data
  },

  async submitSpeaking(input: {
    learnerId: number
    componentId: number
    audio?: Blob
  }): Promise<{ id: number; status: string }> {
    const form = new FormData()
    form.append('learner_id', String(input.learnerId))
    form.append('component_id', String(input.componentId))
    if (input.audio) form.append('audio', input.audio, 'speaking.webm')
    const { data } = await api.post('/speaking-submissions', form)
    return data.data
  },

  async submitAssignment(input: {
    learnerId: number
    componentId: number
    media?: Blob
    filename?: string
  }): Promise<{ id: number; status: string; coins_pending: number }> {
    const form = new FormData()
    form.append('learner_id', String(input.learnerId))
    form.append('component_id', String(input.componentId))
    if (input.media) form.append('media', input.media, input.filename ?? 'assignment.webm')
    const { data } = await api.post('/assignment-submissions', form)
    return data.data
  },

  async complete(lessonId: number, learnerId: number): Promise<CompleteResult> {
    const { data } = await api.post(`/lessons/${lessonId}/complete`, { learner_id: learnerId })
    return data.data
  },
}

export const contentApi = {
  async courses(): Promise<CourseSummary[]> {
    const { data } = await api.get('/courses')
    return data.data
  },

  /** Paginated + filterable course list for the admin/CMS oversight view. */
  async coursesPaged(params: AdminCoursesQuery = {}): Promise<Paginated<CourseSummary>> {
    const { data } = await api.get('/courses', { params })
    return data
  },

  async publishCourse(courseId: number): Promise<CourseSummary> {
    const { data } = await api.post(`/courses/${courseId}/publish`)
    return data.data
  },

  async unpublishCourse(courseId: number): Promise<CourseSummary> {
    const { data } = await api.post(`/courses/${courseId}/unpublish`)
    return data.data
  },

  async createCourse(input: CreateCourseInput): Promise<CourseSummary> {
    const { data } = await api.post('/courses', input)
    return data.data
  },

  async levels(courseId: number): Promise<AuthorLevel[]> {
    const { data } = await api.get(`/courses/${courseId}/levels`)
    return data.data
  },

  async createLevel(courseId: number, input: CreateLevelInput): Promise<AuthorLevel> {
    const { data } = await api.post(`/courses/${courseId}/levels`, input)
    return data.data
  },

  async lessons(levelId: number): Promise<AuthorLesson[]> {
    const { data } = await api.get(`/levels/${levelId}/lessons`)
    return data.data
  },

  async createLesson(levelId: number, input: CreateLessonInput): Promise<AuthorLesson> {
    const { data } = await api.post(`/levels/${levelId}/lessons`, input)
    return data.data
  },

  async lesson(lessonId: number): Promise<AuthorLesson> {
    const { data } = await api.get(`/lessons/${lessonId}`)
    return data.data
  },

  async addComponent(lessonId: number, input: AddComponentInput): Promise<AuthorComponent> {
    const { data } = await api.post(`/lessons/${lessonId}/components`, input)
    return data.data
  },

  /** Edit a component in place (type is immutable; quiz questions are replaced). */
  async updateComponent(componentId: number, input: AddComponentInput): Promise<AuthorComponent> {
    const { data } = await api.put(`/components/${componentId}`, input)
    return data.data
  },

  async deleteComponent(componentId: number): Promise<void> {
    await api.delete(`/components/${componentId}`)
  },

  /** Publish a lesson; throws ApiError (details = string[] of failures) on 422. */
  async publishLesson(lessonId: number): Promise<AuthorLesson> {
    const { data } = await api.post(`/lessons/${lessonId}/publish`)
    return data.data
  },

  /** Drop-off funnel + per-question accuracy for the authoring "Insights" view. */
  async lessonAnalytics(lessonId: number): Promise<LessonAnalytics> {
    const { data } = await api.get(`/lessons/${lessonId}/analytics`)
    return data.data
  },

  /** Parse an uploaded CSV/XLSX into quiz questions for review (no DB writes). */
  async parseQuizImport(file: File): Promise<QuizImportResult> {
    const form = new FormData()
    form.append('file', file)
    const { data } = await api.post('/quiz-imports/parse', form)
    return data.data
  },

  /** Upload a media file to local storage; returns the created asset (id + url). */
  async uploadMedia(file: File): Promise<{ id: number; type: string; url: string }> {
    const form = new FormData()
    form.append('file', file)
    const { data } = await api.post('/media/upload', form)
    return data.data
  },

  /** Lightweight list (first page) — used by the video picker; pass type/per_page. */
  async media(params: MediaQuery = {}): Promise<MediaAsset[]> {
    const { data } = await api.get('/media', { params })
    return data.data
  },

  /** Paginated library for the Media page. */
  async mediaLibrary(params: MediaQuery = {}): Promise<Paginated<MediaAsset>> {
    const { data } = await api.get('/media', { params })
    return data
  },

  async mediaOrphans(params: MediaQuery = {}): Promise<Paginated<MediaAsset>> {
    const { data } = await api.get('/media/orphans', { params })
    return data
  },

  async purgeMediaOrphans(ids: number[]): Promise<{ deleted: number; skipped: number }> {
    const { data } = await api.post('/media/orphans/purge', { ids })
    return data.data
  },

  async deleteMedia(id: number): Promise<void> {
    await api.delete(`/media/${id}`)
  },
}

export const billingApi = {
  async plans(): Promise<Plan[]> {
    const { data } = await api.get('/plans')
    return data.data
  },

  async subscriptions(): Promise<SubscriptionHistoryRow[]> {
    const { data } = await api.get('/subscriptions')
    return data.data
  },

  /** Card → returns checkout_url (activated later by webhook). Invoice → active now. */
  async createSubscription(input: CreateSubscriptionInput): Promise<{
    subscription_id: number
    status: string
    payment_reference?: string
    checkout_url?: string | null
  }> {
    const { data } = await api.post('/subscriptions', input, {
      headers: { 'Idempotency-Key': idempotencyKey() },
    })
    return data.data
  },

  async previewPromo(planId: number, code: string): Promise<PromoPreview> {
    const { data } = await api.post('/subscriptions/promo-preview', { plan_id: planId, code })
    return data.data
  },

  async cancelSubscription(id: number): Promise<{ status: string; message: string }> {
    const { data } = await api.post(`/subscriptions/${id}/cancel`)
    return data.data
  },

  // --- Telco (airtime VAS) ---
  async telcoStatus(): Promise<TelcoStatus> {
    const { data } = await api.get('/telco/status')
    return data.data
  },

  async telcoRequestOtp(input: { msisdn: string; operator: TelcoOperator }): Promise<{ expires_at: string; msisdn: string }> {
    const { data } = await api.post('/telco/otp/request', input)
    return data.data
  },

  async telcoVerifyOtp(input: { msisdn: string; code: string }): Promise<{ verified: boolean }> {
    const { data } = await api.post('/telco/otp/verify', input)
    return data.data
  },

  async telcoSubscribe(input: { plan_id: number; msisdn: string; operator: TelcoOperator }): Promise<{
    subscription_id: number
    state: string
    msisdn: string
    next_attempt_at: string | null
  }> {
    const { data } = await api.post('/telco/subscribe', input)
    return data.data
  },

  // --- Data bundles ---
  async dataBundles(): Promise<DataBundle[]> {
    const { data } = await api.get('/data-bundles')
    return data.data
  },

  async purchaseDataBundle(input: { operator: TelcoOperator; bundle_mb: number; consent: boolean }): Promise<{
    purchase_id: number
    status: string
    amount_minor: number
  }> {
    const { data } = await api.post('/data-bundles/purchase', input, {
      headers: { 'Idempotency-Key': idempotencyKey() },
    })
    return data.data
  },
}

export const adminApi = {
  async metrics(): Promise<AdminMetrics> {
    const { data } = await api.get('/admin/metrics')
    return data.data
  },

  async billingHealth(): Promise<BillingHealth> {
    const { data } = await api.get('/admin/billing/health')
    return data.data
  },

  async settlements(): Promise<Settlements> {
    const { data } = await api.get('/admin/settlements')
    return data.data
  },

  async organizations(params: AdminOrgQuery = {}): Promise<AdminOrgList> {
    const { data } = await api.get('/admin/organizations', { params })
    return data
  },

  async organization(orgId: number): Promise<AdminOrgDetail> {
    const { data } = await api.get(`/admin/organizations/${orgId}`)
    return data.data
  },

  async createOrg(input: CreateOrgInput): Promise<{ id: number; name: string; status: string }> {
    const { data } = await api.post('/admin/organizations', input)
    return data.data
  },

  async updateOrg(orgId: number, input: UpdateOrgInput): Promise<{ id: number; name: string; status: string }> {
    const { data } = await api.patch(`/admin/organizations/${orgId}`, input)
    return data.data
  },

  async activateOrg(orgId: number): Promise<{ id: number; status: string }> {
    const { data } = await api.post(`/admin/organizations/${orgId}/activate`)
    return data.data
  },

  async setOrgStatus(orgId: number, status: OrgStatus): Promise<{ id: number; status: string }> {
    const { data } = await api.post(`/admin/organizations/${orgId}/status`, { status })
    return data.data
  },

  async inviteOrgAdmin(orgId: number, input: InviteOrgAdminInput): Promise<{ id: number; name: string; email: string }> {
    const { data } = await api.post(`/admin/organizations/${orgId}/invite-admin`, input)
    return data.data
  },

  async createPromo(input: CreatePromoInput): Promise<{ id: number; code: string }> {
    const { data } = await api.post('/admin/promo-codes', input)
    return data.data
  },

  async users(params: AdminUsersQuery = {}): Promise<Paginated<AdminUserRow>> {
    const { data } = await api.get('/admin/users', { params })
    return data
  },

  async assignUserRole(userId: number, input: AssignRoleInput): Promise<AdminUserRow> {
    const { data } = await api.post(`/admin/users/${userId}/roles`, input)
    return data.data
  },

  async setUserStatus(userId: number, status: UserStatus): Promise<AdminUserRow> {
    const { data } = await api.post(`/admin/users/${userId}/status`, { status })
    return data.data
  },

  async roles(): Promise<RolesMatrix> {
    const { data } = await api.get('/admin/roles')
    return data.data
  },

  async payouts(params: AdminPayoutsQuery = {}): Promise<Paginated<AdminPayout>> {
    const { data } = await api.get('/admin/payouts', { params })
    return data
  },

  async approvePayout(payoutId: number): Promise<{ id: number; status: string; approved_by: number }> {
    const { data } = await api.post(`/admin/payouts/${payoutId}/approve`)
    return data.data
  },

  async rejectPayout(payoutId: number): Promise<{ id: number; status: string }> {
    const { data } = await api.post(`/admin/payouts/${payoutId}/reject`)
    return data.data
  },

  async incomeReport(params: IncomeReportQuery = {}): Promise<IncomeReport> {
    const { data } = await api.get('/admin/reports/income', { params })
    return data.data
  },

  async growthReport(params: IncomeReportQuery = {}): Promise<GrowthReport> {
    const { data } = await api.get('/admin/reports/growth', { params })
    return data.data
  },

  async subscriptionsReport(params: IncomeReportQuery = {}): Promise<SubscriptionsReport> {
    const { data } = await api.get('/admin/reports/subscriptions', { params })
    return data.data
  },

  async referralsReport(params: IncomeReportQuery = {}): Promise<ReferralsReport> {
    const { data } = await api.get('/admin/reports/referrals', { params })
    return data.data
  },

  async orgActivityReport(params: IncomeReportQuery = {}): Promise<OrgActivityReport> {
    const { data } = await api.get('/admin/reports/org-activity', { params })
    return data.data
  },

  async renewalsReport(params: IncomeReportQuery = {}): Promise<RenewalsReport> {
    const { data } = await api.get('/admin/reports/renewals', { params })
    return data.data
  },

  // ── Email: campaigns ──
  async emailCampaigns(): Promise<EmailCampaignRow[]> {
    const { data } = await api.get('/admin/email-campaigns')
    return data.data
  },
  async createEmailCampaign(input: CreateCampaignInput): Promise<EmailCampaignRow> {
    const { data } = await api.post('/admin/email-campaigns', input)
    return data.data
  },
  async emailCampaign(id: number): Promise<EmailCampaignDetail> {
    const { data } = await api.get(`/admin/email-campaigns/${id}`)
    return data.data
  },
  async testEmailCampaign(id: number): Promise<{ sent_to: string }> {
    const { data } = await api.post(`/admin/email-campaigns/${id}/test`)
    return data.data
  },
  async sendEmailCampaign(id: number, scheduledAt?: string): Promise<EmailCampaignRow> {
    const { data } = await api.post(`/admin/email-campaigns/${id}/send`, scheduledAt ? { scheduled_at: scheduledAt } : {})
    return data.data
  },
  async cancelEmailCampaign(id: number): Promise<EmailCampaignRow> {
    const { data } = await api.post(`/admin/email-campaigns/${id}/cancel`)
    return data.data
  },
  async campaignRecipients(id: number, params: { status?: string; page?: number } = {}): Promise<Paginated<CampaignRecipientRow>> {
    const { data } = await api.get(`/admin/email-campaigns/${id}/recipients`, { params })
    return data
  },

  // ── Email: contact lists + upload ──
  async contactLists(): Promise<ContactListRow[]> {
    const { data } = await api.get('/admin/contact-lists')
    return data.data
  },
  async createContactList(input: { name: string; description?: string }): Promise<{ id: number; name: string }> {
    const { data } = await api.post('/admin/contact-lists', input)
    return data.data
  },
  async contactList(id: number, page = 1): Promise<ContactListDetail> {
    const { data } = await api.get(`/admin/contact-lists/${id}`, { params: { page } })
    return data
  },
  async previewContacts(id: number, form: { emails?: string; file?: File }): Promise<ImportPreview> {
    const body = new FormData()
    if (form.emails) body.append('emails', form.emails)
    if (form.file) body.append('file', form.file)
    const { data } = await api.post(`/admin/contact-lists/${id}/import/preview`, body)
    return data.data
  },
  async importContacts(id: number, contacts: { email: string; name: string | null }[]): Promise<{ imported: number; skipped: number }> {
    const { data } = await api.post(`/admin/contact-lists/${id}/import`, { contacts })
    return data.data
  },
  async contactUploads(id: number): Promise<UploadBatchRow[]> {
    const { data } = await api.get(`/admin/contact-lists/${id}/uploads`)
    return data.data
  },
  async rollbackUpload(listId: number, batchId: number): Promise<{ removed: number }> {
    const { data } = await api.post(`/admin/contact-lists/${listId}/uploads/${batchId}/rollback`)
    return data.data
  },
  async addContact(listId: number, input: { email: string; name?: string }): Promise<{ id: number; email: string }> {
    const { data } = await api.post(`/admin/contact-lists/${listId}/contacts`, input)
    return data.data
  },
  async updateContact(listId: number, contactId: number, input: { name?: string; status?: string }): Promise<{ id: number; status: string }> {
    const { data } = await api.patch(`/admin/contact-lists/${listId}/contacts/${contactId}`, input)
    return data.data
  },
  async deleteContact(listId: number, contactId: number): Promise<void> {
    await api.delete(`/admin/contact-lists/${listId}/contacts/${contactId}`)
  },

  // ── Email: log ──
  async emailLog(params: EmailLogQuery = {}): Promise<EmailLogPage> {
    const { data } = await api.get('/admin/email-log', { params })
    return data
  },

  // ── Email: templates ──
  async emailTemplates(): Promise<EmailTemplateSummary[]> {
    const { data } = await api.get('/admin/email-templates')
    return data.data
  },
  async emailTemplate(key: string): Promise<EmailTemplateDetail> {
    const { data } = await api.get(`/admin/email-templates/${key}`)
    return data.data
  },
  async emailTemplatePreview(key: string): Promise<EmailTemplatePreview> {
    const { data } = await api.get(`/admin/email-templates/${key}/preview`)
    return data.data
  },
  async updateEmailTemplate(key: string, input: EmailTemplateContent): Promise<EmailTemplateDetail> {
    const { data } = await api.put(`/admin/email-templates/${key}`, input)
    return data.data
  },
  async resetEmailTemplate(key: string): Promise<EmailTemplateDetail> {
    const { data } = await api.delete(`/admin/email-templates/${key}`)
    return data.data
  },

  async paymentGateways(): Promise<GatewayStatus> {
    const { data } = await api.get('/admin/payment-gateways')
    return data.data
  },

  async testGateway(provider: string): Promise<GatewayTestResult> {
    const { data } = await api.post(`/admin/payment-gateways/${provider}/test`)
    return data.data
  },

  async auditLogs(params: AuditLogQuery = {}): Promise<AuditLogPage> {
    const { data } = await api.get('/admin/audit-logs', { params })
    return data
  },

  async supportTickets(params: AdminTicketsQuery = {}): Promise<AdminTicketsPage> {
    const { data } = await api.get('/admin/support-tickets', { params })
    return data
  },

  async updateTicket(ticketId: number, input: UpdateTicketInput): Promise<SupportTicket> {
    const { data } = await api.patch(`/admin/support-tickets/${ticketId}`, input)
    return data.data
  },

  async replyTicket(ticketId: number, body: string): Promise<SupportTicket> {
    const { data } = await api.post(`/admin/support-tickets/${ticketId}/messages`, { body })
    return data.data
  },

  async plans(): Promise<AdminPlan[]> {
    const { data } = await api.get('/admin/plans')
    return data.data
  },

  async createPlan(input: CreatePlanInput): Promise<AdminPlan> {
    const { data } = await api.post('/admin/plans', input)
    return data.data
  },

  async updatePlan(planId: number, input: UpdatePlanInput): Promise<AdminPlan> {
    const { data } = await api.patch(`/admin/plans/${planId}`, input)
    return data.data
  },

  async languages(): Promise<AdminLanguage[]> {
    const { data } = await api.get('/admin/languages')
    return data.data
  },

  async setLanguageActive(languageId: number, isActive: boolean): Promise<{ id: number; is_active: boolean }> {
    const { data } = await api.patch(`/admin/languages/${languageId}`, { is_active: isActive })
    return data.data
  },

  async reorderLanguages(ids: number[]): Promise<{ ids: number[] }> {
    const { data } = await api.post('/admin/languages/reorder', { ids })
    return data.data
  },

  async flaggedReferrals(): Promise<FlaggedReferral[]> {
    const { data } = await api.get('/admin/referrals/flagged')
    return data.data
  },

  async clearReferral(codeId: number): Promise<{ id: number; status: string }> {
    const { data } = await api.post(`/admin/referrals/${codeId}/clear`)
    return data.data
  },

  async freezeReferral(codeId: number): Promise<{ id: number; status: string }> {
    const { data } = await api.post(`/admin/referrals/${codeId}/freeze`)
    return data.data
  },

  async settings(): Promise<SettingsResponse> {
    const { data } = await api.get('/admin/settings')
    return data.data
  },

  async updateSettings(values: Record<string, SettingValue>): Promise<SettingsResponse> {
    const { data } = await api.patch('/admin/settings', { values })
    return data.data
  },

  /* ── Language & Culture competition (organiser) ── */
  async competitions(): Promise<AdminCompetition[]> {
    const { data } = await api.get('/admin/competitions')
    return data.data
  },

  async competition(id: number): Promise<AdminCompetitionDetail> {
    const { data } = await api.get(`/admin/competitions/${id}`)
    return data.data
  },

  async createCompetition(input: CreateCompetitionInput): Promise<AdminCompetition> {
    const { data } = await api.post('/admin/competitions', input)
    return data.data
  },

  async moderateEntry(competitionId: number, entryId: number, status: EntryModeration): Promise<{ id: number; status: string }> {
    const { data } = await api.post(`/admin/competitions/${competitionId}/entries/${entryId}/moderate`, { status })
    return data.data
  },

  async setCompetitionStatus(id: number, status: CompetitionStatus): Promise<AdminCompetition> {
    const { data } = await api.post(`/admin/competitions/${id}/status`, { status })
    return data.data
  },

  async judgeCompetition(id: number, awards: { entry_id: number; rank: number }[]): Promise<AdminCompetition> {
    const { data } = await api.post(`/admin/competitions/${id}/judge`, { awards })
    return data.data
  },
}

export const competitionApi = {
  async list(): Promise<CompetitionSummary[]> {
    const { data } = await api.get('/competitions')
    return data.data
  },

  async show(id: number): Promise<CompetitionDetail> {
    const { data } = await api.get(`/competitions/${id}`)
    return data.data
  },

  async mine(): Promise<MyCompetitionEntry[]> {
    const { data } = await api.get('/competitions/mine')
    return data.data
  },

  async submitEntry(competitionId: number, input: SubmitEntryInput): Promise<{ id: number; status: string }> {
    const { data } = await api.post(`/competitions/${competitionId}/entries`, input)
    return data.data
  },

  async vote(competitionId: number, entryId: number): Promise<{ votes_count: number }> {
    const { data } = await api.post(`/competitions/${competitionId}/entries/${entryId}/vote`)
    return data.data
  },
}

export const schoolApi = {
  async dashboard(orgId: number): Promise<SchoolDashboard> {
    const { data } = await api.get(`/schools/${orgId}/dashboard`)
    return data.data
  },

  async classes(): Promise<SchoolClassRow[]> {
    const { data } = await api.get('/classes')
    return data.data
  },

  async classDetail(classId: number): Promise<SchoolClassDetail> {
    const { data } = await api.get(`/classes/${classId}`)
    return data.data
  },

  async classAnalytics(classId: number): Promise<ClassAnalytics> {
    const { data } = await api.get(`/classes/${classId}/analytics`)
    return data.data
  },

  async createClass(input: CreateClassInput): Promise<{ id: number; name: string }> {
    const { data } = await api.post('/classes', input)
    return data.data
  },

  async classAssignments(classId: number): Promise<ClassAssignmentRow[]> {
    const { data } = await api.get(`/classes/${classId}/assignments`)
    return data.data
  },

  async classAssignmentDetail(classId: number, assignmentId: number): Promise<ClassAssignmentDetail> {
    const { data } = await api.get(`/classes/${classId}/assignments/${assignmentId}`)
    return data.data
  },

  async createClassAssignment(
    classId: number,
    input: CreateClassAssignmentInput,
  ): Promise<{ id: number; title: string }> {
    const { data } = await api.post(`/classes/${classId}/assignments`, input)
    return data.data
  },

  async gradeSubmission(
    classId: number,
    assignmentId: number,
    submissionId: number,
    input: GradeSubmissionInput,
  ): Promise<GradeSubmissionResult> {
    const { data } = await api.post(
      `/classes/${classId}/assignments/${assignmentId}/submissions/${submissionId}/grade`,
      input,
    )
    return data.data
  },

  async teacherCompensationSummary(): Promise<TeacherCompensationSummary> {
    const { data } = await api.get('/teacher-compensation/summary')
    return data.data
  },

  async requestTeacherCompensationPayout(
    input: RequestTeacherCompensationPayoutInput,
  ): Promise<{ id: number; status: string }> {
    const { data } = await api.post('/teacher-compensation/payouts/request', input, {
      headers: { 'Idempotency-Key': idempotencyKey() },
    })
    return data.data
  },

  async seats(orgId: number): Promise<SeatInfo> {
    const { data } = await api.get(`/schools/${orgId}/seats`)
    return data.data
  },

  async purchaseSeats(orgId: number, input: PurchaseSeatsInput): Promise<PurchaseSeatsResult> {
    const { data } = await api.post(`/schools/${orgId}/seats/purchase`, input)
    return data.data
  },

  async invoices(orgId: number): Promise<SchoolInvoice[]> {
    const { data } = await api.get(`/schools/${orgId}/invoices`)
    return data.data
  },

  async payInvoice(orgId: number, invoiceId: number, gateway?: string): Promise<PayInvoiceResult> {
    const { data } = await api.post(`/schools/${orgId}/invoices/${invoiceId}/pay`, gateway ? { gateway } : {})
    return data.data
  },

  /** Fetch the invoice PDF (auth-protected) and trigger a browser download. */
  async downloadInvoice(orgId: number, invoiceId: number): Promise<void> {
    const res = await api.get(`/schools/${orgId}/invoices/${invoiceId}/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(res.data as Blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `invoice-${invoiceId}.pdf`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  },

  async importRoster(
    orgId: number,
    input: { file?: File; students?: { display_name: string; level?: string }[]; class_id?: number },
  ): Promise<RosterImportResult> {
    if (input.file) {
      const form = new FormData()
      form.append('file', input.file)
      if (input.class_id) form.append('class_id', String(input.class_id))
      const { data } = await api.post(`/schools/${orgId}/students/import`, form)
      return data.data
    }
    const { data } = await api.post(`/schools/${orgId}/students/import`, {
      students: input.students,
      class_id: input.class_id,
    })
    return data.data
  },

  async referralSummary(orgId: number): Promise<SchoolReferralSummary> {
    const { data } = await api.get(`/schools/${orgId}/referrals/summary`)
    return data.data
  },

  async requestReferralPayout(orgId: number, input: RequestPayoutInput): Promise<{ id: number; status: string }> {
    const { data } = await api.post(`/schools/${orgId}/referrals/payouts/request`, input, {
      headers: { 'Idempotency-Key': idempotencyKey() },
    })
    return data.data
  },
}

export const referralApi = {
  async code(): Promise<ReferralCode> {
    const { data } = await api.get('/referral-code')
    return data.data
  },

  async summary(): Promise<ReferralSummary> {
    const { data } = await api.get('/referrals/summary')
    return data.data
  },

  async payouts(): Promise<Payout[]> {
    const { data } = await api.get('/payouts')
    return data.data
  },

  async requestPayout(input: RequestPayoutInput): Promise<{ id: number; status: string }> {
    const { data } = await api.post('/payouts/request', input, {
      headers: { 'Idempotency-Key': idempotencyKey() },
    })
    return data.data
  },
}

export const gamificationApi = {
  async streak(learnerId: number): Promise<StreakInfo> {
    const { data } = await api.get(`/learners/${learnerId}/streak`)
    return data.data
  },

  async armShield(learnerId: number): Promise<{ protection_id: number; type: string; active_to: string | null }> {
    const { data } = await api.post('/streak/shield', { learner_id: learnerId })
    return data.data
  },

  async hearts(learnerId: number): Promise<HeartsInfo> {
    const { data } = await api.get('/hearts', { params: { learner_id: learnerId } })
    return data.data
  },

  async refillHearts(learnerId: number, method: 'ad' | 'coins'): Promise<HeartsInfo> {
    const { data } = await api.post('/hearts/refill', { learner_id: learnerId, method })
    return data.data
  },

  async badges(learnerId: number): Promise<BadgesInfo> {
    const { data } = await api.get(`/learners/${learnerId}/badges`)
    return data.data
  },

  async leagueCurrent(learnerId: number): Promise<LeagueStanding> {
    const { data } = await api.get('/leagues/current', { params: { learner_id: learnerId } })
    return data.data
  },

  async leaderboard(learnerId: number): Promise<LeaderboardRow[]> {
    const { data } = await api.get('/leaderboard', { params: { learner_id: learnerId } })
    return data.data
  },
}

export const profileApi = {
  /**
   * Switch the active child learner profile. `pin` is required for pin-protected
   * profiles (the API answers 403 `invalid_pin` otherwise).
   */
  async switch(learnerId: number, pin?: string): Promise<{ active_learner_id: number }> {
    const { data } = await api.post(`/profiles/${learnerId}/switch`, pin ? { pin } : {})
    return data.data
  },
}

export const supportApi = {
  async tickets(): Promise<SupportTicket[]> {
    const { data } = await api.get('/support/tickets')
    return data.data
  },

  async createTicket(input: CreateTicketInput): Promise<{ id: number; status: string }> {
    const { data } = await api.post('/support/tickets', input)
    return data.data
  },

  async replyTicket(ticketId: number, body: string): Promise<{ id: number; status: string }> {
    const { data } = await api.post(`/support/tickets/${ticketId}/messages`, { body })
    return data.data
  },
}
