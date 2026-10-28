import { useRef, useState, type ReactNode, type TouchEvent } from 'react'
import type { StoryFormat, StoryPage } from '@/lib/api'

type StoryContentProps = {
  format: StoryFormat
  pages: StoryPage[]
  previewVideoUrl?: string | null
  fullVideoUrl?: string | null
  endContent?: ReactNode
  endLabel?: string
  tone?: 'light' | 'dark'
}

export function StoryContent({ format, pages, previewVideoUrl, fullVideoUrl, endContent, endLabel = 'Finish preview', tone = 'dark' }: StoryContentProps) {
  const [index, setIndex] = useState(0)
  const deckRef = useRef<HTMLElement>(null)
  const touchStart = useRef<{ x: number; y: number } | null>(null)
  const videoUrl = fullVideoUrl || previewVideoUrl
  const slides = videoUrl ? [{ type: 'video' as const, url: videoUrl }, ...pages.map((page) => ({ type: 'page' as const, page }))] : pages.map((page) => ({ type: 'page' as const, page }))
  const total = slides.length + (endContent ? 1 : 0)
  const current = Math.min(index, Math.max(total - 1, 0))
  const atEnd = current === slides.length && !!endContent
  const slide = slides[current]

  function go(next: number) {
    if (next < 0 || next >= total || next === current) return
    setIndex(next)
    requestAnimationFrame(() => deckRef.current?.scrollIntoView?.({ behavior: 'smooth', block: 'start' }))
  }

  function onTouchEnd(event: TouchEvent<HTMLElement>) {
    const start = touchStart.current
    touchStart.current = null
    if (!start) return
    const deltaX = event.changedTouches[0].clientX - start.x
    const deltaY = event.changedTouches[0].clientY - start.y
    if (Math.abs(deltaX) < 55 || Math.abs(deltaX) < Math.abs(deltaY) * 1.4) return
    go(current + (deltaX < 0 ? 1 : -1))
  }

  if (total === 0) return null

  return <section ref={deckRef} className="scroll-mt-24" aria-label="Story slides" tabIndex={0}
    onKeyDown={(event) => { if ((event.target as HTMLElement).closest('input, select, textarea, video')) return; if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') { event.preventDefault(); go(current + (event.key === 'ArrowRight' ? 1 : -1)) } }}
    onTouchStart={(event) => { touchStart.current = { x: event.touches[0].clientX, y: event.touches[0].clientY } }} onTouchEnd={onTouchEnd}>
    <div className={`mb-3 flex flex-wrap items-center justify-between gap-3 text-xs font-bold uppercase tracking-widest ${tone === 'light' ? 'text-amber-900' : 'text-muted'}`}>
      <span>{atEnd ? 'Preview complete' : slide?.type === 'video' ? 'Video' : `${format === 'reading' ? 'Chapter' : 'Page'} ${slide?.page.position}`}</span>
      <div className="flex items-center gap-3"><span aria-live="polite">{current + 1} of {total}</span>
        {total > 1 && <div className="flex gap-1" aria-label="Quick story navigation"><button type="button" aria-label="Previous page" disabled={current === 0} onClick={() => go(current - 1)} className={`grid size-9 place-items-center rounded-full border text-base disabled:opacity-40 ${tone === 'light' ? 'border-amber-900/30 bg-[#fffaf0] text-amber-900 hover:bg-amber-100' : 'border-amber-500/35 text-amber-500 hover:bg-amber-500/10'}`}>←</button><button type="button" aria-label="Next page" disabled={current === total - 1} onClick={() => go(current + 1)} className={`grid size-9 place-items-center rounded-full border text-base disabled:opacity-40 ${tone === 'light' ? 'border-amber-900/30 bg-[#fffaf0] text-amber-900 hover:bg-amber-100' : 'border-amber-500/35 text-amber-500 hover:bg-amber-500/10'}`}>→</button></div>}
      </div>
    </div>
    <div role="progressbar" aria-label="Story progress" aria-valuemin={1} aria-valuemax={total} aria-valuenow={current + 1} className="mb-5 flex gap-1.5">
      {Array.from({ length: total }, (_, position) => <span key={position} className={`h-1.5 min-w-0 flex-1 rounded-full ${position <= current ? 'bg-amber-500' : 'bg-amber-500/20'}`} />)}
    </div>

    <div aria-live="polite" className="min-w-0">
      {atEnd ? endContent : slide?.type === 'video' ? <article className="rounded-2xl border border-amber-900/15 bg-[#fffaf0] p-4 shadow-[0_12px_40px_rgba(48,32,11,.08)] sm:p-7">
        <h2 className="mb-4 text-center font-serif text-xl font-bold text-[#372d24]">{fullVideoUrl ? 'Full story' : 'Preview clip'}</h2>
        <video key={slide.url} controls preload="metadata" src={slide.url} className="aspect-video w-full rounded-xl bg-black" />
      </article> : slide?.type === 'page' ? <article key={slide.page.id} className="flex min-h-[min(65dvh,680px)] flex-col justify-center rounded-2xl border border-amber-900/15 bg-[#fffaf0] px-5 py-7 text-[#372d24] shadow-[0_12px_40px_rgba(48,32,11,.08)] sm:px-10 sm:py-10 lg:px-16">
        <p className="text-center text-xs font-bold uppercase tracking-[.22em] text-amber-800">{format === 'reading' ? 'Chapter' : 'Page'} {slide.page.position}</p>
        {slide.page.title && <h2 className="mt-4 text-center font-serif text-2xl font-bold sm:text-3xl">{slide.page.title}</h2>}
        {slide.page.image_url && <img src={slide.page.image_url} alt={slide.page.title ?? `Page ${slide.page.position}`} className="mx-auto mt-6 max-h-[50dvh] w-full rounded-xl object-contain" />}
        {slide.page.body && <p className="mx-auto mt-6 max-w-2xl whitespace-pre-wrap font-serif text-lg leading-8 sm:text-xl sm:leading-9">{slide.page.body}</p>}
        <p className="mt-8 text-center text-xs text-amber-800/70">{slide.page.position}</p>
      </article> : null}
    </div>

    {total > 1 && <nav className="mt-5 flex items-center justify-between gap-3" aria-label="Story navigation">
      <button type="button" disabled={current === 0} onClick={() => go(current - 1)} className={`min-h-11 rounded-full border px-4 py-2 text-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-40 ${tone === 'light' ? 'border-amber-900/30 bg-[#fffaf0] text-amber-900 hover:bg-amber-100' : 'border-amber-800/25 text-foreground hover:bg-amber-500/10'}`}>← Previous</button>
      <span className={`hidden text-xs sm:block ${tone === 'light' ? 'text-amber-900' : 'text-muted'}`}>Swipe or use the buttons to turn the page</span>
      <button type="button" disabled={current === total - 1} onClick={() => go(current + 1)} className="min-h-11 rounded-full bg-amber-500 px-4 py-2 text-sm font-bold text-amber-950 transition-colors hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-40">{current === slides.length - 1 && endContent ? endLabel : 'Next →'}</button>
    </nav>}
  </section>
}
