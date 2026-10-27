import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Badge, Button, Input, Modal, Skeleton } from '@/components/ui'
import { StoryCover } from '@/components/story/StoryCover'
import { ApiError, contentApi, type StoryAgeBand, type StoryFormat, type StorySummary } from '@/lib/api'
import { formatsForStoryBand, STORY_BANDS, storyLength } from '@/lib/story/presentation'
import { useAuthorCourses } from '@/lib/content/queries'
import { useCanManageContent } from '@/lib/content/permissions'

export function StoriesPage() {
  const navigate = useNavigate()
  const qc = useQueryClient()
  const canManage = useCanManageContent()
  const [page, setPage] = useState(1)
  const [open, setOpen] = useState(false)
  const [deleting, setDeleting] = useState<StorySummary | null>(null)
  const [courseId, setCourseId] = useState(0)
  const [title, setTitle] = useState('')
  const [summary, setSummary] = useState('')
  const [band, setBand] = useState<StoryAgeBand>('0_5')
  const [format, setFormat] = useState<StoryFormat>('colouring')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const courses = useAuthorCourses()
  const stories = useQuery({ queryKey: ['content', 'stories', page], queryFn: () => contentApi.stories(page) })
  const items = stories.data?.data ?? []

  function openCreate(nextBand: StoryAgeBand = '0_5') {
    setBand(nextBand)
    setFormat(formatsForStoryBand(nextBand)[0])
    setError(null)
    setOpen(true)
  }

  async function create(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const story = await contentApi.createStory({ course_id: courseId, title: title.trim(), summary, age_band: band, format })
      await qc.invalidateQueries({ queryKey: ['content', 'stories'] })
      navigate(`/courses/stories/${story.id}`)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not create this story.')
    } finally { setBusy(false) }
  }

  async function remove() {
    if (!deleting) return
    setBusy(true)
    setError(null)
    try {
      await contentApi.deleteStory(deleting.id)
      await qc.invalidateQueries({ queryKey: ['content', 'stories'] })
      setDeleting(null)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not delete this story.')
    } finally { setBusy(false) }
  }

  return <div className="flex flex-col gap-7">
    <header className="flex flex-wrap items-end justify-between gap-4">
      <div>
        <button className="text-sm font-semibold text-primary hover:underline" onClick={() => navigate('/courses')}>← Courses</button>
        <h1 className="mt-2 font-display text-3xl font-bold">Stories & Folklore</h1>
        <p className="mt-1 text-muted">Build illustrated stories, animated books and reading collections for every age.</p>
      </div>
      {canManage && <Button onClick={() => openCreate()}>+ New story</Button>}
    </header>

    <div className="rounded-2xl border border-gold-400/40 bg-gold-400/10 px-5 py-4 text-sm text-foreground">
      <strong className="text-gold-400">Every story is ₦500 per item.</strong> Readers can try the first 12 pages, the preview clip or chapter one for free. Premium does not include stories.
    </div>

    {error && !open && !deleting && <Alert variant="danger">{error}</Alert>}
    {stories.isError && <Alert variant="danger">Could not load stories.</Alert>}
    {stories.isLoading && <Skeleton className="h-64" />}
    {stories.data && <>
      <details className="overflow-hidden rounded-2xl border border-border bg-surface" open>
        <summary className="cursor-pointer border-b border-border px-5 py-4 font-display text-lg font-bold">Table of contents</summary>
        <div className="grid gap-2 p-5 sm:grid-cols-2 lg:grid-cols-3">
          {STORY_BANDS.map((group) => <a key={group.value} href={`#story-band-${group.value}`} className="flex items-center justify-between rounded-xl bg-surface-muted px-4 py-3 text-sm hover:bg-surface-sunken">
            <span className="font-semibold">{group.label}</span><span className="text-muted">{items.filter((item) => item.age_band === group.value).length}</span>
          </a>)}
        </div>
      </details>

      {STORY_BANDS.map((group, index) => {
        const groupStories = items.filter((item) => item.age_band === group.value)
        return <section id={`story-band-${group.value}`} key={group.value} className="scroll-mt-24 rounded-3xl border border-border bg-surface p-5 sm:p-6">
          <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div><p className="text-xs font-bold uppercase tracking-widest text-primary">Band {index + 1}</p><h2 className="mt-1 font-display text-xl font-bold">{group.label}</h2><p className="text-sm text-muted">{group.description}</p></div>
            {canManage && <Button size="sm" variant="outline" onClick={() => openCreate(group.value)}>+ Story</Button>}
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {groupStories.map((story) => <article key={story.id} className="flex flex-col rounded-2xl border border-border bg-background p-3">
              <StoryCover story={story} className="aspect-[3/4] w-full" />
              <h3 className="mt-3 line-clamp-2 min-h-12 font-display font-bold leading-snug">{story.title}</h3>
              <p className="mt-1 text-xs uppercase tracking-wide text-muted">{story.format} · {storyLength(story)} · {story.language.toUpperCase()}</p>
              <p className="mt-1 truncate text-xs text-muted" title={story.course_title}>{story.course_title}</p>
              <div className="mt-3 flex flex-wrap items-center gap-2"><Badge variant={story.published_at ? 'success' : 'neutral'}>{story.published_at ? 'Published' : 'Draft'}</Badge></div>
              <div className="mt-auto flex gap-2 border-t border-border pt-3"><Button size="sm" variant="secondary" className="flex-1" onClick={() => window.open(`/courses/stories/${story.id}/preview`, '_blank', 'noopener')}>Preview</Button><Button size="sm" variant="secondary" className="flex-1" onClick={() => navigate(`/courses/stories/${story.id}`)}>Edit</Button>{canManage && <Button size="sm" variant="ghost" className="text-danger" aria-label={`Delete ${story.title}`} onClick={() => setDeleting(story)}>Delete</Button>}</div>
            </article>)}
            {canManage && <button type="button" onClick={() => openCreate(group.value)} className="flex min-h-64 flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-border p-6 text-center text-sm font-bold text-primary transition-colors hover:border-primary hover:bg-primary-soft"><span className="text-3xl" aria-hidden="true">＋</span>Add {group.value === 'folklore' ? 'folktale' : group.value === '0_5' ? 'colouring or tracing book' : 'story'}</button>}
            {groupStories.length === 0 && !canManage && <p className="text-sm text-muted">No stories in this band yet.</p>}
          </div>
        </section>
      })}
      {stories.data.meta.last_page > 1 && <div className="flex items-center justify-between gap-3"><Button variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button><span className="text-sm text-muted">Page {page} of {stories.data.meta.last_page}</span><Button variant="secondary" disabled={page >= stories.data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button></div>}
    </>}

    <Modal open={open} onClose={() => setOpen(false)} title="New story" description="Choose a course, then add its cover and pages or video.">
      <form onSubmit={create} className="flex flex-col gap-4">
        {error && <Alert variant="danger">{error}</Alert>}
        <label className="flex flex-col gap-1 text-sm font-semibold">Course<select className="h-11 rounded-xl border border-border bg-surface px-3" value={courseId} onChange={(event) => setCourseId(Number(event.target.value))} required><option value={0}>Choose a language course</option>{courses.data?.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}</select></label>
        <Input label="Title" value={title} onChange={(event) => setTitle(event.target.value)} required />
        <Input label="Summary" value={summary} onChange={(event) => setSummary(event.target.value)} />
        <label className="flex flex-col gap-1 text-sm font-semibold">Age band<select className="h-11 rounded-xl border border-border bg-surface px-3" value={band} onChange={(event) => { const next = event.target.value as StoryAgeBand; setBand(next); setFormat(formatsForStoryBand(next)[0]) }}>{STORY_BANDS.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select></label>
        <label className="flex flex-col gap-1 text-sm font-semibold">Format<select className="h-11 rounded-xl border border-border bg-surface px-3" value={format} onChange={(event) => setFormat(event.target.value as StoryFormat)}>{formatsForStoryBand(band).map((item) => <option key={item} value={item}>{item}</option>)}</select></label>
        <Button type="submit" loading={busy} disabled={!courseId || !title.trim()}>Create story</Button>
      </form>
    </Modal>
    <Modal open={!!deleting} onClose={() => { setDeleting(null); setError(null) }} title="Delete story" description="This removes the story and its media. Stories with payment attempts cannot be deleted.">
      <p className="mb-4 font-semibold">{deleting?.title}</p>{error && <Alert variant="danger">{error}</Alert>}
      <div className="mt-4 flex justify-end gap-2"><Button variant="secondary" onClick={() => setDeleting(null)}>Cancel</Button><Button variant="danger" loading={busy} onClick={remove}>Delete story</Button></div>
    </Modal>
  </div>
}
