import type { ReactNode } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { adminApi, schoolApi, type AdminMetrics, type SchoolDashboard, type SettingsResponse } from '@/lib/api'
import { AssignmentsPage, AssignmentDetailContent } from './AssignmentsPage'
import { ClassPage } from './ClassPage'
import { SchoolDashboardPage } from './SchoolDashboardPage'
import { AdminOverviewPage } from './AdminOverviewPage'
import { SettlementsPage } from './SettlementsPage'

const auth = vi.hoisted(() => ({ roles: ['school_admin'] }))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => ({ activeOrgId: 3, user: { user: { id: 7, roles: auth.roles }, organizations: [{ id: 3 }] }, hasRole: (...roles: string[]) => roles.some(role => auth.roles.includes(role)) }) }))
vi.mock('@/components/referral/ReferralActivity', () => ({ ReferralActivity: () => null }))
beforeEach(() => { auth.roles = ['school_admin'] })
afterEach(() => vi.restoreAllMocks())

function show(page: ReactNode, entry = '/') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(<QueryClientProvider client={client}><MemoryRouter initialEntries={[entry]}>{page}</MemoryRouter></QueryClientProvider>)
  return client
}
const stats = { lesson_targets: 4, lessons_completed: 1, completion_rate: 25, quiz_scored: 3, avg_quiz_score: 80, speaking_scored: 0, avg_speaking_score: null }
const metrics: AdminMetrics = { users: 20, users_by_type: { school: 5, family: 10, single: 5 }, organizations: { active: 2 }, subscriptions: { active: 3 }, languages: 1, revenue_minor: 12345,
  revenue_channels: { wallet_funding_minor: 0, school_invoices_minor: 0, telco_minor: 0, subscription_payments_minor: 12345 },
  language_analytics: [{ id: 1, name: 'Yoruba', active: true, learners: 2, lessons_completed: 1, quizzes_scored: 3, avg_quiz_score: 80 }],
  ai_analytics: { enabled: true, scoring_status: 'deferred', submissions: 2, scored: 0, needs_review: 2, avg_stored_score: null } }
function setting(bps = 500): SettingsResponse { return { groups: [{ key: 'referrals', label: 'Referrals', settings: [{ key: 'referral.commission_bps', label: 'Commission', help: null, type: 'int', min: 0, max: 10000, value: bps }] }] } }

