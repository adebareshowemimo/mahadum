import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Alert, Button, Input, Skeleton } from '@/components/ui'
import { learningApi } from '@/lib/api'
import { useConfig } from '@/lib/config/useConfig'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'

export function ProverbsTriviaLibraryPage() {
  const { activeLearner } = useActiveProfile()
  const navigate = useNavigate()
  const config = useConfig()
  const [kind, setKind] = useState<'proverb' | 'trivia'>('proverb')
  const [language, setLanguage] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const sets = useQuery({ queryKey: ['learning', 'library-sets', activeLearner?.id, kind, language, search, page],
    queryFn: () => learningApi.librarySets({ learner_id: activeLearner!.id, kind, language: language || undefined, q: search || undefined, page }), enabled: !!activeLearner })

  return <div className="mx-auto flex max-w-7xl flex-col gap-6">
    <header><p className="text-xs font-bold uppercase tracking-[.2em] text-primary">Learn & play</p><h1 className="mt-2 font-display text-3xl font-bold">Proverbs & Trivia</h1><p className="mt-2 text-muted">Choose a set, then move through its levels and lessons.</p></header>
    {!activeLearner && <Alert variant="info">Choose a learner profile to browse these sets.</Alert>}
    <div className="flex flex-wrap gap-2" role="tablist" aria-label="Library type">{(['proverb', 'trivia'] as const).map((item) => <Button key={item} role="tab" aria-selected={kind === item} variant={kind === item ? 'primary' : 'secondary'} onClick={() => { setKind(item); setPage(1) }}>{item === 'proverb' ? 'Proverb sets' : 'Trivia sets'}</Button>)}</div>
    <div className="grid gap-3 rounded-2xl border border-border bg-surface p-4 sm:grid-cols-[1fr_12rem]"><Input label="Search sets" type="search" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1) }} /><label className="flex flex-col gap-1.5 text-sm font-semibold">Language<select className="h-11 rounded-xl border border-border-strong bg-surface px-3" value={language} onChange={(event) => { setLanguage(event.target.value); setPage(1) }}><option value="">All languages</option>{config.data?.languages.map((item) => <option key={item.id} value={item.code}>{item.name}</option>)}</select></label></div>
    {sets.isError && <Alert variant="danger">Could not load these sets.</Alert>}
    {sets.isLoading && <Skeleton className="h-48" />}
    {sets.data && <><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{sets.data.data.length === 0 && <p className="rounded-2xl border border-border bg-surface p-6 text-muted">No published {kind} sets yet.</p>}{sets.data.data.map((set) => <article key={set.id} className="flex flex-col rounded-2xl border border-border bg-surface"><div className="flex-1 p-5"><p className="text-xs font-bold uppercase tracking-widest text-primary">{set.language?.toUpperCase() ?? 'Language'}</p><h2 className="mt-2 font-display text-xl font-bold">{set.title}</h2>{set.description && <p className="mt-2 text-sm text-muted">{set.description}</p>}<p className="mt-4 text-sm font-semibold">{set.levels_count} levels · {set.lessons_count} lessons</p>{set.completed_count > 0 && <p className="mt-1 text-sm text-primary">{set.completed_count} completed</p>}</div><div className="border-t border-border p-4"><Button size="sm" onClick={() => navigate(`/learn/proverbs-trivia/${set.id}`)}>{set.completed_count > 0 ? 'Continue' : 'Explore set'} →</Button></div></article>)}</div>{sets.data.meta.last_page > 1 && <div className="flex items-center justify-between"><Button variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button><span>Page {page} of {sets.data.meta.last_page}</span><Button variant="secondary" disabled={page >= sets.data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button></div>}</>}
  </div>
}
