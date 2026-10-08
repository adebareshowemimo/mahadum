import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { afterEach, expect, it, vi } from 'vitest'
import { schoolApi } from '@/lib/api'
import { usePurchaseSeats } from './queries'

afterEach(() => vi.restoreAllMocks())

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
