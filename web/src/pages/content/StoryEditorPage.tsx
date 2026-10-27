import { useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Badge, Button, Input, Skeleton } from '@/components/ui'
import { ApiError, contentApi, type StoryPage } from '@/lib/api'
import { useCanManageContent } from '@/lib/content/permissions'

export function StoryEditorPage() {
  const id = Number(useParams().storyId)
  const navigate = useNavigate()
  const qc = useQueryClient()
  const canManage = useCanManageContent()
  const story = useQuery({ queryKey: ['content', 'story', id], queryFn: () => contentApi.story(id), enabled: id > 0 })
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [pageTitle, setPageTitle] = useState('')
  const [pageBody, setPageBody] = useState('')

  async function refresh() {
    await qc.invalidateQueries({ queryKey: ['content', 'story', id] })
    await qc.invalidateQueries({ queryKey: ['content', 'stories'] })
  }

  async function changePublished(publish: boolean) {
    setBusy(true)
    setError(null)
    try {
      await contentApi.setStoryPublished(id, publish)
      await refresh()
      setNotice(publish ? 'Story published.' : 'Story unpublished.')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not update publication status.')
    } finally { setBusy(false) }
  }

  async function upload(slot: 'cover' | 'preview_video' | 'full_video' | 'page_image', file?: File, pageId?: number) {
    if (!file) return
    setBusy(true)
    setError(null)
    try {
      await contentApi.uploadStoryMedia(id, slot, file, pageId)
      await refresh()
      setNotice('File saved.')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not upload the file.')
    } finally { setBusy(false) }
  }

  async function addPage(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await contentApi.addStoryPage(id, { title: pageTitle || undefined, body: pageBody || undefined })
      setPageTitle('')
      setPageBody('')
      await refresh()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not add this page.')
    } finally { setBusy(false) }
  }

  if (story.isLoading) return <Skeleton className="h-64" />
  if (story.isError || !story.data) return <Alert variant="danger">Could not load this story.</Alert>
  const item = story.data
  const book = item.format !== 'animated'

  return <div className="flex flex-col gap-6">
    <header className="flex flex-wrap items-end justify-between gap-3">
      <div>
        <button className="text-sm font-semibold text-primary hover:underline" onClick={() => navigate('/courses/stories')}>← Stories & Folklore</button>
        <h1 className="mt-2 font-display text-2xl font-bold">{item.title}</h1>
        <p className="mt-1 text-muted">{item.course_title} · {item.language.toUpperCase()} · {item.format} · ₦500 per item</p>
      </div>
      <div className="flex items-center gap-3">
        <Badge variant={item.published_at ? 'success' : 'neutral'}>{item.published_at ? 'Published' : 'Draft'}</Badge>
        {canManage && <Button variant={item.published_at ? 'secondary' : 'primary'} loading={busy} onClick={() => changePublished(!item.published_at)}>{item.published_at ? 'Unpublish' : 'Publish story'}</Button>}
      </div>
    </header>
    {error && <Alert variant="danger">{error}</Alert>}
    {notice && <Alert variant="success">{notice}</Alert>}

    <StoryDetails story={item} onSaved={refresh} />
    <section className="rounded-2xl border border-border bg-surface p-5">
      <h2 className="font-display text-lg font-bold">Cover</h2>
      <div className="mt-3 flex flex-wrap items-center gap-4">
        {item.cover_url && <img src={item.cover_url} alt="Story cover" className="h-32 w-24 rounded-lg object-cover" />}
        <label className="cursor-pointer rounded-xl border border-border px-4 py-2 text-sm font-semibold">Upload cover image
          <input className="sr-only" type="file" accept="image/*" onChange={(event) => void upload('cover', event.target.files?.[0])} />
        </label>
      </div>
    </section>

    {book ? <section className="rounded-2xl border border-border bg-surface p-5">
      <h2 className="font-display text-lg font-bold">{item.format === 'reading' ? 'Chapters' : 'Pages'}</h2>
      <p className="mt-1 text-sm text-muted">{item.format === 'reading' ? 'Chapter 1 is the free preview. Add text to at least two chapters.' : 'The first 12 pages are the free preview. Add images to at least 13 pages.'} Unpublish before changing the page count.</p>
      <div className="mt-4 flex flex-col gap-4">
        {item.pages.map((page) => <StoryPageEditor key={page.id} storyId={id} page={page} refresh={refresh} upload={upload} />)}
      </div>
      {canManage && <form onSubmit={addPage} className="mt-5 flex flex-col gap-3 rounded-xl border border-dashed border-border p-4">
        <h3 className="font-semibold">Add {item.format === 'reading' ? 'chapter' : 'page'}</h3>
        <Input label="Title (optional)" value={pageTitle} onChange={(event) => setPageTitle(event.target.value)} />
        <label className="text-sm font-semibold">Text (optional)
          <textarea className="mt-1 min-h-28 w-full rounded-xl border border-border bg-surface p-3" value={pageBody} onChange={(event) => setPageBody(event.target.value)} />
        </label>
        <Button type="submit" loading={busy}>Add {item.format === 'reading' ? 'chapter' : 'page'}</Button>
      </form>}
    </section> : <section className="rounded-2xl border border-border bg-surface p-5">
      <h2 className="font-display text-lg font-bold">Animated story videos</h2>
      <p className="mt-1 text-sm text-muted">Upload a prepared one-minute preview clip and the full video separately. Only paid readers receive the full video link.</p>
      <div className="mt-4 grid gap-4 sm:grid-cols-2">
        {(['preview_video', 'full_video'] as const).map((slot) => <label key={slot} className="cursor-pointer rounded-xl border border-border p-4 text-sm font-semibold">
          {slot === 'preview_video' ? 'Free preview clip' : 'Full video'} · {(slot === 'preview_video' ? item.has_preview_video : item.has_full_video) ? 'Added' : 'Missing'}
          <input type="file" accept="video/*" className="mt-2 block w-full text-sm" onChange={(event) => void upload(slot, event.target.files?.[0])} />
        </label>)}
      </div>
    </section>}
  </div>
}

