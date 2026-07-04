// Shared shapes for the Mahadum.360 API. These mirror the Laravel API resources
// (UserResource, FamilyResource, MeController) — keep them in sync with the backend.

export type Role =
  | 'super_admin'
  | 'content_owner'
  | 'teacher'
  | 'supervisor'
  | 'school_admin'
  | 'parent'
  | 'student'

/** The user object returned inside the login/register token response (UserResource). */
export interface ApiUser {
  id: number
  first_name: string
  last_name: string
  name: string
  email: string
  username: string | null
  locale: string | null
  roles: Role[]
  active_organization_id: number | null
}

/** Successful response from POST /auth/login, /auth/register, /auth/google. */
export interface AuthSession {
  token: string
  token_type: string
  expires_at: string
  abilities: string[]
  user: ApiUser
}

/** Token-only response from POST /auth/refresh. */
export interface RefreshedToken {
  token: string
  token_type: string
  expires_at: string
}

export interface LearnerProfile {
  id: number
  display_name: string
  avatar_id: number | null
  age_band: string | null
  current_level: number | null
  target_language?: string | null
  /** True for child profiles (no own user account). */
  is_child: boolean
  /** Requires a parental PIN to switch into. */
  pin_protected: boolean
}

export interface Family {
  id: number
  name: string
  child_limit: number
  learners?: LearnerProfile[]
}

export interface OrganizationMembership {
  id: number
  name: string | null
  role: string
}

/** Capability flags derived from the active plan (or Free defaults). */
export interface Entitlements {
  tier: string
  tier_name: string
  ads: boolean
  offline_download: boolean
  unlimited_hearts: boolean
  family_dashboard: boolean
  teacher_analytics: boolean
  max_profiles: number
}

export type EntitlementFeature = keyof Pick<
  Entitlements,
  'offline_download' | 'unlimited_hearts' | 'family_dashboard' | 'teacher_analytics'
>

export interface SubscriptionInfo {
  id: number
  status: string
  method: string
  plan_code: string | null
  plan_name: string | null
  renews_at: string | null
}

/** Response from GET /me — the canonical session snapshot used across the app. */
export interface Me {
  user: {
    id: number
    first_name: string
    last_name: string
    name: string
    email: string
    email_verified: boolean
    roles: Role[]
  }
  families: Family[]
  organizations: OrganizationMembership[]
  active_organization_id: number | null
  subscription: SubscriptionInfo | null
  entitlements: Entitlements
}

export interface Plan {
  id: number
  code: string
  name: string
  price_minor: number
  currency: string
  interval: string
  audience?: string | null
  max_profiles: number | null
  features: Record<string, boolean | number | string>
}

export interface SubscriptionHistoryRow {
  id: number
  status: string
  method: string
  plan_code: string | null
  plan_name: string | null
  price_minor: number | null
  started_at: string | null
  renews_at: string | null
  cancelled_at: string | null
}

export interface CreateSubscriptionInput {
  plan_id: number
  method: 'card' | 'invoice'
  promo_code?: string
}

export interface PromoPreview {
  code: string
  price_minor: number
  discount_minor: number
  final_minor: number
}

export type TelcoOperator = 'mtn' | 'airtel' | 'glo' | 't2'

export interface TelcoStatus {
  state: string
  grace_until: string | null
  next_attempt_at: string | null
}

export interface DataBundle {
  bundle_mb: number
  amount_minor: number
  currency: string
}

export interface AppLanguage {
  id: number
  code: string
  name: string
  script?: string
  rtl?: boolean
}

// ---- Family hub ----

export interface WalletBalance {
  coin_balance: number
  /** Cash balance in minor units (e.g. kobo). */
  currency_minor: number
  currency: string
}

export interface FamilyOverview {
  id: number
  name: string
  child_limit: number
  pin_set: boolean
  wallet: WalletBalance
  learners: { id: number; display_name: string; is_child: boolean }[]
}

export type ChoreStatus = 'active' | 'pending_review' | 'approved' | 'rejected'

export interface Chore {
  id: number
  title: string
  status: ChoreStatus
  coin_reward: number
  assignee: string | null
  due_at: string | null
}

