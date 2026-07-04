import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { learningApi } from '@/lib/api'

export const learningKeys = {
  path: (learnerId: number) => ['learner-path', learnerId] as const,
  courses: ['courses', 'published'] as const,
}

export function usePath(learnerId: number | null | undefined) {
  return useQuery({
    queryKey: learningKeys.path(learnerId ?? 0),
    queryFn: () => learningApi.path(learnerId as number),
    enabled: !!learnerId,
  })
}

export function useCourses() {
  return useQuery({ queryKey: learningKeys.courses, queryFn: learningApi.courses })
}

export function useEnroll(learnerId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (courseId: number) => learningApi.enroll(learnerId, courseId),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: learningKeys.path(learnerId) })
    },
  })
}
