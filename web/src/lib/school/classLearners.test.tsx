import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider, useQuery } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { afterEach, expect, it, vi } from 'vitest'
import { schoolApi } from '@/lib/api'
import { batch4Api } from '@/lib/batch4/api'
import { useAddClassLearner, useSchoolDashboard } from './queries'

afterEach(() => vi.restoreAllMocks())

it('refreshes previously loaded school totals and student class labels after adding a learner', async () => {
  let assigned = false
  vi.spyOn(schoolApi, 'addClassLearner').mockImplementation(async () => {
    assigned = true
    return { learner_id: 8, display_name: 'Amara', courses_enrolled: 0 }
  })
  vi.spyOn(schoolApi, 'dashboard').mockImplementation(async () => ({
    student_counts: { total: 1, in_classes: assigned ? 1 : 0, unassigned: assigned ? 0 : 1 },
  }) as never)
  vi.spyOn(batch4Api, 'students').mockImplementation(async () => [{
    id: 8, name: 'Amara', email: null, level: 'L2', classes: assigned ? [{ id: 12, name: 'Igbo class' }] : [],
  }])
  const cache = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity }, mutations: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={cache}>{children}</QueryClientProvider>
  const { result } = renderHook(() => ({
    dashboard: useSchoolDashboard(7),
    directory: useQuery({ queryKey: ['school-students', 10, 7], queryFn: () => batch4Api.students(7) }),
    add: useAddClassLearner(12),
  }), { wrapper })
  await waitFor(() => expect(result.current.directory.isSuccess && result.current.dashboard.isSuccess).toBe(true))
  expect(result.current.dashboard.data?.student_counts?.unassigned).toBe(1)
  expect(result.current.directory.data?.[0].classes).toEqual([])

  await act(async () => { await result.current.add.mutateAsync({ learner_id: 8 }) })

  await waitFor(() => expect(result.current.dashboard.data?.student_counts).toEqual({ total: 1, in_classes: 1, unassigned: 0 }))
  await waitFor(() => expect(result.current.directory.data?.[0].classes).toEqual([{ id: 12, name: 'Igbo class' }]))
})
