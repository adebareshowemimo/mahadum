import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { batch4Api } from '@/lib/batch4/api'
import { SchoolStudentsPage, SchoolTeachersPage } from './SchoolDirectoriesPage'
import { TeacherInvitationPage } from './TeacherInvitationPage'
import { FamilyGoalsPage } from './FamilyGoalsPage'
import { NotificationsPage } from './NotificationsPage'

const auth = vi.hoisted(() => ({
  user: {
    user: { id: 1, email: 'teacher@example.com', email_verified: true },
    organizations: [],
    families: [],
  },
  status: 'authenticated',
  refresh: vi.fn(),
  setActiveOrg: vi.fn(),
  hasRole: () => true,
}))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => auth }))
vi.mock('@/components/school/SchoolGate', () => ({
  SchoolGate: ({ children }: { children: (id: number) => React.ReactNode }) =>
    children(7),
}))
vi.mock('@/lib/batch4/api', () => ({
  batch4Api: {
    students: vi.fn(),
    teachers: vi.fn(),
    invite: vi.fn(),
    revoke: vi.fn(),
    invitation: vi.fn(),
    accept: vi.fn(),
    goals: vi.fn(),
    challenge: vi.fn(),
    pool: vi.fn(),
    move: vi.fn(),
    alerts: vi.fn(),
    cheer: vi.fn(),
    cheers: vi.fn(),
  },
}))
vi.mock('@/lib/family/queries', () => ({
  familyKeys: { family: ['family'], wallet: ['wallet'] },
  useFamily: () => ({ data: { learners: [{ id: 3, display_name: 'Ada' }] } }),
  useWallet: () => ({ data: { coin_balance: 50 } }),
}))
vi.mock('@/lib/api/client', () => ({
  api: {
    get: vi.fn(async () => ({
      data: { data: [], unread: 0, meta: { last_page: 1 } },
    })),
    post: vi.fn(),
  },
}))
vi.mock('@/lib/notifications/browserPush', () => ({
  pushSupported: () => false,
  currentPush: vi.fn(),
  enablePush: vi.fn(),
  disablePush: vi.fn(),
  pushApi: {
    config: vi.fn(async () => ({ enabled: false, public_key: null })),
  },
}))

function setup(page: React.ReactNode, path = '/') {
  const cache = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })
  return render(
    <QueryClientProvider client={cache}>
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route path="*" element={page} />
          <Route path="/teacher-invitations/:token" element={page} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}
