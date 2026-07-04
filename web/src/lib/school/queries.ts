import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  schoolApi,
  type CreateClassInput,
  type PurchaseSeatsInput,
} from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'

/** The organization a school-admin operates on: active org, else their first membership. */
export function useSchoolOrgId(): number | null {
  const { activeOrgId, user } = useAuth()
  return activeOrgId ?? user?.organizations[0]?.id ?? null
}

export const schoolKeys = {
  dashboard: (org: number) => ['school-dashboard', org] as const,
  classes: ['school-classes'] as const,
  seats: (org: number) => ['school-seats', org] as const,
  invoices: (org: number) => ['school-invoices', org] as const,
}

export function useSchoolDashboard(orgId: number | null) {
  return useQuery({
    queryKey: schoolKeys.dashboard(orgId ?? 0),
    queryFn: () => schoolApi.dashboard(orgId as number),
    enabled: !!orgId,
  })
}

export function useClasses() {
  return useQuery({ queryKey: schoolKeys.classes, queryFn: schoolApi.classes })
}

export function useSeats(orgId: number | null) {
  return useQuery({
    queryKey: schoolKeys.seats(orgId ?? 0),
    queryFn: () => schoolApi.seats(orgId as number),
    enabled: !!orgId,
  })
}

export function useInvoices(orgId: number | null) {
  return useQuery({
    queryKey: schoolKeys.invoices(orgId ?? 0),
    queryFn: () => schoolApi.invoices(orgId as number),
    enabled: !!orgId,
  })
}

export function useCreateClass() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateClassInput) => schoolApi.createClass(input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: schoolKeys.classes })
      // Dashboard KPI ['school-dashboard', org] — invalidate by prefix.
      void qc.invalidateQueries({ queryKey: ['school-dashboard'] })
    },
  })
}

export function usePurchaseSeats(orgId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: PurchaseSeatsInput) => schoolApi.purchaseSeats(orgId, input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: schoolKeys.seats(orgId) })
      void qc.invalidateQueries({ queryKey: schoolKeys.invoices(orgId) })
      void qc.invalidateQueries({ queryKey: schoolKeys.dashboard(orgId) })
    },
  })
}

export function useImportRoster(orgId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: { file?: File; class_id?: number }) => schoolApi.importRoster(orgId, input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: schoolKeys.dashboard(orgId) })
      void qc.invalidateQueries({ queryKey: schoolKeys.seats(orgId) })
    },
  })
}
