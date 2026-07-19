import { useState, type CSSProperties } from 'react'
import { Link } from 'react-router-dom'
import { Figure } from '@/components/landing/Figure'
import { LANDING_LANGUAGES } from '@/components/landing/languages'
import { Reveal } from '@/components/landing/Reveal'
import { LinkButton } from '@/components/ui'
import { cn } from '@/lib/cn'
import { CheckLine, ConceptFooter, ConceptHeader, LanguageButtons } from './LandingVariantsPage'

function Waveform({ inverse = false }: { inverse?: boolean }) {
  return (
    <div aria-hidden="true" className="flex h-12 items-center justify-center gap-1">
      {Array.from({ length: 20 }, (_, index) => (
        <span
          key={index}
          className={cn('motion-wave-bar w-1 rounded-full', inverse ? 'bg-white' : 'bg-chore-600')}
          style={{
            '--bar-i': index,
            height: `${18 + ((index * 17) % 30)}px`,
          } as CSSProperties}
        />
      ))}
    </div>
  )
}

const FAMILY_MOMENTS = [
  {
    id: 'breakfast',
    label: 'Breakfast',
    title: 'Begin with a greeting they can use today.',
    body: 'The first lesson is short enough for breakfast and useful enough for the next family call.',
    response: 'Iya answers—and the word becomes part of the morning.',
  },
  {
    id: 'practice',
    label: 'After school',
    title: 'Hear it, repeat it, then make it yours.',
    body: 'Listening, speaking and a tiny quiz work together so recognition turns into confident recall.',
    response: 'A parent can listen later and leave encouragement.',
  },
  {
    id: 'story',
    label: 'Story time',
    title: 'Meet the word again inside a living story.',
    body: 'Folktales, songs and cultural situations explain when a phrase belongs—not only what it translates to.',
    response: 'Meaning grows because the child understands the moment.',
  },
  {
    id: 'call',
    label: 'Family call',
    title: 'Let the lesson arrive in a real conversation.',
    body: 'The child has one small, achievable mission: greet, answer and surprise someone they love.',
    response: 'Practice becomes connection, not another score on a screen.',
  },
]

/* -------------------------------------------------------------------------- */
/* V4 — living language / family journey                                      */
/* -------------------------------------------------------------------------- */

