import { renderHook } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAdsAllowed } from './queries'

const fixture = vi.hoisted(() => ({ roles: [] as string[], ads: true }))
vi.mock('@/lib/auth/AuthProvider', () => ({
  useAuth: () => ({ hasRole: (...roles: string[]) => roles.some(role => fixture.roles.includes(role)) }),
}))
vi.mock('@/lib/billing/entitlements', () => ({ useEntitlements: () => ({ ads: fixture.ads }) }))

beforeEach(() => { fixture.roles = []; fixture.ads = true })

describe('advert audience', () => {
  it.each(['super_admin', 'content_owner', 'teacher', 'school_admin', 'supervisor'])('suppresses adverts for %s including mixed parent accounts', (role) => {
    fixture.roles = ['parent', role]
    expect(renderHook(() => useAdsAllowed()).result.current).toBe(false)
  })

  it('allows free consumer accounts and suppresses premium accounts', () => {
    fixture.roles = ['parent']
    const view = renderHook(() => useAdsAllowed())
    expect(view.result.current).toBe(true)
    fixture.ads = false
    view.rerender()
    expect(view.result.current).toBe(false)
  })
})
