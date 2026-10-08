import { render, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { InlineAdvert } from './InlineAdvert'

const fixture = vi.hoisted(() => ({ roles: ['parent'], allowed: true, child: false, capabilities: ['billing.databundles.manage'] }))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => ({ user: { user: { capabilities: fixture.capabilities } }, hasRole: (...roles: string[]) => roles.some(role => fixture.roles.includes(role)) }) }))
vi.mock('@/lib/profile/ActiveProfile', () => ({ useActiveProfile: () => ({ activeLearner: { is_child: fixture.child } }) }))
vi.mock('@/lib/adverts/queries', () => ({
  useAdsAllowed: () => fixture.allowed,
  useActiveAdvert: () => ({ data: { id: 1, image_url: '/storage/adverts/data.svg', target_url: '/billing', position: 'profile_data_topup', size: '300x250' } }),
  useRecordImpression: () => ({ mutate: vi.fn() }), useRecordClick: () => ({ mutate: vi.fn() }),
}))
beforeEach(() => {
  fixture.roles = ['parent']; fixture.allowed = true; fixture.child = false; fixture.capabilities = ['billing.databundles.manage']
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
    fixture.capabilities = []
    render(<InlineAdvert position="profile_data_topup" />)
    expect(screen.getByText(/Ask your grown-up to buy a mobile data bundle/)).toBeInTheDocument()
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })
  it('uses child messaging when a parent operates a child profile', () => {
    fixture.child = true
    render(<InlineAdvert position="profile_data_topup" />)
    expect(screen.getByText(/Ask your grown-up/)).toBeInTheDocument()
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })
  it('uses child messaging on the viewed child page even if the active profile differs', () => {
    render(<InlineAdvert position="profile_data_topup" childProfile />)
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })
  it('allows an eligible adult Individual with the student role to browse data', () => {
    fixture.roles = ['student']
    render(<InlineAdvert position="profile_data_topup" />)
    expect(screen.getByRole('link')).toHaveAttribute('href', '/billing/data')
  })
  it('preserves premium and staff advert suppression', () => {
    fixture.allowed = false
    render(<InlineAdvert position="profile_data_topup" />)
    expect(screen.queryByRole('img')).not.toBeInTheDocument()
  })
})
