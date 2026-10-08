import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { afterEach, expect, it, vi } from 'vitest'
import { schoolApi } from '@/lib/api'
import { usePurchaseSeats } from './queries'

const account = vi.hoisted(() => ({ id: 10 }))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => ({ user: { user: { id: account.id } } }) }))
afterEach(() => { vi.restoreAllMocks(); sessionStorage.clear(); account.id = 10 })

it('keeps the seat purchase reference after a lost response and starts a fresh reference after success', async () => {
  const request = vi.spyOn(schoolApi, 'purchaseSeats').mockRejectedValueOnce(new Error('Response lost')).mockResolvedValue({ invoice_id: 10, allocation_id: 20 } as never)
  const cache = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={cache}>{children}</QueryClientProvider>
  const { result } = renderHook(() => usePurchaseSeats(7), { wrapper })
  const input = { quantity: 100, term_label: '2026/27', include_registration: true }
  await act(async () => { await expect(result.current.mutateAsync(input)).rejects.toThrow('Response lost') })
  await act(async () => { await result.current.mutateAsync({ ...input }) })
  expect(request.mock.calls[0][2]).toBeTruthy()
  expect(request.mock.calls[1][2]).toBe(request.mock.calls[0][2])
  await act(async () => { await result.current.mutateAsync(input) })
  expect(request.mock.calls[2][2]).not.toBe(request.mock.calls[0][2])
})

it('retains an uncertain purchase across remounts and scopes it to the signed-in account', async () => {
  const request = vi.spyOn(schoolApi, 'purchaseSeats').mockRejectedValue(new Error('Response lost'))
  const cache = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={cache}>{children}</QueryClientProvider>
  const input = { quantity: 100 }
  const first = renderHook(() => usePurchaseSeats(7), { wrapper })
  await act(async () => { await expect(first.result.current.mutateAsync(input)).rejects.toThrow() })
  first.unmount()
  const retry = renderHook(() => usePurchaseSeats(7), { wrapper })
  await act(async () => { await expect(retry.result.current.mutateAsync(input)).rejects.toThrow() })
  expect(request.mock.calls[1][2]).toBe(request.mock.calls[0][2])
  retry.unmount()
  account.id = 11
  const other = renderHook(() => usePurchaseSeats(7), { wrapper })
  await act(async () => { await expect(other.result.current.mutateAsync(input)).rejects.toThrow() })
  expect(request.mock.calls[2][2]).not.toBe(request.mock.calls[0][2])
})

it('keeps in-memory retry safety when browser storage is disabled', async () => {
  vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('Storage blocked') })
  vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('Storage blocked') })
  const request = vi.spyOn(schoolApi, 'purchaseSeats').mockRejectedValueOnce(new Error('Response lost')).mockResolvedValue({ invoice_id: 10 } as never)
  const cache = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={cache}>{children}</QueryClientProvider>
  const { result } = renderHook(() => usePurchaseSeats(7), { wrapper })
  await act(async () => { await expect(result.current.mutateAsync({ quantity: 100 })).rejects.toThrow() })
  await act(async () => { await result.current.mutateAsync({ quantity: 100 }) })
  expect(request.mock.calls[1][2]).toBe(request.mock.calls[0][2])
})
