import { useMemo, useState, type CSSProperties } from 'react'
import { Link, NavLink } from 'react-router-dom'
import { Figure } from '@/components/landing/Figure'
import { LANDING_LANGUAGES } from '@/components/landing/languages'
import { Reveal } from '@/components/landing/Reveal'
import { TryItLesson } from '@/components/landing/TryItLesson'
import { Logo } from '@/components/Logo'
import { Icon, LinkButton } from '@/components/ui'
import { cn } from '@/lib/cn'
import { TAGLINE, WORDMARK } from '@/lib/brand'

type ConceptTone = 'light' | 'blue' | 'navy'

const CONCEPTS = [
  { to: '/v1', short: 'V1', label: 'Family story' },
  { to: '/v2', short: 'V2', label: 'Culture world' },
  { to: '/v3', short: 'V3', label: 'Whole community' },
  { to: '/v4', short: 'V4', label: 'Living language' },
  { to: '/v5', short: 'V5', label: 'Learning ecosystem' },
]

export function ConceptHeader({ tone = 'light' }: { tone?: ConceptTone }) {
  const dark = tone !== 'light'

  return (
    <header
      className={cn(
        'relative z-30 border-b',
        tone === 'light' && 'border-chore-100 bg-white',
        tone === 'blue' && 'border-white/20 bg-[#0757bd] text-white',
        tone === 'navy' && 'border-white/15 bg-[#061a3c] text-white',
      )}
    >
      <div className="mx-auto flex min-h-[4.75rem] max-w-[90rem] flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2 sm:h-[4.75rem] sm:flex-nowrap sm:px-6 sm:py-0 lg:px-10">
        <Link
          to="/"
          aria-label={`${WORDMARK} home`}
          className={cn(
            'inline-flex min-h-11 shrink-0 items-center rounded-lg px-1',
            dark && 'bg-white/95 px-2',
          )}
        >
          <Logo className="h-8 sm:h-9" />
        </Link>

        <nav aria-label="Landing page concepts" className="order-3 mx-auto flex w-full items-center justify-center gap-1 rounded-full bg-black/8 p-1 sm:order-none sm:w-auto">
          {CONCEPTS.map((concept) => (
            <NavLink
              key={concept.to}
              to={concept.to}
              className={({ isActive }) =>
                cn(
                  'inline-flex min-h-10 items-center rounded-full px-3 font-display text-xs font-bold transition-colors sm:px-4 sm:text-sm',
                  isActive && (dark ? 'bg-white text-navy-950 shadow-sm' : 'bg-white text-navy-950 shadow-sm'),
                  !isActive &&
                    (dark
                      ? 'text-white/75 hover:bg-white/15 hover:text-white'
                      : 'text-navy-600 hover:bg-chore-50 hover:text-chore-700'),
                )
              }
            >
              <span>{concept.short}</span>
              <span className="ml-1.5 hidden lg:inline">{concept.label}</span>
            </NavLink>
          ))}
        </nav>

        <div className="flex items-center gap-2">
          <Link
            to="/login"
            className={cn(
              'hidden min-h-11 items-center px-3 font-display text-sm font-bold sm:inline-flex',
              dark ? 'text-white hover:text-white/75' : 'text-navy-700 hover:text-chore-700',
            )}
          >
            Sign in
          </Link>
          <LinkButton to="/register" variant={dark ? 'accent' : 'parent'} size="md">
            Start free
          </LinkButton>
        </div>
      </div>
    </header>
  )
}

export function ConceptFooter({ tone = 'light' }: { tone?: ConceptTone }) {
  const dark = tone !== 'light'
  return (
    <footer className={cn('border-t', dark ? 'border-white/15 bg-[#061a3c] text-white' : 'border-chore-100 bg-white')}>
      <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-10 sm:px-6 lg:flex-row lg:items-end lg:justify-between lg:px-8">
        <div className="max-w-md">
          <span className={cn('inline-flex rounded-lg p-2', dark && 'bg-white')}>
            <Logo className="h-8" />
          </span>
          <p className={cn('mt-4 text-sm font-semibold', dark ? 'text-white/75' : 'text-navy-600')}>{TAGLINE}</p>
        </div>
        <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm font-bold">
          <Link className="inline-flex min-h-11 items-center hover:underline" to="/pricing">Pricing</Link>
          <Link className="inline-flex min-h-11 items-center hover:underline" to="/register">Families</Link>
          <Link className="inline-flex min-h-11 items-center hover:underline" to="/pricing">Schools</Link>
          <Link className="inline-flex min-h-11 items-center hover:underline" to="/login">Sign in</Link>
        </div>
      </div>
    </footer>
  )
}

