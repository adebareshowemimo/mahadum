import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { afterEach, expect, it, vi } from 'vitest'
import { ApiError, referralApi } from '@/lib/api'
import { api } from '@/lib/api/client'
import { useRequestPayout } from './queries'

const account = vi.hoisted(() => ({ id: 10 }))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => ({ user: { user: { id: account.id } } }) }))
afterEach(() => { vi.restoreAllMocks(); sessionStorage.clear(); account.id = 10 })

function setup() {
  const cache = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={cache}>{children}</QueryClientProvider>
  return renderHook(() => useRequestPayout(), { wrapper })
}

const input = { amount_minor: 500_000, method: 'bank' as const }

it('reuses the request reference after a lost response and remount, clearing it after success', async () => {
  const post = vi.spyOn(api, 'post').mockRejectedValueOnce(new Error('Response lost'))
    .mockResolvedValue({ data: { data: { id: 42, status: 'requested' } } })
  const first = setup()
  await act(async () => { await expect(first.result.current.mutateAsync(input)).rejects.toThrow('Response lost') })
  first.unmount()
  const retry = setup()
  await act(async () => { expect((await retry.result.current.mutateAsync(input)).id).toBe(42) })
  expect(post.mock.calls[1][2]?.headers?.['Idempotency-Key']).toBe(post.mock.calls[0][2]?.headers?.['Idempotency-Key'])
  await act(async () => { await retry.result.current.mutateAsync(input) })
  expect(post.mock.calls[2][2]?.headers?.['Idempotency-Key']).not.toBe(post.mock.calls[0][2]?.headers?.['Idempotency-Key'])
})

it('isolates pending requests by account, amount and payout method', async () => {
  const post = vi.spyOn(api, 'post').mockRejectedValue(new Error('Response lost'))
  const first = setup()
  for (const payload of [input, { ...input, amount_minor: 600_000 }, { ...input, method: 'coins' as const }]) {
    await act(async () => { await expect(first.result.current.mutateAsync(payload)).rejects.toThrow() })
  }
  first.unmount()
  account.id = 11
  const other = setup()
  await act(async () => { await expect(other.result.current.mutateAsync(input)).rejects.toThrow() })
  expect(new Set(post.mock.calls.map(call => call[2]?.headers?.['Idempotency-Key'])).size).toBe(4)
  other.unmount()
  account.id = 10
  const original = setup()
  await act(async () => { await expect(original.result.current.mutateAsync(input)).rejects.toThrow() })
  expect(post.mock.calls[4][2]?.headers?.['Idempotency-Key']).toBe(post.mock.calls[0][2]?.headers?.['Idempotency-Key'])
})

it('clears a definitely rejected request so the corrected attempt can proceed', async () => {
  const post = vi.spyOn(api, 'post').mockRejectedValueOnce(new ApiError('Insufficient balance', 'insufficient_balance', 422))
    .mockRejectedValue(new Error('Response lost'))
  const hook = setup()
  await act(async () => { await expect(hook.result.current.mutateAsync(input)).rejects.toThrow('Insufficient balance') })
  await act(async () => { await expect(hook.result.current.mutateAsync(input)).rejects.toThrow('Response lost') })
  expect(post.mock.calls[1][2]?.headers?.['Idempotency-Key']).not.toBe(post.mock.calls[0][2]?.headers?.['Idempotency-Key'])
})

it.each([0, 409, 500])('retains an uncertain reference after status %s', async (status) => {
  const post = vi.spyOn(api, 'post').mockRejectedValue(new ApiError('Uncertain request', 'unknown', status))
  const hook = setup()
  for (let i = 0; i < 2; i++) {
    await act(async () => { await expect(hook.result.current.mutateAsync(input)).rejects.toThrow() })
  }
  expect(post.mock.calls[1][2]?.headers?.['Idempotency-Key']).toBe(post.mock.calls[0][2]?.headers?.['Idempotency-Key'])
})

it('keeps in-memory retry safety when browser storage is blocked', async () => {
  for (const method of ['getItem', 'setItem', 'removeItem'] as const) {
    vi.spyOn(Storage.prototype, method).mockImplementation(() => { throw new Error('Blocked') })
  }
  const post = vi.spyOn(api, 'post').mockRejectedValueOnce(new Error('Response lost'))
    .mockResolvedValue({ data: { data: { id: 42, status: 'requested' } } })
  const hook = setup()
  await act(async () => { await expect(hook.result.current.mutateAsync(input)).rejects.toThrow() })
  await act(async () => { await hook.result.current.mutateAsync(input) })
  expect(post.mock.calls[1][2]?.headers?.['Idempotency-Key']).toBe(post.mock.calls[0][2]?.headers?.['Idempotency-Key'])
})

it('does not let a late successful retry clear a newer uncertain request', async () => {
  type Result = { id: number; status: string }
  let resolveFirst!: (value: Result) => void
  let resolveRetry!: (value: Result) => void
  const firstResponse = new Promise<Result>(resolve => { resolveFirst = resolve })
  const retryResponse = new Promise<Result>(resolve => { resolveRetry = resolve })
  const request = vi.spyOn(referralApi, 'requestPayout').mockReturnValueOnce(firstResponse)
    .mockReturnValueOnce(retryResponse).mockRejectedValueOnce(new Error('New response lost'))
    .mockResolvedValue({ id: 43, status: 'requested' })
  const hook = setup()
  let firstCall!: Promise<Result>
  let oldRetry!: Promise<Result>
  act(() => {
    firstCall = hook.result.current.mutateAsync(input)
    oldRetry = hook.result.current.mutateAsync(input)
  })
  await act(async () => {
    resolveFirst({ id: 42, status: 'requested' })
    await firstCall
  })
  await act(async () => { await expect(hook.result.current.mutateAsync(input)).rejects.toThrow('New response lost') })
  await act(async () => {
    resolveRetry({ id: 42, status: 'requested' })
    await oldRetry
  })
  hook.unmount()
  const recovered = setup()
  await act(async () => { expect((await recovered.result.current.mutateAsync(input)).id).toBe(43) })
  expect(request.mock.calls[1][1]).toBe(request.mock.calls[0][1])
  expect(request.mock.calls[2][1]).not.toBe(request.mock.calls[0][1])
  expect(request.mock.calls[3][1]).toBe(request.mock.calls[2][1])
})
