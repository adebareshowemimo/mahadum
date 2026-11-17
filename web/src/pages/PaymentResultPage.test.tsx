import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { PaymentResultPage } from './PaymentResultPage'

const payments = vi.hoisted(() => ({ wallet: vi.fn(), subscription: vi.fn(), story: vi.fn(), invoice: vi.fn() }))
vi.mock('@/lib/api', async (load) => {
  const actual = await load<typeof import('@/lib/api')>()
  return {
    ...actual,
    familyApi: { ...actual.familyApi, confirmWalletFunding: payments.wallet },
    billingApi: { ...actual.billingApi, confirmSubscription: payments.subscription },
    learningApi: { ...actual.learningApi, confirmStoryPurchase: payments.story },
    schoolApi: { ...actual.schoolApi, confirmInvoice: payments.invoice },
  }
})

function show(path: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<QueryClientProvider client={client}><MemoryRouter initialEntries={[path]}><Routes>
    <Route path="/payment/:kind/:id" element={<PaymentResultPage />} />
    <Route path="/payment/invoice/:orgId/:id" element={<PaymentResultPage />} />
  </Routes></MemoryRouter></QueryClientProvider>)
}

describe('payment return pages', () => {
  beforeEach(() => Object.values(payments).forEach((mock) => mock.mockReset()))

  it('shows a confirmed wallet top-up with its payment record', async () => {
    payments.wallet.mockResolvedValue({ funding_id: 7, status: 'success', message: 'Payment confirmed. Your wallet has been credited.', amount_minor: 10000, currency: 'NGN', gateway: 'flutterwave', gateway_ref: 'wallet-ref', created_at: '2026-11-03T10:00:00Z', confirmed_at: '2026-11-03T10:05:00Z' })
    show('/payment/wallet/7?status=successful')
    expect(await screen.findByRole('heading', { name: 'Payment confirmed' })).toBeInTheDocument()
    expect(screen.getByText('₦100.00')).toBeInTheDocument()
    expect(screen.getByText('wallet-ref')).toBeInTheDocument()
    expect(payments.wallet).toHaveBeenCalledWith(7)
  })

  it('keeps an unverified plan pending despite a successful gateway URL', async () => {
    payments.subscription.mockResolvedValue({ status: 'pending', plan_name: 'Family', amount_minor: 300000, currency: 'NGN', gateway: 'monnify', reference: 'sub_9', receipt_id: null, paid_at: null, renews_at: null })
    show('/payment/subscription/9?paymentStatus=PAID')
    expect(await screen.findByRole('heading', { name: 'Payment being checked' })).toBeInTheDocument()
    expect(screen.getByText('sub_9')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Payment confirmed' })).not.toBeInTheDocument()
  })

  it('shows a paid story and a link back to it', async () => {
    payments.story.mockResolvedValue({ id: 11, story_id: 6, status: 'paid', amount_minor: 50000, provider: 'monnify', reference: 'story-ref', story_title: 'Why Tortoise carries his shell', paid_at: '2026-11-03T10:00:00Z' })
    show('/payment/story/11')
    expect(await screen.findByText('Why Tortoise carries his shell')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Open story' })).toHaveAttribute('href', '/learn/stories/6')
  })

  it('shows the paid school invoice number and amount', async () => {
    payments.invoice.mockResolvedValue({ id: 12, status: 'paid', verification: 'success', amount_minor: 12900000, payment_gateway: 'monnify', payment_reference: 'invoice_12', issued_at: '2026-11-01T10:00:00Z', paid_at: '2026-11-03T10:00:00Z' })
    show('/payment/invoice/3/12')
    expect(await screen.findByRole('heading', { name: 'Payment confirmed' })).toBeInTheDocument()
    expect(screen.getByText('#000012')).toBeInTheDocument()
    expect(screen.getByText('₦129,000.00')).toBeInTheDocument()
    expect(payments.invoice).toHaveBeenCalledWith(3, 12)
  })
})