export function LanguageButtons({
  selected,
  onSelect,
  inverse = false,
}: {
  selected: number
  onSelect: (index: number) => void
  inverse?: boolean
}) {
  return (
    <fieldset>
      <legend className={cn('text-sm font-bold', inverse ? 'text-white/80' : 'text-navy-700')}>
        Choose a first language
      </legend>
      <div className="mt-3 flex flex-wrap gap-2">
        {LANDING_LANGUAGES.map((language, index) => {
          const active = selected === index
          return (
            <button
              key={language.code}
              type="button"
              aria-pressed={active}
              onClick={() => onSelect(index)}
              className={cn(
                'min-h-11 rounded-full px-4 font-display text-sm font-bold transition-[background-color,color,transform]',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-chore-400 focus-visible:ring-offset-2',
                active && (inverse ? 'bg-white text-[#073c87]' : 'bg-navy-950 text-white'),
                !active && inverse && 'bg-white/12 text-white hover:bg-white/20',
                !active && !inverse && 'bg-white text-navy-800 hover:-translate-y-0.5 hover:bg-chore-50',
              )}
            >
              {language.name}
            </button>
          )
        })}
      </div>
    </fieldset>
  )
}

export function CheckLine({ children, inverse = false }: { children: string; inverse?: boolean }) {
  return (
    <li className={cn('flex gap-2 text-sm font-bold', inverse ? 'text-white/85' : 'text-navy-700')}>
      <span aria-hidden="true" className={inverse ? 'text-[#ffb277]' : 'text-leaf-600'}>✓</span>
      {children}
    </li>
  )
}

/* -------------------------------------------------------------------------- */
/* V1 — intimate diaspora-family story                                        */
/* -------------------------------------------------------------------------- */