export type ChoreDecision = 'approve' | 'reject' | 'more_evidence'
export type AssignmentDecision = 'approve' | 'reject'

export interface AssignmentReviewItem {
  id: number
  learner_profile_id: number
  lesson_component_id: number
  parent_review_status: string
  learner: string | null
  prompt: string | null
  expected_media: string | null
  coin_reward: number
  media_url: string | null
}

export interface ReviewQueue {
  chores: { chore_id: number; title: string; assignee: string | null; coin_reward: number; status: ChoreStatus }[]
  speaking: { id: number; learner_profile_id: number; lesson_component_id: number; status: string }[]
  assignments: AssignmentReviewItem[]
}

export interface AddChildInput {
  display_name: string
  date_of_birth?: string
  age_band?: string
  target_language_id?: number
  /** Verifiable parental consent — required by the API for every child. */
  consent: boolean
}

export interface CreateChoreInput {
  title: string
  description?: string
  assignee_learner_profile_id: number
  coin_reward: number
  due_at?: string
}

// ---- Learner app ----

export type NodeState = 'locked' | 'active' | 'completed'

export interface PathNode {
  lesson_id: number
  title: string
  state: NodeState
  position: number
}

export interface PathUnit {
  title: string
  nodes: PathNode[]
}

export interface LearnerPath {
  units: PathUnit[]
}

export interface CourseSummary {
  id: number
  title: string
  description: string | null
  level_band: string | null
  status: string
  is_published: boolean
  language?: string | null
  /** Present on admin/CMS listings (course owner name, level count). */
  owner?: string | null
  levels_count?: number
}

export interface AdminCoursesQuery {
  q?: string
  language?: string
  status?: string
  page?: number
}

// ---- Content authoring (CMS) ----

export interface MediaAsset {
  id: number
  type: string
  url: string
  original_name?: string | null
  created_at: string | null
}

export interface MediaQuery {
  q?: string
  type?: string
  per_page?: number
  page?: number
}

export interface AuthorComponent {
  id: number
  type: string
  position: number
  title: string | null
  is_required: boolean
  xp_value: number
  settings: Record<string, unknown> | null
  detail: Record<string, unknown> | null
}

export interface AuthorLesson {
  id: number
  title: string
  position: number
  est_minutes: number | null
  is_locked_by_default: boolean
  version?: number
  published_at: string | null
  is_published: boolean
  components?: AuthorComponent[]
}

export interface AuthorLevel {
  id: number
  title: string
  position: number
  has_assessment: boolean
  lessons?: AuthorLesson[]
}

export interface CreateCourseInput {
  language_id: number
  title: string
  description?: string
  level_band?: string
}

export interface CreateLevelInput {
  title: string
  position?: number
  has_assessment?: boolean
}

export interface CreateLessonInput {
  title: string
  est_minutes?: number
  position?: number
  is_locked_by_default?: boolean
}

/** Question types the grader supports (option-based, ordered, pairs + free-text). */
export type QuestionType =
  | 'mcq_single'
  | 'mcq_multi'
  | 'true_false'
  | 'fill_blank'
  | 'listen_and_respond'
  | 'complete_the_chat'
  | 'word_bank'
  | 'match_pairs'
  | 'type_what_you_hear'

export interface AuthorQuestionInput {
  type: QuestionType
  prompt: string
  explanation?: string
  target_text?: string
  points?: number
  prompt_audio_asset_id?: number
  prompt_image_asset_id?: number
  options?: { label: string; is_correct?: boolean; match_target?: string }[]
}

/** Result of parsing an uploaded CSV/XLSX of quiz questions (no DB writes). */
export interface QuizImportResult {
  questions: AuthorQuestionInput[]
  errors: { row: number; error: string }[]
  imported: number
}

