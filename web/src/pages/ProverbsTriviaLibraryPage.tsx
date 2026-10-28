import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Alert, Button, Skeleton } from '@/components/ui'
import { learningApi, type LibraryLesson } from '@/lib/api'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'

function LessonCard({ lesson, onOpen }: { lesson: LibraryLesson; onOpen: () => void }) {
  const proverb = lesson.kind === 'proverb'
  return <article className={`flex flex-col overflow-hidden rounded-2xl border border-border bg-surface ${proverb ? 'border-l-4 border-l-amber-400' : 'border-l-4 border-l-violet-500'}`}>
    <div className="flex-1 p-5">
      <p className="text-xs font-bold uppercase tracking-widest text-primary">{lesson.language.toUpperCase()} · {lesson.course_title}</p>
      <h3 className="mt-2 font-display text-lg font-bold">{lesson.title}</h3>
      <p className="mt-1 text-sm text-muted">{lesson.level_title}</p>
      <div className="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
        <span className="rounded-full bg-surface-muted px-3 py-1.5">{proverb ? `${lesson.proverb_count} ${lesson.proverb_count === 1 ? 'proverb' : 'proverbs'}` : `${lesson.question_count} ${lesson.question_count === 1 ? 'question' : 'questions'}`}</span>
        {!proverb && lesson.time_limit_seconds && <span className="rounded-full bg-surface-muted px-3 py-1.5">{lesson.time_limit_seconds}s per question</span>}
        {lesson.has_audio && <span className="rounded-full bg-surface-muted px-3 py-1.5">Audio</span>}
      </div>
    </div>
    <div className="border-t border-border px-5 py-4"><Button size="sm" onClick={onOpen}>{lesson.access_reason ? 'See plans' : proverb ? 'Explore set' : 'Play trivia'} →</Button></div>
  </article>
}

export function ProverbsTriviaLibraryPage() {
  const { activeLearner } = useActiveProfile()
  const navigate = useNavigate()
  const [proverbPage, setProverbPage] = useState(1)
  const [triviaPage, setTriviaPage] = useState(1)
  const proverbs = useQuery({ queryKey: ['learning', 'library-lessons', activeLearner?.id, 'proverb', proverbPage], queryFn: () => learningApi.libraryLessons({ kind: 'proverb', learner_id: activeLearner!.id, page: proverbPage }), enabled: !!activeLearner })
  const trivia = useQuery({ queryKey: ['learning', 'library-lessons', activeLearner?.id, 'trivia', triviaPage], queryFn: () => learningApi.libraryLessons({ kind: 'trivia', learner_id: activeLearner!.id, page: triviaPage }), enabled: !!activeLearner })
  const sample = proverbs.data?.data.find((lesson) => lesson.sample_proverb)
  const sections = [{ title: 'Proverb sets', description: 'Short lessons with sayings, meanings and practice.', query: proverbs, page: proverbPage, setPage: setProverbPage }, { title: 'Trivia sets', description: 'Quick challenges with rules chosen by the creator.', query: trivia, page: triviaPage, setPage: setTriviaPage }]

  return <div className="mx-auto flex max-w-7xl flex-col gap-8">
    <header><p className="text-xs font-bold uppercase tracking-[.2em] text-primary">Learn & play</p><h1 className="mt-2 font-display text-3xl font-bold sm:text-4xl">Proverbs & Trivia</h1><p className="mt-2 text-muted">Wisdom to explore and questions to enjoy with {activeLearner?.display_name ?? 'your learner'}.</p></header>
    {!activeLearner && <Alert variant="info">Choose a learner profile to browse these lessons.</Alert>}
    {sample && <section className="rounded-3xl bg-gradient-to-br from-amber-200 via-orange-100 to-violet-100 p-6 text-amber-950 sm:p-8">
      <p className="text-xs font-bold uppercase tracking-[.2em]">Proverb of the day</p>
      <blockquote className="mt-4 max-w-3xl font-display text-2xl font-bold leading-snug sm:text-3xl">“{sample.sample_proverb}”</blockquote>
      {sample.sample_meaning && <p className="mt-3 text-amber-900">{sample.sample_meaning}</p>}
      <Button className="mt-5" size="sm" onClick={() => navigate(sample.access_reason ? '/billing' : `/learn/lessons/${sample.id}`)}>Explore this set →</Button>
    </section>}
    {sections.map(({ title, description, query, page, setPage }) => <section key={title} className="flex flex-col gap-4">
      <div><h2 className="font-display text-xl font-bold">{title}</h2><p className="text-sm text-muted">{description}</p></div>
      {query.isError && <Alert variant="danger">Could not load {title.toLowerCase()}.</Alert>}
      {query.isLoading && <Skeleton className="h-40" />}
      {query.data && <><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{query.data.data.length === 0 && <p className="rounded-2xl border border-border bg-surface p-6 text-muted">No published {title.toLowerCase()} yet.</p>}{query.data.data.map((lesson) => <LessonCard key={lesson.id} lesson={lesson} onOpen={() => navigate(lesson.access_reason ? '/billing' : `/learn/lessons/${lesson.id}`)} />)}</div>
        {query.data.meta.last_page > 1 && <div className="flex items-center justify-between"><Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button><span className="text-sm text-muted">Page {page} of {query.data.meta.last_page}</span><Button size="sm" variant="secondary" disabled={page >= query.data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button></div>}
      </>}
    </section>)}
  </div>
}