export function LandingV1Page() {
  const [languageIndex, setLanguageIndex] = useState(0)
  const language = LANDING_LANGUAGES[languageIndex]
  const [approved, setApproved] = useState(false)

  return (
    <div className="variant-page variant-v1 min-h-screen bg-white text-navy-950">
      <a href="#v1-main" className="variant-skip-link">Skip to main content</a>
      <ConceptHeader />
      <main id="v1-main">
        <section className="motion-hero relative overflow-hidden bg-[#dff3ff] lg:min-h-[calc(100svh-4.75rem)]">
          <img
            src="/images/landing-v1-family-call.webp"
            alt="Amara greeting family on a video call while learning with Iya"
            className="motion-hero-media absolute inset-0 hidden size-full object-cover object-center lg:block"
          />
          <div className="pointer-events-none absolute inset-0 hidden bg-[linear-gradient(90deg,rgba(223,243,255,0.98)_0%,rgba(223,243,255,0.9)_35%,rgba(223,243,255,0)_58%)] lg:block" />

          <div className="relative z-10 mx-auto max-w-[90rem] px-4 py-12 sm:px-6 sm:py-16 lg:px-10 lg:py-[clamp(5rem,9vw,9rem)]">
            <div className="motion-hero-copy max-w-[42rem]">
              <p className="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-sm font-bold text-chore-700 shadow-sm">
                <span aria-hidden="true" className="size-2 rounded-full bg-rainbow-orange" />
                For families raising children between cultures
              </p>
              <h1 className="mt-6 max-w-[12ch] font-display text-[clamp(3rem,6.1vw,5.8rem)] font-extrabold leading-[0.98] tracking-[-0.035em] text-navy-950">
                One hello can bring the whole family closer.
              </h1>
              <p className="mt-6 max-w-[36rem] text-lg font-semibold leading-relaxed text-navy-700 sm:text-xl">
                Five joyful minutes a day gives your child the words to greet Iya, follow the family story, and answer back with confidence.
              </p>
              <div className="mt-7 flex flex-col gap-3 sm:flex-row">
                <LinkButton to="/register" size="lg" variant="parent">Start your family free</LinkButton>
                <a href="#v1-how" className="inline-flex h-12 items-center justify-center rounded-xl border-2 border-chore-200 bg-white px-6 font-display font-bold text-chore-700 hover:bg-chore-50">
                  See how a word travels
                </a>
              </div>
              <p className="mt-3 text-sm font-bold text-navy-600">No card. Every lesson included on Free.</p>

              <div className="mt-8 border-t border-chore-300/70 pt-6">
                <LanguageButtons selected={languageIndex} onSelect={setLanguageIndex} />
                <div key={language.code} data-testid="v1-greeting" className="motion-word-pop mt-5 flex items-center gap-4">
                  <span aria-hidden="true" className="flex size-12 items-center justify-center rounded-full bg-rainbow-orange text-xl">👋🏾</span>
                  <div>
                    <p className="text-sm font-bold text-navy-600">Their first morning greeting</p>
                    <p className="font-display text-2xl font-extrabold text-chore-700">{language.greeting}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <img
            src="/images/landing-v1-family-call.webp"
            alt="Amara greeting family on a video call while learning with Iya"
            className="motion-hero-media aspect-[16/10] w-full object-cover object-[68%_center] lg:hidden"
          />
        </section>

        <section id="v1-how" className="bg-white py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <Reveal className="max-w-3xl">
              <h2 className="font-display text-4xl font-extrabold leading-tight text-navy-950 sm:text-6xl">
                A lesson is only finished when the word reaches home.
              </h2>
            </Reveal>
            <div className="mt-14 grid overflow-hidden rounded-[1rem] bg-navy-950 lg:grid-cols-[1.3fr_0.7fr]">
              <Figure
                src="/images/culture-storytelling.webp"
                alt="Iya sharing a folktale with Amara and her cousins at blue hour"
                className="aspect-[5/4] min-h-full rounded-none"
              />
              <div className="flex flex-col justify-center p-8 text-white sm:p-12">
                <p className="font-display text-2xl font-extrabold text-[#ffb277]">From “what does it mean?”</p>
                <p className="mt-3 font-display text-4xl font-extrabold leading-tight">to “I know this story.”</p>
                <p className="mt-5 text-lg font-semibold leading-relaxed text-white/78">
                  Tone marks, greetings for elders, proverbs, songs, folktales and festivals sit inside the course—not outside it as decoration.
                </p>
                <ul className="mt-7 space-y-3">
                  <CheckLine inverse>Native-speaker audio and speaking practice</CheckLine>
                  <CheckLine inverse>Stories that explain when and why words are used</CheckLine>
                  <CheckLine inverse>Yorùbá, Igbo, Hausa and Nigerian Pidgin</CheckLine>
                </ul>
              </div>
            </div>
          </div>
        </section>

        <section className="border-y border-[#ffd9c4] bg-[#fff6f0] py-20 sm:py-28">
          <div className="mx-auto grid max-w-7xl gap-14 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:items-center lg:px-8">
            <Reveal>
              <p className="font-display text-xl font-extrabold text-rainbow-orange">Parents do more than pay.</p>
              <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight text-navy-950 sm:text-5xl">
                You turn practice into a family ritual.
              </h2>
              <p className="mt-5 text-lg font-semibold leading-relaxed text-navy-700">
                Follow progress, listen to speaking attempts, set a chore or reward, and release coins only after you approve what your child submits.
              </p>
            </Reveal>

            <Reveal delay={80}>
              <div className="overflow-hidden rounded-[1rem] bg-white shadow-md">
                <div className="flex items-center justify-between border-b border-chore-100 px-6 py-4">
                  <p className="font-display font-extrabold text-navy-950">Tonight in your family</p>
                  <span className={cn('rounded-full px-3 py-1 text-xs font-bold', approved ? 'motion-confirm bg-leaf-100 text-leaf-700' : 'bg-chore-100 text-chore-800')}>
                    {approved ? 'Reward released' : 'Waiting for you'}
                  </span>
                </div>
                <div className="grid gap-0 sm:grid-cols-3">
                  <div className="p-6">
                    <span className="text-3xl" aria-hidden="true">🎧</span>
                    <p className="mt-3 font-display font-bold">Amara practised</p>
                    <p className="mt-1 text-sm text-navy-600">3 greetings · 92% lesson score</p>
                  </div>
                  <div className="border-y border-chore-100 p-6 sm:border-x sm:border-y-0">
                    <span className="text-3xl" aria-hidden="true">🧹</span>
                    <p className="mt-3 font-display font-bold">Room tidied</p>
                    <p className="mt-1 text-sm text-navy-600">Photo submitted · 50 coins held</p>
                  </div>
                  <div className="flex flex-col justify-between p-6">
                    <p className="text-sm font-bold text-navy-600">Coins move only when you say so.</p>
                    <div className="relative mt-5">
                      {approved && (
                        <span aria-hidden="true" className="pointer-events-none absolute inset-0">
                          {[0, 1, 2, 3, 4].map((coin) => (
                            <span key={coin} className="motion-coin absolute left-1/2 top-1/2 text-lg" style={{ '--coin-i': coin } as CSSProperties}>●</span>
                          ))}
                        </span>
                      )}
                      <button
                        type="button"
                        onClick={() => setApproved((value) => !value)}
                        className={cn(
                          'motion-press inline-flex min-h-11 w-full items-center justify-center rounded-xl px-4 font-display font-bold text-white transition-colors',
                          approved ? 'bg-navy-700 hover:bg-navy-800' : 'bg-chore-600 hover:bg-chore-700',
                        )}
                      >
                        {approved ? 'Replay approval' : 'Approve 50 coins'}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </Reveal>
          </div>
        </section>

        <section className="bg-chore-700 py-20 text-white sm:py-24">
          <div className="mx-auto max-w-5xl px-4 text-center sm:px-6">
            <Reveal>
              <p className="font-display text-2xl font-bold text-[#ffb277]">The whole language belongs to the learner.</p>
              <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight sm:text-6xl">Every lesson stays free.</h2>
              <p className="mx-auto mt-5 max-w-2xl text-lg font-semibold leading-relaxed text-white/80">
                Free includes the complete learning path. Paid plans remove ads and add offline learning and family controls; they never unlock the language itself.
              </p>
              <LinkButton to="/register" size="lg" variant="accent" className="mt-8">Create your family account</LinkButton>
            </Reveal>
          </div>
        </section>
      </main>
      <ConceptFooter />
    </div>
  )
}