/** Payload for POST /lessons/{id}/components (one of video/quiz/speaking detail). */
export interface AddComponentInput {
  type: 'video' | 'quiz' | 'speaking' | 'exercise' | 'game' | 'assignment'
  title?: string
  is_required?: boolean
  xp_value?: number
  settings?: Record<string, unknown>
  video?: {
    title: string
    presenter_name?: string
    duration_seconds?: number
    default_quality?: '240p' | '360p' | '720p'
    status?: 'uploading' | 'processing' | 'ready' | 'failed'
    language_id?: number
    source_asset_id?: number
  }
  quiz?: {
    title?: string
    pass_threshold?: number
    hearts_enabled?: boolean
    questions?: AuthorQuestionInput[]
  }
  speaking?: { prompt_text: string; target_text?: string }
  assignment?: {
    prompt: string
    expected_media?: 'video' | 'audio'
    max_duration_seconds?: number
    coin_reward?: number
  }
  exercise?: {
    mode?: string
    cards?: { front_text: string; back_text: string; mnemonic?: string; audio_asset_id?: number; image_asset_id?: number }[]
  }
  game?: {
    game_type: 'memory' | 'match' | 'tone_pop' | 'word_builder'
    config?: { pairs?: { a: string; b: string }[] }
  }
}

export interface QuizOption {
  id: number
  label: string
}

export interface QuizQuestion {
  id: number
  type: string
  prompt: string
  /** Prompt media for audio/image question types. */
  prompt_audio?: string | null
  prompt_image?: string | null
  options: QuizOption[]
  /** Shuffled right-side pool for match_pairs (pairing stays server-side). */
  match_pool?: string[]
}

export interface VideoPayload {
  duration: number | null
  quality: string | null
  /** Direct file URL (local-disk upload). Null until a source is attached. */
  src: string | null
  hls: string | null
  poster: string | null
  captions: unknown[]
}

export interface QuizPayload {
  pass_threshold: number
  hearts_enabled: boolean
  max_attempts?: number | null
  questions: QuizQuestion[]
}

export interface SpeakingPayload {
  prompt: string
  target_text: string | null
}

export interface AssignmentPayload {
  prompt: string
  expected_media: string
  max_duration_seconds: number | null
  coin_reward: number
}

export interface ExercisePayload {
  mode: string
  cards: { id: number; front: string; back: string; mnemonic: string | null; audio: string | null; image: string | null }[]
}

export interface GamePayload {
  game_type: string
  pairs: { a: string; b: string }[]
}

export interface PlayComponent {
  id: number
  type: 'video' | 'quiz' | 'speaking' | 'exercise' | 'game' | 'assignment' | string
  position: number
  xp: number
  /** Video gate: learner must finish the clip before the step unlocks. */
  require_watch?: boolean
  /** Saved playhead (seconds) for the active learner, for resume. */
  resume_position?: number
  /** Whether the active learner already completed this component. */
  completed?: boolean
  video?: VideoPayload | null
  quiz?: QuizPayload | null
  speaking?: SpeakingPayload | null
  assignment?: AssignmentPayload | null
  exercise?: ExercisePayload | null
  game?: GamePayload | null
}

export interface LessonPlay {
  lesson: { id: number; title: string; est_minutes: number | null }
  components: PlayComponent[]
}

export interface AnswerResult {
  correct: boolean
  correct_answer: {
    option_ids?: number[]
    text?: string
    pairs?: { option_id: number; match_target: string }[]
  }
  explanation: string | null
  hearts_remaining: number
  xp_awarded: number
  /** True when a replay past `max_attempts` is graded for practice (nothing scored). */
  attempts_exhausted?: boolean
}

export interface LessonAnalytics {
  lesson: { id: number; title: string }
  learners_started: number
  learners_completed: number
  funnel: { component_id: number; type: string; title: string | null; position: number; reached: number; completed: number }[]
  questions: { question_id: number; prompt: string; type: string; answered: number; correct: number; accuracy: number | null }[]
}

export interface CompleteResult {
  lesson_score: number
  xp_total: number
  streak: { count: number; state: string }
  badges_unlocked: { id: number; name: string }[] | string[]
  next_node: { lesson_id: number; unlocked: boolean } | null
}

// ---- Gamification ----

export interface StreakInfo {
  count: number
  longest: number
  state: string
  frozen_until: string | null
}

