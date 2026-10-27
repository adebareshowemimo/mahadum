import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Alert, Badge, Button, Skeleton } from '@/components/ui'
import { learningApi, type StoryAgeBand } from '@/lib/api'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'

const bands: { value: StoryAgeBand | ''; label: string }[] = [
  { value: '', label: 'All stories' }, { value: '0_5', label: 'Ages 0–5' },
  { value: '6_12', label: 'Ages 6–12' }, { value: '13_17', label: 'Ages 13–17' },
  { value: '18_plus', label: 'Adults 18+' }, { value: 'folklore', label: 'Folklore' },
]

export function StoryLibraryPage() {
  const { activeLearner } = useActiveProfile()
  const navigate = useNavigate()
  const [band, setBand] = useState<StoryAgeBand | ''>('')
  const [page, setPage] = useState(1)
  const stories = useQuery({
    queryKey: ['learning', 'stories', activeLearner?.id, band, page],
    queryFn: () => learningApi.stories({ learner_id: activeLearner!.id, band: band || undefined, page }),
    enabled: !!activeLearner,
  })

  return <div className="flex flex-col gap-6">
    <header>
      <h1 className="font-display text-2xl font-bold">Stories & Folklore</h1>
      <p className="mt-1 text-muted">Preview stories for free. Each full story is ₦500 and stays available after a plan ends.</p>
    </header>
    {!activeLearner && <Alert variant="info">Choose a learner profile to browse stories.</Alert>}
    <div className="flex flex-wrap gap-2" aria-label="Story age groups">
      {bands.map((item) => <Button key={item.value} size="sm" variant={band === item.value ? 'primary' : 'secondary'} onClick={() => { setBand(item.value); setPage(1) }}>{item.label}</Button>)}
    </div>
    {stories.isError && <Alert variant="danger">Could not load stories.</Alert>}
    {stories.isLoading ? <Skeleton className="h-48" /> : <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {stories.data?.data.length === 0 && activeLearner && <p className="text-muted">No published stories in this group yet.</p>}
      {stories.data?.data.map((story) => <article key={story.id} className="overflow-hidden rounded-2xl border border-border bg-surface">
        {story.cover_url ? <img src={story.cover_url} alt="" className="h-44 w-full object-cover" /> : <div className="h-44 bg-primary-soft" />}
        <div className="p-5">
          <p className="text-xs font-semibold uppercase text-primary">{story.language} · {story.course_title}</p>
          <h2 className="mt-2 font-display text-lg font-bold">{story.title}</h2>
          {story.summary && <p className="mt-2 line-clamp-3 text-sm text-muted">{story.summary}</p>}
          <div className="mt-3 flex items-center gap-2"><Badge variant={story.purchased ? 'success' : 'neutral'}>{story.purchased ? 'Owned' : '₦500'}</Badge><span className="text-xs text-muted">{story.format}</span></div>
          <Button className="mt-4" size="sm" onClick={() => navigate(`/learn/stories/${story.id}`)}>{story.purchased ? 'Read or watch' : 'Free preview'}</Button>
        </div>
      </article>)}
    </div>}
    {stories.data && stories.data.meta.last_page > 1 && <div className="flex items-center justify-between">
      <Button variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
      <span className="text-sm text-muted">Page {page} of {stories.data.meta.last_page}</span>
      <Button variant="secondary" disabled={page >= stories.data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button>
    </div>}
  </div>
}
