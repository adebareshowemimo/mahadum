import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { DataSalesPage } from './DataSalesPage'
import { useDataSales } from '@/lib/admin/queries'

vi.mock('@/lib/admin/queries', () => ({ useDataSales: vi.fn() }))

describe('Data sales dashboard', () => {
  it('shows distinct totals, attention counts and purchase activity links', () => {
    vi.mocked(useDataSales).mockReturnValue({ data: {
      summary: { purchases: 4, successful: 1, delivered_sales_minor: 10000, verified_payments_minor: 60000, awaiting_payment: 1, processing: 1, needs_attention: 1, payment_failed: 0 },
      statuses: { success: 1, failed: 1 }, networks: [{ network: 'MTN', purchases: 4, successful: 1, sales_minor: 10000 }], daily: [],
      data: [{ id: 1, buyer: 'buyer@example.com', network: 'MTN', plan: 'Weekly', amount_minor: 10000, status: 'success', recipient_last4: '5678', reference: 'sale_1', created_at: null, paid_at: null }],
      meta: { current_page: 1, last_page: 1, total: 1 },
    }, isLoading: false, isFetching: false, error: null, refetch: vi.fn() } as unknown as ReturnType<typeof useDataSales>)
    render(<MemoryRouter><DataSalesPage /></MemoryRouter>)
    expect(screen.getByText('Verified payments')).toBeInTheDocument()
    expect(screen.getByText('₦600.00')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'View activity' })).toHaveAttribute('href', '/admin/audit?q=sale_1')
    fireEvent.click(screen.getByRole('button', { name: 'All time' }))
    expect(useDataSales).toHaveBeenLastCalledWith(expect.objectContaining({ from: undefined, to: undefined }))
  })
})