export interface HeartsInfo {
  current: number
  refills_at: string | null
}

export interface EarnedBadge {
  code: string
  name: string
  earned_at: string | null
}

export interface LockedBadge {
  code: string
  name: string
  description: string | null
}

export interface BadgesInfo {
  earned: EarnedBadge[]
  locked: LockedBadge[]
}

export interface LeagueStanding {
  league: { id: number; name: string; tier: number | string; week_start: string | null }
  rank: number | null
  weekly_xp: number | null
}

export interface LeaderboardRow {
  rank: number
  learner_id: number
  display_name: string | null
  weekly_xp: number
}

// ---- Referrals & payouts ----

export interface ReferralCode {
  code: string
  status: string
  share_url: string
  share_text: string
}

export interface CommissionStat {
  status: string
  c: number
  /** Sum of amount in minor units. */
  total: number
}

export interface ReferralSummary {
  code: string
  /** Count of referrals keyed by status, e.g. { pending: 2, verified: 1 }. */
  referrals: Record<string, number>
  /** Commission totals keyed by status. */
  commissions: Record<string, CommissionStat>
}

export type PayoutMethod = 'bank' | 'coins'

export interface Payout {
  id: number
  amount_minor: number
  method: PayoutMethod | string
  source: string
  status: string
  requested_at: string | null
  paid_at: string | null
}

export interface RequestPayoutInput {
  amount_minor: number
  method: PayoutMethod
}

// ---- School operations ----

export interface TeacherCompensationMonth {
  period: string
  paying_student_count: number
  rate_minor: number
  amount_minor: number
}

export interface TeacherCompensationSummary {
  available_minor: number
  accrued_total_minor: number
  months: TeacherCompensationMonth[]
}

export interface RequestTeacherCompensationPayoutInput {
  amount_minor: number
}

export interface SchoolDashboard {
  organization: { id: number; name: string; status: string }
  classes: number
  students: number
  seats: { purchased: number; filled: number }
  invoices: { unpaid: number; unpaid_minor: number }
}

export interface SeatAllocation {
  id: number
  total_purchased: number
  active_filled: number
  term_label: string | null
  expires_at: string | null
}

export interface SeatPricingBand {
  label: string
  registration_minor: number
  per_student_minor: number
}

export interface SeatInfo {
  total_purchased: number
  active_filled: number
  bands: SeatPricingBand[]
  allocations: SeatAllocation[]
}

export interface SchoolClassRow {
  id: number
  name: string
  level: string | null
  teacher: string | null
  students: number
}

export interface SchoolClassDetail {
  id: number
  name: string
  level: string | null
  teacher: string | null
  students: { learner_id: number; display_name: string | null }[]
}

export interface ClassAnalyticsStudent {
  learner_id: number
  display_name: string | null
  lessons_completed: number
  avg_score: number | null
  quiz_total: number
  quiz_correct: number
  quiz_accuracy: number | null
  speaking_count: number
  assignments_submitted: number
  assignments_passed: number
}

export interface ClassAnalytics {
  class: { id: number; name: string }
  students: ClassAnalyticsStudent[]
}

export interface SchoolInvoice {
  id: number
  type: string
  amount_minor: number
  status: string
  issued_at: string | null
  paid_at: string | null
  has_pdf: boolean
}

export interface PayInvoiceResult {
  invoice_id: number
  payment_reference: string
  checkout_url: string | null
}

export interface RosterImportResult {
  created: number
  errors: { row: number; error: string }[]
}

export interface PurchaseSeatsInput {
  quantity: number
  term_label?: string
  auto_renew?: boolean
  include_registration?: boolean
}

export interface PurchaseSeatsResult {
  allocation_id: number
  quantity: number
  band: string
  per_student_minor: number
  seats_subtotal_minor: number
  registration_minor: number
  amount_minor: number
  invoice_id: number
}

export interface CreateClassInput {
  name: string
  level?: string
  teacher_user_id?: number
}

export interface ClassAssignmentRow {
  id: number
  title: string
  due_at: string | null
  coin_reward: number
  total_students: number
  submitted_count: number
  graded_count: number
}