describe('Batch 3 assignment and reporting surfaces', () => {
  it('lets a school admin reach creation and submit the two-step wizard for the linked class', async () => {
    vi.spyOn(schoolApi, 'classes').mockResolvedValue([{ id: 11, name: 'Alpha', level: null, teacher: 'Teacher', students: 2 }])
    vi.spyOn(schoolApi, 'classAssignments').mockResolvedValue([])
    vi.spyOn(schoolApi, 'classCompletion').mockResolvedValue([])
    vi.spyOn(schoolApi, 'createClassAssignment').mockResolvedValue({ id: 9, title: 'Greetings' })
    show(<AssignmentsPage />, '/assignments?class=11')
    const user = userEvent.setup()
    await user.click(await screen.findByRole('button', { name: 'Create assignment' }))
    const modal = screen.getByRole('dialog', { name: 'Create assignment' })
    await user.click(within(modal).getByRole('button', { name: 'Next' }))
    expect(within(modal).getByText('Enter an assignment title.')).toBeInTheDocument()
    await user.type(within(modal).getByLabelText('Title'), 'Greetings')
    await user.type(within(modal).getByLabelText('Instructions (optional)'), 'Write a greeting')
    await user.click(within(modal).getByRole('button', { name: 'Next' }))
    expect(within(modal).getByText(/Coins wait for parent approval/)).toBeInTheDocument()
    await user.type(within(modal).getByLabelText('Coin reward'), '50')
    await user.click(within(modal).getByRole('button', { name: 'Create assignment' }))
    await waitFor(() => expect(schoolApi.createClassAssignment).toHaveBeenCalledWith(11, { title: 'Greetings', instructions: 'Write a greeting', due_at: undefined, coin_reward: 50 }))
    expect(schoolApi.classes).toHaveBeenCalledWith({ mine: false })
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('uses the server capability for a teacher’s class creation control', async () => {
    auth.roles = ['teacher']
    vi.spyOn(schoolApi, 'classDetail').mockResolvedValue({ id: 11, name: 'Alpha', teacher: 'Other teacher', level: null, capabilities: { update: false, assign_teacher: false, create_assignment: false }, students: [] })
    vi.spyOn(schoolApi, 'classCourses').mockResolvedValue([])
    vi.spyOn(schoolApi, 'classAssignments').mockResolvedValue([])
    show(<Routes><Route path="/classes/:classId" element={<ClassPage />} /></Routes>, '/classes/11?tab=assignments')
    expect(await screen.findByText('No assignments yet')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Create assignment' })).not.toBeInTheDocument()
  })

  it('shows school-admin assignment submissions without offering teacher grading', async () => {
    vi.spyOn(schoolApi, 'classAssignmentDetail').mockResolvedValue({ id: 9, title: 'Greetings', instructions: 'Write', coin_reward: 50, due_at: null, can_grade: false, roster: [{ learner_id: 1, display_name: 'Ada', submission_id: 1, status: 'submitted', passed: null, score: null, feedback: null, text_body: 'E kaaro', media_url: null, submitted_at: null, graded_at: null }] })
    show(<AssignmentDetailContent classId={11} assignmentId={9} />)
    expect(await screen.findByText('E kaaro')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Grade' })).not.toBeInTheDocument()
  })

  it('shows weighted school performance, missing speech scores and ranked class destinations', async () => {
    vi.spyOn(schoolApi, 'dashboard').mockResolvedValue({ organization: { id: 3, name: 'QA School', status: 'active' }, classes: 1, students: 2, seats: { purchased: 10, filled: 2 }, invoices: { unpaid: 0, unpaid_minor: 0 }, subscription: { status: 'active', last_payment_at: null }, learning: { ...stats, top_students: [{ ...stats, id: 1, name: 'Ada' }], top_classes: [{ ...stats, id: 11, name: 'Alpha' }] } } satisfies SchoolDashboard)
    vi.spyOn(schoolApi, 'classes').mockResolvedValue([])
    vi.spyOn(schoolApi, 'teachers').mockResolvedValue([])
    show(<SchoolDashboardPage />)
    expect(await screen.findByText('Average quiz score')).toBeInTheDocument()
    expect(screen.getByText('80%')).toBeInTheDocument()
    expect(screen.getByText('1/4 enrolled lesson targets')).toBeInTheDocument()
    expect(screen.getByText('No scoring data yet')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Alpha' })).toHaveAttribute('href', '/classes/11?tab=analytics')
  })

  it('renders language and honest deferred-AI metrics with recorded subscription revenue', async () => {
    vi.spyOn(adminApi, 'metrics').mockResolvedValue(metrics)
    vi.spyOn(adminApi, 'billingHealth').mockResolvedValue({ telco: { attempts: 0, success: 0, success_rate: null }, funding: {}, subscriptions: {} })
    show(<AdminOverviewPage />)
    expect(await screen.findByRole('heading', { name: 'Language analytics' })).toBeInTheDocument()
    expect(screen.getByText('Yoruba')).toBeInTheDocument()
    expect(screen.getByText('80%')).toBeInTheDocument()
    expect(screen.getAllByText('₦123.45')).toHaveLength(2)
    expect(screen.getByText(/AI scoring is deferred/)).toBeInTheDocument()
    expect(screen.getByText(/earlier subscription charges are unavailable/)).toBeInTheDocument()
  })

  it('saves a percentage as basis points and shows recorded daily billing, not an invented share', async () => {
    vi.spyOn(adminApi, 'settlements').mockResolvedValue({ commissions: {}, payouts: {}, telco_revenue_minor: 300, clawback: { pending_count: 0, pending_minor: 0 }, referral_commission_bps: 500, daily_telco: { timezone: 'UTC', days: [{ date: '2026-10-07', attempts: 4, success: 2, success_rate: 50 }] }, telco_share: { status: 'not_configured', platform_bps: null, platform_minor: null } })
    vi.spyOn(adminApi, 'settings').mockResolvedValue(setting())
    vi.spyOn(adminApi, 'updateSettings').mockResolvedValue(setting(625))
    show(<SettlementsPage />)
    const input = await screen.findByLabelText('Referral commission (%)')
    expect(input).toHaveValue(5)
    await userEvent.clear(input)
    await userEvent.type(input, '6.25')
    await userEvent.click(screen.getByRole('button', { name: 'Save percentage' }))
    await waitFor(() => expect(adminApi.updateSettings).toHaveBeenCalledWith({ 'referral.commission_bps': 625 }))
    expect(await screen.findByRole('status')).toHaveTextContent('Referral commission updated.')
    expect(input).toHaveValue(6.25)
    expect(screen.getByText('50%')).toBeInTheDocument()
    expect(screen.getByText(/approved operator\/platform split is required/)).toBeInTheDocument()
  })
})