/* -------------------------------------------------------------------------- */
/* V2 — child-first culture world                                              */
/* -------------------------------------------------------------------------- */

const ADVENTURES = [
  {
    id: 'market',
    tab: 'Market mission',
    title: 'Greet, count and choose.',
    body: 'The words arrive inside a real situation: say good morning, count the oranges, ask how much, and hear the answer.',
    words: ['Ẹ káàrọ̀', 'ọ̀sàn', 'mélòó?'],
    image: '/images/landing-v2-market-world.webp',
    alt: 'Amara using new words with family and neighbours in a colourful Nigerian market',
  },
  {
    id: 'story',
    tab: 'Story night',
    title: 'Listen, wonder and answer back.',
    body: 'A folktale carries tone, humour and values. Short questions make sure the child is following the story—not only watching it.',
    words: ['Àlọ́', 'ìjàpá', 'ọgbọ́n'],
    image: '/images/culture-storytelling.webp',
    alt: 'Iya telling Amara and her cousins a folktale by lantern light',
  },
  {
    id: 'challenge',
    tab: 'Class challenge',
    title: 'Practise together, then perform.',
    body: 'Assignments, speaking turns and friendly competition turn a lesson into something children can share at school and at home.',
    words: ['gbọ́', 'sọ', 'ṣe'],
    image: '/images/school-classroom.webp',
    alt: 'Amara practising a language challenge with classmates and their teacher',
  },
]

