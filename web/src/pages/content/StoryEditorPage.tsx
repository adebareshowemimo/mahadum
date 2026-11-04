import { useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Badge, Button, Input, Skeleton } from '@/components/ui'
import { ApiError, contentApi, type StoryDetail, type StoryPage } from '@/lib/api'
import { useConfig } from '@/lib/config/useConfig'
import { useCanManageContent } from '@/lib/content/permissions'
import { formatsForStoryBand, STORY_BANDS } from '@/lib/story/presentation'

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
  const [questionPrompt, setQuestionPrompt] = useState('')
  const [questionOptions, setQuestionOptions] = useState(['', ''])
  const [correctIndex, setCorrectIndex] = useState(0)
  const [questionExplanation, setQuestionExplanation] = useState('')

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

  async function addQuestion(event: FormEvent) {
    event.preventDefault()
    setBusy(true); setError(null)
    try {
      await contentApi.addStoryQuestion(id, { prompt: questionPrompt.trim(), options: questionOptions.map((option) => option.trim()), correct_index: correctIndex, explanation: questionExplanation.trim() })
      setQuestionPrompt(''); setQuestionOptions(['', '']); setCorrectIndex(0); setQuestionExplanation('')
      await refresh()
    } catch (err) { setError(err instanceof ApiError ? err.message : 'Could not add the question.') }
    finally { setBusy(false) }
  }

  async function deleteQuestion(questionId: number) {
    setBusy(true); setError(null)
    try { await contentApi.deleteStoryQuestion(id, questionId); await refresh() }
    catch (err) { setError(err instanceof ApiError ? err.message : 'Could not remove the question.') }
    finally { setBusy(false) }
  }

  if (story.isLoading) return <Skeleton className="h-64" />
  if (story.isError || !story.data) return <Alert variant="danger">Could not load this story.</Alert>
  const item = story.data
  const book = !['animated', 'video', 'quiz'].includes(item.format)

  if (!canManage) return <div className="space-y-5">
    <button className="text-sm font-semibold text-primary hover:underline" onClick={() => navigate('/courses/stories')}>← Stories & Folklore</button>
    <h1 className="font-display text-2xl font-bold">{item.title}</h1>
    <p className="text-muted">{item.language.toUpperCase()} · Level {item.level} · {item.format}</p>
    <Alert variant="info">This is a read-only catalogue view. Content owners edit and publish stories.</Alert>
    <Button variant="secondary" onClick={() => window.open(`/courses/stories/${id}/preview`, '_blank', 'noopener')}>Preview story</Button>
  </div>

  return <div className="flex flex-col gap-6">
    <header className="flex flex-wrap items-end justify-between gap-3">
      <div>
        <button className="text-sm font-semibold text-primary hover:underline" onClick={() => navigate('/courses/stories')}>← Stories & Folklore</button>
        <h1 className="mt-2 font-display text-2xl font-bold">{item.title}</h1>
        <p className="mt-1 text-muted">{item.language.toUpperCase()} · Level {item.level} · {item.format} · ₦500 per item</p>
      </div>
      <div className="flex items-center gap-3">
        <Badge variant={item.published_at ? 'success' : 'neutral'}>{item.published_at ? 'Published' : 'Draft'}</Badge>
        <Button variant="secondary" onClick={() => window.open(`/courses/stories/${id}/preview`, '_blank', 'noopener')}>Preview</Button>
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

    {item.format === 'quiz' ? <section className="rounded-2xl border border-border bg-surface p-5">
      <h2 className="font-display text-lg font-bold">Quiz questions</h2>
      <p className="mt-1 text-sm text-muted">Question one is the free preview. Add at least two questions. Answers stay on the server for learners.</p>
      <div className="mt-4 space-y-3">{item.questions.map((question) => <div key={question.id} className="rounded-xl border border-border p-4"><p className="font-semibold">{question.position}. {question.prompt}</p><ol className="mt-2 list-inside list-decimal text-sm">{question.options.map((option, index) => <li key={index}>{option}{index === question.correct_index ? ' ✓' : ''}</li>)}</ol>{canManage && <Button size="sm" variant="danger" disabled={busy || !!item.published_at} onClick={() => void deleteQuestion(question.id)}>Delete question</Button>}</div>)}</div>
      {canManage && !item.published_at && <form onSubmit={addQuestion} className="mt-5 space-y-3 rounded-xl border border-dashed border-border p-4">
        <Input label="Question" value={questionPrompt} onChange={(event) => setQuestionPrompt(event.target.value)} required />
        {questionOptions.map((option, index) => <Input key={index} label={`Answer ${index + 1}`} value={option} onChange={(event) => setQuestionOptions((items) => items.map((value, i) => i === index ? event.target.value : value))} required />)}
        <Button type="button" size="sm" variant="secondary" disabled={questionOptions.length >= 6} onClick={() => setQuestionOptions((items) => [...items, ''])}>Add answer</Button>
        <label className="block text-sm font-semibold">Correct answer<select className="mt-1 block h-11 w-full rounded-xl border border-border bg-surface px-3" value={correctIndex} onChange={(event) => setCorrectIndex(Number(event.target.value))}>{questionOptions.map((_, index) => <option key={index} value={index}>Answer {index + 1}</option>)}</select></label>
        <Input label="Explanation (optional)" value={questionExplanation} onChange={(event) => setQuestionExplanation(event.target.value)} />
        <Button type="submit" loading={busy} disabled={!questionPrompt.trim() || questionOptions.some((option) => !option.trim())}>Add question</Button>
      </form>}
    </section> : book ? <section className="rounded-2xl border border-border bg-surface p-5">
      <h2 className="font-display text-lg font-bold">{item.format === 'reading' || item.format === 'text' ? 'Chapters' : 'Pages'}</h2>
      <p className="mt-1 text-sm text-muted">{item.format === 'reading' || item.format === 'text' ? 'Chapter 1 is the free preview. Add text to at least two chapters.' : 'The first 12 pages are the free preview. Add images to at least 13 pages.'} Unpublish before changing the page count.</p>
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

function StoryDetails({ story, onSaved }: { story: StoryDetail; onSaved: () => Promise<void> }) {
  const config = useConfig()
  const [title, setTitle] = useState(story.title)
  const [summary, setSummary] = useState(story.summary ?? '')
  const [languageId, setLanguageId] = useState(story.language_id)
  const [level, setLevel] = useState(story.level)
  const [band, setBand] = useState(story.age_band)
  const [format, setFormat] = useState(story.format)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  async function save(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await contentApi.updateStory(story.id, { title: title.trim(), summary, language_id: languageId, standalone_level: level, age_band: band, format })
      await onSaved()
    } catch (err) { setError(err instanceof ApiError ? err.message : 'Could not save story details.') }
    finally { setBusy(false) }
  }
  return <form onSubmit={save} className="flex flex-col gap-3 rounded-2xl border border-border bg-surface p-5">
    <h2 className="font-display text-lg font-bold">Details</h2>
    {error && <Alert variant="danger">{error}</Alert>}
    <Input label="Title" value={title} onChange={(event) => setTitle(event.target.value)} required />
    <label className="text-sm font-semibold">Language<select className="mt-1 h-11 w-full rounded-xl border border-border bg-surface px-3" value={languageId} onChange={(event) => setLanguageId(Number(event.target.value))} required>{config.data?.languages.map((language) => <option key={language.id} value={language.id}>{language.name}</option>)}</select></label>
    <label className="text-sm font-semibold">Level<select className="mt-1 h-11 w-full rounded-xl border border-border bg-surface px-3" value={level} onChange={(event) => setLevel(Number(event.target.value))} required>{Array.from({ length: 10 }, (_, index) => <option key={index + 1} value={index + 1}>Level {index + 1}</option>)}</select></label>
    <label className="text-sm font-semibold">Age band<select className="mt-1 h-11 w-full rounded-xl border border-border bg-surface px-3" value={band} disabled={!!story.published_at} onChange={(event) => { const next = event.target.value as StoryDetail['age_band']; setBand(next); setFormat(formatsForStoryBand(next)[0]) }}>{STORY_BANDS.map((group) => <option key={group.value} value={group.value}>{group.label}</option>)}</select></label>
    <label className="text-sm font-semibold">Format<select className="mt-1 h-11 w-full rounded-xl border border-border bg-surface px-3" value={format} disabled={!!story.published_at} onChange={(event) => setFormat(event.target.value as StoryDetail['format'])}>{formatsForStoryBand(band).map((value) => <option key={value} value={value}>{value}</option>)}</select></label>
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