export function LandingV4Page() {
  const [languageIndex, setLanguageIndex] = useState(0)
  const [momentIndex, setMomentIndex] = useState(0)
  const [reviewed, setReviewed] = useState(false)
  const language = LANDING_LANGUAGES[languageIndex]
  const moment = FAMILY_MOMENTS[momentIndex]

  return (
    <div className="variant-page variant-v4 min-h-screen bg-white text-navy-950">
      <a href="#v4-main" className="variant-skip-link">Skip to main content</a>
      <ConceptHeader tone="navy" />
      <main id="v4-main">
        <section className="motion-hero relative min-h-[calc(100svh-8rem)] overflow-hidden bg-[#083f9f] sm:min-h-[calc(100svh-4.75rem)]">
          <img
            src="/images/landing-v4-kitchen-hero.webp"
            alt="Amara and Iya sharing language while preparing puff-puff together"
            className="motion-hero-media absolute inset-0 size-full object-cover object-[64%_center]"
          />
          <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(4,47,125,0.98)_0%,rgba(4,47,125,0.88)_100%)] lg:bg-[linear-gradient(90deg,rgba(4,47,125,0.98)_0%,rgba(4,47,125,0.92)_35%,rgba(4,47,125,0.08)_61%)]" />
          <div className="relative z-10 mx-auto flex min-h-[calc(100svh-8rem)] max-w-[90rem] items-center px-4 py-16 sm:min-h-[calc(100svh-4.75rem)] sm:px-6 lg:px-10">
            <div className="motion-hero-copy max-w-[43rem] text-white">
              <p className="font-display text-xl font-extrabold text-[#ffd36a]">Language belongs in the life you already share.</p>
              <h1 className="mt-4 max-w-[11ch] font-display text-[clamp(3.1rem,6.5vw,6rem)] font-extrabold leading-[0.95] tracking-[-0.035em]">
                Hear it at breakfast. Say it by bedtime.
              </h1>
              <p className="mt-6 max-w-xl text-lg font-semibold leading-relaxed text-white/85 sm:text-xl">
                Mahadum turns five-minute lessons into greetings, stories and little family moments a child can actually use.
              </p>
              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="#v4-journey" className="motion-press inline-flex h-12 items-center justify-center rounded-xl bg-[#ff650f] px-6 font-display font-extrabold text-white hover:bg-[#d94d00]">Follow one word home</a>
                <LinkButton to="/register" size="lg" variant="ghost" className="border-2 border-white/55 bg-white/10 text-white hover:bg-white/20">Start free</LinkButton>
              </div>
              <p className="mt-4 text-sm font-bold text-white/72">No card · Complete learning on Free · Built for families everywhere</p>
            </div>
          </div>
          <div aria-hidden="true" className="pointer-events-none absolute bottom-10 right-[6%] z-10 hidden lg:block">
            <span className="motion-float block rounded-full bg-white px-5 py-3 font-display text-xl font-extrabold text-[#083f9f] shadow-md">{language.greeting}</span>
            <span className="motion-float ml-24 mt-3 block rounded-full bg-[#ffd44a] px-4 py-2 font-display font-bold text-navy-950" style={{ '--float-i': 1 } as CSSProperties}>I heard you!</span>
          </div>
        </section>

        <section id="v4-journey" className="bg-white py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <Reveal className="max-w-4xl">
              <h2 className="font-display text-4xl font-extrabold leading-tight sm:text-6xl">Watch one word travel through the day.</h2>
              <p className="mt-4 max-w-2xl text-lg font-semibold text-navy-700">Each step has a job: introduce, practise, add meaning, then create a reason to speak.</p>
            </Reveal>
            <div className="mt-12 grid gap-8 lg:grid-cols-[0.35fr_0.65fr]">
              <div role="tablist" aria-label="A word through the day" className="flex gap-2 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible">
                {FAMILY_MOMENTS.map((item, index) => (
                  <button
                    key={item.id}
                    type="button"
                    role="tab"
                    aria-selected={momentIndex === index}
                    aria-controls="family-moment-panel"
                    onClick={() => setMomentIndex(index)}
                    className={cn(
                      'motion-press min-h-14 shrink-0 rounded-xl px-5 text-left font-display text-lg font-extrabold lg:w-full',
                      momentIndex === index ? 'bg-chore-700 text-white' : 'bg-chore-50 text-navy-800 hover:bg-chore-100',
                    )}
                  >
                    {item.label}
                  </button>
                ))}
              </div>
              <div id="family-moment-panel" role="tabpanel" key={moment.id} className="motion-panel-swap overflow-hidden rounded-[1rem] bg-navy-950 text-white">
                <div className="grid lg:grid-cols-[0.58fr_0.42fr]">
                  <div className="p-7 sm:p-10">
                    <p className="font-display text-lg font-extrabold text-[#ffb277]">{moment.label}</p>
                    <h3 className="mt-3 font-display text-3xl font-extrabold leading-tight sm:text-4xl">{moment.title}</h3>
                    <p className="mt-5 text-lg font-semibold leading-relaxed text-white/75">{moment.body}</p>
                    <p className="mt-7 border-t border-white/15 pt-5 font-display text-xl font-bold text-[#ffd36a]">{moment.response}</p>
                  </div>
                  <div className="flex flex-col justify-center bg-[#0f63bc] p-7 text-center sm:p-10">
                    <p className="text-sm font-bold text-white/70">Today’s phrase</p>
                    <p className="motion-word-pop mt-3 font-display text-4xl font-extrabold">{language.greeting}</p>
                    <p className="mt-2 font-bold text-white/75">{language.greetingMeaning}</p>
                    <div className="mt-6"><Waveform inverse /></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="bg-[#eef6ff] py-20 sm:py-28">
          <div className="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:px-8">
            <Reveal>
              <Figure
                src="/images/landing-v4-speaking-review.webp"
                alt="Amara practising a spoken phrase while her father encourages and approves her attempt"
                className="aspect-[4/3] rounded-[1rem]"
              />
            </Reveal>
            <Reveal delay={80}>
              <p className="font-display text-xl font-extrabold text-rainbow-orange">Speaking needs a listener.</p>
              <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight sm:text-5xl">Practice comes back with a human response.</h2>
              <p className="mt-5 text-lg font-semibold leading-relaxed text-navy-700">Children record a short attempt. A parent or teacher can listen when they have time, respond with encouragement and decide what needs another try.</p>
              <ol className="mt-8 divide-y divide-chore-200 border-y border-chore-200">
                {['Listen to a native voice', 'Record a short reply', 'Receive adult feedback', 'Use it in the next family moment'].map((step, index) => (
                  <li key={step} className="flex items-center gap-4 py-4 font-display text-lg font-extrabold">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-chore-700 text-white">{index + 1}</span>
                    {step}
                  </li>
                ))}
              </ol>
            </Reveal>
          </div>
        </section>

        <section className="bg-[#ff650f] py-20 text-white sm:py-28">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <Reveal className="max-w-4xl">
              <h2 className="font-display text-4xl font-extrabold leading-tight sm:text-6xl">Not a phrasebook. A complete learning path.</h2>
              <p className="mt-5 max-w-2xl text-lg font-bold leading-relaxed text-white/88">Change the language and see how translation, pronunciation and context stay connected.</p>
            </Reveal>
            <div className="mt-10 grid gap-8 lg:grid-cols-[0.42fr_0.58fr]">
              <LanguageButtons selected={languageIndex} onSelect={setLanguageIndex} inverse />
              <div key={language.code} className="motion-panel-swap rounded-[1rem] bg-white p-7 text-navy-950 sm:p-10">
                <p className="font-display text-4xl font-extrabold text-chore-700">{language.greeting}</p>
                <p className="mt-2 text-lg font-bold text-navy-600">{language.endonym} · {language.greetingMeaning}</p>
                <div className="mt-7 grid gap-5 sm:grid-cols-2">
                  <div><p className="font-display font-extrabold">Hear the sound</p><p className="mt-2 font-semibold text-navy-700">Native-speaker audio introduces rhythm and pronunciation before recall.</p></div>
                  <div><p className="font-display font-extrabold">Understand the moment</p><p className="mt-2 font-semibold text-navy-700">Notes explain how age, respect, time and setting shape what is said.</p></div>
                </div>
                <p className="mt-7 border-t border-chore-100 pt-5 font-semibold text-navy-700">{language.quiz[0].note}</p>
              </div>
            </div>
          </div>
        </section>

        <section className="bg-white py-20 sm:py-28">
          <div className="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8">
            <Reveal>
              <p className="font-display text-xl font-extrabold text-chore-700">Parents participate without hovering.</p>
              <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight sm:text-5xl">One calm place for progress, speaking and rewards.</h2>
              <p className="mt-5 text-lg font-semibold leading-relaxed text-navy-700">Under-13 learners use profiles instead of managing logins. Grown-ups keep consent, payments and coin approvals in the adult view.</p>
              <ul className="mt-7 space-y-3">
                <CheckLine>Listen to speaking submissions</CheckLine>
                <CheckLine>See lessons, words and weekly progress</CheckLine>
                <CheckLine>Approve chores before coins are released</CheckLine>
                <CheckLine>Use a parent PIN for adult-only actions</CheckLine>
              </ul>
            </Reveal>
            <Reveal delay={80}>
              <div className="rounded-[1rem] bg-navy-950 p-7 text-white sm:p-9">
                <div className="flex items-center justify-between gap-4">
                  <div><p className="font-display text-xl font-extrabold">Amara’s speaking review</p><p className="mt-1 text-sm font-semibold text-white/65">Morning greeting · 8 seconds</p></div>
                  <span className={cn('rounded-full px-3 py-1 text-xs font-bold', reviewed ? 'motion-confirm bg-leaf-500 text-navy-950' : 'bg-white/12 text-white')}>{reviewed ? 'Encouragement sent' : 'Ready to hear'}</span>
                </div>
                <div className="mt-7 rounded-xl bg-white/8 px-4 py-5"><Waveform inverse /></div>
                <button type="button" onClick={() => setReviewed((value) => !value)} className="motion-press mt-6 min-h-12 w-full rounded-xl bg-[#ff650f] px-5 font-display font-extrabold hover:bg-[#df5100]">
                  {reviewed ? 'Replay response' : 'Listen and encourage'}
                </button>
              </div>
            </Reveal>
          </div>
        </section>

        <section className="bg-[#061a3c] py-20 text-white sm:py-28">
          <div className="mx-auto grid max-w-7xl gap-0 overflow-hidden px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            <Reveal className="bg-[#0f63bc] p-8 sm:p-12">
              <p className="font-display text-3xl font-extrabold">Weak connection? The lesson keeps moving.</p>
              <p className="mt-4 text-lg font-semibold leading-relaxed text-white/78">Lightweight activities, downloadable lessons and offline convenience respect families whose data and signal are not unlimited.</p>
            </Reveal>
            <Reveal delay={80} className="bg-white p-8 text-navy-950 sm:p-12">
              <p className="font-display text-3xl font-extrabold">Free means the whole language.</p>
              <p className="mt-4 text-lg font-semibold leading-relaxed text-navy-700">Every learning lesson stays available on Free. Paid plans remove ads and add offline and family conveniences—not access to knowledge.</p>
            </Reveal>
          </div>
        </section>

        <section className="bg-[#ffd44a] py-20 text-center text-navy-950 sm:py-24">
          <Reveal className="mx-auto max-w-4xl px-4 sm:px-6">
            <h2 className="font-display text-5xl font-extrabold leading-[1.02] sm:text-7xl">Start with one word they can use tonight.</h2>
            <p className="mx-auto mt-5 max-w-2xl text-lg font-bold">Choose Yorùbá, Igbo, Hausa or Nigerian Pidgin. No card required.</p>
            <LinkButton to="/register" size="lg" variant="parent" className="mt-8">Create your family account</LinkButton>
          </Reveal>
        </section>
      </main>
      <ConceptFooter tone="navy" />
    </div>
  )
}

