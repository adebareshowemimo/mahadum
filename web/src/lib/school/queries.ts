import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  schoolApi,
  type CreateClassAssignmentInput,
  type CreateClassInput,
  type GradeSubmissionInput,
  type PurchaseSeatsInput,
  type RequestTeacherCompensationPayoutInput,
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
  assignments: (classId: number) => ['class-assignments', classId] as const,
  assignmentDetail: (classId: number, assignmentId: number) =>
    ['class-assignments', classId, assignmentId] as const,
  teacherCompensation: ['teacher-compensation'] as const,
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

export function usePayInvoice(orgId: number | null) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ invoiceId, gateway }: { invoiceId: number; gateway?: string }) =>
      schoolApi.payInvoice(orgId as number, invoiceId, gateway),
    onSuccess: () => void qc.invalidateQueries({ queryKey: schoolKeys.invoices(orgId ?? 0) }),
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

export function useClassAssignments(classId: number | null) {
  return useQuery({
    queryKey: schoolKeys.assignments(classId ?? 0),
    queryFn: () => schoolApi.classAssignments(classId as number),
    enabled: !!classId,
  })
}

export function useClassAssignmentDetail(classId: number | null, assignmentId: number | null) {
  return useQuery({
    queryKey: schoolKeys.assignmentDetail(classId ?? 0, assignmentId ?? 0),
    queryFn: () => schoolApi.classAssignmentDetail(classId as number, assignmentId as number),
    enabled: !!classId && !!assignmentId,
  })
}

export function useCreateClassAssignment(classId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateClassAssignmentInput) => schoolApi.createClassAssignment(classId, input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: schoolKeys.assignments(classId) }),
  })
}

export function useGradeSubmission(classId: number, assignmentId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ submissionId, input }: { submissionId: number; input: GradeSubmissionInput }) =>
      schoolApi.gradeSubmission(classId, assignmentId, submissionId, input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: schoolKeys.assignmentDetail(classId, assignmentId) })
      void qc.invalidateQueries({ queryKey: schoolKeys.assignments(classId) })
    },
  })
}

export function useTeacherCompensation() {
  return useQuery({
    queryKey: schoolKeys.teacherCompensation,
    queryFn: schoolApi.teacherCompensationSummary,
  })
}

export function useRequestTeacherCompensationPayout() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: RequestTeacherCompensationPayoutInput) =>
      schoolApi.requestTeacherCompensationPayout(input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: schoolKeys.teacherCompensation })
      void qc.invalidateQueries({ queryKey: ['payouts'] })
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