export interface ClassAssignmentRosterEntry {
  learner_id: number
  display_name: string | null
  submission_id: number | null
  status: 'submitted' | 'graded' | null
  passed: boolean | null
  score: number | null
  feedback: string | null
  submitted_at: string | null
  graded_at: string | null
  media_url: string | null
}

export interface ClassAssignmentDetail {
  id: number
  title: string
  instructions: string | null
  due_at: string | null
  coin_reward: number
  roster: ClassAssignmentRosterEntry[]
}

export interface CreateClassAssignmentInput {
  title: string
  instructions?: string
  due_at?: string
  coin_reward?: number
}

export interface GradeSubmissionInput {
  passed: boolean
  score?: number
  feedback?: string
}

export interface GradeSubmissionResult {
  submission_id: number
  status: string
  passed: boolean
  coins_released: number
}

// ---- Super admin ----

export interface AdminMetrics {
  users: number
  organizations: Record<string, number>
  subscriptions: Record<string, number>
  revenue_minor: number
  languages: number
}

export interface BillingHealth {
  telco: { attempts: number; success: number; success_rate: number | null }
  funding: Record<string, number>
  subscriptions: Record<string, number>
}

export interface Settlements {
  commissions: Record<string, CommissionStat>
  payouts: Record<string, CommissionStat>
  telco_revenue_minor: number
  clawback: { pending_count: number; pending_minor: number }
}

export interface AdminOrg {
  id: number
  name: string
  type: string
  status: string
  members: number
  classes: number
}

export interface AdminOrgList extends Paginated<AdminOrg> {
  /** Distinct org types, for the filter dropdown. */
  types: string[]
}

export interface AdminOrgQuery {
  q?: string
  type?: string
  status?: string
  page?: number
}

export interface AdminOrgMember {
  id: number
  name: string
  email: string
  role: string
  status: string
}

export interface AdminOrgInvoice {
  id: number
  type: string
  amount_minor: number
  status: string
  issued_at: string | null
}

export interface AdminOrgClass {
  id: number
  name: string
  level: string | null
  students: number
}

export interface AdminOrgReferral {
  id: number
  code: string
  kind: string
  status: string
  created_at: string | null
}

export interface AdminOrgAuditEntry {
  id: number
  action: string
  actor: string | null
  ip: string | null
  created_at: string | null
}

/** Full drill-down for a single organization (GET /admin/organizations/{id}). */
export interface AdminOrgDetail {
  id: number
  name: string
  type: string
  slug: string
  status: string
  contact_email: string | null
  domain: string | null
  domain_verified_at: string | null
  cac_number: string | null
  address: string | null
  created_at: string | null
  counts: { members: number; classes: number; families: number; learners: number }
  seats: { purchased: number; filled: number }
  members: AdminOrgMember[]
  invoices: AdminOrgInvoice[]
  classes: AdminOrgClass[]
  referrals: AdminOrgReferral[]
  audit: AdminOrgAuditEntry[]
}

/** Payload for POST /admin/organizations/{id}/invite-admin. */
export interface InviteOrgAdminInput {
  first_name: string
  last_name: string
  email: string
}

export type OrgStatus = 'pending' | 'active' | 'suspended' | 'inactive'

export interface CreateOrgInput {
  name: string
  type?: string
  contact_email?: string
  domain?: string
  cac_number?: string
  address?: string
  status?: OrgStatus
}

export type UpdateOrgInput = Partial<Omit<CreateOrgInput, 'status'>>

// ---- Admin: plan pricing ----

export interface AdminPlan {
  id: number
  code: string
  name: string
  price_minor: number
  currency: string
  interval: string
  audience: string | null
  max_profiles: number | null
  features: Record<string, boolean | number | string> | null
  editable_flags: string[]
  intervals: string[]
  audiences: string[]
}

export interface UpdatePlanInput {
  name?: string
  price_minor?: number
  interval?: string
  audience?: string | null
  max_profiles?: number | null
  features?: Record<string, boolean>
}

export interface CreatePlanInput {
  code: string
  name: string
  price_minor: number
  interval: string
  audience?: string | null
  max_profiles?: number | null
  features?: Record<string, boolean>
}

