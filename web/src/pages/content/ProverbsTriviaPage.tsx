import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { AdminToolbar, DataTable, FilterSelect, type Column } from '@/components/admin'
import { Alert, Badge, Button, Input, Modal } from '@/components/ui'
import { ApiError, contentApi, type LibrarySetSummary } from '@/lib/api'
import { useConfig } from '@/lib/config/useConfig'
import { useCanManageContent } from '@/lib/content/permissions'
import { libraryKeys, useRefreshLibrary } from '@/lib/content/libraryQueries'
import { LibraryDeleteModal, LibraryError } from '@/components/content/LibraryFeedback'

type Kind = 'proverb' | 'trivia'

export function ProverbsTriviaPage() {
  const navigate = useNavigate()
  const refresh = useRefreshLibrary()
  const canManage = useCanManageContent()
  const config = useConfig()
  const [kind, setKind] = useState<Kind>('proverb')
  const [language, setLanguage] = useState('')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [values, setValues] = useState({ title: '', description: '', language_id: '', level_band: '' })
  const [actingId, setActingId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const [report, setReport] = useState('')
  const [deleting, setDeleting] = useState<LibrarySetSummary | null>(null)
  const query = { kind, language: language || undefined, q: search || undefined, status: status || undefined, page }
  const sets = useQuery({ queryKey: [...libraryKeys.lists, query], queryFn: () => contentApi.librarySets(query) })
  const fields = error instanceof ApiError ? error.fieldErrors : {}
  const busy = saving || actingId !== null
  const filter = (setter: (value: string) => void) => (value: string) => { setter(value); setPage(1) }

  async function create(event: FormEvent) {
    event.preventDefault(); setSaving(true); setError(null)
    try {
      const set = await contentApi.createLibraryCollection({ ...values, kind, language_id: Number(values.language_id), title: values.title.trim() })
      await refresh(); setCreating(false); navigate(`/courses/proverbs-trivia/${set.id}`)
    } catch (err) { setError(err) } finally { setSaving(false) }
  }

  async function action(set: LibrarySetSummary, operation: 'publish' | 'archive' | 'delete') {
    setActingId(set.id); setError(null); setReport('')
    try {
      if (operation === 'publish') {
        const result = await contentApi.setLibraryCollectionPublished(set.id, set.status !== 'published')
        setReport(result.status === 'published' ? `${result.title} is published. All ${result.lessons_count} lessons are live.` : `${result.title} is now unpublished.`)
      } else if (operation === 'archive') {
        await contentApi.setLibraryCollectionArchived(set.id, set.status !== 'archived')
        setReport(set.status === 'archived' ? `${set.title} restored as a draft. Publish it when ready.` : `${set.title} archived.`)
      } else {
        await contentApi.deleteLibraryCollection(set.id); setDeleting(null)
        if (sets.data?.data.length === 1 && page > 1) setPage(page - 1)
      }
      await refresh()
    } catch (err) { setError(err) } finally { setActingId(null) }
  }

  const columns: Column<LibrarySetSummary>[] = [
    { key: 'title', header: 'Set', render: set => <div><p className="font-semibold">{set.title}</p><p className="text-xs uppercase text-muted">{set.language ?? '—'} · {set.level_band || 'no band'} · {set.levels_count} levels · {set.lessons_count} published lessons</p>{set.description && <p className="mt-1 text-sm text-muted">{set.description}</p>}</div> },
    { key: 'owner', header: 'Owner', hideOnMobile: true, render: set => set.owner ?? '—' },
    { key: 'status', header: 'Status', render: set => <Badge variant={set.status === 'published' ? 'success' : 'neutral'}>{set.status}</Badge> },
    { key: 'actions', header: 'Actions', render: set => <div className="flex flex-wrap justify-end gap-2">
      <Button size="sm" variant="ghost" onClick={() => navigate(`/courses/proverbs-trivia/${set.id}`)}>{canManage ? 'Edit' : 'View'}</Button>
      <Button size="sm" variant="ghost" onClick={() => window.open(`/courses/proverbs-trivia/${set.id}/preview`, '_blank', 'noopener')}>Preview</Button>
      {canManage && <>{set.status !== 'archived' && <Button size="sm" variant={set.status === 'published' ? 'ghost' : 'parent'} disabled={busy} onClick={() => void action(set, 'publish')}>{set.status === 'published' ? 'Unpublish' : 'Publish'}</Button>}
        <Button size="sm" variant="ghost" disabled={busy} onClick={() => void action(set, 'archive')}>{set.status === 'archived' ? 'Unarchive' : 'Archive'}</Button>
        <Button size="sm" variant="danger" disabled={busy} onClick={() => { setError(null); setDeleting(set) }}>Delete</Button></>}
    </div> },
  ]

  return <div className="flex flex-col gap-6">
    <header><Link to="/courses" className="text-sm font-semibold text-primary">← Courses</Link><div className="mt-2 flex flex-wrap items-end justify-between gap-3"><div><h1 className="font-display text-2xl font-bold">Proverbs & Trivia</h1><p className="mt-1 text-muted">Build independent sets with levels and lessons.</p></div>{canManage && <Button onClick={() => { setValues({ title: '', description: '', language_id: '', level_band: '' }); setError(null); setCreating(true) }}>New {kind} set</Button>}</div></header>
    <div className="flex gap-2" role="group" aria-label="Library type">{(['proverb', 'trivia'] as const).map(item => <Button key={item} aria-pressed={kind === item} variant={kind === item ? 'primary' : 'secondary'} onClick={() => { setKind(item); setPage(1); setError(null); setReport('') }}>{item === 'proverb' ? 'Proverb sets' : 'Trivia sets'}</Button>)}</div>
    {!creating && !deleting && <LibraryError error={error} />}
    {report && <Alert variant="success">{report}</Alert>}
    {sets.isError && <Alert variant="danger">Could not load sets.</Alert>}
    <DataTable columns={columns} rows={sets.data?.data ?? []} getRowId={set => set.id} isLoading={sets.isLoading} empty="No sets match your filters." toolbar={<AdminToolbar search={search} onSearch={filter(setSearch)} searchPlaceholder="Search set title…">
      <FilterSelect label="Language" value={language} onChange={filter(setLanguage)} options={(config.data?.languages ?? []).map(item => ({ label: item.name, value: item.code }))} allLabel="All languages" />
      <FilterSelect label="Status" value={status} onChange={filter(setStatus)} options={['published', 'draft', 'archived'].map(value => ({ label: value[0].toUpperCase() + value.slice(1), value }))} allLabel="All statuses" />
    </AdminToolbar>} />
    {sets.data && sets.data.meta.total > 0 && <div className="flex items-center justify-between text-sm text-muted"><span>Page {sets.data.meta.current_page} of {sets.data.meta.last_page} · {sets.data.meta.total} sets</span><div className="flex gap-2"><Button variant="ghost" disabled={page <= 1 || sets.isFetching} onClick={() => setPage(page - 1)}>Previous</Button><Button variant="ghost" disabled={page >= sets.data.meta.last_page || sets.isFetching} onClick={() => setPage(page + 1)}>Next</Button></div></div>}
    <Modal open={creating} onClose={() => { if (!saving) setCreating(false) }} title={`New ${kind} set`} description="Create the set, then add levels and lessons."><form className="flex flex-col gap-4" onSubmit={create}>
      <LibraryError error={error} />
      <label className="flex flex-col gap-1.5 text-sm font-semibold">Language<select className="h-11 rounded-xl border border-border-strong bg-surface px-3" value={values.language_id} onChange={event => setValues({ ...values, language_id: event.target.value })} required aria-invalid={!!fields.language_id} aria-describedby={fields.language_id ? 'set-language-error' : undefined}><option value="">Choose a language</option>{config.data?.languages.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}</select>{fields.language_id && <span id="set-language-error" className="text-danger">{fields.language_id}</span>}</label>
      <Input label="Set title" value={values.title} onChange={event => setValues({ ...values, title: event.target.value })} error={fields.title} required autoFocus />
      <Input label="Description (optional)" value={values.description} onChange={event => setValues({ ...values, description: event.target.value })} error={fields.description} />
      <Input label="Level band (optional)" placeholder="e.g. A1" value={values.level_band} onChange={event => setValues({ ...values, level_band: event.target.value })} error={fields.level_band} />
      <div className="flex gap-2"><Button type="button" variant="secondary" fullWidth disabled={saving} onClick={() => setCreating(false)}>Cancel</Button><Button type="submit" fullWidth loading={saving} disabled={!values.title.trim() || !values.language_id}>Create set</Button></div>
    </form></Modal>
    <LibraryDeleteModal title={deleting?.title ?? 'set'} open={!!deleting} busy={busy} error={error} onClose={() => setDeleting(null)} onConfirm={() => { if (deleting) void action(deleting, 'delete') }} />
  </div>
}
