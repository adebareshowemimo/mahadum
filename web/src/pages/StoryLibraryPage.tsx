import { useEffect, useState } from 'react'
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
  const continuing = useQuery({
    queryKey: ['learning', 'stories', 'continue', activeLearner?.id],
    queryFn: () => learningApi.continuingStories(activeLearner!.id),
    enabled: !!activeLearner,
  })
  const items = stories.data?.data ?? []
  const featured = items.find((item) => item.age_band === 'folklore') ?? items[0]
  const visibleBands = STORY_BANDS.filter((group) => group.value !== '18_plus' || stories.data?.meta.can_view_adult)
  const groups = band ? visibleBands.filter((group) => group.value === band) : visibleBands

  useEffect(() => {
    if (band === '18_plus' && stories.data?.meta.can_view_adult === false) { setBand(''); setPage(1) }
  }, [band, stories.data?.meta.can_view_adult])

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
      {visibleBands.map((group) => <Button key={group.value} size="sm" variant={band === group.value ? 'primary' : 'secondary'} onClick={() => { setBand(group.value); setPage(1) }}>{group.short}</Button>)}
    </nav>
    <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gold-400/40 bg-gold-400/10 px-5 py-4">
      <div><p className="font-display text-lg font-bold">Preview free. Keep your favourites.</p><p className="text-sm text-muted">Each story is ₦500. Your purchase stays with your account; a parent’s purchase covers their family.</p></div>
      <span className="rounded-full bg-gold-400 px-4 py-2 font-bold text-charcoal-900">₦500 per story</span>
    </div>
    {stories.isError && <Alert variant="danger">Could not load stories.</Alert>}
    {stories.isLoading && <Skeleton className="h-80" />}
    {!!continuing.data?.length && <section className="flex flex-col gap-3">
      <h2 className="font-display text-xl font-bold">Continue reading</h2>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {continuing.data.map((story) => <button key={story.id} type="button" onClick={() => openStory(story)} className="flex items-center gap-4 rounded-2xl border border-border bg-surface p-3 text-left hover:border-primary">
          <StoryCover story={story} className="h-24 w-20 shrink-0" />
          <span className="min-w-0"><span className="block font-display font-bold">{story.title}</span><span className="mt-1 block text-sm text-muted">{story.format === 'video' || story.format === 'animated' ? 'Continue watching' : story.format === 'quiz' ? 'Continue quiz' : `Resume at ${story.format === 'reading' || story.format === 'text' ? 'chapter' : 'page'} ${story.resume_position ?? 1}`}</span></span>
        </button>)}
      </div>
    </section>}
    {featured && <section className="grid overflow-hidden rounded-3xl border border-border bg-surface lg:grid-cols-[minmax(220px,35%)_1fr]">
      <StoryCover story={featured} className="aspect-[4/3] w-full rounded-none lg:aspect-auto lg:min-h-80" />
      <div className="flex flex-col justify-center p-6 sm:p-9">
        <p className="text-xs font-bold uppercase tracking-[.2em] text-primary">Featured story · {featured.language.toUpperCase()}</p>
        <h2 className="mt-3 font-display text-2xl font-bold sm:text-3xl">{featured.title}</h2>
        {featured.summary && <p className="mt-3 max-w-xl text-muted">{featured.summary}</p>}
        <p className="mt-4 text-sm text-muted">{storyLength(featured)} · Level {featured.level}</p>
        <div className="mt-6"><Button onClick={() => openStory(featured)}>{featured.purchased ? 'Read or watch story' : activeLearner?.is_child ? 'Try free preview' : 'Preview or buy · ₦500'} →</Button></div>
      </div>
    </section>}
    {groups.map((group) => {
      const groupStories = items.filter((item) => item.age_band === group.value)
      return <section key={group.value} className="flex flex-col gap-4">
        <div><h2 className="font-display text-xl font-bold">{group.label}</h2><p className="text-sm text-muted">{group.description}</p></div>
        {groupStories.length === 0 && <p className="rounded-2xl border border-border bg-surface p-6 text-sm text-muted">Stories for this age group are being prepared.</p>}
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {groupStories.map((story) => <article key={story.id} className="flex flex-col rounded-2xl border border-border bg-surface p-3">
            <StoryCover story={story} className="aspect-[3/4] w-full" />
            <div className="flex flex-1 flex-col px-1 pb-1"><p className="mt-3 text-xs font-bold uppercase tracking-wide text-primary">{story.language.toUpperCase()} · Level {story.level} · {story.format}</p>
              <h3 className="mt-1 line-clamp-2 font-display text-lg font-bold">{story.title}</h3>
              <p className="mt-1 text-sm text-muted">{storyLength(story)}</p>
              <Button className="mt-4 w-full" size="sm" variant="secondary" onClick={() => openStory(story)}>{story.purchased ? 'Read or watch' : activeLearner?.is_child ? 'Free preview' : 'Preview or buy · ₦500'}</Button>
            </div>
          </article>)}
        </div>
      </section>
    })}
    {stories.data && stories.data.meta.last_page > 1 && <div className="flex items-center justify-between"><Button variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button><span className="text-sm text-muted">Page {page} of {stories.data.meta.last_page}</span><Button variant="secondary" disabled={page >= stories.data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button></div>}
  </div>
}
