import { useState } from 'react'
import { Link, Navigate } from 'react-router-dom'
import { Icon, LinkButton, type IconName } from '@/components/ui'
import { Logo } from '@/components/Logo'
import { cn } from '@/lib/cn'
import { TAGLINE, WORDMARK } from '@/lib/brand'
import { useAuth } from '@/lib/auth/AuthProvider'
import { Reveal } from '@/components/landing/Reveal'
import { Figure } from '@/components/landing/Figure'
import { TryItLesson } from '@/components/landing/TryItLesson'
import { LANDING_LANGUAGES } from '@/components/landing/languages'

/**
 * Marketing landing page.
 *
 * Positioning, in one line: competitors sell *aspiration* (a language you never
 * had); MAHADUM.360 sells *restoration* (a language that was slipping away).
 * Everything here serves that, plus the two things no competitor in this
 * category offers — a parent who participates rather than just pays, and a
 * school product at all.
 *
 * Visual identity follows the committed master brand: sky/cobalt blue carries
 * trust and learning, orange carries energy, navy carries depth, and the
 * rainbow wordmark stays the most colourful object in the chrome. Heritage is
 * expressed through the characters, language and content rather than a generic
 * cultural-pattern overlay.
 *
 * Honesty constraints, deliberate: no user counts, no testimonials and no
 * efficacy percentages appear anywhere on this page, because none of them are
 * true yet. Credibility is built from architecture and transparent pricing
 * instead. Add real numbers here only when they exist.
 */
export function LandingPage() {
  const { status } = useAuth()
  const [langIndex, setLangIndex] = useState(0)
  const language = LANDING_LANGUAGES[langIndex]

  if (status === 'authenticated') return <Navigate to="/home" replace />

  return (
    <div className="landing-page min-h-screen bg-white text-navy-950">
      <a
        href="#main-content"
        className="fixed left-4 top-3 z-50 -translate-y-20 rounded-lg bg-navy-950 px-4 py-3 font-display font-bold text-white transition-transform focus:translate-y-0"
      >
        Skip to main content
      </a>
      <SiteHeader />
      <main id="main-content">
        <Hero language={language} langIndex={langIndex} onPick={setLangIndex} />
        <TryItSection language={language} />
        <HeritageSection />
        <FamilySection />
        <NeverLockedBand />
        <SchoolsSection />
        <PricingPreview />
        <TrustSection />
        <FaqSection />
        <ClosingCta />
      </main>
      <SiteFooter />
    </div>
  )
}

/* ------------------------------------------------------------------ header */

function SiteHeader() {
  const [open, setOpen] = useState(false)
  const links = [
    { to: '/#families', label: 'For families' },
    { to: '/#schools', label: 'For schools' },
    { to: '/pricing', label: 'Pricing' },
  ]

  return (
    <header className="sticky top-0 z-30 border-b border-chore-100 bg-white/95 backdrop-blur">
      <div className="mx-auto flex h-[4.5rem] max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <Link to="/" aria-label={`${WORDMARK} home`} className="inline-flex min-h-11 shrink-0 items-center">
          <Logo className="h-8 sm:h-9" />
        </Link>

        <nav aria-label="Main" className="hidden items-center gap-1 md:flex">
          {links.map((l) => (
            <a
              key={l.label}
              href={l.to}
              className="inline-flex h-11 items-center rounded-lg px-3 font-display text-sm font-bold text-navy-700 transition-colors hover:bg-chore-50 hover:text-chore-700"
            >
              {l.label}
            </a>
          ))}
        </nav>

        <div className="flex items-center gap-2">
          <LinkButton to="/login" variant="ghost" size="md" className="hidden sm:inline-flex">
            Sign in
          </LinkButton>
          <LinkButton to="/register" size="md" variant="parent">
            Get started
          </LinkButton>
          <button
            type="button"
            aria-label={open ? 'Close menu' : 'Open menu'}
            aria-expanded={open}
            onClick={() => setOpen((o) => !o)}
            className="inline-flex size-11 items-center justify-center rounded-lg text-navy-700 transition-colors hover:bg-chore-50 hover:text-chore-700 md:hidden"
          >
            <Icon name={open ? 'close' : 'menu'} className="size-5" />
          </button>
        </div>
      </div>

      {open && (
        <nav
          aria-label="Main"
          className="animate-step-in border-t border-chore-100 bg-white px-4 py-3 md:hidden"
        >
          {links.map((l) => (
            <a
              key={l.label}
              href={l.to}
              onClick={() => setOpen(false)}
              className="flex min-h-11 items-center rounded-lg px-2 font-display font-bold text-navy-900 hover:bg-chore-50"
            >
              {l.label}
            </a>
          ))}
          <a
            href="/login"
            className="flex min-h-11 items-center rounded-lg px-2 font-display font-bold text-navy-900 hover:bg-chore-50 sm:hidden"
          >
            Sign in
          </a>
        </nav>
      )}
    </header>
  )
}

