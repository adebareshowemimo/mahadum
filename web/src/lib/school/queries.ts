import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  schoolApi,
  type AddClassLearnerInput,
  type CreateClassAssignmentInput,
  type CreateClassInput,
  type GradeSubmissionInput,
  type InviteClassLearnerInput,
  type PurchaseSeatsInput,
  type RequestPayoutInput,
  type RequestTeacherCompensationPayoutInput,
} from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import { gamificationKeys } from '@/lib/gamification/queries'

/** The organization a school-admin operates on: active org, else their first membership. */
export function useSchoolOrgId(): number | null {
  const { activeOrgId, user } = useAuth()
  return activeOrgId ?? user?.organizations[0]?.id ?? null
}

export const schoolKeys = {
  dashboard: (org: number) => ['school-dashboard', org] as const,
  classes: (org: number | null) => ['school-classes', org] as const,
  myClasses: (org: number | null) => ['school-classes', 'mine', org] as const,
  seats: (org: number) => ['school-seats', org] as const,
  invoices: (org: number) => ['school-invoices', org] as const,
  assignments: (classId: number) => ['class-assignments', classId] as const,
  completion: (classId: number) => ['class-completion', classId] as const,
  assignmentDetail: (classId: number, assignmentId: number) =>
    ['class-assignments', classId, assignmentId] as const,
  classCourses: (classId: number) => ['school-classes', classId, 'courses'] as const,
  availableLearners: (classId: number, query = '') => ['school-classes', classId, 'available-learners', query] as const,
  teacherCompensation: ['teacher-compensation'] as const,
  referrals: (org: number) => ['school-referrals', org] as const,
}

export function useSchoolDashboard(orgId: number | null) {
  return useQuery({
    queryKey: schoolKeys.dashboard(orgId ?? 0),
    queryFn: () => schoolApi.dashboard(orgId as number),
    enabled: !!orgId,
  })
}

export function useClasses() {
  const orgId = useSchoolOrgId()
  return useQuery({ queryKey: schoolKeys.classes(orgId), queryFn: () => schoolApi.classes() })
}

/** Classes taught by the current user — for the Teacher Profile page. */
export function useMyClasses() {
  const orgId = useSchoolOrgId()
  return useQuery({ queryKey: schoolKeys.myClasses(orgId), queryFn: () => schoolApi.classes({ mine: true }) })
}

/** Teacher sees own classes; school admin sees every class in the active school. */
export function useManageableClasses() {
  const orgId = useSchoolOrgId()
  const { hasRole } = useAuth()
  const mine = hasRole('teacher') && !hasRole('school_admin')
  return useQuery({
    queryKey: ['school-classes', 'manageable', orgId, mine],
    queryFn: () => schoolApi.classes({ mine }),
  })
}

export function useSeats(orgId: number | null) {
  return useQuery({
    queryKey: schoolKeys.seats(orgId ?? 0),
    queryFn: () => schoolApi.seats(orgId as number),
    enabled: !!orgId,
  })
}

export function useSchoolReferrals(orgId: number | null) {
  return useQuery({
    queryKey: schoolKeys.referrals(orgId ?? 0),
    queryFn: () => schoolApi.referralSummary(orgId as number),
    enabled: !!orgId,
  })
}

export function useRequestSchoolReferralPayout(orgId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: RequestPayoutInput) => schoolApi.requestReferralPayout(orgId, input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: schoolKeys.referrals(orgId) }),
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
      // Both keys are parameterized by org — invalidate by prefix.
      void qc.invalidateQueries({ queryKey: ['school-classes'] })
      void qc.invalidateQueries({ queryKey: ['school-dashboard'] })
    },
  })
}

export function useTeachers(orgId: number | null) {
  return useQuery({
    queryKey: ['school-teachers', orgId ?? 0],
    queryFn: () => schoolApi.teachers(orgId as number),
    enabled: !!orgId,
  })
}

export function useUpdateClass() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ classId, input }: { classId: number; input: Partial<CreateClassInput> }) =>
      schoolApi.updateClass(classId, input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['school-classes'] })
      void qc.invalidateQueries({ queryKey: ['school-dashboard'] })
    },
  })
}

export function useAddClassLearner(classId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: AddClassLearnerInput) => schoolApi.addClassLearner(classId, input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['school-classes'] })
      void qc.invalidateQueries({ queryKey: schoolKeys.classCourses(classId) })
      void qc.invalidateQueries({ queryKey: schoolKeys.availableLearners(classId) })
      void qc.invalidateQueries({ queryKey: schoolKeys.completion(classId) })
    },
  })
}

export function useAvailableClassLearners(classId: number, query: string) {
  return useQuery({
    queryKey: schoolKeys.availableLearners(classId, query),
    queryFn: () => schoolApi.availableClassLearners(classId, query),
    enabled: query.trim().length >= 2,
  })
}

export function useInviteClassLearner(classId: number) {
  return useMutation({
    mutationFn: (input: InviteClassLearnerInput) => schoolApi.inviteClassLearner(classId, input),
  })
}

export function useClassCourses(classId: number | null) {
  return useQuery({
    queryKey: schoolKeys.classCourses(classId ?? 0),
    queryFn: () => schoolApi.classCourses(classId as number),
    enabled: !!classId,
  })
}

export function useAssignClassCourse(classId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (courseId: number) => schoolApi.assignClassCourse(classId, courseId),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: schoolKeys.classCourses(classId) })
      void qc.invalidateQueries({ queryKey: ['learning-courses'] })
    },
  })
}

export function useUnassignClassCourse(classId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (courseId: number) => schoolApi.unassignClassCourse(classId, courseId),
    onSuccess: () => void qc.invalidateQueries({ queryKey: schoolKeys.classCourses(classId) }),
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

export function useClassCompletion(classId: number | null) {
  return useQuery({
    queryKey: schoolKeys.completion(classId ?? 0),
    queryFn: () => schoolApi.classCompletion(classId as number),
    enabled: !!classId,
  })
}

export function useAwardBadge(classId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ learnerId, badgeCode }: { learnerId: number; badgeCode: string }) =>
      schoolApi.awardBadge(classId, learnerId, badgeCode),
    onSuccess: (_res, { learnerId }) => {
      void qc.invalidateQueries({ queryKey: gamificationKeys.badges(learnerId) })
    },
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
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: schoolKeys.assignments(classId) })
      void qc.invalidateQueries({ queryKey: schoolKeys.completion(classId) })
    },
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
