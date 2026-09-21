import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { expectNoA11yViolations } from '@/test/a11y'
import { VerifyEmailPage } from './VerifyEmailPage'
import { ApiError } from '@/lib/api'

const mocks = vi.hoisted(() => ({ auth: vi.fn(), resend: vi.fn(), me: vi.fn(), refresh: vi.fn(), logout: vi.fn() }))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: mocks.auth }))
vi.mock('@/lib/api', async (original) => ({
  ...await original<object>(), authApi: { resendVerificationEmail: mocks.resend, me: mocks.me },
}))

function setup() {
  return render(<MemoryRouter initialEntries={[{ pathname: '/verify-email', state: { from: { pathname: '/learn', search: '?language=yo' } } }]}>
    <Routes>
      <Route path="/verify-email" element={<VerifyEmailPage />} />
      <Route path="/learn" element={<div>LEARN</div>} />
    </Routes>
  </MemoryRouter>)
}

describe('VerifyEmailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mocks.auth.mockReturnValue({ user: { user: { email: 'ada@test.local', email_verified: false } }, refresh: mocks.refresh, logout: mocks.logout })
    mocks.resend.mockResolvedValue(undefined)
    mocks.me.mockResolvedValue({ user: { email_verified: false } })
  })

  it('shows the email and accessible recovery controls', async () => {
    const { container } = setup()
    expect(screen.getByText('ada@test.local')).toBeInTheDocument()
    await expectNoA11yViolations(container)
  })

  it('resends the email and confirms success', async () => {
    setup()
    await userEvent.click(screen.getByRole('button', { name: /resend/i }))
    expect(mocks.resend).toHaveBeenCalledOnce()
    expect(await screen.findByText(/verification email sent/i)).toBeInTheDocument()
  })

  it('shows delivery and throttling errors without claiming success', async () => {
    mocks.resend.mockRejectedValueOnce(new ApiError('Please wait before resending.', 'throttled', 429))
    setup()
    await userEvent.click(screen.getByRole('button', { name: /resend/i }))
    expect(await screen.findByText('Please wait before resending.')).toBeInTheDocument()
    expect(screen.queryByText(/verification email sent/i)).not.toBeInTheDocument()
  })

  it('keeps the gate closed until the server confirms verification', async () => {
    setup()
    await userEvent.click(screen.getByRole('button', { name: /I’ve verified/i }))
    expect(await screen.findByText(/not verified yet/i)).toBeInTheDocument()
    expect(mocks.refresh).not.toHaveBeenCalled()
  })

  it('refreshes the session after server confirmation', async () => {
    mocks.me.mockResolvedValueOnce({ user: { email_verified: true } })
    setup()
    await userEvent.click(screen.getByRole('button', { name: /I’ve verified/i }))
    expect(mocks.refresh).toHaveBeenCalledOnce()
  })

  it('returns verified users to their intended destination', () => {
    mocks.auth.mockReturnValue({ user: { user: { email_verified: true } } })
    setup()
    expect(screen.getByText('LEARN')).toBeInTheDocument()
  })

  it('allows signing out while unverified', async () => {
    setup()
    await userEvent.click(screen.getByRole('button', { name: /sign out/i }))
    expect(mocks.logout).toHaveBeenCalledOnce()
  })
})
