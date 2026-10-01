import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { DashboardPage } from './DashboardPage'

const mocks = vi.hoisted(() => ({
  useAuth: vi.fn(),
  useCoursesPerformance: vi.fn(),
  useReferralActivations: vi.fn(),
}))

vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: mocks.useAuth }))
vi.mock('@/lib/content/queries', () => ({ useCoursesPerformance: mocks.useCoursesPerformance }))
vi.mock('@/lib/referral/queries', () => ({ useReferralActivations: mocks.useReferralActivations }))

const performance = {
  id: 1,
  title: 'Everyday Yorùbá',
  language: 'Yorùbá',
  is_published: true,
  levels_count: 2,
  lessons_count: 8,
  enrollments: 14,
  enrollments_by_status: { active: 14 },
  lesson_completions: 20,
  quiz_accuracy: 0.8,
  subscribers: 12,
  subscriptions_by_status: { active: 8, cancelled: 2, pending: 2 },
  referred_subscribers: 3,
  attributed_revenue_minor: 120000,
  pending_revenue_minor: 30000,
  referral_revenue_minor: 45000,
  referral_commission_minor: 5000,
  pending_commission_minor: 7000,
}

describe('DashboardPage for a content owner', () => {
  beforeEach(() => {
    mocks.useReferralActivations.mockReturnValue({ data: { data: [], meta: { total: 0 } } })
    mocks.useAuth.mockReturnValue({
      user: { user: { first_name: 'Amina' } },
      hasRole: (role: string) => role === 'content_owner',
    })
    mocks.useCoursesPerformance.mockReturnValue({
      data: [performance],
      isLoading: false,
      isFetching: false,
      isError: false,
    })
  })

  it('shows course-level subscription, referral, and pending performance on the home dashboard', () => {
    render(<MemoryRouter><DashboardPage /></MemoryRouter>)

    expect(screen.getByRole('heading', { name: 'Performance by course' })).toBeInTheDocument()
    expect(screen.getByText('Everyday Yorùbá')).toBeInTheDocument()
    expect(screen.getByText('8 active')).toBeInTheDocument()
    expect(screen.getByText('2 cancelled')).toBeInTheDocument()
    expect(screen.getAllByText('2 pending')).toHaveLength(2)
    expect(screen.getByText('3 referred users')).toBeInTheDocument()
    expect(screen.getAllByText('₦370.00')).toHaveLength(2)
  })

  it('filters the per-course dashboard', () => {
    render(<MemoryRouter><DashboardPage /></MemoryRouter>)

    fireEvent.change(screen.getByRole('textbox', { name: 'Search courses or languages' }), {
      target: { value: 'Igbo' },
    })

    expect(screen.queryByText('Everyday Yorùbá')).not.toBeInTheDocument()
    expect(screen.getByText('No courses match these filters.')).toBeInTheDocument()
  })

  it.each(['parent', 'teacher', 'school_admin', 'supervisor', 'student', 'content_owner'])('shows personal referral activity on the %s profile', (role) => {
    mocks.useAuth.mockReturnValue({
      user: { user: { id: 1, first_name: 'Amina', roles: [role] }, families: [], organizations: [] },
      hasRole: (...roles: string[]) => roles.includes(role),
    })
    mocks.useReferralActivations.mockReturnValue({ data: {
      data: [{ sn: 1, code: 'AMINA', activated_at: '2026-09-30', status: 'active', via_email: 'friend@example.test', via_phone: '+2348012345678' }],
      meta: { total: 1, last_page: 1 },
    } })
    render(<MemoryRouter><DashboardPage /></MemoryRouter>)
    expect(screen.getByRole('region', { name: 'Referral activity' })).toBeInTheDocument()
    expect(screen.getByText('friend@example.test')).toBeInTheDocument()
    expect(screen.getByText('+2348012345678')).toBeInTheDocument()
    expect(screen.getByText('Active')).toBeInTheDocument()
  })

  it('hides the referral section when the profile has no activity', () => {
    render(<MemoryRouter><DashboardPage /></MemoryRouter>)
    expect(screen.queryByRole('region', { name: 'Referral activity' })).not.toBeInTheDocument()
  })
})
