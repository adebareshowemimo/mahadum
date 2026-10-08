import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { afterEach, expect, it, vi } from 'vitest'
import { ApiError, familyApi } from '@/lib/api'
import { api } from '@/lib/api/client'
import { useFundWallet } from './queries'

const account = vi.hoisted(() => ({ id: 10 }))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => ({ user: { user: { id: account.id } } }) }))
afterEach(() => { vi.restoreAllMocks(); sessionStorage.clear(); account.id = 10 })

function setup() {
  const cache = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={cache}>{children}</QueryClientProvider>
  return renderHook(() => useFundWallet(), { wrapper })
}

it('recovers the same checkout after a lost response and remount, then permits a new top-up', async () => {
  const post = vi.spyOn(api, 'post').mockRejectedValueOnce(new Error('Response lost')).mockResolvedValue({ data: { data: { funding_id: 1, gateway_ref: 'original', checkout_url: 'https://checkout.example.test/1' } } })
  const input = { amount: 50000, gateway: 'paystack' as const }
  const first = setup()
  await act(async () => { await expect(first.result.current.mutateAsync(input)).rejects.toThrow('Response lost') })
  first.unmount()
  const retry = setup()
  await act(async () => { expect((await retry.result.current.mutateAsync(input)).gateway_ref).toBe('original') })
  expect(post.mock.calls[1][2]?.headers?.['Idempotency-Key']).toBe(post.mock.calls[0][2]?.headers?.['Idempotency-Key'])
  await act(async () => { await retry.result.current.mutateAsync(input) })
  expect(post.mock.calls[2][2]?.headers?.['Idempotency-Key']).not.toBe(post.mock.calls[0][2]?.headers?.['Idempotency-Key'])
})

it('isolates uncertain funding attempts by account, amount and gateway', async () => {
  const request = vi.spyOn(familyApi, 'fundWallet').mockRejectedValue(new Error('Response lost'))
  const first = setup()
  for (const input of [{ amount: 50000, gateway: 'paystack' as const }, { amount: 60000, gateway: 'paystack' as const }, { amount: 50000, gateway: 'monnify' as const }]) {
    await act(async () => { await expect(first.result.current.mutateAsync(input)).rejects.toThrow() })
  }
  first.unmount()
  account.id = 11
  const other = setup()
  await act(async () => { await expect(other.result.current.mutateAsync({ amount: 50000, gateway: 'paystack' })).rejects.toThrow() })
  expect(new Set(request.mock.calls.map(call => call[1])).size).toBe(4)
})

it('uses a fresh reference after a definite validation rejection', async () => {
  const request = vi.spyOn(familyApi, 'fundWallet').mockRejectedValueOnce(new ApiError('Unavailable', 'validation', 422))
    .mockRejectedValue(new Error('Response lost'))
  const hook = setup()
  const input = { amount: 50000, gateway: 'paystack' as const }
  await act(async () => { await expect(hook.result.current.mutateAsync(input)).rejects.toThrow('Unavailable') })
  await act(async () => { await expect(hook.result.current.mutateAsync(input)).rejects.toThrow('Response lost') })
  expect(request.mock.calls[1][1]).not.toBe(request.mock.calls[0][1])
})

it('retains in-memory retry safety when browser storage is blocked', async () => {
  vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('Blocked') })
  vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('Blocked') })
  const request = vi.spyOn(familyApi, 'fundWallet').mockRejectedValue(new Error('Response lost'))
  const hook = setup()
  for (let i = 0; i < 2; i++) {
    await act(async () => { await expect(hook.result.current.mutateAsync({ amount: 50000, gateway: 'paystack' })).rejects.toThrow() })
  }
  expect(request.mock.calls[0][1]).toBeTruthy()
  expect(request.mock.calls[1][1]).toBe(request.mock.calls[0][1])
})
