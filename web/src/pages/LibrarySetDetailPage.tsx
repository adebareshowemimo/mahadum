import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Alert, Badge, Button, Card, CardBody, Skeleton } from '@/components/ui'
import { learningApi } from '@/lib/api'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'

export function LibrarySetDetailPage() {
  const id = Number(useParams().setId)
  const navigate = useNavigate()
  const { activeLearner } = useActiveProfile()
  const set = useQuery({ queryKey: ['learning', 'library-set', id, activeLearner?.id], queryFn: () => learningApi.librarySet(id, activeLearner!.id), enabled: !!activeLearner && id > 0 })
  if (!activeLearner) return <Alert variant="info">Choose a learner profile to open this set.</Alert>
  if (set.isLoading) return <Skeleton className="h-64" />
  if (set.isError || !set.data) return <Alert variant="danger">Could not open this set.</Alert>
  const item = set.data
  const lessons = (item.levels ?? []).flatMap((level) => level.lessons)
  const next = lessons.find((lesson) => !lesson.completed && !lesson.access_reason)
  const open = (lessonId: number) => navigate(`/learn/lessons/${lessonId}?return=${encodeURIComponent(`/learn/proverbs-trivia/${id}`)}`)
  return <div className="mx-auto flex max-w-4xl flex-col gap-6"><header><Button variant="ghost" onClick={() => navigate('/learn/proverbs-trivia')}>← All sets</Button><p className="mt-3 text-xs font-bold uppercase tracking-widest text-primary">{item.kind} · {item.language?.toUpperCase()}</p><h1 className="mt-1 font-display text-3xl font-bold">{item.title}</h1>{item.description && <p className="mt-2 text-muted">{item.description}</p>}<p className="mt-3 text-sm font-semibold">{item.completed_count} of {item.lessons_count} lessons complete</p>{next && <Button className="mt-4" onClick={() => open(next.id)}>{item.completed_count > 0 ? 'Continue learning' : 'Start set'} →</Button>}</header>
    {(item.levels ?? []).map((level) => <Card key={level.id}><CardBody className="space-y-3"><div className="flex items-center gap-2"><h2 className="font-display text-xl font-bold">{level.position}. {level.title}</h2><Badge variant={level.is_free ? 'success' : 'neutral'}>{level.is_free ? 'Free' : 'Paid'}</Badge></div>{level.lessons.length === 0 && <p className="text-sm text-muted">No published lessons in this level yet.</p>}{level.lessons.map((lesson) => <div key={lesson.id} className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3"><div><h3 className="font-semibold">{lesson.title}</h3>{lesson.summary && <p className="line-clamp-2 text-sm text-muted">{lesson.summary}</p>}{lesson.completed && <p className="text-xs font-bold text-primary">Completed</p>}</div><Button size="sm" variant="secondary" disabled={!!lesson.access_reason && activeLearner.is_child} onClick={() => lesson.access_reason ? navigate('/billing') : open(lesson.id)}>{lesson.access_reason ? activeLearner.is_child ? 'Ask a grown-up' : 'See plans' : lesson.completed ? 'Review' : 'Start lesson'}</Button></div>)}</CardBody></Card>)}
  </div>
}