// ---- Admin: language control ----

export interface AdminLanguage {
  id: number
  code: string
  name: string
  script: string
  rtl: boolean
  is_active: boolean
  position: number
  courses_total: number
  courses_published: number
}

// ---- Support tickets ----

export interface SupportMessage {
  id: number
  body: string
  is_staff: boolean
  author: string | null
  created_at: string | null
}

export interface SupportTicket {
  id: number
  subject: string
  category: string | null
  message: string | null
  status: string
  priority: string
  channel?: string
  email?: string | null
  requester?: string | null
  assignee?: string | null
  assigned_to?: number | null
  response: string | null
  resolved_at?: string | null
  created_at: string | null
  messages?: SupportMessage[]
}

export interface SupportAssignee {
  id: number
  name: string
}

export interface AdminTicketsPage extends Paginated<SupportTicket> {
  open_count: number
  assignees: SupportAssignee[]
}

export interface AdminTicketsQuery {
  status?: string
  q?: string
  page?: number
}

export interface CreateTicketInput {
  subject: string
  category?: string
  message: string
}

export interface UpdateTicketInput {
  status?: string
  priority?: string
  assigned_to?: number | null
  response?: string | null
}

// ---- Admin: fraud review ----

export interface FlaggedReferral {
  id: number
  code: string
  kind: string
  status: string
  owner: { type: 'user' | 'organization'; id: number; name: string | null }
  referrals_total: number
  referrals_24h: number
  updated_at: string | null
}

// ---- Admin: system settings ----

export type SettingType = 'int' | 'bool' | 'string'
export type SettingValue = number | boolean | string

export interface SettingItem {
  key: string
  label: string
  help: string | null
  type: SettingType
  min: number | null
  max: number | null
  value: SettingValue
}

export interface SettingGroup {
  key: string
  label: string
  settings: SettingItem[]
}

export interface SettingsResponse {
  groups: SettingGroup[]
}

// ---- Admin: audit log ----

export interface AuditLogRow {
  id: number
  action: string
  actor: { id: number; name: string; email: string } | null
  subject: { type: string; id: number | null } | null
  ip: string | null
  before: Record<string, unknown> | null
  after: Record<string, unknown> | null
  created_at: string | null
}

export interface AuditLogPage extends Paginated<AuditLogRow> {
  /** Distinct action names, for the filter dropdown. */
  actions: string[]
}

export interface AuditLogQuery {
  action?: string
  q?: string
  from?: string
  to?: string
  page?: number
}

// ---- Admin: payment gateways ----

export interface GatewayRequirement {
  label: string
  env: string
  set: boolean
}

export interface GatewayProvider {
  key: string
  label: string
  configured: boolean
  is_default: boolean
  webhook_url: string
  requirements: GatewayRequirement[]
}

export interface GatewayStatus {
  /** PAYMENT_GATEWAY_LIVE — whether outbound checkout calls are enabled. */
  live: boolean
  default: string
  providers: GatewayProvider[]
}

export interface GatewayTestResult {
  ok: boolean
  message: string
}

// ---- Admin: income report ----

export interface IncomeChannel {
  key: string
  label: string
  /** month (YYYY-MM) → amount in minor units. */
  by_month: Record<string, number>
  gross: number
  refunds: number
  net: number
}

export interface IncomeReport {
  from: string
  to: string
  months: string[]
  channels: IncomeChannel[]
  totals: { by_month: Record<string, number>; gross: number; refunds: number; net: number }
}

export interface IncomeReportQuery {
  from?: string
  to?: string
}

export interface ReportSeries {
  key: string
  label: string
  by_month: Record<string, number>
  total: number
}

export interface GrowthReport {
  from: string
  to: string
  months: string[]
  series: ReportSeries[]
  totals: { users: number; organizations: number }
}

export interface SubscriptionsReport {
  from: string
  to: string
  months: string[]
  new: ReportSeries
  by_status: Record<string, number>
  active: number
  total: number
}