const ECOSYSTEM_VIEWS = [
  {
    id: 'learner',
    label: 'Learner',
    heading: 'A playful path that never locks the next lesson.',
    body: 'Five-minute listening, speaking, quiz and culture activities build toward confident use.',
    features: ['Complete course on Free', 'XP, badges and leagues', 'Low-bandwidth activities'],
  },
  {
    id: 'family',
    label: 'Family',
    heading: 'Progress becomes something the household can hear.',
    body: 'Parents manage learner profiles, review speech and approve rewards while relatives join the reason to speak.',
    features: ['Under-13 profiles', 'Speaking review', 'Approval-gated coins'],
  },
  {
    id: 'teacher',
    label: 'Teacher',
    heading: 'The next teaching decision is visible.',
    body: 'Assignments, submissions, quiz accuracy and learner-level progress show who is ready and who needs support.',
    features: ['Class assignments', 'Grading and feedback', 'Student analytics'],
  },
  {
    id: 'school',
    label: 'School',
    heading: 'Language learning has an operational home.',
    body: 'Rosters, seats, classes, reporting, invoices, clubs and competitions live in one tenant-safe workspace.',
    features: ['CSV roster import', 'Seat and class management', 'Reports and invoices'],
  },
]

/* -------------------------------------------------------------------------- */
/* V5 — complete learning ecosystem / school leadership                       */
/* -------------------------------------------------------------------------- */

