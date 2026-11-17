import { Link } from 'react-router-dom'
import { Alert, Badge, Skeleton } from '@/components/ui'
import { useLibrarySet } from '@/lib/content/libraryQueries'

export function LibrarySetContents({ setId, currentLessonId }: { setId: number; currentLessonId?: number }) {
  const set = useLibrarySet(setId)
  return <details open className="rounded-2xl border border-border bg-surface">
    <summary className="cursor-pointer px-4 py-3 font-display text-lg font-bold">Table of contents</summary>
    <div className="border-t border-border p-4">
      <p className="mb-3 text-sm text-muted">Your levels and lessons appear here automatically. Add, rename or reorder them in the set structure.</p>
      {currentLessonId != null && <Link to={`/courses/proverbs-trivia/${setId}#set-structure`} className="mb-3 inline-block text-sm font-semibold text-primary">Edit table of contents</Link>}
      {set.isLoading ? <Skeleton className="h-20" /> : set.isError ? <Alert variant="danger">Could not load the table of contents.</Alert> : !set.data?.levels?.length ? <p className="text-sm text-muted">Add levels and lessons to create your table of contents.</p> :
        <nav aria-label="Set table of contents" className="max-h-80 overflow-y-auto"><ol className="space-y-4">{set.data.levels.map(level => <li key={level.id}>
          <p className="mb-1 text-sm font-semibold">{level.title} <Badge variant={level.is_free ? 'success' : 'neutral'}>{level.is_free ? 'Free' : 'Paid'}</Badge></p>
          {!level.lessons.length && <p className="pl-3 text-sm text-muted">No lessons yet.</p>}
          <ol>{level.lessons.map(lesson => <li key={lesson.id}><Link to={`/courses/proverbs-trivia/${setId}/lessons/${lesson.id}`} aria-current={lesson.id === currentLessonId ? 'page' : undefined} className={`flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm hover:bg-surface-muted ${lesson.id === currentLessonId ? 'bg-primary-soft font-semibold text-primary' : ''}`}>
            <span>{lesson.title}</span><span className="flex items-center gap-2">{lesson.est_minutes != null && <span>{lesson.est_minutes} min</span>}{!lesson.published_at && <Badge variant="neutral">Draft</Badge>}</span>
          </Link></li>)}</ol>
        </li>)}</ol></nav>}
    </div>
  </details>
}