export function LandingV2Page() {
  const [adventureIndex, setAdventureIndex] = useState(0)
  const [languageIndex, setLanguageIndex] = useState(0)
  const adventure = ADVENTURES[adventureIndex]
  const language = LANDING_LANGUAGES[languageIndex]

  return (
    <div className="variant-page variant-v2 min-h-screen bg-[#0757bd] text-white">
      <a href="#v2-main" className="variant-skip-link">Skip to main content</a>
      <ConceptHeader tone="blue" />
      <main id="v2-main">
        <section className="motion-hero relative min-h-[calc(100svh-4.75rem)] overflow-hidden bg-[#0757bd]">
          <img
            src="/images/landing-v2-market-world.webp"
            alt="Amara greeting family and neighbours during a language adventure in a Nigerian market"
            className="motion-hero-pan absolute inset-0 size-full object-cover object-[58%_center]"
          />
          <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(4,64,145,0.98)_0%,rgba(4,64,145,0.82)_100%)] lg:bg-[linear-gradient(90deg,rgba(4,64,145,0.98)_0%,rgba(4,64,145,0.86)_31%,rgba(4,64,145,0.18)_57%,rgba(4,64,145,0.04)_100%)]" />
          <div className="absolute inset-x-0 bottom-0 h-48 bg-[linear-gradient(0deg,rgba(4,64,145,0.92),transparent)]" />
          <div className="relative z-10 mx-auto flex min-h-[calc(100svh-4.75rem)] max-w-[90rem] items-center px-4 py-16 sm:px-6 lg:px-10">
            <div className="motion-hero-copy max-w-[42rem]">
              <p className="font-display text-xl font-extrabold text-[#ffd36a]">A Nigerian language world children can enter.</p>
              <h1 className="mt-4 max-w-[10ch] font-display text-[clamp(3.2rem,6.7vw,6rem)] font-extrabold leading-[0.94] tracking-[-0.035em] text-white">
                Every new word opens a bigger world.
              </h1>
              <p className="mt-6 max-w-xl text-lg font-bold leading-relaxed text-white/88 sm:text-xl">
                Stories, market missions, speaking games and friendly challenges make Yorùbá, Igbo, Hausa and Pidgin feel like play with a purpose.
              </p>
              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="#v2-play" className="inline-flex h-12 items-center justify-center rounded-xl bg-[#ff650f] px-6 font-display font-extrabold text-white hover:bg-[#d94d00]">Play a one-minute lesson</a>
                <LinkButton to="/register" size="lg" variant="ghost" className="border-2 border-white/55 bg-white/10 text-white hover:bg-white/20">Start free</LinkButton>
              </div>
              <div className="mt-8 flex flex-wrap gap-2 text-sm font-bold">
                {['Ages 5+', 'Every lesson free', 'Parent-controlled', 'Built for 3G'].map((item, index) => (
                  <span key={item} className="motion-chip-entry rounded-full bg-white/14 px-3 py-1.5 text-white" style={{ '--chip-i': index } as CSSProperties}>{item}</span>
                ))}
              </div>
            </div>
          </div>
          <div aria-hidden="true" className="pointer-events-none absolute bottom-14 right-[3%] z-10 hidden lg:block">
            {['Ẹ káàrọ̀', 'Nnọọ', 'Sannu'].map((word, index) => (
              <span key={word} className={cn('motion-float absolute rounded-full bg-white px-4 py-2 font-display font-extrabold text-[#0757bd] shadow-md', index === 0 && '-left-72 -top-28', index === 1 && '-left-48 top-2', index === 2 && '-left-20 -top-44')} style={{ '--float-i': index } as CSSProperties}>{word}</span>
            ))}
          </div>
        </section>

        <section className="bg-white py-20 text-navy-950 sm:py-28">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <Reveal className="max-w-4xl">
              <h2 className="font-display text-4xl font-extrabold leading-tight sm:text-6xl">Pick the next five-minute adventure.</h2>
              <p className="mt-4 max-w-2xl text-lg font-semibold text-navy-700">One course moves between words, stories, speaking and culture so attention never has to sit still for long.</p>
            </Reveal>
            <div className="mt-10 flex flex-wrap gap-2" role="tablist" aria-label="Learning adventures">
              {ADVENTURES.map((item, index) => (
                <button
                  key={item.id}
                  type="button"
                  role="tab"
                  aria-selected={adventureIndex === index}
                  aria-controls="adventure-panel"
                  onClick={() => setAdventureIndex(index)}
                  className={cn(
                    'min-h-11 rounded-full px-5 font-display font-bold transition-colors',
                    adventureIndex === index ? 'bg-navy-950 text-white' : 'bg-chore-50 text-chore-800 hover:bg-chore-100',
                  )}
                >
                  {item.tab}
                </button>
              ))}
            </div>
            <div id="adventure-panel" role="tabpanel" className="mt-6 grid overflow-hidden rounded-[1rem] bg-[#eef6ff] lg:grid-cols-[1.35fr_0.65fr]">
              <img key={adventure.image} src={adventure.image} alt={adventure.alt} className="motion-panel-swap aspect-[16/10] size-full object-cover" />
              <div key={adventure.id} className="motion-panel-swap flex flex-col justify-center p-7 sm:p-10">
                <h3 className="font-display text-3xl font-extrabold text-navy-950">{adventure.title}</h3>
                <p className="mt-4 text-lg font-semibold leading-relaxed text-navy-700">{adventure.body}</p>
                <div className="mt-7 flex flex-wrap gap-2">
                  {adventure.words.map((word, index) => <span key={word} className="motion-word-chip rounded-full bg-white px-4 py-2 font-display font-bold text-chore-700 shadow-sm" style={{ '--chip-i': index } as CSSProperties}>{word}</span>)}
                </div>
              </div>
            </div>
          </div>
        </section>

        <section id="v2-play" className="bg-[#061a3c] py-20 sm:py-28">
          <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <Reveal className="text-center">
              <p className="font-display text-xl font-extrabold text-[#ffb277]">No demo video. A real activity.</p>
              <h2 className="mt-3 font-display text-4xl font-extrabold text-white sm:text-6xl">Try the first three words now.</h2>
              <p className="mx-auto mt-4 max-w-2xl text-lg font-semibold text-white/72">Your language choice changes the activity immediately. No account and no email first.</p>
            </Reveal>
            <div className="mx-auto mt-9 max-w-3xl">
              <LanguageButtons selected={languageIndex} onSelect={setLanguageIndex} inverse />
              <div className="mt-6">
                <TryItLesson language={language} />
              </div>
            </div>
          </div>
        </section>

        <section className="bg-[#ff650f] py-20 sm:py-28">
          <div className="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8">
            <Reveal>
              <h2 className="font-display text-4xl font-extrabold leading-tight text-white sm:text-6xl">Kids get the adventure. Parents keep the controls.</h2>
              <p className="mt-5 max-w-xl text-lg font-bold leading-relaxed text-white/88">Under-13 learners have profiles, not logins. You see progress, review recordings and approve chore rewards before coins move.</p>
              <ul className="mt-7 space-y-3">
                <CheckLine inverse>Up to six learner profiles</CheckLine>
                <CheckLine inverse>Parent PIN and consent record</CheckLine>
                <CheckLine inverse>No learning locked behind hearts or payment</CheckLine>
              </ul>
            </Reveal>
            <Reveal delay={80}>
              <div className="rounded-[1rem] bg-white p-7 text-navy-950 shadow-md sm:p-9">
                <div className="flex items-center gap-4">
                  <span className="flex size-14 items-center justify-center rounded-xl bg-chore-100 text-chore-700"><Icon name="shield" className="size-7" /></span>
                  <div>
                    <p className="font-display text-xl font-extrabold">Amara’s grown-up view</p>
                    <p className="text-sm font-semibold text-navy-600">One calm place for the parts children should not manage.</p>
                  </div>
                </div>
                <div className="mt-7 divide-y divide-chore-100 border-y border-chore-100">
                  {[
                    ['Speaking review', '2 recordings ready'],
                    ['Chore approval', '50 coins waiting'],
                    ['Weekly progress', '4 lessons · 18 new words'],
                  ].map(([label, value]) => (
                    <div key={label} className="flex min-h-16 items-center justify-between gap-4 py-3">
                      <span className="font-bold text-navy-700">{label}</span>
                      <span className="text-right font-display font-extrabold text-chore-700">{value}</span>
                    </div>
                  ))}
                </div>
              </div>
            </Reveal>
          </div>
        </section>

        <section className="bg-[#ffd44a] py-20 text-navy-950 sm:py-24">
          <div className="mx-auto max-w-5xl px-4 text-center sm:px-6">
            <Reveal>
              <h2 className="font-display text-5xl font-extrabold leading-[1.02] sm:text-7xl">The next adventure costs ₦0.</h2>
              <p className="mx-auto mt-5 max-w-2xl text-lg font-bold leading-relaxed">Every lesson is open on Free. Paid plans remove ads and add offline and family conveniences.</p>
              <LinkButton to="/register" size="lg" variant="parent" className="mt-8">Let them play the first lesson</LinkButton>
            </Reveal>
          </div>
        </section>
      </main>
      <ConceptFooter tone="blue" />
    </div>
  )
}

