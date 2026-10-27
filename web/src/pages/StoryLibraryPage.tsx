import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Alert, Button, Skeleton } from '@/components/ui'
import { StoryCover } from '@/components/story/StoryCover'
import { learningApi, type StoryAgeBand, type StorySummary } from '@/lib/api'
import { STORY_BANDS, storyLength } from '@/lib/story/presentation'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'

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
  const items = stories.data?.data ?? []
  const featured = items.find((item) => item.age_band === 'folklore') ?? items[0]
  const groups = band ? STORY_BANDS.filter((group) => group.value === band) : STORY_BANDS

  function openStory(story: StorySummary) { navigate(`/learn/stories/${story.id}`) }

  return <div className="mx-auto flex max-w-7xl flex-col gap-7">
    <header>
      <p className="text-xs font-bold uppercase tracking-[.2em] text-primary">The story library</p>
      <h1 className="mt-2 font-display text-3xl font-bold sm:text-4xl">Stories & Folklore</h1>
      <p className="mt-2 text-muted">Picture books, animated tales and reading adventures for every age.</p>
    </header>
    {!activeLearner && <Alert variant="info">Choose a learner profile to browse stories.</Alert>}
    <nav className="flex flex-wrap gap-2" aria-label="Story age groups">
      <Button size="sm" variant={band === '' ? 'primary' : 'secondary'} onClick={() => { setBand(''); setPage(1) }}>All stories</Button>
      {STORY_BANDS.map((group) => <Button key={group.value} size="sm" variant={band === group.value ? 'primary' : 'secondary'} onClick={() => { setBand(group.value); setPage(1) }}>{group.short}</Button>)}
    </nav>
    <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gold-400/40 bg-gold-400/10 px-5 py-4">
      <div><p className="font-display text-lg font-bold">Preview free. Keep your favourites.</p><p className="text-sm text-muted">Each story is ₦500 per item, shared with your family and available after a subscription ends.</p></div>
      <span className="rounded-full bg-gold-400 px-4 py-2 font-bold text-charcoal-900">₦500 per story</span>
    </div>
    {stories.isError && <Alert variant="danger">Could not load stories.</Alert>}
    {stories.isLoading && <Skeleton className="h-80" />}
    {stories.data && items.length === 0 && <p className="rounded-2xl border border-border bg-surface p-8 text-muted">No published stories in this group yet.</p>}
    {featured && <section className="grid overflow-hidden rounded-3xl border border-border bg-surface lg:grid-cols-[minmax(220px,35%)_1fr]">
      <StoryCover story={featured} className="aspect-[4/3] w-full rounded-none lg:aspect-auto lg:min-h-80" />
      <div className="flex flex-col justify-center p-6 sm:p-9">
        <p className="text-xs font-bold uppercase tracking-[.2em] text-primary">Featured story · {featured.language.toUpperCase()}</p>
        <h2 className="mt-3 font-display text-2xl font-bold sm:text-3xl">{featured.title}</h2>
        {featured.summary && <p className="mt-3 max-w-xl text-muted">{featured.summary}</p>}
        <p className="mt-4 text-sm text-muted">{storyLength(featured)} · {featured.course_title}</p>
        <div className="mt-6"><Button onClick={() => openStory(featured)}>{featured.purchased ? 'Read or watch story' : 'Try free preview'} →</Button></div>
      </div>
    </section>}
    {groups.map((group) => {
      const groupStories = items.filter((item) => item.age_band === group.value)
      if (groupStories.length === 0) return null
      return <section key={group.value} className="flex flex-col gap-4">
        <div><h2 className="font-display text-xl font-bold">{group.label}</h2><p className="text-sm text-muted">{group.description}</p></div>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {groupStories.map((story) => <article key={story.id} className="flex flex-col rounded-2xl border border-border bg-surface p-3">
            <StoryCover story={story} className="aspect-[3/4] w-full" />
            <div className="flex flex-1 flex-col px-1 pb-1"><p className="mt-3 text-xs font-bold uppercase tracking-wide text-primary">{story.language.toUpperCase()} · {story.format}</p>
              <h3 className="mt-1 line-clamp-2 font-display text-lg font-bold">{story.title}</h3>
              <p className="mt-1 text-sm text-muted">{storyLength(story)}</p>
              <Button className="mt-4 w-full" size="sm" variant="secondary" onClick={() => openStory(story)}>{story.purchased ? 'Read or watch' : 'Free preview'}</Button>
            </div>
          </article>)}
        </div>
      </section>
    })}
    {stories.data && stories.data.meta.last_page > 1 && <div className="flex items-center justify-between"><Button variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button><span className="text-sm text-muted">Page {page} of {stories.data.meta.last_page}</span><Button variant="secondary" disabled={page >= stories.data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button></div>}
  </div>
}