beforeEach(() => {
  vi.clearAllMocks()
  auth.user.user.email = 'teacher@example.com'
  auth.user.user.email_verified = true
  vi.mocked(batch4Api.goals).mockResolvedValue({
    challenges: [],
    pools: [
      {
        id: 8,
        name: 'Trip',
        coin_balance: 10,
        goal_coins: 100,
        transactions: [],
      },
    ],
    alerts: {
      low_balance_coins: null,
      inactive_days: null,
      review_alerts: false,
    },
  })
  vi.mocked(batch4Api.teachers).mockResolvedValue({
    teachers: [],
    invitations: [],
  })
  vi.mocked(batch4Api.invitation).mockResolvedValue({
    name: 'Teacher',
    email: 'teacher@example.com',
    organization_name: 'School',
    status: 'pending',
    expires_at: '2026-12-01',
  })
})
describe('Batch 4 flows', () => {
  it('filters school students by class while retaining managed learner labels', async () => {
    vi.mocked(batch4Api.students).mockResolvedValue([
      {
        id: 1,
        name: 'Ada',
        email: null,
        classes: [{ id: 5, name: 'Yoruba' }],
      },
      { id: 2, name: 'Bola', email: 'bola@example.com', classes: [] },
    ])
    setup(<SchoolStudentsPage />)
    expect(await screen.findByText('Ada')).toBeInTheDocument()
    fireEvent.change(screen.getByLabelText('Search students or classes'), {
      target: { value: 'Yoruba' },
    })
    expect(screen.queryByText('Bola')).not.toBeInTheDocument()
    expect(screen.getByText('Managed learner · no login')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Yoruba' })).toHaveAttribute(
      'href',
      '/classes/5',
    )
  })
  it('records a teacher invitation and reports unconfigured email honestly', async () => {
    vi.mocked(batch4Api.invite).mockResolvedValue({
      delivery_status: 'not_configured',
    })
    setup(<SchoolTeachersPage />)
    fireEvent.change(screen.getByLabelText('Teacher name'), {
      target: { value: 'Amara' },
    })
    fireEvent.change(screen.getByLabelText('Teacher email'), {
      target: { value: 'amara@example.com' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Send invitation' }))
    expect(
      await screen.findByText(/Email delivery is not configured/),
    ).toBeInTheDocument()
    expect(batch4Api.invite).toHaveBeenCalledWith(7, {
      name: 'Amara',
      email: 'amara@example.com',
    })
  })
  it('prevents accepting an invitation with another account email', async () => {
    auth.user.user.email = 'wrong@example.com'
    setup(<TeacherInvitationPage />, `/teacher-invitations/${'a'.repeat(64)}`)
    expect(
      await screen.findByRole('button', {
        name: 'Accept teaching invitation',
      }),
    ).toBeDisabled()
    expect(
      screen.getByText(/Sign in with the email address/),
    ).toBeInTheDocument()
    expect(batch4Api.accept).not.toHaveBeenCalled()
  })
  it('requires verification before the recipient can accept', async () => {
    auth.user.user.email_verified = false
    setup(<TeacherInvitationPage />, `/teacher-invitations/${'a'.repeat(64)}`)
    expect(
      await screen.findByRole('button', {
        name: 'Accept teaching invitation',
      }),
    ).toBeDisabled()
    expect(screen.getByRole('link', { name: 'Verify email' })).toHaveAttribute(
      'href',
      '/verify-email',
    )
  })
  it('keeps the same pool movement identity after a lost response', async () => {
    vi.mocked(batch4Api.move)
      .mockRejectedValueOnce(new Error('Connection lost'))
      .mockResolvedValueOnce(undefined)
    setup(<FamilyGoalsPage />)
    fireEvent.change(await screen.findByLabelText('Coins to move for Trip'), {
      target: { value: '5' },
    })
    fireEvent.click(
      screen.getByRole('button', { name: 'Approve coin movement' }),
    )
    expect(await screen.findByText('Connection lost')).toBeInTheDocument()
    fireEvent.click(
      screen.getByRole('button', { name: 'Approve coin movement' }),
    )
    await waitFor(() => expect(batch4Api.move).toHaveBeenCalledTimes(2))
    const calls = vi.mocked(batch4Api.move).mock.calls
    expect(calls[0]?.[2]).toBe(calls[1]?.[2])
    expect(calls[0]?.[1]).toEqual({ direction: 'contribute', coins: 5 })
    expect(await screen.findByText('Saved.')).toBeInTheDocument()
  })
  it('saves blank alert thresholds as disabled without inventing defaults', async () => {
    vi.mocked(batch4Api.alerts).mockResolvedValue(undefined)
    setup(<FamilyGoalsPage />)
    fireEvent.click(
      await screen.findByRole('button', {
        name: 'Save alert preferences',
      }),
    )
    await waitFor(() =>
      expect(batch4Api.alerts).toHaveBeenCalledWith({
        low_balance_coins: null,
        inactive_days: null,
        review_alerts: false,
      }),
    )
  })
  it('requires participants before creating a challenge', async () => {
    setup(<FamilyGoalsPage />)
    expect(
      await screen.findByRole('button', { name: 'Create challenge' }),
    ).toBeDisabled()
    fireEvent.click(screen.getByLabelText('Ada'))
    expect(
      screen.getByRole('button', { name: 'Create challenge' }),
    ).toBeEnabled()
  })
  it('shows the notification inbox without requesting browser permission', async () => {
    setup(<NotificationsPage />)
    expect(await screen.findByText('You’re all caught up.')).toBeInTheDocument()
    expect(
      screen.queryByRole('button', {
        name: 'Enable browser notifications',
      }),
    ).not.toBeInTheDocument()
  })
})
