import { renderHook } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { FREE_ENTITLEMENTS, useEntitlements, useHasFeature } from './entitlements'

const { useAuthMock } = vi.hoisted(() => ({ useAuthMock: vi.fn() }))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: useAuthMock }))

describe('useEntitlements', () => {
  beforeEach(() => useAuthMock.mockReset())

  it('falls back to Free defaults when there is no user', () => {
    useAuthMock.mockReturnValue({ user: null })
    const { result } = renderHook(() => useEntitlements())
    expect(result.current).toEqual(FREE_ENTITLEMENTS)
  })

  it('returns the active plan entitlements from /me', () => {
    useAuthMock.mockReturnValue({
      user: { entitlements: { ...FREE_ENTITLEMENTS, tier: 'premium_family', family_dashboard: true, ads: false } },
    })
    const { result } = renderHook(() => useEntitlements())
    expect(result.current.family_dashboard).toBe(true)
    expect(result.current.ads).toBe(false)
  })

  it('useHasFeature reflects the active plan', () => {
    useAuthMock.mockReturnValue({ user: { entitlements: { ...FREE_ENTITLEMENTS, family_dashboard: true } } })
    expect(renderHook(() => useHasFeature('family_dashboard')).result.current).toBe(true)
    expect(renderHook(() => useHasFeature('offline_download')).result.current).toBe(false)
  })
})
