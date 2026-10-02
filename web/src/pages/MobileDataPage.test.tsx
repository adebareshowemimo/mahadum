import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MobileDataPage } from './MobileDataPage'
import { MemoryRouter } from 'react-router-dom'
import { ApiError, billingApi } from '@/lib/api'
import axe from 'axe-core'

vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => ({ user: { user: { id: 1 } } }) }))
vi.mock('@/lib/api', async (importOriginal) => {
  const original = await importOriginal<typeof import('@/lib/api')>()
  return { ...original, billingApi: { dataBillers: vi.fn(), dataBundles: vi.fn(), purchaseDataBundle: vi.fn(), dataBundlePurchase: vi.fn() } }
})
const clients: QueryClient[] = []
function show() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  clients.push(client)
  return render(<QueryClientProvider client={client}><MemoryRouter><MobileDataPage /></MemoryRouter></QueryClientProvider>)
}
const plan = { product_code: 'weekly', biller_code: 'MTN_DATA', name: 'MTN 1GB weekly', amount_minor: 75000, price_type: 'FIXED', currency: 'NGN', duration: 7, duration_unit: 'DAYS' }
beforeEach(() => {
  vi.clearAllMocks(); sessionStorage.clear()
  vi.mocked(billingApi.dataBillers).mockResolvedValue([{ code: 'MTN_DATA', name: 'MTN Data' }, { code: 'AIRTEL_DATA', name: 'Airtel Data' }])
  vi.mocked(billingApi.dataBundles).mockImplementation(async (code) => code === 'MTN_DATA' ? [plan, { ...plan, product_code: 'monthly', name: 'MTN 1GB monthly', amount_minor: 100000, duration: 30 }] : [{ ...plan, biller_code: code, product_code: 'airtel', name: 'Airtel weekly' }])
})
afterEach(() => { cleanup(); clients.splice(0).forEach((c) => c.clear()) })

describe('Monnify mobile data store', () => {
  it('filters by validity and sorts by price without changing provider products', async () => {
    show()
    await screen.findByRole('button', { name: /MTN 1GB weekly/ })
    fireEvent.change(screen.getByLabelText('Sort plans'), { target: { value: 'high' } })
    expect(screen.getAllByRole('button', { name: /MTN 1GB (weekly|monthly)/ })[0]).toHaveAccessibleName(/monthly/)
    fireEvent.click(screen.getByRole('button', { name: '8 days or more' }))
    expect(screen.queryByRole('button', { name: /MTN 1GB weekly/ })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: /MTN 1GB monthly/ })).toBeInTheDocument()
  })
  it('has no automated accessibility violations', async () => {
    const { container } = show()
    await screen.findByRole('button', { name: /MTN 1GB weekly/ })
    expect((await axe.run(container)).violations).toEqual([])
  })
  it('shows provider plan names, validity and prices and searches the catalogue', async () => {
    show()
    expect(await screen.findByRole('button', { name: /MTN 1GB weekly/ })).toBeInTheDocument()
    expect(screen.getByText('7 days')).toBeInTheDocument()
    expect(screen.getByText('₦750.00')).toBeInTheDocument()
    fireEvent.change(screen.getByLabelText('Find a plan'), { target: { value: 'monthly' } })
    expect(screen.queryByText('MTN 1GB weekly')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: /MTN 1GB monthly/ })).toBeInTheDocument()
  })
  it('clears the selected product and consent when switching networks', async () => {
    show()
    fireEvent.click(await screen.findByRole('button', { name: /MTN 1GB weekly/ }))
    fireEvent.change(screen.getByLabelText('Recipient phone number'), { target: { value: '08012345678' } })
    fireEvent.click(screen.getByRole('checkbox'))
    expect(screen.getByRole('button', { name: /Continue to payment/ })).toBeEnabled()
    fireEvent.click(screen.getByRole('button', { name: /Airtel Data/ }))
    expect(await screen.findByRole('button', { name: /Airtel weekly/ })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Choose a plan' })).toBeDisabled()
    expect(screen.getByRole('checkbox')).not.toBeChecked()
  })
  it('displays provider failures without substituting fixed bundles', async () => {
    vi.mocked(billingApi.dataBillers).mockRejectedValue(new ApiError('Monnify is unavailable.', 'provider', 502))
    show()
    expect(await screen.findByText('Monnify is unavailable.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Retry catalogue' })).toBeInTheDocument()
    expect(screen.queryByText('MTN 1GB weekly')).not.toBeInTheDocument()
  })
  it('sends product code, phone and quoted price, preserving the key on retry', async () => {
    vi.mocked(billingApi.purchaseDataBundle).mockRejectedValue(new ApiError('Try again', 'provider', 503))
    show()
    fireEvent.click(await screen.findByRole('button', { name: /MTN 1GB weekly/ }))
    fireEvent.change(screen.getByLabelText('Recipient phone number'), { target: { value: '08012345678' } })
    fireEvent.click(screen.getByRole('checkbox'))
    fireEvent.click(screen.getByRole('button', { name: /Continue to payment/ }))
    await screen.findByText('Try again')
    fireEvent.click(screen.getByRole('button', { name: /Continue to payment/ }))
    await waitFor(() => expect(billingApi.purchaseDataBundle).toHaveBeenCalledTimes(2))
    const calls = vi.mocked(billingApi.purchaseDataBundle).mock.calls
    expect(calls[0][0]).toEqual({ biller_code: 'MTN_DATA', product_code: 'weekly', phone_number: '08012345678', amount_minor: 75000, consent: true })
    expect(calls[0][1]).toEqual(calls[1][1])
  })
  it('resumes an existing purchase after reload and shows checkout instead of delivery success', async () => {
    sessionStorage.setItem('mahadum:data-purchase:1', '25')
    vi.mocked(billingApi.dataBundlePurchase).mockResolvedValue({ purchase_id: 25, status: 'awaiting_payment', amount_minor: 75000, product_name: 'MTN weekly', phone_number: '08012345678', payment_reference: 'data_ref', checkout_url: 'https://sandbox.monnify.com/checkout' })
    show()
    expect(await screen.findByRole('link', { name: /Open Monnify checkout/ })).toHaveAttribute('href', 'https://sandbox.monnify.com/checkout')
    expect(screen.queryByText('Data delivered')).not.toBeInTheDocument()
  })
})
