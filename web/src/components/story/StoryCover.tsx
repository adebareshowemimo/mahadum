import type { StorySummary } from '@/lib/api'
import { storyBand, storyLength } from '@/lib/story/presentation'

export function StoryCover({ story, className = '', showPrice = true }: {
  story: StorySummary
  className?: string
  showPrice?: boolean
}) {
  return <div className={`relative isolate overflow-hidden rounded-2xl bg-gradient-to-b from-indigo-900 via-violet-700 to-amber-500 ${className}`}>
    {story.cover_url ? <img src={story.cover_url} alt="" className="absolute inset-0 h-full w-full object-cover" /> : <div className="absolute inset-0 bg-[radial-gradient(circle_at_68%_24%,#ffe29a_0_12%,transparent_13%),linear-gradient(165deg,#30246f_0%,#74469b_65%,#bf6b78_100%)]" />}
    <div className="absolute inset-0 bg-gradient-to-b from-black/25 via-transparent to-black/45" />
    <div className="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-3 text-[11px] font-bold">
      <span className="rounded-full bg-black/75 px-2.5 py-1 text-white">{storyBand(story).short}</span>
      <span className="rounded-full bg-black/75 px-2.5 py-1 text-white">{storyLength(story)}</span>
    </div>
    {showPrice && <span className="absolute bottom-3 right-3 rounded-full bg-gold-400 px-2.5 py-1 text-xs font-extrabold text-charcoal-900">{story.purchased ? 'Owned' : '₦500'}</span>}
  </div>
}
