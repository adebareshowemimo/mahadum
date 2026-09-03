import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  contentApi,
  type AddComponentInput,
  type AdminCoursesQuery,
  type AuthorLesson,
  type AuthorLevel,
  type CreateCourseInput,
  type CreateLessonInput,
  type CreateLevelInput,
  type MediaQuery,
} from '@/lib/api'

export const contentKeys = {
  courses: ['content', 'courses'] as const,
  coursesPaged: (params: AdminCoursesQuery) => ['content', 'courses-paged', params] as const,
  levels: (courseId: number) => ['content', 'levels', courseId] as const,
  lessons: (levelId: number) => ['content', 'lessons', levelId] as const,
  lesson: (lessonId: number) => ['content', 'lesson', lessonId] as const,
  media: (params: MediaQuery = {}) => ['content', 'media', 'picker', params] as const,
  mediaLibraryInfinite: (params: MediaQuery) => ['content', 'media', 'library-infinite', params] as const,
  mediaOrphans: (params: MediaQuery = {}) => ['content', 'media', 'orphans', params] as const,
  analytics: (lessonId: number) => ['content', 'lesson-analytics', lessonId] as const,
  coursesPerformance: ['content', 'courses-performance'] as const,
}

/** Lesson drop-off + quiz-item analytics (authoring "Insights"). */
export function useLessonAnalytics(lessonId: number, enabled = true) {
  return useQuery({
    queryKey: contentKeys.analytics(lessonId),
    queryFn: () => contentApi.lessonAnalytics(lessonId),
    enabled,
  })
}

/** Per-course engagement dashboard (content_owner: own courses; super_admin: every course). */
export function useCoursesPerformance() {
  return useQuery({ queryKey: contentKeys.coursesPerformance, queryFn: contentApi.coursesPerformance })
}

/** Lightweight list (first page) — for the video picker. */
export function useMediaAssets(params: MediaQuery = {}) {
  return useQuery({ queryKey: contentKeys.media(params), queryFn: () => contentApi.media(params) })
}

/**
 * Infinite-scroll library for the Media page. `params` should omit `page` — it is
 * supplied as the page cursor. Scales to any number of assets without prev/next.
 */
export function useMediaLibraryInfinite(params: MediaQuery) {
  return useInfiniteQuery({
    queryKey: contentKeys.mediaLibraryInfinite(params),
    queryFn: ({ pageParam }) => contentApi.mediaLibrary({ ...params, page: pageParam }),
    initialPageParam: 1,
    getNextPageParam: (last) =>
      last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined,
  })
}

// Both media lists share the ['content','media'*] prefix so uploads/deletes refresh all.
const MEDIA_PREFIX = ['content', 'media'] as const

export function useUploadMedia() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (file: File) => contentApi.uploadMedia(file),
    onSuccess: () => void qc.invalidateQueries({ queryKey: MEDIA_PREFIX }),
  })
}

export function useDeleteMedia() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => contentApi.deleteMedia(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: MEDIA_PREFIX }),
  })
}

/** Unreferenced assets, for the cleanup panel. Enabled on demand. */
export function useMediaOrphans(params: MediaQuery, enabled = true) {
  return useQuery({
    queryKey: contentKeys.mediaOrphans(params),
    queryFn: () => contentApi.mediaOrphans(params),
    enabled,
    placeholderData: (prev) => prev,
  })
}

export function usePurgeMediaOrphans() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (ids: number[]) => contentApi.purgeMediaOrphans(ids),
    onSuccess: () => void qc.invalidateQueries({ queryKey: MEDIA_PREFIX }),
  })
}

export function useAuthorCourses() {
  return useQuery({ queryKey: contentKeys.courses, queryFn: contentApi.courses })
}

export function useCreateCourse() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateCourseInput) => contentApi.createCourse(input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: contentKeys.courses }),
  })
}

export function useUpdateCourse() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ courseId, input }: { courseId: number; input: Partial<CreateCourseInput> }) =>
      contentApi.updateCourse(courseId, input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: contentKeys.courses })
      void qc.invalidateQueries({ queryKey: ['content', 'courses-paged'] })
    },
  })
}

export function useAdminCourses(params: AdminCoursesQuery) {
  return useQuery({
    queryKey: contentKeys.coursesPaged(params),
    queryFn: () => contentApi.coursesPaged(params),
    placeholderData: (prev) => prev,
  })
}

export function useSetCoursePublished() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ courseId, publish }: { courseId: number; publish: boolean }) =>
      publish ? contentApi.publishCourse(courseId) : contentApi.unpublishCourse(courseId),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['content', 'courses-paged'] })
      void qc.invalidateQueries({ queryKey: contentKeys.courses })
      // Publishing a course cascades to its draft lessons, so any lesson list
      // or detail already in cache (the course builder) is now stale.
      void qc.invalidateQueries({ queryKey: ['content', 'lessons'] })
      void qc.invalidateQueries({ queryKey: ['content', 'lesson'] })
    },
  })
}

export function useSetCourseArchived() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ courseId, archive }: { courseId: number; archive: boolean }) =>
      archive ? contentApi.archiveCourse(courseId) : contentApi.unarchiveCourse(courseId),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['content', 'courses-paged'] })
      void qc.invalidateQueries({ queryKey: contentKeys.courses })
    },
  })
}