/* -------------------------------------------------------------------- hero */

function Hero({
  language,
  langIndex,
  onPick,
}: {
  language: (typeof LANDING_LANGUAGES)[number]
  langIndex: number
  onPick: (i: number) => void
}) {
  return (
    <section className="landing-hero relative overflow-hidden">
      <div className="mx-auto grid max-w-7xl gap-10 px-4 py-10 sm:px-6 sm:py-16 lg:grid-cols-[1.03fr_0.97fr] lg:items-center lg:gap-14 lg:px-8 lg:py-20">
        <div className="relative z-10">
          <Reveal>
            <span className="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-bold text-chore-700 shadow-sm">
              <span aria-hidden="true" className="size-2 rounded-full bg-rainbow-orange" />
              Four Nigerian languages. One family closer.
            </span>

            <h1 className="mt-5 max-w-2xl font-display text-[2.65rem] font-extrabold leading-[1.02] tracking-[-0.035em] text-navy-950 sm:text-6xl lg:text-[4.5rem]">
              Hear your child say it in{' '}
              <span className="text-chore-600">your language.</span>
            </h1>

            <p className="mt-5 max-w-xl text-lg font-medium leading-relaxed text-navy-700 sm:text-xl">
              Joyful five-minute lessons in Yorùbá, Igbo, Hausa and Pidgin—made with
              native voices, living culture, and a family that learns together.
            </p>
          </Reveal>

          <Reveal delay={80}>
            <div className="mt-7 flex flex-col gap-3 sm:flex-row">
              <LinkButton to="/register" size="lg" variant="parent" className="w-full sm:w-auto">
                Start free — no card needed
              </LinkButton>
              <a
                href="#try"
                className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl border-2 border-chore-200 bg-white px-6 font-display font-bold text-chore-700 transition-colors hover:border-chore-400 hover:bg-chore-50 sm:w-auto"
              >
                Try a 60-second lesson
                <Icon name="chevron" className="size-4 rotate-90" />
              </a>
            </div>
            <p className="mt-3 flex items-center gap-2 text-sm font-semibold text-navy-700">
              <span aria-hidden="true" className="text-leaf-600">✓</span>
              Every lesson stays free. Paying only adds convenience.
            </p>
          </Reveal>

          <Reveal delay={120}>
            {/* A small, reversible first interaction that immediately updates
                the greeting on the character artwork and the live lesson. */}
            <fieldset className="mt-7 border-t border-chore-200/80 pt-5">
              <legend className="text-sm font-bold text-navy-800">Choose your language</legend>
              <div className="mt-3 flex flex-wrap gap-2">
                {LANDING_LANGUAGES.map((l, i) => {
                  const active = i === langIndex
                  return (
                    <button
                      key={l.code}
                      type="button"
                      onClick={() => onPick(i)}
                      aria-pressed={active}
                      className={cn(
                        'min-h-11 rounded-full px-4 font-display text-sm font-bold transition-[background-color,color,transform]',
                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-chore-500 focus-visible:ring-offset-2',
                        active
                          ? 'bg-navy-950 text-white'
                          : 'bg-white text-navy-800 hover:-translate-y-0.5 hover:bg-chore-50',
                      )}
                    >
                      {l.name}
                    </button>
                  )
                })}
              </div>
            </fieldset>
          </Reveal>
        </div>

        {/* Iya and Amara are the character anchors for every generated scene. */}
        <Reveal delay={100} className="relative mx-auto w-full max-w-[34rem] lg:max-w-none">
          <Figure
            src="/images/hero-grandmother-child.webp"
            alt="Iya and her granddaughter Amara laughing together while learning on a tablet"
            label="Iya and Amara learning together"
            priority
            className="aspect-[4/5] w-full rounded-[1rem] bg-white shadow-lg"
          />
          <div
            key={language.code}
            data-testid="hero-greeting"
            className="animate-pop-in absolute bottom-4 left-4 max-w-[15rem] rounded-[1rem] bg-white p-4 shadow-lg sm:bottom-6 sm:left-6"
          >
            <p className="text-xs font-bold text-rainbow-orange">
              {language.name}
            </p>
            <p className="mt-0.5 font-display text-xl font-extrabold text-navy-950">
              {language.greeting}
            </p>
            <p className="text-sm font-semibold text-navy-600">“{language.greetingMeaning}”</p>
          </div>
          <div className="absolute right-3 top-3 rounded-full bg-rainbow-orange px-3 py-1.5 font-display text-xs font-extrabold text-white shadow-sm sm:right-5 sm:top-5">
            Made for ages 5+
          </div>
        </Reveal>
      </div>

      <div className="border-y border-chore-100 bg-white/90">
        <ul className="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-8 gap-y-2 px-4 py-4 text-sm font-bold text-navy-700 sm:px-6 lg:px-8">
          <li>Native-speaker audio</li>
          <li>Culture in every course</li>
          <li>Parent-controlled child profiles</li>
          <li>Built for low-bandwidth learning</li>
        </ul>
      </div>
    </section>
  )
}

