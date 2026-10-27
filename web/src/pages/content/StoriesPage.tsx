import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Badge, Button, Input, Modal, Skeleton } from '@/components/ui'
import { ApiError, contentApi, type StoryAgeBand, type StoryFormat } from '@/lib/api'
import { useAuthorCourses } from '@/lib/content/queries'
import { useCanManageContent } from '@/lib/content/permissions'

const BANDS: Array<{ value: StoryAgeBand; label: string }> = [
  { value: '0_5', label: '0–5 · Early childhood' },
  { value: '6_12', label: '6–12 · Middle childhood' },
  { value: '13_17', label: '13–17 · Teen' },
  { value: '18_plus', label: '18+ · Adults' },
  { value: 'folklore', label: 'Folklore · All ages' },
]

function formatsFor(band: StoryAgeBand): StoryFormat[] {
  if (band === '0_5') return ['colouring', 'tracing']
  if (band === '6_12') return ['animated']
  if (band === 'folklore') return ['colouring', 'tracing', 'animated', 'reading']
  return ['reading']
}

export function StoriesPage() {
  const navigate = useNavigate()
  const qc = useQueryClient()
  const canManage = useCanManageContent()
  const [page, setPage] = useState(1)
  const [open, setOpen] = useState(false)
  const [courseId, setCourseId] = useState(0)
  const [title, setTitle] = useState('')
  const [summary, setSummary] = useState('')
  const [band, setBand] = useState<StoryAgeBand>('0_5')
  const [format, setFormat] = useState<StoryFormat>('colouring')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const courses = useAuthorCourses()
  const stories = useQuery({ queryKey: ['content', 'stories', page], queryFn: () => contentApi.stories(page) })

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
    } finally {
      setBusy(false)
    }
  }

  return <div className="flex flex-col gap-6">
    <header className="flex flex-wrap items-end justify-between gap-3">
      <div>
        <button className="text-sm font-semibold text-primary hover:underline" onClick={() => navigate('/courses')}>← Courses</button>
        <h1 className="mt-2 font-display text-2xl font-bold">Stories & Folklore</h1>
        <p className="mt-1 text-muted">Create a separate ₦500 item with a free preview. Premium does not include stories.</p>
      </div>
      {canManage && <Button onClick={() => setOpen(true)}>New story</Button>}
    </header>
    {stories.isError && <Alert variant="danger">Could not load stories.</Alert>}
    {stories.isLoading ? <Skeleton className="h-48" /> : <div className="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-surface">
      {stories.data?.data.length === 0 && <p className="p-6 text-muted">No stories yet. Create one in an existing language course.</p>}
      {stories.data?.data.map((story) => <div key={story.id} className="flex flex-wrap items-center justify-between gap-4 p-4">
        <div className="flex items-center gap-4">
          {story.cover_url ? <img src={story.cover_url} alt="" className="h-16 w-12 rounded object-cover" /> : <div className="h-16 w-12 rounded bg-primary-soft" />}
          <div>
            <p className="font-semibold">{story.title}</p>
            <p className="text-sm text-muted">{story.course_title} · {story.language.toUpperCase()} · {BANDS.find((item) => item.value === story.age_band)?.label} · {story.format}</p>
            <p className="text-sm font-semibold text-primary">₦500 per item</p>
          </div>
        </div>
        <div className="flex items-center gap-3">
          <Badge variant={story.published_at ? 'success' : 'neutral'}>{story.published_at ? 'Published' : 'Draft'}</Badge>
          <Button size="sm" variant="secondary" onClick={() => navigate(`/courses/stories/${story.id}`)}>Edit</Button>
        </div>
      </div>)}
    </div>}
    {stories.data && stories.data.meta.last_page > 1 && <div className="flex justify-between">
      <Button variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
      <span className="text-sm text-muted">Page {page} of {stories.data.meta.last_page}</span>
      <Button variant="secondary" disabled={page >= stories.data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button>
    </div>}

    <Modal open={open} onClose={() => setOpen(false)} title="New story" description="Create the item, then add its cover, pages or video.">
      <form onSubmit={create} className="flex flex-col gap-4">
        {error && <Alert variant="danger">{error}</Alert>}
        <label className="flex flex-col gap-1 text-sm font-semibold">Course
          <select className="h-11 rounded-xl border border-border bg-surface px-3" value={courseId} onChange={(event) => setCourseId(Number(event.target.value))} required>
            <option value={0}>Choose a language course</option>
            {courses.data?.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}
          </select>
        </label>
        <Input label="Title" value={title} onChange={(event) => setTitle(event.target.value)} required />
        <Input label="Summary" value={summary} onChange={(event) => setSummary(event.target.value)} />
        <label className="flex flex-col gap-1 text-sm font-semibold">Age band
          <select className="h-11 rounded-xl border border-border bg-surface px-3" value={band} onChange={(event) => { const next = event.target.value as StoryAgeBand; setBand(next); setFormat(formatsFor(next)[0]) }}>
            {BANDS.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
          </select>
        </label>
        <label className="flex flex-col gap-1 text-sm font-semibold">Format
          <select className="h-11 rounded-xl border border-border bg-surface px-3" value={format} onChange={(event) => setFormat(event.target.value as StoryFormat)}>
            {formatsFor(band).map((item) => <option key={item} value={item}>{item}</option>)}
          </select>
        </label>
        <Button type="submit" loading={busy} disabled={!courseId || !title.trim()}>Create story</Button>
      </form>
    </Modal>
  </div>
}