export function LandingV5Page() {
  const [viewIndex, setViewIndex] = useState(0)
  const [languageIndex, setLanguageIndex] = useState(0)
  const view = ECOSYSTEM_VIEWS[viewIndex]
  const language = LANDING_LANGUAGES[languageIndex]

  return (
    <div className="variant-page variant-v5 min-h-screen bg-white text-navy-950">
      <a href="#v5-main" className="variant-skip-link">Skip to main content</a>
      <ConceptHeader tone="navy" />
      <main id="v5-main">
        <section className="motion-hero relative min-h-[calc(100svh-8rem)] overflow-hidden bg-[#061a3c] sm:min-h-[calc(100svh-4.75rem)]">
          <img
            src="/images/landing-v5-festival-hero.webp"
            alt="Amara confidently leading a language performance while classmates, teachers and family respond"
            className="motion-hero-pan absolute inset-0 size-full object-cover object-[63%_center]"
          />
          <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(6,26,60,0.99)_0%,rgba(6,26,60,0.82)_100%)] lg:bg-[linear-gradient(90deg,rgba(6,26,60,0.99)_0%,rgba(6,26,60,0.92)_35%,rgba(6,26,60,0.12)_61%)]" />
          <div className="relative z-10 mx-auto flex min-h-[calc(100svh-8rem)] max-w-[90rem] items-center px-4 py-16 sm:min-h-[calc(100svh-4.75rem)] sm:px-6 lg:px-10">
            <div className="motion-hero-copy max-w-[44rem] text-white">
              <p className="font-display text-xl font-extrabold text-[#ffb277]">The platform behind every confident voice.</p>
              <h1 className="mt-4 max-w-[11ch] font-display text-[clamp(3.1rem,6.4vw,5.9rem)] font-extrabold leading-[0.96] tracking-[-0.035em]">
                Build the place where a language keeps growing.
              </h1>
              <p className="mt-6 max-w-xl text-lg font-semibold leading-relaxed text-white/82 sm:text-xl">One curriculum connects the child learning, the family listening, the teacher guiding and the school making it sustainable.</p>
              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <LinkButton to="/register" size="lg" variant="accent">Start a learning circle</LinkButton>
                <a href="#v5-system" className="motion-press inline-flex h-12 items-center justify-center rounded-xl border-2 border-white/45 bg-white/8 px-6 font-display font-bold text-white hover:bg-white/15">Explore the system</a>
              </div>
              <ul className="mt-8 grid max-w-xl gap-3 sm:grid-cols-2">
                <CheckLine inverse>Families and schools in one platform</CheckLine>
                <CheckLine inverse>Complete learning stays free</CheckLine>
                <CheckLine inverse>Child-safe roles and approvals</CheckLine>
                <CheckLine inverse>Clubs, competitions and culture</CheckLine>
              </ul>
            </div>
          </div>
        </section>

        <div className="motion-marquee border-y border-chore-200 bg-[#eef6ff] py-5" aria-label="Platform capabilities">
          <div className="motion-marquee-track font-display text-lg font-extrabold text-chore-800">
            {[0, 1].map((copy) => (
              <div key={copy} aria-hidden={copy === 1 ? 'true' : undefined} className="flex shrink-0 gap-10 pr-10">
                {['LESSONS', 'NATIVE VOICES', 'FAMILY PROFILES', 'ASSIGNMENTS', 'SPEAKING REVIEW', 'ROSTERS', 'REPORTS', 'CULTURE CLUBS'].map((item) => <span key={item} className="whitespace-nowrap">{item} <span className="ml-10 text-rainbow-orange">●</span></span>)}
              </div>
            ))}
          </div>
        </div>

        <section id="v5-system" className="bg-white py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <Reveal className="max-w-4xl">
              <h2 className="font-display text-4xl font-extrabold leading-tight sm:text-6xl">One learner, surrounded by the right people.</h2>
              <p className="mt-4 max-w-2xl text-lg font-semibold text-navy-700">Choose a role to see what moves through the same learning journey.</p>
            </Reveal>
            <div className="mt-12 grid gap-8 lg:grid-cols-[0.38fr_0.62fr]">
              <div role="tablist" aria-label="Learning ecosystem roles" className="grid grid-cols-2 gap-2 lg:grid-cols-1">
                {ECOSYSTEM_VIEWS.map((item, index) => (
                  <button
                    key={item.id}
                    type="button"
                    role="tab"
                    aria-selected={viewIndex === index}
                    aria-controls="ecosystem-panel"
                    onClick={() => setViewIndex(index)}
                    className={cn('motion-press min-h-16 rounded-xl px-5 text-left font-display text-lg font-extrabold', viewIndex === index ? 'bg-navy-950 text-white' : 'bg-chore-50 text-navy-800 hover:bg-chore-100')}
                  >
                    <span className="block text-sm opacity-65">0{index + 1}</span>{item.label}
                  </button>
                ))}
              </div>
              <div id="ecosystem-panel" role="tabpanel" key={view.id} className="motion-panel-swap rounded-[1rem] bg-[#0f63bc] p-7 text-white sm:p-10">
                <p className="font-display text-lg font-extrabold text-[#ffd36a]">{view.label} view</p>
                <h3 className="mt-3 max-w-2xl font-display text-3xl font-extrabold leading-tight sm:text-5xl">{view.heading}</h3>
                <p className="mt-5 max-w-2xl text-lg font-semibold leading-relaxed text-white/82">{view.body}</p>
                <ul className="mt-8 grid gap-3 sm:grid-cols-3">{view.features.map((feature) => <CheckLine key={feature} inverse>{feature}</CheckLine>)}</ul>
              </div>
            </div>
          </div>
        </section>

        <section className="bg-[#061a3c] py-20 text-white sm:py-28">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <Reveal className="max-w-4xl">
              <p className="font-display text-xl font-extrabold text-[#ffb277]">One lesson, five handoffs</p>
              <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight sm:text-6xl">From curriculum to confident use—without losing the thread.</h2>
            </Reveal>
            <div className="mt-14 overflow-x-auto pb-3">
              <div className="relative min-w-[56rem]">
                <div aria-hidden="true" className="absolute left-7 right-7 top-6 h-1 overflow-hidden bg-white/15"><span className="motion-network-signal absolute inset-0" /></div>
                <ol className="relative grid grid-cols-5 gap-5">
                {[
                  ['Author', 'Builds the lesson and cultural context.'],
                  ['Teacher', 'Assigns the right level to a class.'],
                  ['Learner', 'Listens, speaks and completes it.'],
                  ['Adult', 'Reviews the attempt and responds.'],
                  ['School', 'Sees progress, seats and outcomes.'],
                ].map(([title, body], index) => (
                  <li key={title} className="relative pt-14">
                    <span className="absolute left-0 top-0 flex size-12 items-center justify-center rounded-full bg-white font-display text-lg font-extrabold text-navy-950">{index + 1}</span>
                    <h3 className="font-display text-xl font-extrabold">{title}</h3>
                    <p className="mt-2 font-semibold leading-relaxed text-white/68">{body}</p>
                  </li>
                ))}
                </ol>
              </div>
            </div>
          </div>
        </section>

        <section className="bg-[#eef6ff] py-20 sm:py-28">
          <div className="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[1.08fr_0.92fr] lg:items-center lg:px-8">
            <Reveal>
              <Figure src="/images/landing-v5-teacher-dashboard.webp" alt="A teacher reviews learner speaking progress while Amara and classmates practise nearby" className="aspect-[4/3] rounded-[1rem]" />
            </Reveal>
            <Reveal delay={80}>
              <p className="font-display text-xl font-extrabold text-chore-700">Useful insight, close to the teaching.</p>
              <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight sm:text-5xl">See who participated, what they understood and what needs another pass.</h2>
              <p className="mt-5 text-lg font-semibold leading-relaxed text-navy-700">Teachers review assignments and speaking attempts. School leaders see class and learner progress without entering the child’s learning space.</p>
              <ul className="mt-7 space-y-3">
                <CheckLine>Per-student lesson and quiz progress</CheckLine>
                <CheckLine>Assignment grading and speaking review</CheckLine>
                <CheckLine>Roster, class and seat administration</CheckLine>
                <CheckLine>Tenant-safe organization reporting</CheckLine>
              </ul>
            </Reveal>
          </div>
        </section>

        <section className="bg-white py-20 sm:py-28">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
              <Reveal>
                <p className="font-display text-xl font-extrabold text-rainbow-orange">Culture gives progress somewhere to go.</p>
                <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight sm:text-5xl">Stories at home. Clubs at school. A stage when they are ready.</h2>
                <p className="mt-5 text-lg font-semibold leading-relaxed text-navy-700">Folktales, family missions, Language & Culture clubs and competitions turn a curriculum into a community children can enter.</p>
                <LinkButton to="/register" variant="parent" size="lg" className="mt-7">Start the first learning circle</LinkButton>
              </Reveal>
              <Reveal delay={80}>
                <Figure src="/images/culture-storytelling.webp" alt="Children and Iya sharing a family folktale together at blue hour" className="aspect-[5/4] rounded-[1rem]" />
              </Reveal>
            </div>
          </div>
        </section>

        <section className="bg-[#ff650f] py-20 text-white sm:py-28">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <Reveal className="max-w-4xl">
              <h2 className="font-display text-4xl font-extrabold leading-tight sm:text-6xl">Built for the realities around the lesson.</h2>
            </Reveal>
            <div className="mt-12 divide-y divide-white/25 border-y border-white/25">
              {[
                ['Free learning', 'Every lesson remains available without payment; paid plans add convenience.'],
                ['Child-safe identity', 'Under-13 learners use profiles while adults manage consent and sensitive actions.'],
                ['Unreliable connections', 'Low-bandwidth activities and offline options reduce dependence on constant data.'],
                ['Real school operations', 'Seats, rosters, classes, invoices, assignments and reporting are part of the platform.'],
              ].map(([title, body], index) => (
                <Reveal key={title} className="grid gap-3 py-6 sm:grid-cols-[3rem_0.35fr_0.65fr] sm:items-start" delay={index * 55}>
                  <span className="font-display text-xl font-extrabold text-[#ffd36a]">0{index + 1}</span>
                  <h3 className="font-display text-2xl font-extrabold">{title}</h3>
                  <p className="max-w-xl text-lg font-semibold leading-relaxed text-white/82">{body}</p>
                </Reveal>
              ))}
            </div>
          </div>
        </section>

        <section className="bg-[#ffd44a] py-20 text-navy-950 sm:py-24">
          <div className="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.65fr_0.35fr] lg:items-end lg:px-8">
            <Reveal>
              <h2 className="font-display text-5xl font-extrabold leading-[1.02] sm:text-7xl">Begin with a learner. Grow into a community.</h2>
              <p className="mt-5 max-w-2xl text-lg font-bold">Families can start free today. Schools can inspect transparent seat pricing and request a quote.</p>
            </Reveal>
            <Reveal delay={80}>
              <LanguageButtons selected={languageIndex} onSelect={setLanguageIndex} />
              <p className="motion-word-pop mt-5 font-display text-3xl font-extrabold text-chore-700">{language.greeting}</p>
              <div className="mt-6 flex flex-col gap-3"><LinkButton to="/register" size="lg" variant="parent">Start learning free</LinkButton><Link to="/pricing" className="motion-press inline-flex min-h-12 items-center justify-center rounded-xl border-2 border-navy-950 px-5 font-display font-extrabold hover:bg-white/55">See school pricing</Link></div>
            </Reveal>
          </div>
        </section>
      </main>
      <ConceptFooter tone="navy" />
    </div>
  )
}