export interface ReferralsReport {
  from: string
  to: string
  months: string[]
  new: ReportSeries
  referrals_by_status: Record<string, number>
  commissions_by_status: Record<string, { count: number; total_minor: number }>
}

export interface OrgActivityReport {
  from: string
  to: string
  months: string[]
  new: ReportSeries
  by_status: Record<string, number>
  totals: { organizations: number; classes: number; students: number }
}

// ---- Admin: email (campaigns, contacts, log) ----

export interface EmailCampaignRow {
  id: number
  subject: string
  audience_type: 'user_segment' | 'contact_list'
  status: string
  scheduled_at: string | null
  recipients_count: number
  sent_count: number
  failed_count: number
  sent_at: string | null
  created_at: string | null
}

export interface EmailCampaignDetail extends EmailCampaignRow {
  body: string
  audience: Record<string, unknown> | null
  recipients_by_status: Record<string, number>
}

export interface CreateCampaignInput {
  subject: string
  body: string
  audience_type: 'user_segment' | 'contact_list'
  audience?: Record<string, unknown>
}

export interface CampaignRecipientRow {
  id: number
  email: string
  status: string
}

export interface ContactListRow {
  id: number
  name: string
  description: string | null
  contacts: number
  subscribed: number
  created_at: string | null
}

export interface ContactRow {
  id: number
  email: string
  name: string | null
  status: string
  source: string | null
}

export interface ContactListDetail extends Paginated<ContactRow> {
  data: ContactRow[]
  list: { id: number; name: string; description: string | null }
}

export interface ImportPreview {
  counts: { total: number; valid: number; duplicate: number; invalid: number; suppressed: number }
  valid: { email: string; name: string | null }[]
}

export interface UploadBatchRow {
  id: number
  imported: number
  skipped: number
  status: string
  created_at: string | null
}

export interface EmailLogRow {
  id: number
  to_email: string
  type: string
  source: string | null
  subject: string | null
  status: string
  sent_at: string | null
  created_at: string | null
}

export interface EmailLogPage extends Paginated<EmailLogRow> {
  sources: string[]
}

export interface EmailLogQuery {
  q?: string
  type?: string
  status?: string
  source?: string
  from?: string
  to?: string
  page?: number
}

export interface EmailTemplateSummary {
  key: string
  label: string
  category: string
  trigger: string
  customizable: boolean
  customized: boolean
}

export interface EmailTemplatePreview {
  key: string
  subject: string
  html: string
}

export interface EmailTemplateContent {
  subject: string
  greeting: string | null
  body: string
  action_text: string | null
  action_url: string | null
}

export interface EmailTemplateOverrideContent extends EmailTemplateContent {
  updated_at: string | null
}

export interface EmailTemplateDetail {
  key: string
  label: string
  category: string
  trigger: string
  customizable: boolean
  placeholders: Record<string, string>
  default: EmailTemplateContent | null
  override: EmailTemplateOverrideContent | null
}

export interface RenewalsReport {
  from: string
  to: string
  months: string[]
  count: ReportSeries
  revenue: ReportSeries
  by_method: Record<string, number>
  reminders: { reminded: number; total: number }
}

// ---- Admin: payouts ----

export interface AdminPayout {
  id: number
  amount_minor: number
  method: string
  source: string
  status: string
  requested_at: string | null
  paid_at: string | null
  approved_by: number | null
  beneficiary: { type: 'user' | 'organization'; id: number; name: string | null }
}

export interface AdminPayoutsQuery {
  status?: string
  page?: number
}

// ---- Admin: users & roles ----

export type UserStatus = 'active' | 'suspended'

export interface AdminUserOrg {
  id: number
  name: string | null
  role: string
  status: string
}