/* -------------------------------------------------------------------------- */
/* V3 — whole community / school-forward                                      */
/* -------------------------------------------------------------------------- */

const ROLE_VIEWS: Array<{
  id: 'family' | 'school' | 'teacher'
  label: string
  heading: string
  body: string
  features: string[]
  cta: string
}> = [
  {
    id: 'family',
    label: 'Families',
    heading: 'Keep the learning alive after the lesson.',
    body: 'Profiles, progress, speaking reviews, chores, coins and a parent PIN turn language into something the whole household can support.',
    features: ['Up to 6 learners', 'Parent approval queue', 'Airtime or card billing'],
    cta: 'Start a family account',
  },
  {
    id: 'school',
    label: 'Schools',
    heading: 'Run language learning without building the system yourself.',
    body: 'Import the roster, allocate seats, create classes, assign lessons, inspect results and download invoices from one tenant-safe school workspace.',
    features: ['CSV roster import', 'Class and learner analytics', 'Nine-month school plans'],
    cta: 'See school pricing',
  },
  {
    id: 'teacher',
    label: 'Teachers',
    heading: 'Teach, review and see who needs help next.',
    body: 'Class assignments, speaking submissions, per-student scores and quiz accuracy make the next teaching decision visible.',
    features: ['Assignments and grading', 'Speaking review', 'Student-level analytics'],
    cta: 'Explore the teacher experience',
  },
]

const SCHOOL_BANDS = [
  { max: 99, registration: 50_000, perStudent: 7_000 },
  { max: 249, registration: 100_000, perStudent: 6_000 },
  { max: 500, registration: 150_000, perStudent: 5_500 },
  { max: Infinity, registration: 200_000, perStudent: 5_000 },
]

const money = new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 })

