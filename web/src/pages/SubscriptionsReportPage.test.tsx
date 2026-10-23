import { fireEvent, render, screen, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { SubscriptionsReportPage } from './SubscriptionsReportPage'

const { useSubscriptionsReportMock, useSubscriptionRecordsMock } = vi.hoisted(() => ({
  useSubscriptionsReportMock: vi.fn(),
  useSubscriptionRecordsMock: vi.fn(),
}))

vi.mock('@/lib/admin/queries', () => ({
  useSubscriptionsReport: useSubscriptionsReportMock,
  useSubscriptionRecords: useSubscriptionRecordsMock,
}))

describe('SubscriptionsReportPage', () => {
  beforeEach(() => {
    useSubscriptionsReportMock.mockReset().mockReturnValue({
      data: {
        from: '2026-05-01', to: '2026-10-31', months: ['2026-10'],
        new: { key: 'new', label: 'New subscriptions', by_month: { '2026-10': 2 }, total: 2 },
        by_status: { active: 1, pending: 1 }, active: 1, total: 2,
      },
      isLoading: false, isError: false,
    })
    useSubscriptionRecordsMock.mockReset().mockReturnValue({
      data: {
        data: [{
          id: 7, user_id: 3, name: 'Lucy Okafor', email: 'lucy@example.test',
          phone: '+2348012345678', account_status: 'active', subscription_plan: 'Premium (Individual)',
          subscription_status: 'pending', subscription_start: null, subscription_expiry: null,
          payment_status: 'pending',
        }],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
      },
      isLoading: false, isError: false, isFetching: false,
    })
  })

  it('shows the requested subscriber fields and keeps pending dates empty', () => {
    render(<MemoryRouter><SubscriptionsReportPage /></MemoryRouter>)

    const table = within(screen.getByRole('region', { name: 'Subscriber details' })).getByRole('table')
    for (const header of [
      'Name', 'Email', 'Phone', 'Account status', 'Subscription plan',
      'Subscription status', 'Subscription start', 'Subscription expiry', 'Payment status',
    ]) {
      expect(within(table).getByRole('columnheader', { name: header })).toBeInTheDocument()
    }

    const row = within(table).getByRole('row', { name: /Lucy Okafor/ })
    expect(within(row).getByText('+2348012345678')).toBeInTheDocument()
    expect(within(row).getAllByText('—')).toHaveLength(2)
    expect(within(row).getByRole('link', { name: 'Lucy Okafor' })).toHaveAttribute('href', '/admin/users/3')
  })

  it('filters the subscriber table by status', () => {
    render(<MemoryRouter><SubscriptionsReportPage /></MemoryRouter>)
    fireEvent.change(screen.getByLabelText('Subscription status'), { target: { value: 'pending' } })
    expect(useSubscriptionRecordsMock).toHaveBeenLastCalledWith(expect.objectContaining({ status: 'pending', page: 1 }))
  })
})
