import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { learningApi, profileApi, type CourseCatalogQuery, type Me } from '@/lib/api'

export const learningKeys = {
  path: (learnerId: number) => ['learner-path', learnerId] as const,
  courses: (query: CourseCatalogQuery) => ['courses', 'published', query] as const,
}

export function usePath(learnerId: number | null | undefined) {
  return useQuery({
    queryKey: learningKeys.path(learnerId ?? 0),
    queryFn: () => learningApi.path(learnerId as number),
    enabled: !!learnerId,
  })
}

export function useCourses(query: CourseCatalogQuery) {
  return useQuery({
    queryKey: learningKeys.courses(query),
    queryFn: () => learningApi.courses(query),
    placeholderData: (previous) => previous,
  })
}

export function useEnroll(learnerId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (courseId: number) => learningApi.enroll(learnerId, courseId),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: learningKeys.path(learnerId) })
      void qc.invalidateQueries({ queryKey: ['courses', 'published'] })
    },
  })
}

/** Parent/adult learner: establish their own profile and enroll in one action. */
export function useStartCourseAsSelf() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (courseId: number) => {
      const learner = await profileApi.ensureSelfLearner()
      await learningApi.enroll(learner.id, courseId)
      return learner
    },
    onSuccess: (learner) => {
      // Seed /me before the caller selects the profile, preventing the active
      // profile provider from briefly treating a newly-created id as stale.
      qc.setQueryData<Me>(['me'], (current) => {
        if (!current || current.learner_profiles.some((item) => item.id === learner.id)) return current
        return { ...current, learner_profiles: [...current.learner_profiles, learner] }
      })
      void qc.invalidateQueries({ queryKey: ['me'] })
      void qc.invalidateQueries({ queryKey: learningKeys.path(learner.id) })
      void qc.invalidateQueries({ queryKey: ['courses', 'published'] })
    },
  })
}