export interface AdminUserRow {
  id: number
  name: string
  email: string
  phone: string | null
  status: string
  roles: Role[]
  email_verified: boolean
  created_at: string | null
  last_login_at: string | null
  organizations: AdminUserOrg[]
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export interface AdminUsersQuery {
  q?: string
  role?: string
  status?: string
  organization_id?: number
  page?: number
}

export interface AssignRoleInput {
  role: Role
  action: 'assign' | 'revoke'
}

/** GET /admin/roles — permission-matrix data. */
export interface PermissionGroup {
  group: string
  permissions: string[]
}

export interface RolesMatrix {
  roles: Role[]
  groups: PermissionGroup[]
  /** role name → list of permission names it holds. */
  matrix: Record<string, string[]>
}

export interface CreatePromoInput {
  code: string
  discount_type: 'percent' | 'fixed'
  value: number
  applicable_tier?: string
  valid_from?: string
  valid_to?: string
  max_redemptions?: number
}

/** Response from GET /config — launch-time bootstrap (public). */
export interface AppConfig {
  min_supported_version: { ios: string | null; android: string | null }
  /** Age below which a learner needs verifiable parental consent (admin-set). */
  digital_age: number
  feature_flags: Record<string, unknown>
  cdn_base: string | null
  languages: AppLanguage[]
}

// ---- request payloads ----

export interface LoginInput {
  /** Email OR username. */
  login: string
  password: string
}

export interface RegisterInput {
  first_name: string
  last_name: string
  email: string
  password: string
  password_confirmation: string
  username?: string
  /** Defaults to 'parent' on the backend. */
  account_type?: 'parent' | 'learner'
  family_name?: string
  date_of_birth?: string
  referral_code?: string
}

/* ── Language & Culture competition ─────────────────────────────────────── */

export type CompetitionCategory = 'school_play' | 'diaspora_folklore'
export type CompetitionStatus = 'draft' | 'open' | 'voting' | 'closed'

export interface CompetitionSummary {
  id: number
  title: string
  slug: string
  season: number
  status: CompetitionStatus
  entries_count: number
  submissions_close_at: string | null
  voting_closes_at: string | null
  min_activity_days: number
  accepting_entries: boolean
  accepting_votes: boolean
}

export interface CompetitionEntry {
  id: number
  category: CompetitionCategory
  title: string
  synopsis: string | null
  status: string
  votes_count: number
  award_rank: number | null
  entrant: string | null
  language: string | null
}

export interface CompetitionDetail extends CompetitionSummary {
  description: string | null
  voted_categories: CompetitionCategory[]
  can_enter: boolean
  entries: CompetitionEntry[]
}

export interface SubmitEntryInput {
  category: CompetitionCategory
  title: string
  synopsis?: string
  language_id?: number
  organization_id?: number
  learner_profile_id?: number
}

export interface MyCompetitionEntry {
  id: number
  competition: string
  season: number
  category: CompetitionCategory
  title: string
  status: string
  votes_count: number
  award_rank: number | null
}

/** Admin (organiser) view of a competition — carries the status option list. */
export interface AdminCompetition {
  id: number
  title: string
  slug: string
  season: number
  description: string | null
  status: CompetitionStatus
  statuses: CompetitionStatus[]
  submissions_close_at: string | null
  voting_closes_at: string | null
  min_activity_days: number
  entries_count: number
}

export interface CreateCompetitionInput {
  title: string
  season: number
  description?: string
  min_activity_days?: number
}

export type EntryModeration = 'approved' | 'rejected' | 'disqualified'

export interface AdminCompetitionEntry {
  id: number
  category: CompetitionCategory
  title: string
  synopsis: string | null
  status: string
  votes_count: number
  award_rank: number | null
  entrant: string | null
  language: string | null
  submitted_at: string | null
}

export interface AdminCompetitionDetail extends AdminCompetition {
  entries: AdminCompetitionEntry[]
}

/* ── Public marketing pricing ───────────────────────────────────────────── */

export interface PricingConsumerPlan {
  code: string
  name: string
  audience: 'individual' | 'family' | null
  price_minor: number
  currency: string
  interval: string
  max_profiles: number | null
  features: {
    ads: boolean
    offline_download: boolean
    unlimited_hearts: boolean
    family_dashboard: boolean
  }
}

export interface PricingBand {
  label: string
  registration_minor: number
  per_student_minor: number
}

export interface PricingInfo {
  free: { name: string; blurb: string }
  consumer: PricingConsumerPlan[]
  school: { term_months: number; bands: PricingBand[] }
}
