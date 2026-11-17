import { render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { WalletPage } from './WalletPage'

const mocks = vi.hoisted(() => ({ gateways: ['monnify'] as string[] }))
vi.mock('@/lib/family/queries', () => ({
  useWallet: () => ({ data: { coin_balance: 0, currency_minor: 0, currency: 'NGN', funding_gateways: mocks.gateways }, isLoading: false, isError: false }),
  useFamily: () => ({ data: { learners: [] } }),
  useFundWallet: () => ({ mutateAsync: vi.fn(), isPending: false }),
  useTransfer: () => ({ mutateAsync: vi.fn(), isPending: false }),
}))
function Location() { return <span data-testid="location">{useLocation().pathname}</span> }
describe('Wallet gateway availability', () => {
  beforeEach(() => { mocks.gateways = ['monnify']; window.history.replaceState({}, '', '/wallet') })
  it('offers only the gateways the server marks available', () => {
    render(<WalletPage />)
    expect(screen.getByRole('option', { name: 'Monnify' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Paystack' })).not.toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Flutterwave' })).not.toBeInTheDocument()
  })
  it('shows a clear unavailable state with no payment submission', () => {
    mocks.gateways = []
    render(<WalletPage />)
    expect(screen.getByText(/Wallet funding is currently unavailable/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Continue to payment' })).not.toBeInTheDocument()
  })
  it('routes a returning payment to its dedicated result page', async () => {
    window.history.replaceState({}, '', '/wallet?funding_id=42')
    render(<MemoryRouter initialEntries={['/wallet']}><WalletPage /><Location /></MemoryRouter>)
    await waitFor(() => expect(screen.getByTestId('location')).toHaveTextContent('/payment/wallet/42'))
  })
  it('checks a Monnify return even when its query follows a second question mark', async () => {
    window.history.replaceState({}, '', '/wallet?funding_id=42?paymentReference=wallet-42&paymentStatus=PAID')
    render(<MemoryRouter initialEntries={['/wallet']}><WalletPage /><Location /></MemoryRouter>)
    await waitFor(() => expect(screen.getByTestId('location')).toHaveTextContent('/payment/wallet/42'))
  })
})
