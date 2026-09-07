import { Link } from 'react-router-dom'
import { Alert, Badge, Skeleton } from '@/components/ui'
import { useCourseLevels, useLevelLessons } from '@/lib/content/queries'
import type { AuthorLevel } from '@/lib/api'
import { cn } from '@/lib/cn'

/** The saved course hierarchy is the single source of truth for navigation. */
export function CourseContents({ courseId, currentLessonId }: { courseId: number; currentLessonId?: number }) {
  const levels = useCourseLevels(courseId)

  return (
    <details open className="rounded-2xl border border-border bg-surface">
      <summary className="cursor-pointer px-4 py-3 font-display text-lg font-bold text-foreground">
        Table of contents
      </summary>
      <div className="border-t border-border p-4">
        <p className="mb-3 text-sm text-muted">Your units and lessons appear here automatically. Add, rename or reorder them in the course structure.</p>
        {currentLessonId != null && (
          <Link to={`/courses/${courseId}#course-structure`} className="mb-3 inline-block text-sm font-semibold text-primary hover:underline">
            Edit table of contents
          </Link>
        )}
        {levels.isLoading ? <Skeleton className="h-20" /> : levels.isError ? (
          <Alert variant="danger">Couldn’t load the table of contents.</Alert>
        ) : !levels.data?.length ? (
          <p className="text-sm text-muted">Add a level and lessons below to create your table of contents.</p>
        ) : (
          <nav aria-label="Course table of contents" className="max-h-80 overflow-y-auto">
            <ol className="space-y-4">
              {[...levels.data].sort((a, b) => a.position - b.position || a.id - b.id).map((level) => (
                <ContentsUnit key={level.id} courseId={courseId} level={level} currentLessonId={currentLessonId} />
              ))}
            </ol>
          </nav>
        )}
      </div>
    </details>
  )
}

function ContentsUnit({ courseId, level, currentLessonId }: { courseId: number; level: AuthorLevel; currentLessonId?: number }) {
  const lessons = useLevelLessons(level.id)
  return (
    <li>
      <p className="mb-1 text-sm font-semibold text-foreground">{level.title}</p>
      {lessons.isLoading ? <Skeleton className="h-10" /> : lessons.isError ? (
        <Alert variant="danger">Couldn’t load lessons for {level.title}.</Alert>
      ) : !lessons.data?.length ? <p className="pl-3 text-sm text-muted">No lessons yet.</p> : (
        <ol className="space-y-1">
          {[...lessons.data].sort((a, b) => a.position - b.position || a.id - b.id).map((lesson) => (
            <li key={lesson.id}>
              <Link
                to={`/courses/${courseId}/lessons/${lesson.id}`}
                aria-current={lesson.id === currentLessonId ? 'page' : undefined}
                className={cn('flex flex-wrap items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                  lesson.id === currentLessonId ? 'bg-primary-soft font-semibold text-primary' : 'text-foreground')}
              >
                <span>{lesson.title}</span>
                <span className="flex items-center gap-2">
                  {lesson.est_minutes != null && <span className="text-xs text-muted">{lesson.est_minutes} min</span>}
                  {!lesson.is_published && <Badge variant="neutral">Draft</Badge>}
                </span>
              </Link>
            </li>
          ))}
        </ol>
      )}
    </li>
  )
}