/* ------------------------------------------------------------------ try it */

function TryItSection({ language }: { language: (typeof LANDING_LANGUAGES)[number] }) {
  return (
    <section id="try" className="scroll-mt-20 bg-[#f7fbff] py-16 sm:py-24">
      <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <Reveal className="text-center">
          <h2 className="font-display text-3xl font-extrabold text-navy-950 sm:text-5xl">
            Try a real lesson. No sign-up.
          </h2>
          <p className="mx-auto mt-3 max-w-xl font-medium text-navy-700">
            Learn three useful words in {language.name}. Your language choice follows you
            from the hero straight into the activity.
          </p>
        </Reveal>
        <Reveal delay={80} className="mt-10">
          <TryItLesson language={language} />
        </Reveal>
      </div>
    </section>
  )
}

/* ---------------------------------------------------------------- heritage */

const HERITAGE_POINTS: { icon: IconName; title: string; body: string }[] = [
  {
    icon: 'sparkles',
    title: 'Tone marks, done properly',
    body: 'Ẹ káàrọ̀ is not "e kaaro". Tone changes meaning, so tone lives in our lessons, our quizzes and our speaking practice — not just our fonts.',
  },
  {
    icon: 'book',
    title: 'Culture is part of the course',
    body: 'Every course carries proverbs, folktales and festivals as required components — not a blog bolted on the side.',
  },
  {
    icon: 'users',
    title: 'Real Nigerian voices',
    body: 'Native speakers record the audio and video, so your child hears the language as it is actually spoken at home.',
  },
]

function HeritageSection() {
  return (
    <section className="bg-white py-16 sm:py-24">
      <div className="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:items-center lg:gap-16 lg:px-8">
        <Reveal>
          <Figure
            src="/images/culture-storytelling.webp"
            alt="Iya telling Amara and two cousins a folktale by lantern light in the family courtyard"
            label="Culture — evening storytelling"
            className="aspect-[5/4] w-full rounded-[1rem] shadow-md"
          />
        </Reveal>
        <Reveal delay={80}>
          <h2 className="font-display text-3xl font-extrabold text-navy-950 sm:text-5xl">
            A language is more than vocabulary.
          </h2>
          <p className="mt-4 text-lg font-medium leading-relaxed text-navy-700">
            Children need to know what a greeting means, when to use it, and why it
            matters. We build every course from the language outward—tones, stories,
            proverbs and the everyday moments where belonging lives.
          </p>
          <ul className="mt-8 flex flex-col gap-6">
            {HERITAGE_POINTS.map((p) => (
              <li key={p.title} className="flex gap-4">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-chore-50 text-chore-700">
                  <Icon name={p.icon} className="size-5" />
                </span>
                <div>
                  <h3 className="font-display text-lg font-bold text-navy-950">{p.title}</h3>
                  <p className="mt-1 text-navy-700">{p.body}</p>
                </div>
              </li>
            ))}
          </ul>
        </Reveal>
      </div>
    </section>
  )
}

