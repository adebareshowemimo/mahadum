import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Badge, Button, Input, Modal, Skeleton } from '@/components/ui'
import { ApiError, contentApi, type LibrarySetSummary } from '@/lib/api'
import { useConfig } from '@/lib/config/useConfig'
import { useCanManageContent } from '@/lib/content/permissions'

type Kind = 'proverb' | 'trivia'

export function ProverbsTriviaPage() {
  const navigate = useNavigate()
  const client = useQueryClient()
  const canManage = useCanManageContent()
  const config = useConfig()
  const [kind, setKind] = useState<Kind>('proverb')
  const [language, setLanguage] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [languageId, setLanguageId] = useState(0)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const sets = useQuery({ queryKey: ['content', 'library-sets', kind, language, search, page], queryFn: () => contentApi.librarySets({ kind, language: language || undefined, q: search || undefined, page }) })

  async function create(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError(null)
    try {
      const set = await contentApi.createLibraryCollection({ kind, language_id: languageId, title: title.trim(), description: description.trim() })
      await client.invalidateQueries({ queryKey: ['content', 'library-sets'] })
      setCreating(false); navigate(`/courses/proverbs-trivia/${set.id}`)
    } catch (err) { setError(err instanceof ApiError ? err.message : 'Could not create this set.') }
    finally { setBusy(false) }
  }

  async function remove(set: LibrarySetSummary) {
    if (!window.confirm(`Delete “${set.title}” and its lessons?`)) return
    setBusy(true); setError(null)
    try { await contentApi.deleteLibraryCollection(set.id); await client.invalidateQueries({ queryKey: ['content', 'library-sets'] }) }
    catch (err) { setError(err instanceof ApiError ? err.message : 'Could not delete this set.') }
    finally { setBusy(false) }
  }

  return <div className="flex flex-col gap-6">
    <header className="flex flex-wrap items-end justify-between gap-3"><div><h1 className="font-display text-2xl font-bold">Proverbs & Trivia</h1><p className="mt-1 text-muted">Build independent sets with levels and lessons.</p></div>{canManage && <Button onClick={() => { setTitle(''); setDescription(''); setLanguageId(0); setError(null); setCreating(true) }}>New {kind} set</Button>}</header>
    <div className="flex flex-wrap gap-2" role="tablist" aria-label="Library type">{(['proverb', 'trivia'] as const).map((item) => <Button key={item} role="tab" aria-selected={kind === item} variant={kind === item ? 'primary' : 'secondary'} onClick={() => { setKind(item); setPage(1) }}>{item === 'proverb' ? 'Proverb sets' : 'Trivia sets'}</Button>)}</div>
    <div className="grid gap-3 rounded-2xl border border-border bg-surface p-4 sm:grid-cols-[1fr_12rem]"><Input label="Search sets" type="search" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1) }} placeholder="Search by title" /><label className="flex flex-col gap-1.5 text-sm font-semibold">Language<select className="h-11 rounded-xl border border-border-strong bg-surface px-3" value={language} onChange={(event) => { setLanguage(event.target.value); setPage(1) }}><option value="">All languages</option>{config.data?.languages.map((item) => <option key={item.id} value={item.code}>{item.name}</option>)}</select></label></div>
    {error && !creating && <Alert variant="danger">{error}</Alert>}
    {sets.isError && <Alert variant="danger">Could not load sets.</Alert>}
    {sets.isLoading && <Skeleton className="h-48" />}
    {sets.data && <><div className="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-surface">{sets.data.data.length === 0 && <p className="p-6 text-muted">No {kind} sets yet.</p>}{sets.data.data.map((set) => <article key={set.id} className="flex flex-wrap items-center justify-between gap-4 p-5"><div><div className="flex items-center gap-2"><h2 className="font-display text-lg font-bold">{set.title}</h2><Badge variant={set.status === 'published' ? 'success' : 'neutral'}>{set.status}</Badge></div>{set.description && <p className="mt-1 text-sm text-muted">{set.description}</p>}<p className="mt-2 text-xs font-semibold text-muted">{set.language?.toUpperCase() ?? 'Language'} · {set.levels_count} levels · {set.lessons_count} published lessons</p></div><div className="flex gap-2"><Button size="sm" variant="secondary" onClick={() => navigate(`/courses/proverbs-trivia/${set.id}`)}>{canManage ? 'Edit' : 'View'}</Button>{canManage && <Button size="sm" variant="danger" disabled={busy} onClick={() => void remove(set)}>Delete</Button>}</div></article>)}</div>{sets.data.meta.last_page > 1 && <div className="flex items-center justify-between"><Button variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button><span className="text-sm">Page {page} of {sets.data.meta.last_page}</span><Button variant="secondary" disabled={page >= sets.data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button></div>}</>}
    <Modal open={creating} onClose={() => setCreating(false)} title={`New ${kind} set`} description="Create the set, then add levels and lessons as you do for a course."><form className="flex flex-col gap-4" onSubmit={create}>{error && <Alert variant="danger">{error}</Alert>}<Input label="Set title" value={title} onChange={(event) => setTitle(event.target.value)} required /><Input label="Description" value={description} onChange={(event) => setDescription(event.target.value)} /><label className="flex flex-col gap-1.5 text-sm font-semibold">Language<select className="h-11 rounded-xl border border-border-strong bg-surface px-3" value={languageId} onChange={(event) => setLanguageId(Number(event.target.value))} required><option value={0}>Choose a language</option>{config.data?.languages.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><Button type="submit" loading={busy} disabled={!title.trim() || !languageId}>Create set</Button></form></Modal>
  </div>
}