function StoryDetails({ story, onSaved }: { story: { id: number; title: string; summary: string | null }; onSaved: () => Promise<void> }) {
  const [title, setTitle] = useState(story.title)
  const [summary, setSummary] = useState(story.summary ?? '')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  async function save(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await contentApi.updateStory(story.id, { title: title.trim(), summary })
      await onSaved()
    } catch (err) { setError(err instanceof ApiError ? err.message : 'Could not save story details.') }
    finally { setBusy(false) }
  }
  return <form onSubmit={save} className="flex flex-col gap-3 rounded-2xl border border-border bg-surface p-5">
    <h2 className="font-display text-lg font-bold">Details</h2>
    {error && <Alert variant="danger">{error}</Alert>}
    <Input label="Title" value={title} onChange={(event) => setTitle(event.target.value)} required />
    <label className="text-sm font-semibold">Summary
      <textarea className="mt-1 min-h-20 w-full rounded-xl border border-border bg-surface p-3" value={summary} onChange={(event) => setSummary(event.target.value)} />
    </label>
    <Button type="submit" loading={busy} disabled={!title.trim()}>Save details</Button>
  </form>
}

function StoryPageEditor({ storyId, page, refresh, upload }: {
  storyId: number; page: StoryPage; refresh: () => Promise<void>
  upload: (slot: 'page_image', file?: File, pageId?: number) => Promise<void>
}) {
  const [title, setTitle] = useState(page.title ?? '')
  const [body, setBody] = useState(page.body ?? '')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  async function save() {
    setBusy(true); setError(null)
    try { await contentApi.updateStoryPage(storyId, page.id, { title, body }); await refresh() }
    catch (err) { setError(err instanceof ApiError ? err.message : 'Could not save the page.') }
    finally { setBusy(false) }
  }
  async function remove() {
    setBusy(true); setError(null)
    try { await contentApi.deleteStoryPage(storyId, page.id); await refresh() }
    catch (err) { setError(err instanceof ApiError ? err.message : 'Could not remove the page.') }
    finally { setBusy(false) }
  }
  return <div className="flex flex-col gap-3 rounded-xl border border-border p-4">
    <h3 className="font-semibold">{page.position}. {page.title || 'Untitled'} {page.has_image && <span className="text-xs text-primary">· Image added</span>}</h3>
    {error && <Alert variant="danger">{error}</Alert>}
    <Input label="Title" value={title} onChange={(event) => setTitle(event.target.value)} />
    <label className="text-sm font-semibold">Text
      <textarea className="mt-1 min-h-28 w-full rounded-xl border border-border bg-surface p-3" value={body} onChange={(event) => setBody(event.target.value)} />
    </label>
    <label className="text-sm font-semibold">Page image
      <input type="file" accept="image/*" className="mt-1 block" onChange={(event) => void upload('page_image', event.target.files?.[0], page.id)} />
    </label>
    <div className="flex gap-2"><Button size="sm" onClick={save} loading={busy}>Save page</Button><Button size="sm" variant="danger" onClick={remove} disabled={busy}>Delete</Button></div>
  </div>
}
