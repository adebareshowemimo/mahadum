import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ReferralsPage } from './ReferralsPage'
import { ApiError } from '@/lib/api'
import * as queries from '@/lib/referral/queries'

vi.mock('@/lib/referral/queries', () => ({
  useReferralCode: vi.fn(),
  useReferralSummary: vi.fn(),
  usePayouts: vi.fn(),
  useReferralActivations: vi.fn(),
  useReferralInvitations: vi.fn(),
  useSendInvitation: vi.fn(),
  useRequestPayout: vi.fn(),
  referralKeys: { code: [], summary: [], payouts: [], activations: () => [], invitations: [] },
}))

const m = vi.mocked(queries)

beforeEach(() => {
  m.useReferralCode.mockReturnValue({ data: { code: 'ABC123', status: 'active', share_url: 'x', share_text: 'y' } } as never)
  m.useReferralSummary.mockReturnValue({ data: { code: 'ABC123', referrals: {}, commissions: {}, available_minor: 0 } } as never)
  m.usePayouts.mockReturnValue({ data: [] } as never)
  m.useReferralInvitations.mockReturnValue({ data: [] } as never)
  m.useRequestPayout.mockReturnValue({ mutateAsync: vi.fn(), isPending: false } as never)
  m.useReferralActivations.mockReturnValue({
    data: {
      data: [
        { sn: 1, activated_at: '2026-08-26', code: 'ABC123', via_email: null, via_phone: '2349028111111', status: 'active' },
        { sn: 2, activated_at: '2026-08-25', code: 'ABC123', via_email: 'x@y.com', via_phone: null, status: 'inactive' },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 2 },
    },
    isLoading: false,
    isError: false,
  } as never)
})

describe('ReferralsPage', () => {
  it('lists activations with their contact channel and status', () => {
    m.useSendInvitation.mockReturnValue({ mutateAsync: vi.fn(), isPending: false } as never)
    render(<ReferralsPage />)

    expect(screen.getByText('2349028111111')).toBeInTheDocument()
    expect(screen.getByText('x@y.com')).toBeInTheDocument()
    expect(screen.getByText('Active')).toBeInTheDocument()
    expect(screen.getByText('Inactive')).toBeInTheDocument()
  })

  it('shows the "account already exists" prompt when the invite is refused', async () => {
    const mutateAsync = vi.fn().mockRejectedValue(new ApiError('exists', 'account_exists', 422))
    m.useSendInvitation.mockReturnValue({ mutateAsync, isPending: false } as never)
    render(<ReferralsPage />)

    await userEvent.type(screen.getByLabelText(/friend's email/i), 'taken@example.com')
    await userEvent.click(screen.getByRole('button', { name: /send invite/i }))

    await waitFor(() => expect(screen.getByText(/already exists/i)).toBeInTheDocument())
  })
})
