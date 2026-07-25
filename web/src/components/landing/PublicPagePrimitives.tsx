import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { Icon, LinkButton, type IconName } from '@/components/ui'
import { cn } from '@/lib/cn'
import { ConceptFooter } from '@/pages/LandingVariantsPage'
import { ConceptHeader } from './ConceptHeader'
import { Reveal } from './Reveal'

export function PublicPageShell({
  children,
  mainId,
  overlayHeader = false,
}: {
  children: ReactNode
  mainId: string
  overlayHeader?: boolean
}) {
  return (
    <div className="variant-page min-h-screen bg-white text-navy-950">
      <a href={`#${mainId}`} className="variant-skip-link">Skip to main content</a>
      <ConceptHeader tone={overlayHeader ? 'navy' : 'light'} overlay={overlayHeader} />
      <main id={mainId}>{children}</main>
      <ConceptFooter />
    </div>
  )
}

export function SpeechCircle({ inverse = false, className }: { inverse?: boolean; className?: string }) {
  return (
    <div
      aria-hidden="true"
      className={cn(
        'relative flex size-20 shrink-0 items-center justify-center rounded-full',
        inverse ? 'bg-white text-chore-700' : 'bg-[#eef6ff] text-chore-700',
        className,
      )}
    >
      <span className="absolute inset-2 rounded-full border-[3px] border-chore-600 border-r-[#f45b16]" />
      <span className="flex h-8 items-end gap-1">
        {[15, 25, 32, 22, 12].map((height, index) => (
          <span
            key={height}
            className="motion-wave-bar w-1.5 rounded-full bg-[#55c8eb]"
            style={{ height, '--bar-i': index } as React.CSSProperties}
          />
        ))}
      </span>
    </div>
  )
}

export type FlowItem = {
  title: string
  body: string
  icon: IconName
}

export function CapabilityFlow({
  heading,
  intro,
  items,
  tone = 'light',
}: {
  heading: string
  intro: string
  items: FlowItem[]
  tone?: 'light' | 'sky'
}) {
  return (
    <section className={cn('py-12 sm:py-16', tone === 'sky' ? 'bg-[#eef6ff]' : 'bg-white')}>
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid gap-8 lg:grid-cols-[0.66fr_1.34fr] lg:items-center">
          <Reveal>
            <SpeechCircle />
            <h2 className="mt-5 max-w-[15ch] font-display text-3xl font-extrabold leading-tight sm:text-4xl">{heading}</h2>
            <p className="mt-4 max-w-xl text-base leading-relaxed text-navy-700 sm:text-lg">{intro}</p>
          </Reveal>
          <ol className="relative grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <span aria-hidden="true" className="motion-network-signal absolute left-[8%] right-[8%] top-8 hidden h-0.5 bg-chore-200 lg:block" />
            {items.map((item, index) => (
              <Reveal key={item.title} as="li" delay={index * 55} className="relative">
                <div className="flex h-full gap-4 border-b border-chore-200 py-4 lg:block lg:border-0 lg:py-0">
                  <span className="relative z-10 flex size-14 shrink-0 items-center justify-center rounded-full bg-navy-950 text-white">
                    <Icon name={item.icon} className="size-6" />
                  </span>
                  <div className="lg:mt-5">
                    <p className="font-display text-sm font-extrabold text-chore-700">{index + 1}</p>
                    <h3 className="mt-1 font-display text-xl font-extrabold">{item.title}</h3>
                    <p className="mt-2 text-sm leading-relaxed text-navy-700">{item.body}</p>
                  </div>
                </div>
              </Reveal>
            ))}
          </ol>
        </div>
      </div>
    </section>
  )
}

export type AudienceItem = {
  title: string
  body: string
  link: string
  linkLabel: string
}

