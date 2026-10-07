import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { AdminOverviewPage } from './AdminOverviewPage'

vi.mock('@/lib/admin/queries', () => ({
  useAdminMetrics: () => ({
    isLoading: false,
    isError: false,
    data: {
      users: 54,
      revenue_minor: 0,
      languages: 4,
      users_by_type: { single: 42, family: 8, school: 4 },
      organizations: { active: 7 },
      subscriptions: { grace: 2 },
    },
  }),
  useBillingHealth: () => ({ isLoading: false, isError: true, data: undefined }),
}))

describe('AdminOverviewPage user types', () => {
  it('presents the existing single count as Individual without relabeling other status groups', () => {
    render(<AdminOverviewPage />)
    expect(screen.getByText('42 Individual')).toBeInTheDocument()
    expect(screen.queryByText('42 single')).not.toBeInTheDocument()
    expect(screen.getByText('8 family')).toBeInTheDocument()
    expect(screen.getByText('4 school')).toBeInTheDocument()
    expect(screen.getByText('7 active')).toBeInTheDocument()
    expect(screen.getByText('2 grace')).toBeInTheDocument()
  })
})
