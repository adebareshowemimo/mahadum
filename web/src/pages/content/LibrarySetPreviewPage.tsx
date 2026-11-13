import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Alert, Badge, Button, Card, CardBody, Skeleton } from '@/components/ui'
import { contentApi } from '@/lib/api'
import { useLibrarySet } from '@/lib/content/libraryQueries'
import { contentKeys } from '@/lib/content/queries'
import { authorToSlides } from '@/components/learning/player'
import { LessonDeck } from './CoursePreviewPage'

/** Author-only API data; preview grading and completion stay in this tab. */
export function LibrarySetPreviewPage() {
  const { setId, levelId } = useParams()
  const id = Number(setId)
  const set = useLibrarySet(id)
  const [activeId, setActiveId] = useState<number | null>(null)
  const [completed, setCompleted] = useState<number[]>([])
  const [run, setRun] = useState(0)
  const [readId, setReadId] = useState<number | null>(null)
  const lesson = useQuery({ queryKey: contentKeys.lesson(activeId ?? 0), queryFn: () => contentApi.lesson(activeId!), enabled: activeId !== null })
  const levels = (set.data?.levels ?? []).filter(level => !levelId || level.id === Number(levelId))
  const lessons = levels.flatMap(level => level.lessons)
  const next = lessons[lessons.findIndex(item => item.id === activeId) + 1]
  if (activeId !== null && lesson.data) {
    const built = authorToSlides(lesson.data)
    if (lesson.data.summary && readId !== activeId) return <main className="mx-auto max-w-3xl space-y-5 px-4 py-8"><p className="text-sm text-muted">Creator preview · nothing is saved</p><h1 className="font-display text-2xl font-bold">{lesson.data.title}</h1><p className="whitespace-pre-wrap rounded-xl border border-border bg-surface p-5">{lesson.data.summary}</p><Button onClick={() => {
      if (built.slides.length) setReadId(activeId)
      else { setCompleted(ids => ids.includes(activeId) ? ids : [...ids, activeId]); setActiveId(null) }
    }}>{built.slides.length ? 'Continue to lesson' : 'Complete reading'}</Button><Button variant="secondary" onClick={() => setActiveId(null)}>Back to set preview</Button></main>
    if (built.slides.length) return <LessonDeck key={`${activeId}-${run}`} lesson={lesson.data} built={built} onComplete={() => setCompleted(ids => ids.includes(activeId) ? ids : [...ids, activeId])} onExit={() => { setActiveId(null); setReadId(null) }} onReplay={() => { setRun(value => value + 1); setReadId(null) }} onNext={next ? () => { setActiveId(next.id); setReadId(null) } : undefined} nextTitle={next?.title} exitLabel="Back to set preview" />
  }
  return <div className="min-h-screen bg-background"><header className="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-surface p-4"><div><p className="text-xs font-bold uppercase text-primary">{levelId ? 'Level' : 'Set'} preview</p><h1 className="font-display text-xl font-bold">{set.data?.title ?? 'Preview'}</h1></div><Link to={`/courses/proverbs-trivia/${id}`} className="font-semibold text-primary">Back to editor</Link></header>
    <main className="mx-auto max-w-3xl space-y-5 px-4 py-6"><Alert variant="info">Draft lessons are included. Answers are graded locally; learner progress, hearts and XP are not saved.</Alert>
      {set.isLoading && <Skeleton className="h-48" />}{set.isError && <Alert variant="danger">Could not load this set.</Alert>}
      {set.data?.description && <p>{set.data.description}</p>}
      {levelId && <Link to={`/courses/proverbs-trivia/${id}/preview`} className="inline-block text-primary">← All levels</Link>}
      {activeId !== null && <div className="space-y-3">{lesson.isLoading ? <Skeleton className="h-32" /> : <Alert variant={lesson.isError ? 'danger' : 'info'}>{lesson.isError ? 'Could not load this lesson.' : 'Add text, video or quiz steps to preview this lesson.'}</Alert>}<Button variant="secondary" onClick={() => setActiveId(null)}>Back to levels</Button></div>}
      {set.data && !levels.length && <p className="text-muted">{levelId ? 'This level is not part of this set.' : 'Add levels and lessons to preview this set.'}</p>}
      {levels.map(level => <Card key={level.id}><CardBody className="space-y-3"><div className="flex items-center justify-between gap-3"><h2 className="font-display text-xl font-bold">{level.position}. {level.title}</h2><Badge variant={level.is_free ? 'success' : 'neutral'}>{level.is_free ? 'Free' : 'Paid'}</Badge></div>
        {!levelId && <Link className="inline-block text-sm font-semibold text-primary" to={`/courses/proverbs-trivia/${id}/levels/${level.id}/preview`}>Preview this level →</Link>}
        {!level.lessons.length && <p className="text-muted">No lessons yet.</p>}
        <ol className="space-y-2">{level.lessons.map(item => <li key={item.id} className="flex items-center justify-between gap-3 rounded-xl border border-border p-3"><div><p className="font-semibold">{item.position}. {item.title}</p><p className="text-sm text-muted">{item.est_minutes ? `${item.est_minutes} min · ` : ''}{item.published_at ? 'Published' : 'Draft'}{completed.includes(item.id) ? ' · Completed in preview' : ''}</p></div><Button variant="secondary" onClick={() => setActiveId(item.id)}>{completed.includes(item.id) ? 'Replay' : 'Play lesson'}</Button></li>)}</ol>
      </CardBody></Card>)}
    </main>
  </div>
}
