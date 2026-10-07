import { render, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { InlineAdvert } from './InlineAdvert'

const fixture = vi.hoisted(() => ({ roles: ['parent'], allowed: true }))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => ({ hasRole: (...roles: string[]) => roles.some(role => fixture.roles.includes(role)) }) }))
vi.mock('@/lib/adverts/queries', () => ({
  useAdsAllowed: () => fixture.allowed,
  useActiveAdvert: () => ({ data: { id: 1, image_url: '/storage/adverts/data.svg', target_url: '/billing', position: 'profile_data_topup', size: '300x250' } }),
  useRecordImpression: () => ({ mutate: vi.fn() }), useRecordClick: () => ({ mutate: vi.fn() }),
}))
beforeEach(() => {
  fixture.roles = ['parent']; fixture.allowed = true
  vi.stubGlobal('IntersectionObserver', class { observe() {} disconnect() {} })
})
afterEach(() => vi.unstubAllGlobals())
describe('data promotion', () => {
  it('takes a parent to Buy data and does not promise delivery', () => {
    render(<InlineAdvert position="profile_data_topup" />)
    expect(screen.getByRole('link')).toHaveAttribute('href', '/billing/data')
    expect(screen.getByRole('img', { name: 'Buy data / top-up' })).toBeInTheDocument()
    expect(screen.getByText(/Availability is shown in the store/)).toBeInTheDocument()
  })
  it('promotes data to a child through their grown-up without exposing checkout', () => {
    fixture.roles = ['student']
    render(<InlineAdvert position="profile_data_topup" />)
    expect(screen.getByText(/Ask your grown-up to buy a mobile data bundle/)).toBeInTheDocument()
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })
  it('preserves premium and staff advert suppression', () => {
    fixture.allowed = false
    render(<InlineAdvert position="profile_data_topup" />)
    expect(screen.queryByRole('img')).not.toBeInTheDocument()
  })
})
