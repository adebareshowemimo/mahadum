import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Alert, Badge, Button, Icon, Skeleton } from '@/components/ui'
import { StoryContent } from '@/components/story/StoryContent'
import { contentApi } from '@/lib/api'

export function StoryPreviewPage() {
  const storyId = Number(useParams().storyId)
  const [mode, setMode] = useState<'free' | 'full'>('free')
  const story = useQuery({ queryKey: ['content', 'story', storyId], queryFn: () => contentApi.story(storyId), enabled: storyId > 0 })
  const item = story.data
  const closeTo = `/courses/stories/${storyId}`
  const previewCount = item?.format === 'colouring' || item?.format === 'tracing' ? 12 : 1
  const pages = mode === 'free' ? item?.pages.slice(0, previewCount) ?? [] : item?.pages ?? []
  const video = mode === 'free' ? item?.preview_video_url : item?.full_video_url

  return <div className="min-h-screen bg-background">
    <header className="sticky top-0 z-20 border-b border-border bg-surface/90 backdrop-blur">
      <div className="mx-auto flex max-w-4xl items-center gap-3 px-4 py-3">
        <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-lg" aria-hidden="true">👁</span>
        <div className="min-w-0 flex-1"><p className="text-[11px] font-bold uppercase tracking-wide text-primary">Story preview</p><p className="truncate font-display font-bold text-foreground">{item?.title ?? 'Loading…'}</p></div>
        <Link to={closeTo} className="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-muted hover:bg-surface-muted"><Icon name="close" className="size-4" /> Close</Link>
      </div>
    </header>
    <main className="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-6">
      {story.isLoading && <Skeleton className="h-72" />}
      {story.isError && <Alert variant="danger">Couldn’t load this story preview.</Alert>}
      {item && <>
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div><div className="flex flex-wrap items-center gap-3"><h1 className="font-display text-3xl font-bold">{item.title}</h1><Badge variant={item.published_at ? 'success' : 'neutral'}>{item.published_at ? 'Published' : 'Draft'}</Badge></div><p className="mt-1 text-muted">{item.course_title} · {item.language.toUpperCase()} · {item.format}</p>{item.summary && <p className="mt-3">{item.summary}</p>}</div>
          <div className="flex gap-2" aria-label="Preview mode"><Button variant={mode === 'free' ? 'primary' : 'secondary'} onClick={() => setMode('free')}>Free preview</Button><Button variant={mode === 'full' ? 'primary' : 'secondary'} onClick={() => setMode('full')}>Full story</Button></div>
        </div>
        <Alert variant="info">Creator preview · {mode === 'free' ? 'This is the content readers can see before purchase.' : 'This is the content readers see after purchase.'} Nothing here changes purchases or learner progress.</Alert>
        {item.format === 'animated'
          ? video ? <StoryContent format={item.format} pages={[]} previewVideoUrl={mode === 'free' ? video : null} fullVideoUrl={mode === 'full' ? video : null} /> : <Alert variant="info">Upload the {mode === 'free' ? 'preview clip' : 'full video'} to see it here.</Alert>
          : pages.length > 0 ? <StoryContent format={item.format} pages={pages} /> : <Alert variant="info">Add {item.format === 'reading' ? 'chapters' : 'pages'} to see this story here.</Alert>}
        {mode === 'free' && item.format !== 'animated' && item.pages.length > previewCount && <p className="rounded-xl border border-primary/30 bg-primary-soft p-4 text-center text-sm font-semibold">The reader stops here and can unlock the remaining {item.format === 'reading' ? 'chapters' : 'pages'} for ₦500.</p>}
      </>}
    </main>
  </div>
}