/* ------------------------------------------------------------------ family */

/**
 * The moat. Every competitor sells a seat to one learner; the parent is a
 * payer. Here the parent is a participant — and coins physically cannot move
 * without their approval (BR-8). The demo below makes that tangible.
 */
function FamilySection() {
  return (
    <section id="families" className="scroll-mt-20 border-y border-[#ffe1cf] bg-[#fff8f2] py-16 sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Reveal className="mx-auto max-w-2xl text-center">
          <span className="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-sm font-bold text-rainbow-orange shadow-sm">
            For families
          </span>
          <h2 className="mt-4 font-display text-3xl font-extrabold text-navy-950 sm:text-5xl">
            Turn learning into a family ritual.
          </h2>
          <p className="mt-4 text-lg font-medium text-navy-700">
            Follow each child&rsquo;s progress, set meaningful rewards, and celebrate the
            words that finally make it from the lesson into your home.
          </p>
        </Reveal>

        <div className="mt-12 grid gap-8 lg:grid-cols-2 lg:items-center">
          <Reveal>
            <ApprovalDemo />
          </Reveal>
          <Reveal delay={80} className="flex flex-col gap-6">
            {[
              {
                icon: 'users' as IconName,
                title: 'Up to 6 child profiles',
                body: 'Each child gets their own path and progress. Under-13s have no login at all — you operate their profile, protected by a parent PIN.',
              },
              {
                icon: 'wallet' as IconName,
                title: 'A family wallet that teaches',
                body: 'Coins turn practice into pocket money, and pocket money into a habit. Every transaction is logged, and no chore reward is released without you.',
              },
              {
                icon: 'clipboard' as IconName,
                title: 'One review queue',
                body: 'Chores, speaking recordings and assignments land in a single place. Approve, ask for another try, or leave a note — in a couple of minutes.',
              },
            ].map((f) => (
              <div key={f.title} className="flex gap-4">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-chore-100 text-chore-700">
                  <Icon name={f.icon} className="size-5" />
                </span>
                <div>
                  <h3 className="font-display text-lg font-bold text-navy-950">{f.title}</h3>
                  <p className="mt-1 text-navy-700">{f.body}</p>
                </div>
              </div>
            ))}
          </Reveal>
        </div>
      </div>
    </section>
  )
}

/** Miniature of the real parent review queue — approving releases the coins. */
function ApprovalDemo() {
  const [approved, setApproved] = useState(false)

  return (
    <div className="rounded-[1rem] bg-white p-6 shadow-md sm:p-8">
      <div className="flex items-center justify-between">
        <p className="font-display text-sm font-bold text-foreground">Review queue</p>
        <span
          className={cn(
            'rounded-full px-2.5 py-1 text-xs font-bold transition-colors',
            approved ? 'bg-leaf-100 text-leaf-700' : 'bg-chore-100 text-chore-800',
          )}
        >
          {approved ? 'All caught up' : '1 waiting'}
        </span>
      </div>

      <div className="mt-5 rounded-[1rem] bg-[#f7fbff] p-5">
        <div className="flex items-center gap-3">
          <span
            aria-hidden="true"
            className="flex size-11 items-center justify-center rounded-full bg-clay-100 text-xl"
          >
            🧒🏾
          </span>
          <div>
            <p className="font-display font-bold text-foreground">Adaeze finished a chore</p>
            <p className="text-sm text-muted">Tidied her room · photo attached</p>
          </div>
        </div>

        <div className="mt-5 flex items-center justify-between rounded-xl bg-gold-50 px-4 py-3">
          <span className="text-sm font-semibold text-charcoal-800">Reward on approval</span>
          <span className="font-display font-bold text-gold-700">🪙 50 coins</span>
        </div>

        {!approved ? (
          <button
            type="button"
            onClick={() => setApproved(true)}
            className="mt-5 inline-flex h-11 w-full items-center justify-center rounded-xl bg-chore-500 font-display font-bold text-white transition-colors hover:bg-chore-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
          >
            Approve &amp; release coins
          </button>
        ) : (
          <div className="mt-5 animate-pop-in rounded-xl border border-leaf-200 bg-leaf-50 p-4 text-center">
            <p className="font-display font-bold text-leaf-700">🎉 50 coins released</p>
            <p className="mt-1 text-sm text-leaf-700/80">
              Adaeze can now spend them — because you said so.
            </p>
            <button
              type="button"
              onClick={() => setApproved(false)}
              className="mt-3 min-h-11 text-sm font-semibold text-muted underline hover:text-foreground"
            >
              Replay
            </button>
          </div>
        )}
      </div>

      <p className="mt-4 text-center text-xs text-subtle">
        A working preview of the real review queue.
      </p>
    </div>
  )
}

