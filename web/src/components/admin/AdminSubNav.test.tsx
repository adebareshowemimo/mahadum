import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { AdminSubNav } from './AdminSubNav'

function activeGroup(path: string): string | null {
  render(
    <MemoryRouter initialEntries={[path]}>
      <AdminSubNav />
    </MemoryRouter>,
  )
  const current = screen.queryByRole('link', { current: 'page' })
  return current?.textContent ?? null
}

describe('AdminSubNav active group (longest-prefix match)', () => {
  it.each([
    ['/admin', 'Overview'],
    ['/admin/orgs', 'Orgs'],
    ['/admin/orgs/42', 'Orgs'],
    ['/admin/users', 'Users'],
    ['/admin/roles', 'Users'],
    ['/admin/courses', 'Content'],
    ['/admin/languages', 'Content'],
    ['/admin/payouts', 'Finance'],
    ['/admin/plans', 'Finance'],
    // The disambiguation that broke with NavLink: gateways is Finance, not System,
    // and bare /admin must not swallow every sub-route as Overview.
    ['/admin/settings/gateways', 'Finance'],
    ['/admin/settings', 'System'],
    ['/admin/reports', 'Reports'],
    ['/admin/reports/income', 'Reports'],
    ['/admin/audit', 'System'],
    ['/admin/support', 'System'],
  ])('marks %s active under %s', (path, group) => {
    expect(activeGroup(path)).toBe(group)
  })

  it('marks exactly one group active', () => {
    render(
      <MemoryRouter initialEntries={['/admin/settings/gateways']}>
        <AdminSubNav />
      </MemoryRouter>,
    )
    expect(screen.getAllByRole('link', { current: 'page' })).toHaveLength(1)
  })
})
