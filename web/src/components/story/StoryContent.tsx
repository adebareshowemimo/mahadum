import type { StoryFormat, StoryPage } from '@/lib/api'

export function StoryContent({ format, pages, previewVideoUrl, fullVideoUrl }: {
  format: StoryFormat
  pages: StoryPage[]
  previewVideoUrl?: string | null
  fullVideoUrl?: string | null
}) {
  return <>
    {previewVideoUrl && <section><h2 className="mb-2 font-display text-lg font-bold">Preview</h2><video controls preload="metadata" src={previewVideoUrl} className="w-full rounded-2xl bg-black" /></section>}
    {fullVideoUrl && <section><h2 className="mb-2 font-display text-lg font-bold">Full story</h2><video controls preload="metadata" src={fullVideoUrl} className="w-full rounded-2xl bg-black" /></section>}
    {pages.length > 0 && <div className="flex flex-col gap-5">{pages.map((page) => <article key={page.id} className="rounded-2xl border border-border bg-surface p-5">
      <p className="text-xs font-semibold uppercase text-primary">{format === 'reading' ? 'Chapter' : 'Page'} {page.position}</p>
      {page.title && <h2 className="mt-2 font-display text-xl font-bold">{page.title}</h2>}
      {page.image_url && <img src={page.image_url} alt={page.title ?? `Page ${page.position}`} className="mt-4 max-h-[32rem] w-full rounded-xl object-contain" />}
      {page.body && <p className="mt-4 whitespace-pre-wrap leading-relaxed">{page.body}</p>}
    </article>)}</div>}
  </>
}
