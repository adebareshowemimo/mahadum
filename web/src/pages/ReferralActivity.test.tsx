import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { referralApi } from '@/lib/api'
import { ActivationsSection } from './ReferralsPage'

afterEach(() => vi.restoreAllMocks())

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
      <ActivationsSection />
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
})
