import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { learningApi } from '@/lib/api'

export const learningKeys = {
  path: (learnerId: number) => ['learner-path', learnerId] as const,
  courses: (learnerId: number) => ['courses', 'published', learnerId] as const,
}

export function usePath(learnerId: number | null | undefined) {
  return useQuery({
    queryKey: learningKeys.path(learnerId ?? 0),
    queryFn: () => learningApi.path(learnerId as number),
    enabled: !!learnerId,
  })
}

export function useCourses(learnerId: number | null | undefined) {
  return useQuery({
    queryKey: learningKeys.courses(learnerId ?? 0),
    queryFn: () => learningApi.courses(learnerId as number),
    enabled: !!learnerId,
  })
}

export function useEnroll(learnerId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (courseId: number) => learningApi.enroll(learnerId, courseId),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: learningKeys.path(learnerId) })
      void qc.invalidateQueries({ queryKey: learningKeys.courses(learnerId) })
    },
  })
}
