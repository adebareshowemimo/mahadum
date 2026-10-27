import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Alert, Button, Skeleton } from '@/components/ui'
import { learningApi } from '@/lib/api'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'

export function ProverbsTriviaLibraryPage() {
  const { activeLearner } = useActiveProfile()
  const navigate = useNavigate()
  const [kind, setKind] = useState<'proverb' | 'trivia'>('proverb')
  const [page, setPage] = useState(1)
  const lessons = useQuery({
    queryKey: ['learning', 'library-lessons', activeLearner?.id, kind, page],
    queryFn: () => learningApi.libraryLessons({ kind, learner_id: activeLearner!.id, page }),
    enabled: !!activeLearner,
  })

  return <div className="flex flex-col gap-6">
    <header>
      <h1 className="font-display text-2xl font-bold">Proverbs & Trivia</h1>
      <p className="mt-1 text-muted">Choose a short lesson to watch or play with {activeLearner?.display_name ?? 'your learner'}.</p>
    </header>
    {!activeLearner && <Alert variant="info">Choose a learner profile to browse these lessons.</Alert>}
    <div className="flex gap-2" role="tablist" aria-label="Browse by type">
      {(['proverb', 'trivia'] as const).map((item) => <Button key={item} variant={kind === item ? 'primary' : 'secondary'} onClick={() => { setKind(item); setPage(1) }}>{item === 'proverb' ? 'Proverbs' : 'Trivia'}</Button>)}
    </div>
    {lessons.isError && <Alert variant="danger">Could not load this library.</Alert>}
    {lessons.isLoading ? <Skeleton className="h-48" /> : <div className="grid gap-4 md:grid-cols-2">
      {lessons.data?.data.length === 0 && activeLearner && <p className="text-muted">No published {kind} lessons yet.</p>}
      {lessons.data?.data.map((lesson) => <article key={lesson.id} className="rounded-2xl border border-border bg-surface p-5">
        <p className="text-xs font-semibold uppercase text-primary">{lesson.language} · {lesson.course_title}</p>
        <h2 className="mt-2 font-display text-lg font-bold">{lesson.title}</h2>
        <p className="mt-1 text-sm text-muted">{lesson.level_title} · About {lesson.est_minutes ?? 5} minutes</p>
        <Button className="mt-4" size="sm" onClick={() => navigate(lesson.access_reason ? '/billing' : `/learn/lessons/${lesson.id}`)}>
          {lesson.access_reason ? 'See plans' : 'Watch lesson'}
        </Button>
      </article>)}
    </div>}
    {lessons.data && lessons.data.meta.last_page > 1 && <div className="flex items-center justify-between">
      <Button variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
      <span className="text-sm text-muted">Page {page} of {lessons.data.meta.last_page}</span>
      <Button variant="secondary" disabled={page >= lessons.data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button>
    </div>}
  </div>
}
