import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import axe from 'axe-core'
import { ApiError, billingApi, type DataBundlePurchase } from '@/lib/api'
import { DataPurchaseStatusPage } from './DataPurchaseStatusPage'
import { MobileDataPage } from './MobileDataPage'

vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => ({ user: { user: { id: 1 } } }) }))
vi.mock('@/lib/api', async (importOriginal) => {
  const original = await importOriginal<typeof import('@/lib/api')>()
  return { ...original, billingApi: { verifyDataBundlePurchase: vi.fn(), dataBundlePurchase: vi.fn(), dataBillers: vi.fn(), dataBundles: vi.fn(), dataPurchaseEvent: vi.fn(), dataBundlePurchases: vi.fn(), purchaseDataBundle: vi.fn() } }
})

const reference = `data_${'a'.repeat(64)}`
const order: DataBundlePurchase = { purchase_id: 26, status: 'success', amount_minor: 80000,
  product_name: '1GB weekly', phone_number: '09028111111', payment_reference: reference,
  checkout_url: null, payment_confirmed: true, delivery_started: true }
const clients: QueryClient[] = []
function Location() {
  const location = useLocation()
  return <p data-testid="location">{location.pathname}{location.search}</p>
}
function show(path = `/billing/data/status?paymentReference=${reference}`) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  clients.push(client)
  return render(<QueryClientProvider client={client}><MemoryRouter initialEntries={[path]}>
    <Location />
    <Routes>
      <Route path="/billing/data/status" element={<DataPurchaseStatusPage />} />
      <Route path="/billing/data" element={path.startsWith('/billing/data?') ? <MobileDataPage /> : <p>Buy data catalogue</p>} />
    </Routes>
  </MemoryRouter></QueryClientProvider>)
}
beforeEach(() => {
  vi.resetAllMocks(); sessionStorage.clear()
  vi.mocked(billingApi.verifyDataBundlePurchase).mockResolvedValue(order)
})
afterEach(() => { cleanup(); clients.splice(0).forEach((client) => client.clear()); vi.useRealTimers() })