export function AudiencePath({
  heading,
  intro,
  items,
}: {
  heading: string
  intro: string
  items: AudienceItem[]
}) {
  return (
    <section className="bg-[#55c8eb] py-16 sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Reveal className="max-w-4xl">
          <h2 className="max-w-[18ch] font-display text-4xl font-extrabold leading-tight sm:text-5xl">{heading}</h2>
          <p className="mt-5 max-w-3xl text-lg leading-relaxed text-navy-800">{intro}</p>
        </Reveal>
        <div className="mt-10 divide-y divide-navy-950/20 border-y border-navy-950/20">
          {items.map((item, index) => (
            <Reveal
              key={item.title}
              delay={index * 45}
              className="grid gap-3 py-6 md:grid-cols-[3.5rem_0.7fr_1.3fr_auto] md:items-center md:gap-6"
            >
              <span className="font-display text-2xl font-extrabold text-navy-700">{String(index + 1).padStart(2, '0')}</span>
              <h3 className="font-display text-2xl font-extrabold">{item.title}</h3>
              <p className="max-w-2xl leading-relaxed text-navy-800">{item.body}</p>
              <Link className="inline-flex min-h-11 items-center font-display font-extrabold text-[#8f2b02] hover:underline" to={item.link}>
                {item.linkLabel} <span aria-hidden="true" className="ml-2">→</span>
              </Link>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  )
}

export function PublicClosing({
  heading,
  body,
  primary,
  secondary,
}: {
  heading: string
  body: string
  primary: { label: string; to: string }
  secondary?: { label: string; to: string }
}) {
  return (
    <section className="bg-navy-950 py-16 text-white sm:py-24">
      <Reveal className="mx-auto max-w-5xl px-4 text-center sm:px-6 lg:px-8">
        <SpeechCircle inverse className="mx-auto" />
        <h2 className="mx-auto mt-6 max-w-[18ch] font-display text-4xl font-extrabold leading-tight sm:text-5xl">{heading}</h2>
        <p className="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-white/78">{body}</p>
        <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
          <LinkButton to={primary.to} variant="accent" size="lg">{primary.label}</LinkButton>
          {secondary && (
            <LinkButton to={secondary.to} variant="secondary" size="lg">{secondary.label}</LinkButton>
          )}
        </div>
      </Reveal>
    </section>
  )
}

export type PolicySection = {
  id: string
  title: string
  paragraphs: string[]
  bullets?: string[]
}

export function PolicyPage({
  title,
  summary,
  updated,
  sections,
}: {
  title: string
  summary: string
  updated: string
  sections: PolicySection[]
}) {
  return (
    <PublicPageShell mainId={`policy-${sections[0]?.id ?? 'main'}`}>
      <section className="bg-navy-950 py-14 text-white sm:py-20">
        <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
          <p className="font-display text-lg font-extrabold text-[#ffb277]">Trust, explained clearly</p>
          <h1 className="mt-3 max-w-[16ch] font-display text-4xl font-extrabold leading-tight sm:text-6xl">{title}</h1>
          <p className="mt-5 max-w-3xl text-lg leading-relaxed text-white/80">{summary}</p>
          <p className="mt-5 text-sm font-bold text-white/72">Last updated: {updated}</p>
        </div>
      </section>

      <div className="mx-auto grid max-w-6xl gap-12 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[15rem_1fr] lg:px-8">
        <aside className="lg:sticky lg:top-6 lg:self-start">
          <p className="font-display text-lg font-extrabold">On this page</p>
          <nav aria-label={`${title} sections`} className="mt-3 border-y border-chore-200 py-3">
            {sections.map((section) => (
              <a key={section.id} href={`#${section.id}`} className="block min-h-11 py-2 text-sm font-bold text-navy-700 hover:text-chore-700 hover:underline">
                {section.title}
              </a>
            ))}
          </nav>
          <Link to="/contact" className="mt-5 inline-flex min-h-11 items-center font-display font-extrabold text-[#a93403] hover:underline">
            Ask a question →
          </Link>
        </aside>

        <article className="max-w-[72ch]">
          {sections.map((section) => (
            <section key={section.id} id={section.id} className="scroll-mt-6 border-b border-chore-200 pb-10 not-first:pt-10">
              <h2 className="font-display text-3xl font-extrabold leading-tight">{section.title}</h2>
              {section.paragraphs.map((paragraph) => (
                <p key={paragraph} className="mt-4 leading-[1.75] text-navy-800">{paragraph}</p>
              ))}
              {section.bullets && (
                <ul className="mt-5 space-y-3">
                  {section.bullets.map((bullet) => (
                    <li key={bullet} className="flex gap-3 leading-[1.7] text-navy-800">
                      <span aria-hidden="true" className="mt-2 size-2 shrink-0 rounded-full bg-chore-600" />
                      {bullet}
                    </li>
                  ))}
                </ul>
              )}
            </section>
          ))}
        </article>
      </div>
    </PublicPageShell>
  )
}