/* ----------------------------------------------------------- never locked */

function NeverLockedBand() {
  return (
    <section className="bg-chore-700 py-14 sm:py-16">
      <div className="mx-auto max-w-4xl px-4 text-center sm:px-6">
        <Reveal>
          <h2 className="font-display text-3xl font-extrabold text-white sm:text-4xl">
            The language is never the premium feature.
          </h2>
          <p className="mx-auto mt-4 max-w-2xl text-lg font-medium leading-relaxed text-chore-50">
            Every lesson stays open on Free. Hearts invite a quick practice, never a
            countdown lock. Paid plans remove ads and add offline learning; they do not
            sell your family&rsquo;s language back to you.
          </p>
        </Reveal>
      </div>
    </section>
  )
}

/* ----------------------------------------------------------------- schools */

const SEAT_BANDS = [
  { size: '1–99 students', registration: '₦50,000', perStudent: '₦7,000' },
  { size: '100–249 students', registration: '₦100,000', perStudent: '₦6,000' },
  { size: '250–500 students', registration: '₦150,000', perStudent: '₦5,500' },
  { size: 'Above 500 students', registration: '₦200,000', perStudent: '₦5,000' },
]

function SchoolsSection() {
  return (
    <section id="schools" className="scroll-mt-20 bg-white py-16 sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid gap-12 lg:grid-cols-2 lg:items-center lg:gap-16">
          <Reveal>
            <span className="inline-flex items-center gap-2 rounded-full bg-chore-50 px-3 py-1 text-sm font-bold text-chore-700">
              For schools
            </span>
            <h2 className="mt-4 font-display text-3xl font-extrabold text-navy-950 sm:text-5xl">
              The policy says teach it. The materials never arrived.
            </h2>
            <p className="mt-4 text-lg font-medium leading-relaxed text-navy-700">
              Nigeria&rsquo;s National Language Policy supports mother-tongue instruction at
              basic level, but implementation varies widely. Schools are still often left
              to find teachers, a curriculum and materials on their own.
            </p>
            <p className="mt-4 text-lg font-medium leading-relaxed text-navy-700">
              MAHADUM.360 gives a school a structured curriculum across four languages,
              rosters imported from a spreadsheet, and per-student progress a head teacher
              can actually inspect — without hiring a specialist for every language.
            </p>
            <ul className="mt-8 grid gap-3 sm:grid-cols-2">
              {[
                'CSV roster import',
                'Per-student analytics',
                'Class assignments & grading',
                'Seat invoices & receipts',
                'Language & Culture club',
                'National competition entry',
              ].map((item) => (
                <li key={item} className="flex items-center gap-2 text-sm font-bold text-navy-800">
                  <span aria-hidden="true" className="font-bold text-leaf-600">
                    ✓
                  </span>
                  {item}
                </li>
              ))}
            </ul>
            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <LinkButton to="/pricing" size="lg" variant="premium" className="w-full sm:w-auto">
                Get a quote for your school
              </LinkButton>
            </div>
          </Reveal>

          <Reveal delay={80}>
            <Figure
              src="/images/school-classroom.webp"
              alt="Amara and two classmates practising a language lesson on a shared tablet with their teacher"
              label="Schools — classroom"
              className="aspect-[5/4] w-full rounded-[1rem] shadow-md"
            />
            <div className="mt-6 overflow-x-auto rounded-[1rem] border border-chore-100 bg-white">
              <table className="w-full text-left text-sm">
                <caption className="sr-only">
                  School pricing bands for a nine-month academic year
                </caption>
                <thead className="bg-surface-muted">
                  <tr>
                    <th scope="col" className="px-4 py-3 font-display font-bold text-foreground">
                      School size
                    </th>
                    <th scope="col" className="px-4 py-3 font-display font-bold text-foreground">
                      Registration
                    </th>
                    <th scope="col" className="px-4 py-3 font-display font-bold text-foreground">
                      Per student
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {SEAT_BANDS.map((b) => (
                    <tr key={b.size} className="border-t border-border">
                      <td className="px-4 py-3 text-muted">{b.size}</td>
                      <td className="px-4 py-3 font-semibold text-foreground">{b.registration}</td>
                      <td className="px-4 py-3 font-semibold text-foreground">{b.perStudent}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="mt-3 text-xs text-subtle">
              Annual registration plus a per-student rate, for a nine-month academic year.
            </p>
          </Reveal>
        </div>
      </div>
    </section>
  )
}

/* ----------------------------------------------------------------- pricing */

const PLANS = [
  {
    name: 'Free',
    price: '₦0',
    cadence: 'forever',
    blurb: 'Full learning, forever. Ad-supported.',
    features: ['Every lesson, unlocked', 'Speaking practice & quizzes', 'Streaks, XP and badges'],
    cta: 'Start free',
    variant: 'outline' as const,
  },
  {
    name: 'Individual',
    price: '₦3,000',
    cadence: 'per month',
    blurb: 'About ₦100/day on airtime.',
    features: ['Ad-free learning', 'Offline lessons', 'Unlimited hearts', '1 learner profile'],
    cta: 'Choose Individual',
    variant: 'outline' as const,
  },
  {
    name: 'Family',
    price: '₦6,000',
    cadence: 'per month',
    blurb: 'About ₦200/day on airtime.',
    features: [
      'Everything in Individual',
      'Up to 6 learner profiles',
      'Family dashboard & chores',
      'Coins, wallet & approvals',
    ],
    cta: 'Choose Family',
    variant: 'parent' as const,
    highlight: true,
  },
]

function PricingPreview() {
  return (
    <section className="border-y border-chore-100 bg-[#f7fbff] py-16 sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Reveal className="mx-auto max-w-2xl text-center">
          <h2 className="font-display text-3xl font-extrabold text-navy-950 sm:text-5xl">
            Honest pricing, in naira
          </h2>
          <p className="mt-3 text-muted">
            Pay by card or straight from your airtime. Cancel any time by texting STOP to
            3600.
          </p>
        </Reveal>

        <div className="mt-12 grid gap-5 md:grid-cols-3">
          {PLANS.map((p, i) => (
            <Reveal key={p.name} delay={i * 70}>
              <div
                className={cn(
                  'flex h-full flex-col gap-5 rounded-[1rem] border bg-white p-7',
                  p.highlight ? 'border-chore-500 ring-2 ring-chore-200' : 'border-chore-100',
                )}
              >
                <div>
                  <div className="flex items-center gap-2">
                    <h3 className="font-display text-lg font-bold text-foreground">{p.name}</h3>
                    {p.highlight && (
                      <span className="rounded-full bg-[#fff0e6] px-2 py-0.5 text-xs font-bold text-[#b63c00]">
                        Most families
                      </span>
                    )}
                  </div>
                  <p className="mt-3 font-display text-3xl font-extrabold text-foreground">
                    {p.price}
                    <span className="ml-1 text-sm font-semibold text-muted">{p.cadence}</span>
                  </p>
                  <p className="mt-1 text-sm text-muted">{p.blurb}</p>
                </div>
                <ul className="flex flex-1 flex-col gap-2">
                  {p.features.map((f) => (
                    <li key={f} className="flex gap-2 text-sm text-foreground">
                      <span aria-hidden="true" className="font-bold text-leaf-600">
                        ✓
                      </span>
                      {f}
                    </li>
                  ))}
                </ul>
                <LinkButton to="/register" variant={p.variant} fullWidth>
                  {p.cta}
                </LinkButton>
              </div>
            </Reveal>
          ))}
        </div>

        <Reveal className="mt-8 text-center">
          <Link to="/pricing" className="inline-flex min-h-11 items-center font-display font-bold text-primary hover:underline">
            See annual pricing and school rates →
          </Link>
        </Reveal>
      </div>
    </section>
  )
}

/* ------------------------------------------------------------------- trust */

const TRUST = [
  {
    icon: 'shield' as IconName,
    title: 'Children have no login',
    body: 'An under-13 learner has a profile, not an account. There is no password to leak and no inbox to reach them through.',
  },
  {
    icon: 'users' as IconName,
    title: 'Consent is recorded',
    body: 'Creating a child profile captures a verifiable parental consent record, in line with COPPA and Nigeria’s NDPA.',
  },
  {
    icon: 'card' as IconName,
    title: 'We never hold card details',
    body: 'Payments run through licensed gateways with tokenisation. Card numbers never touch our servers.',
  },
]

function TrustSection() {
  return (
    <section className="bg-white py-16 sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Reveal className="mx-auto max-w-2xl text-center">
          <h2 className="font-display text-3xl font-extrabold text-navy-950 sm:text-5xl">
            Built for children, and treated that way
          </h2>
          <p className="mt-3 text-muted">
            Safety here is structural, not a policy page. It is how the accounts are
            designed.
          </p>
        </Reveal>
        <div className="mt-12 overflow-hidden rounded-[1rem] border border-chore-100 md:grid md:grid-cols-3 md:divide-x md:divide-chore-100">
          {TRUST.map((t, i) => (
            <Reveal key={t.title} delay={i * 70}>
              <div className="flex h-full flex-col gap-3 border-b border-chore-100 bg-white p-6 last:border-b-0 md:border-b-0">
                <span className="flex size-11 items-center justify-center rounded-xl bg-primary-soft text-primary">
                  <Icon name={t.icon} className="size-5" />
                </span>
                <h3 className="font-display text-lg font-bold text-foreground">{t.title}</h3>
                <p className="text-sm text-muted">{t.body}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  )
}

/* --------------------------------------------------------------------- faq */

const FAQS = [
  {
    q: 'Is it really free?',
    a: 'Yes — and not in the way other apps mean it. Every lesson is open on the free plan, supported by ads between lessons. Paying removes the ads and adds offline downloads, unlimited hearts and the family dashboard. It never unlocks content.',
  },
  {
    q: 'My child was born abroad and only speaks English. Is this too late?',
    a: 'No. The courses start from absolute zero, with a placement check so a learner begins where they actually are. Most diaspora children start at the beginning, and hearing the language at home alongside the lessons is what makes it stick.',
  },
  {
    q: 'Do I need a fast connection?',
    a: 'No. Video starts at 360p by default and can drop to 240p on a weak link. Lesson screens are designed around a sub-three-second 3G performance budget, and Premium adds offline downloads for journeys with no signal at all.',
  },
  {
    q: 'Can I pay with airtime instead of a card?',
    a: 'Yes. You can subscribe with daily airtime billing on participating Nigerian networks — roughly ₦100 a day for Individual — or pay by card or transfer. Cancel any time by texting STOP to 3600.',
  },
  {
    q: 'How do the coins work?',
    a: 'Children earn coins from lessons and from chores you set. Chore coins are held until you approve the submission — a photo, a recording or a simple tick. Nothing is ever released automatically.',
  },
  {
    q: 'We are a school. How do we start?',
    a: 'Request a quote and we will size it to your roll. Schools get an annual registration plus a per-student rate that steps down as the school grows, covering a nine-month academic year, including the Language & Culture club and entry to the national competition.',
  },
]

function FaqSection() {
  const [open, setOpen] = useState<number | null>(0)

  return (
    <section className="border-t border-chore-100 bg-[#f7fbff] py-16 sm:py-24">
      <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <Reveal className="text-center">
          <h2 className="font-display text-3xl font-extrabold text-navy-950 sm:text-5xl">
            Questions parents actually ask
          </h2>
        </Reveal>

        {/* Standard WAI-ARIA accordion (heading + button + region) rather than
            a <dl>: the Reveal wrapper puts a <div> between <dl> and its
            <dt>/<dd>, which breaks the definition-list contract. */}
        <div className="mt-10 flex flex-col gap-3">
          {FAQS.map((f, i) => {
            const isOpen = open === i
            return (
              <Reveal key={f.q} delay={i * 40}>
                <div className="overflow-hidden rounded-[1rem] border border-chore-100 bg-white">
                  <h3 className="font-display">
                    <button
                      type="button"
                      onClick={() => setOpen(isOpen ? null : i)}
                      aria-expanded={isOpen}
                      aria-controls={`faq-panel-${i}`}
                      id={`faq-trigger-${i}`}
                      className="flex min-h-14 w-full items-center justify-between gap-4 px-5 py-4 text-left font-display font-bold text-foreground transition-colors hover:bg-surface-muted/60"
                    >
                      {f.q}
                      <Icon
                        name="chevron"
                        className={cn(
                          'size-5 shrink-0 text-muted transition-transform duration-200',
                          isOpen && 'rotate-180',
                        )}
                      />
                    </button>
                  </h3>
                  {isOpen && (
                    <div
                      id={`faq-panel-${i}`}
                      role="region"
                      aria-labelledby={`faq-trigger-${i}`}
                      className="animate-step-in px-5 pb-5 text-muted"
                    >
                      {f.a}
                    </div>
                  )}
                </div>
              </Reveal>
            )
          })}
        </div>
      </div>
    </section>
  )
}

/* ------------------------------------------------------------- closing cta */

function ClosingCta() {
  return (
    <section className="bg-chore-700 py-20 sm:py-28">
      <div className="mx-auto max-w-3xl px-4 text-center sm:px-6">
        <Reveal>
          <h2 className="font-display text-3xl font-extrabold leading-tight text-white sm:text-5xl">
            The next family conversation could be in{' '}
            <span className="text-[#ffb277]">your language</span>.
          </h2>
          <p className="mx-auto mt-5 max-w-xl text-lg text-navy-100">
            Free to begin. No card required. Your first lesson takes about five minutes.
          </p>
          <div className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <LinkButton to="/register" size="lg" variant="accent">
              Create your free account
            </LinkButton>
            <LinkButton
              to="/pricing"
              size="lg"
              variant="ghost"
              className="text-white hover:bg-white/10"
            >
              Talk to us about schools
            </LinkButton>
          </div>
        </Reveal>
      </div>
    </section>
  )
}

/* ------------------------------------------------------------------ footer */

function SiteFooter() {
  return (
    <footer className="border-t border-chore-100 bg-white">
      <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="flex flex-col gap-8 sm:flex-row sm:items-start sm:justify-between">
          <div className="max-w-xs">
            <Logo className="h-8" />
            <p className="mt-3 text-sm font-medium text-muted">{TAGLINE}</p>
          </div>
          <nav aria-label="Footer" className="grid grid-cols-2 gap-x-12 gap-y-2 sm:grid-cols-2">
            <a
              href="#try"
              className="flex min-h-11 items-center text-sm font-semibold text-muted hover:text-foreground"
            >
              Try a lesson
            </a>
            <a
              href="#families"
              className="flex min-h-11 items-center text-sm font-semibold text-muted hover:text-foreground"
            >
              For families
            </a>
            <a
              href="#schools"
              className="flex min-h-11 items-center text-sm font-semibold text-muted hover:text-foreground"
            >
              For schools
            </a>
            <Link
              to="/pricing"
              className="flex min-h-11 items-center text-sm font-semibold text-muted hover:text-foreground"
            >
              Pricing
            </Link>
            <Link
              to="/login"
              className="flex min-h-11 items-center text-sm font-semibold text-muted hover:text-foreground"
            >
              Sign in
            </Link>
            <Link
              to="/register"
              className="flex min-h-11 items-center text-sm font-semibold text-muted hover:text-foreground"
            >
              Get started
            </Link>
          </nav>
        </div>
        <div className="mt-10 flex flex-col gap-2 border-t border-border pt-6 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-xs text-subtle">
            © {WORDMARK} · {new Date().getFullYear()} · Lagos, Nigeria
          </p>
          <p className="text-xs text-subtle">Yorùbá · Igbo · Hausa · Pidgin</p>
        </div>
      </div>
    </footer>
  )
}