export function LandingV3Page() {
  const [roleIndex, setRoleIndex] = useState(0)
  const [students, setStudents] = useState(120)
  const role = ROLE_VIEWS[roleIndex]
  const band = useMemo(() => SCHOOL_BANDS.find((item) => students <= item.max) ?? SCHOOL_BANDS[3], [students])
  const schoolTotal = band.registration + students * band.perStudent

  return (
    <div className="variant-page variant-v3 min-h-screen bg-white text-navy-950">
      <a href="#v3-main" className="variant-skip-link">Skip to main content</a>
      <ConceptHeader tone="navy" />
      <main id="v3-main">
        <section className="motion-hero relative min-h-[calc(100svh-4.75rem)] overflow-hidden bg-[#061a3c]">
          <img
            src="/images/landing-v3-school-community.webp"
            alt="Amara learning with classmates while family members arrive for the school Language and Culture club"
            className="motion-hero-media absolute inset-0 size-full object-cover object-[54%_center]"
          />
          <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(6,26,60,0.98)_0%,rgba(6,26,60,0.8)_100%)] lg:bg-[linear-gradient(90deg,rgba(6,26,60,0.98)_0%,rgba(6,26,60,0.9)_35%,rgba(6,26,60,0.08)_62%)]" />
          <div className="relative z-10 mx-auto flex min-h-[calc(100svh-4.75rem)] max-w-[90rem] items-center px-4 py-16 sm:px-6 lg:px-10">
            <div className="motion-hero-copy max-w-[43rem] text-white">
              <p className="font-display text-xl font-extrabold text-[#ffb277]">One learning system from home to school.</p>
              <h1 className="mt-4 max-w-[11ch] font-display text-[clamp(3.1rem,6.4vw,5.9rem)] font-extrabold leading-[0.96] tracking-[-0.035em]">
                Keep the language moving everywhere a child learns.
              </h1>
              <p className="mt-6 max-w-xl text-lg font-semibold leading-relaxed text-white/78 sm:text-xl">
                A complete Nigerian-language platform for learners, families, teachers and schools—joined by one curriculum and one view of progress.
              </p>
              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <LinkButton to="/register" size="lg" variant="accent">Start learning free</LinkButton>
                <a href="#v3-school" className="inline-flex h-12 items-center justify-center rounded-xl border-2 border-white/45 bg-white/8 px-6 font-display font-bold text-white hover:bg-white/15">Price a school</a>
              </div>
              <ul className="mt-8 grid max-w-xl gap-3 sm:grid-cols-2">
                <CheckLine inverse>Complete learning on Free</CheckLine>
                <CheckLine inverse>Native-language and culture curriculum</CheckLine>
                <CheckLine inverse>Parent and teacher review</CheckLine>
                <CheckLine inverse>Low-bandwidth and offline options</CheckLine>
              </ul>
            </div>
          </div>
          <div aria-hidden="true" className="motion-network absolute bottom-10 right-8 z-10 hidden items-center gap-3 rounded-xl bg-[#061a3c]/88 px-5 py-4 text-xs font-extrabold text-white lg:flex">
            {['HOME', 'LESSON', 'SCHOOL'].map((node, index) => (
              <span key={node} className="flex items-center gap-3">
                <span className="motion-network-node flex size-9 items-center justify-center rounded-full bg-white text-[#061a3c]" style={{ '--node-i': index } as CSSProperties}>{index + 1}</span>
                <span>{node}</span>
                {index < 2 && <span className="motion-network-signal h-px w-10 bg-white/35" />}
              </span>
            ))}
          </div>
        </section>

        <section className="bg-white py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <Reveal className="max-w-4xl">
              <h2 className="font-display text-4xl font-extrabold leading-tight text-navy-950 sm:text-6xl">One child. Three circles of support.</h2>
              <p className="mt-4 max-w-2xl text-lg font-semibold text-navy-700">Choose a view to see how the same learning journey serves each person around the learner.</p>
            </Reveal>
            <div className="mt-10 grid gap-8 lg:grid-cols-[0.42fr_0.58fr]">
              <div role="tablist" aria-label="Platform audiences" className="flex flex-col gap-2">
                {ROLE_VIEWS.map((item, index) => (
                  <button
                    key={item.id}
                    type="button"
                    role="tab"
                    aria-selected={roleIndex === index}
                    aria-controls="role-panel"
                    onClick={() => setRoleIndex(index)}
                    className={cn(
                      'flex min-h-16 items-center justify-between rounded-xl px-5 text-left font-display text-xl font-extrabold transition-colors',
                      roleIndex === index ? 'bg-chore-700 text-white' : 'bg-chore-50 text-navy-800 hover:bg-chore-100',
                    )}
                  >
                    {item.label}
                    <Icon name="chevron" className="size-5 -rotate-90" />
                  </button>
                ))}
              </div>
              <div id="role-panel" role="tabpanel" key={role.id} className="motion-panel-swap rounded-[1rem] bg-[#eef6ff] p-7 sm:p-10">
                <h3 className="font-display text-3xl font-extrabold leading-tight text-navy-950 sm:text-4xl">{role.heading}</h3>
                <p className="mt-4 max-w-2xl text-lg font-semibold leading-relaxed text-navy-700">{role.body}</p>
                <ul className="mt-7 grid gap-3 sm:grid-cols-3">
                  {role.features.map((feature) => <CheckLine key={feature}>{feature}</CheckLine>)}
                </ul>
                <LinkButton to={role.id === 'school' ? '/pricing' : '/register'} variant="parent" className="mt-8">{role.cta}</LinkButton>
              </div>
            </div>
          </div>
        </section>

        <section className="bg-[#eef6ff] py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="grid gap-14 lg:grid-cols-2 lg:items-center">
              <Reveal>
                <p className="font-display text-xl font-extrabold text-rainbow-orange">The 360° learning loop</p>
                <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight sm:text-5xl">Teach it. Use it. Hear it come back.</h2>
                <p className="mt-5 text-lg font-semibold leading-relaxed text-navy-700">A child meets a word in a lesson, practises it aloud, uses it in an assignment or family moment, and receives feedback from the adult who knows the context.</p>
              </Reveal>
              <ol className="divide-y divide-chore-200 border-y border-chore-200">
                {[
                  ['Learn', 'Five-minute lessons with listening, speaking, quizzes and cultural context.'],
                  ['Practise', 'Assignments, stories and repetition move the word into use.'],
                  ['Review', 'Parents and teachers hear submissions and respond with encouragement.'],
                  ['Celebrate', 'XP, badges, coins, leagues, clubs and competitions make progress visible.'],
                ].map(([title, body], index) => (
                  <Reveal key={title} as="li" delay={index * 65} className="grid grid-cols-[3rem_1fr] gap-4 py-5">
                    <span className="font-display text-2xl font-extrabold text-chore-600">{index + 1}</span>
                    <div><h3 className="font-display text-xl font-extrabold">{title}</h3><p className="mt-1 font-semibold text-navy-700">{body}</p></div>
                  </Reveal>
                ))}
              </ol>
            </div>
          </div>
        </section>

        <section id="v3-school" className="bg-white py-20 sm:py-28">
          <div className="mx-auto grid max-w-7xl gap-14 px-4 sm:px-6 lg:grid-cols-[0.85fr_1.15fr] lg:items-center lg:px-8">
            <Reveal>
              <p className="font-display text-xl font-extrabold text-chore-700">A school quote you can understand.</p>
              <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight sm:text-5xl">Price the roll, then see what the team gets.</h2>
              <p className="mt-5 text-lg font-semibold leading-relaxed text-navy-700">School plans combine annual registration with a per-student rate that steps down as enrollment grows.</p>
              <ul className="mt-7 space-y-3">
                <CheckLine>CSV roster import with row-level errors</CheckLine>
                <CheckLine>Classes, seats, assignments and learner analytics</CheckLine>
                <CheckLine>Invoices, receipts and referral earnings</CheckLine>
                <CheckLine>Language & Culture club and competition entry</CheckLine>
              </ul>
            </Reveal>
            <Reveal delay={80}>
              <div className="rounded-[1rem] bg-navy-950 p-7 text-white shadow-md sm:p-10">
                <label htmlFor="school-students" className="font-display text-lg font-extrabold text-white">How many students?</label>
                <div className="mt-3 flex items-end justify-between gap-4">
                  <output htmlFor="school-students" className="motion-value font-display text-5xl font-extrabold text-[#ffb277]">{students}</output>
                  <span className="pb-1 text-sm font-bold text-white/65">9-month academic year</span>
                </div>
                <input
                  id="school-students"
                  type="range"
                  min="25"
                  max="800"
                  step="5"
                  value={students}
                  onChange={(event) => setStudents(Number(event.target.value))}
                  className="mt-6 w-full accent-[#ff650f]"
                />
                <div className="mt-8 divide-y divide-white/15 border-y border-white/15">
                  <div className="flex items-center justify-between py-4"><span className="font-bold text-white/70">Registration</span><strong className="font-display text-xl">{money.format(band.registration)}</strong></div>
                  <div className="flex items-center justify-between py-4"><span className="font-bold text-white/70">Per student</span><strong className="font-display text-xl">{money.format(band.perStudent)}</strong></div>
                  <div className="flex items-center justify-between py-4"><span className="font-bold text-white/70">Estimated total</span><strong className="font-display text-2xl text-[#ffb277]">{money.format(schoolTotal)}</strong></div>
                </div>
                <p className="mt-4 text-xs font-semibold text-white/58">Estimate based on published bands; final invoice reflects confirmed seats and organization terms.</p>
                <LinkButton to="/pricing" variant="accent" fullWidth className="mt-6">Get a school quote</LinkButton>
              </div>
            </Reveal>
          </div>
        </section>

        <section className="relative overflow-hidden bg-[#061a3c] py-20 text-white sm:py-28">
          <div className="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:items-center lg:px-8">
            <Reveal>
              <p className="font-display text-xl font-extrabold text-[#ffb277]">Beyond the screen</p>
              <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight sm:text-5xl">A language becomes a community children can join.</h2>
              <p className="mt-5 text-lg font-semibold leading-relaxed text-white/75">School clubs, family stories and national competitions give children a reason to speak, perform, vote and celebrate what they know.</p>
              <LinkButton to="/register" variant="accent" size="lg" className="mt-7">Build the first learning circle</LinkButton>
            </Reveal>
            <Figure src="/images/culture-storytelling.webp" alt="Children gathered with Iya for a family story at blue hour" className="aspect-[5/4] rounded-[1rem]" />
          </div>
        </section>
      </main>
      <ConceptFooter tone="navy" />
    </div>
  )
}
