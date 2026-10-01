import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { referralApi, schoolApi } from '@/lib/api'
import { ReferralActivity } from '@/components/referral/ReferralActivity'

const auth = vi.hoisted(() => ({ userId: 1 }))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => ({ user: { user: { id: auth.userId } } }) }))

afterEach(() => { vi.restoreAllMocks(); auth.userId = 1 })

describe('Referral activity', () => {
  it('shows contacts and activation state, reaches later pages and resets paging for search', async () => {
    const api = vi.spyOn(referralApi, 'activations').mockImplementation(async (params = {}) => ({
      data: [{
        sn: params.page === 2 ? 21 : 1,
        code: 'MYCODE',
        activated_at: params.page === 2 ? '2026-09-07' : null,
        via_email: params.page === 2 ? 'active@example.test' : 'pending@example.test',
        via_phone: '+2348011111111',
        status: params.page === 2 ? 'active' : 'pending',
      }],
      meta: { current_page: params.page ?? 1, last_page: 2, per_page: 20, total: 21 },
    }))
    const user = userEvent.setup()
    render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <ReferralActivity />
    </QueryClientProvider>)
    expect(await screen.findByText('pending@example.test')).toBeInTheDocument()
    expect(screen.getByText('+2348011111111')).toBeInTheDocument()
    expect(screen.getByText('Pending')).toBeInTheDocument()
    for (const name of ['Activation date', 'Activation status', 'Email', 'Phone number']) {
      expect(screen.getByRole('columnheader', { name })).toBeInTheDocument()
    }
    expect(screen.getByRole('button', { name: 'Previous' })).toBeDisabled()
    await user.click(screen.getByRole('button', { name: 'Next' }))
    expect(await screen.findByText('active@example.test')).toBeInTheDocument()
    expect(screen.getByText('Active')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Next' })).toBeDisabled()
    await user.type(screen.getByRole('textbox', { name: 'Search referral activity' }), 'pending')
    await waitFor(() => expect(api).toHaveBeenLastCalledWith({ search: 'pending', page: 1 }))
    expect(await screen.findByText('pending@example.test')).toBeInTheDocument()
  })

  it('loads the school activity endpoint and shows a pending activation', async () => {
    const personal = vi.spyOn(referralApi, 'activations')
    const school = vi.spyOn(schoolApi, 'referralActivations').mockResolvedValue({
      data: [{ sn: 1, code: 'SCHOOL', activated_at: null, via_email: 'school-friend@example.test', via_phone: null, status: 'pending' }],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
    })
    render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <ReferralActivity organizationId={12} />
    </QueryClientProvider>)
    expect(await screen.findByText('school-friend@example.test')).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'School referral activity' })).toBeInTheDocument()
    expect(screen.getByText('Pending')).toBeInTheDocument()
    expect(school).toHaveBeenCalledWith(12, { search: undefined, page: 1 })
    expect(personal).not.toHaveBeenCalled()
  })

  it('does not reuse another account’s cached contacts after switching users', async () => {
    const api = vi.spyOn(referralApi, 'activations').mockImplementation(async () => ({
      data: [{ sn: 1, code: 'OWN', activated_at: null, via_email: `owner${auth.userId}@example.test`, via_phone: null, status: 'pending' }],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
    }))
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const view = () => <QueryClientProvider client={client}><ReferralActivity /></QueryClientProvider>
    const { rerender } = render(view())
    expect(await screen.findByText('owner1@example.test')).toBeInTheDocument()
    auth.userId = 2
    rerender(view())
    expect(await screen.findByText('owner2@example.test')).toBeInTheDocument()
    expect(screen.queryByText('owner1@example.test')).not.toBeInTheDocument()
    expect(api).toHaveBeenCalledTimes(2)
  })
})
