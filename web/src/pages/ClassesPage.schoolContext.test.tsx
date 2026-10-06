import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, schoolApi } from '@/lib/api'
import { ClassesPage } from './ClassesPage'

const auth = vi.hoisted(() => ({ orgId: null as number | null }))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => ({
  activeOrgId: auth.orgId,
  user: { user: { id: 7, roles: ['parent', 'teacher'] }, organizations: auth.orgId ? [{ id: auth.orgId }] : [] },
  hasRole: (...roles: string[]) => roles.some((role) => ['parent', 'teacher'].includes(role)),
}) }))

function mount() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(<QueryClientProvider client={client}><MemoryRouter><ClassesPage /></MemoryRouter></QueryClientProvider>)
}

describe('mixed parent and teacher school context', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    auth.orgId = null
    vi.spyOn(schoolApi, 'classes').mockResolvedValue([])
  })

  it('does not request unscoped classes or offer class creation without a school membership', async () => {
    mount()
    expect(await screen.findByText(/no school linked/i)).toBeInTheDocument()
    await waitFor(() => expect(schoolApi.classes).not.toHaveBeenCalled())
    expect(screen.queryByRole('button', { name: /new class|create class/i })).not.toBeInTheDocument()
    expect(screen.queryByText(/please refresh/i)).not.toBeInTheDocument()
  })

  it('loads the mixed-role teachers own classes in the active school', async () => {
    auth.orgId = 3
    vi.mocked(schoolApi.classes).mockResolvedValue([{ id: 11, name: 'Assigned class', level: 'L1', teacher: 'Teacher', students: 1 }])
    mount()
    expect(await screen.findByRole('link', { name: /assigned class/i })).toBeInTheDocument()
    expect(schoolApi.classes).toHaveBeenCalledWith({ mine: true })
  })

  it('explains a forbidden response without promising that refresh grants access', async () => {
    auth.orgId = 3
    vi.mocked(schoolApi.classes).mockRejectedValue(new ApiError('Forbidden', 'http_error', 403))
    mount()
    expect(await screen.findByText(/ask a school administrator to check your active teacher membership/i)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /new class/i })).not.toBeInTheDocument()
  })
})
