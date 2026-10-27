import type { StoryFormat, StoryPage } from '@/lib/api'

export function StoryContent({ format, pages, previewVideoUrl, fullVideoUrl }: {
  format: StoryFormat
  pages: StoryPage[]
  previewVideoUrl?: string | null
  fullVideoUrl?: string | null
}) {
  return <>
    {previewVideoUrl && <section><h2 className="mb-3 font-display text-lg font-bold">Preview clip</h2><video controls preload="metadata" src={previewVideoUrl} className="w-full rounded-2xl bg-black" /></section>}
    {fullVideoUrl && <section><h2 className="mb-3 font-display text-lg font-bold">Full story</h2><video controls preload="metadata" src={fullVideoUrl} className="w-full rounded-2xl bg-black" /></section>}
    {pages.length > 0 && <div className="flex flex-col gap-6">{pages.map((page) => <article key={page.id} className="rounded-2xl border border-amber-900/15 bg-[#fffaf0] px-6 py-8 text-[#372d24] shadow-[0_12px_40px_rgba(48,32,11,.08)] sm:px-10 sm:py-10">
      <p className="text-center text-xs font-bold uppercase tracking-[.22em] text-amber-800">{format === 'reading' ? 'Chapter' : 'Page'} {page.position}</p>
      {page.title && <h2 className="mt-4 text-center font-serif text-2xl font-bold sm:text-3xl">{page.title}</h2>}
      {page.image_url && <img src={page.image_url} alt={page.title ?? `Page ${page.position}`} className="mx-auto mt-6 max-h-[32rem] w-full rounded-xl object-contain" />}
      {page.body && <p className="mx-auto mt-6 max-w-2xl whitespace-pre-wrap font-serif text-lg leading-9 sm:text-xl">{page.body}</p>}
      <p className="mt-8 text-center text-xs text-amber-800/70">{page.position}</p>
    </article>)}</div>}
  </>
}