export function useDeleteCourse() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (courseId: number) => contentApi.deleteCourse(courseId),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['content', 'courses-paged'] })
      void qc.invalidateQueries({ queryKey: contentKeys.courses })
    },
  })
}

export function useCourseLevels(courseId: number) {
  return useQuery({ queryKey: contentKeys.levels(courseId), queryFn: () => contentApi.levels(courseId) })
}

export function useCreateLevel(courseId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateLevelInput) => contentApi.createLevel(courseId, input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: contentKeys.levels(courseId) }),
  })
}

export function useUpdateLevel(courseId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ levelId, input }: { levelId: number; input: Partial<CreateLevelInput> }) =>
      contentApi.updateLevel(levelId, input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: contentKeys.levels(courseId) }),
  })
}

export function useReorderLevels(courseId: number) {
  const qc = useQueryClient()
  const key = contentKeys.levels(courseId)
  return useMutation({
    mutationFn: (order: number[]) => contentApi.reorderLevels(courseId, order),
    onMutate: async (order) => {
      await qc.cancelQueries({ queryKey: key })
      const previous = qc.getQueryData<AuthorLevel[]>(key)
      if (previous) {
        const byId = new Map(previous.map((l) => [l.id, l]))
        qc.setQueryData(
          key,
          order.map((id, i) => ({ ...byId.get(id)!, position: i + 1 })),
        )
      }
      return { previous }
    },
    onError: (_err, _order, context) => {
      if (context?.previous) qc.setQueryData(key, context.previous)
    },
    onSettled: () => void qc.invalidateQueries({ queryKey: key }),
  })
}

export function useDeleteLevel(courseId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (levelId: number) => contentApi.deleteLevel(levelId),
    onSuccess: () => void qc.invalidateQueries({ queryKey: contentKeys.levels(courseId) }),
  })
}

export function useLevelLessons(levelId: number) {
  return useQuery({ queryKey: contentKeys.lessons(levelId), queryFn: () => contentApi.lessons(levelId) })
}

export function useCreateLesson(levelId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateLessonInput) => contentApi.createLesson(levelId, input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: contentKeys.lessons(levelId) }),
  })
}

export function useUpdateLesson(levelId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ lessonId, input }: { lessonId: number; input: Partial<CreateLessonInput> }) =>
      contentApi.updateLesson(lessonId, input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: contentKeys.lessons(levelId) }),
  })
}

export function useDeleteLesson(levelId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (lessonId: number) => contentApi.deleteLesson(lessonId),
    onSuccess: () => void qc.invalidateQueries({ queryKey: contentKeys.lessons(levelId) }),
  })
}

export function useReorderLessons(levelId: number) {
  const qc = useQueryClient()
  const key = contentKeys.lessons(levelId)
  return useMutation({
    mutationFn: (order: number[]) => contentApi.reorderLessons(levelId, order),
    onMutate: async (order) => {
      await qc.cancelQueries({ queryKey: key })
      const previous = qc.getQueryData<AuthorLesson[]>(key)
      if (previous) {
        const byId = new Map(previous.map((l) => [l.id, l]))
        qc.setQueryData(
          key,
          order.map((id, i) => ({ ...byId.get(id)!, position: i + 1 })),
        )
      }
      return { previous }
    },
    onError: (_err, _order, context) => {
      if (context?.previous) qc.setQueryData(key, context.previous)
    },
    onSettled: () => void qc.invalidateQueries({ queryKey: key }),
  })
}

export function useLessonDetail(lessonId: number) {
  return useQuery({ queryKey: contentKeys.lesson(lessonId), queryFn: () => contentApi.lesson(lessonId) })
}

export function useAddComponent(lessonId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: AddComponentInput) => contentApi.addComponent(lessonId, input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: contentKeys.lesson(lessonId) }),
  })
}

export function useUpdateComponent(lessonId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ componentId, input }: { componentId: number; input: AddComponentInput }) =>
      contentApi.updateComponent(componentId, input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: contentKeys.lesson(lessonId) }),
  })
}

export function useDeleteComponent(lessonId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (componentId: number) => contentApi.deleteComponent(componentId),
    onSuccess: () => void qc.invalidateQueries({ queryKey: contentKeys.lesson(lessonId) }),
  })
}

export function useReorderComponents(lessonId: number) {
  const qc = useQueryClient()
  const key = contentKeys.lesson(lessonId)
  return useMutation({
    mutationFn: (order: number[]) => contentApi.reorderComponents(lessonId, order),
    onMutate: async (order) => {
      await qc.cancelQueries({ queryKey: key })
      const previous = qc.getQueryData<AuthorLesson>(key)
      if (previous?.components) {
        const byId = new Map(previous.components.map((c) => [c.id, c]))
        qc.setQueryData(key, {
          ...previous,
          components: order.map((id, i) => ({ ...byId.get(id)!, position: i + 1 })),
        })
      }
      return { previous }
    },
    onError: (_err, _order, context) => {
      if (context?.previous) qc.setQueryData(key, context.previous)
    },
    onSettled: () => void qc.invalidateQueries({ queryKey: key }),
  })
}

export function usePublishLesson(lessonId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => contentApi.publishLesson(lessonId),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: contentKeys.lesson(lessonId) })
      // Reflect published state in the course-builder lesson lists.
      void qc.invalidateQueries({ queryKey: ['content', 'lessons'] })
    },
  })
}
