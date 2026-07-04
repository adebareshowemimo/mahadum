import { render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ReferralStatusAlert } from './ReferralStatusAlert'
import { useReferralCode } from '@/lib/referral/queries'

vi.mock('@/lib/referral/queries', () => ({ useReferralCode: vi.fn() }))

const code = (status: string) => ({
  data: { code: 'ABC123', status, share_url: 'x', share_text: 'y' },
})

describe('ReferralStatusAlert', () => {
  beforeEach(() => vi.mocked(useReferralCode).mockReset())

  it('renders nothing when the code is active', () => {
    vi.mocked(useReferralCode).mockReturnValue(code('active') as ReturnType<typeof useReferralCode>)
    const { container } = render(<ReferralStatusAlert />)
    expect(container).toBeEmptyDOMElement()
  })

  it('shows an amber review notice + support CTA when flagged', () => {
    vi.mocked(useReferralCode).mockReturnValue(code('flagged') as ReturnType<typeof useReferralCode>)
    render(<ReferralStatusAlert />)
    expect(screen.getByText(/under review/i)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /contact support/i })).toHaveAttribute(
      'href',
      expect.stringContaining('mailto:'),
    )
  })
})