describe('data purchase payment return', () => {
  it.each([null, '999'])('uses the returned reference with saved purchase %s', async (saved) => {
    if (saved) sessionStorage.setItem('mahadum:data-purchase:1', saved)
    show()
    expect(await screen.findByText('Data delivered')).toBeInTheDocument()
    expect(billingApi.verifyDataBundlePurchase).toHaveBeenCalledWith(reference)
    expect(billingApi.dataBundlePurchase).not.toHaveBeenCalled()
    expect(billingApi.dataBillers).not.toHaveBeenCalled()
    expect(screen.getByText('Confirmed')).toBeInTheDocument()
    expect(screen.getByText('₦800.00')).toBeInTheDocument()
    expect(screen.getByText(`Reference: ${reference}`)).toBeInTheDocument()
  })

  it('redirects older checkout returns to the dedicated status page before querying saved orders', async () => {
    sessionStorage.setItem('mahadum:data-purchase:1', '999')
    show(`/billing/data?paymentReference=${reference}&paymentStatus=PAID`)
    expect(await screen.findByText('Data delivered')).toBeInTheDocument()
    expect(screen.getByTestId('location')).toHaveTextContent(`/billing/data/status?paymentReference=${reference}`)
    expect(billingApi.verifyDataBundlePurchase).toHaveBeenCalledWith(reference)
    expect(billingApi.dataBundlePurchase).not.toHaveBeenCalled()
    expect(billingApi.dataBillers).not.toHaveBeenCalled()
  })

  it('does not treat a PAID redirect parameter as payment or delivery confirmation', async () => {
    vi.mocked(billingApi.verifyDataBundlePurchase).mockResolvedValue({ ...order, status: 'awaiting_payment', payment_confirmed: false, delivery_started: false })
    show(`/billing/data/status?paymentReference=${reference}&paymentStatus=PAID`)
    expect(await screen.findByText('Awaiting payment confirmation')).toBeInTheDocument()
    expect(screen.getByText('Not started')).toBeInTheDocument()
    expect(screen.queryByText('Confirmed')).not.toBeInTheDocument()
    expect(screen.queryByText('Data delivered')).not.toBeInTheDocument()
  })

  it('shows verification progress before receiving a result', async () => {
    vi.mocked(billingApi.verifyDataBundlePurchase).mockReturnValue(new Promise(() => {}))
    show()
    expect(screen.getByText('Verifying your payment with Monnify…')).toHaveAttribute('role', 'status')
    expect(screen.queryByText('Data delivered')).not.toBeInTheDocument()
  })

  it('polls delivery until success without asking the browser to create another purchase', async () => {
    vi.useFakeTimers()
    vi.mocked(billingApi.verifyDataBundlePurchase)
      .mockResolvedValueOnce({ ...order, status: 'processing' }).mockResolvedValue(order)
    show()
    await act(async () => { await vi.advanceTimersByTimeAsync(1) })
    expect(screen.getByText('Delivering your data')).toBeInTheDocument()
    await act(async () => { await vi.advanceTimersByTimeAsync(5000) })
    expect(screen.getByText('Data delivered')).toBeInTheDocument()
    await act(async () => { await vi.advanceTimersByTimeAsync(10000) })
    expect(billingApi.verifyDataBundlePurchase).toHaveBeenCalledTimes(2)
    expect(billingApi.purchaseDataBundle).not.toHaveBeenCalled()
  })

  it.each(['payment_failed', 'failed', 'needs_review'] as const)('displays %s and keeps another purchase available', async (status) => {
    vi.mocked(billingApi.verifyDataBundlePurchase).mockResolvedValue({ ...order, status, payment_confirmed: status === 'failed' })
    sessionStorage.setItem('mahadum:data-purchase:1', '26')
    show()
    expect(await screen.findByText(status === 'payment_failed' ? 'Payment unsuccessful' : 'Your purchase needs attention')).toBeInTheDocument()
    expect(screen.queryByText('Data delivered')).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('link', { name: 'Buy another bundle' }))
    expect(await screen.findByText('Buy data catalogue')).toBeInTheDocument()
    expect(sessionStorage.getItem('mahadum:data-purchase:1')).toBeNull()
  })

  it.each(['', '?paymentReference=invalid'])('rejects a missing or invalid reference %s without contacting the provider', (query) => {
    show(`/billing/data/status${query}`)
    expect(screen.getByText('Purchase reference missing or invalid')).toBeInTheDocument()
    expect(billingApi.verifyDataBundlePurchase).not.toHaveBeenCalled()
  })

  it('shows an unavailable order without leaking another account’s purchase', async () => {
    vi.mocked(billingApi.verifyDataBundlePurchase).mockRejectedValue(new ApiError('Not found', 'not_found', 404))
    show()
    expect(await screen.findByText('Purchase unavailable')).toBeInTheDocument()
    expect(screen.queryByText('1GB weekly')).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Buy another bundle' })).toBeInTheDocument()
  })

  it('can retry a temporary verification failure using the same reference', async () => {
    vi.mocked(billingApi.verifyDataBundlePurchase).mockRejectedValueOnce(new ApiError('Monnify is unavailable.', 'provider', 503)).mockResolvedValue(order)
    show()
    expect(await screen.findByText('Monnify is unavailable.')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Check status' }))
    expect(await screen.findByText('Data delivered')).toBeInTheDocument()
    await waitFor(() => expect(billingApi.verifyDataBundlePurchase).toHaveBeenCalledTimes(2))
    expect(vi.mocked(billingApi.verifyDataBundlePurchase).mock.calls.every(([ref]) => ref === reference)).toBe(true)
  })

  it('has no automated accessibility violations', async () => {
    const { container } = show()
    await screen.findByText('Data delivered')
    expect((await axe.run(container)).violations).toEqual([])
  })
})
