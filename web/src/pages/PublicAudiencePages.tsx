import { Figure } from '@/components/landing/Figure'
import {
  AudiencePath,
  PublicClosing,
  PublicPageShell,
} from '@/components/landing/PublicPagePrimitives'
import { Reveal } from '@/components/landing/Reveal'
import { Icon, LinkButton } from '@/components/ui'

const FAMILY_AUDIENCES = [
  {
    title: 'Children',
    body: 'Playful, age-respectful lessons that begin from zero and never lock learning behind hearts or payment.',
    link: '/register',
    linkLabel: 'Start a family',
  },
  {
    title: 'Teenagers',
    body: 'Speaking challenges, stories, clubs and competitions give older learners a reason to perform and belong.',
    link: '/schools',
    linkLabel: 'Explore clubs',
  },
  {
    title: 'Parents',
    body: 'One grown-up view for progress, recordings, consent, chores and rewards—without giving children payment controls.',
    link: '/child-safety',
    linkLabel: 'See parent controls',
  },
  {
    title: 'Extended family',
    body: 'Native voices and practical language help each new word travel into calls, visits, stories and celebrations.',
    link: '/about',
    linkLabel: 'Meet Mahadum',
  },
]

const INSTITUTION_FLOW = [
  { title: 'Learning', body: 'Choose Nigerian-language pathways, age bands and culturally grounded learning outcomes.', icon: 'book' as const },
  { title: 'Delivery', body: 'Support self-paced learning, facilitated groups, clubs, campaigns or connected access.', icon: 'layers' as const },
  { title: 'Oversight', body: 'Use roles, rosters, assignments, reporting and safeguarding appropriate to the programme.', icon: 'shield' as const },
  { title: 'Reach', body: 'Extend access across campuses, communities, public initiatives and telecom channels.', icon: 'building' as const },
]

const INSTITUTION_AUDIENCES = [
  {
    title: 'Universities',
    body: 'Offer language learning, cultural enrichment and student-led programmes with structured delivery and reporting.',
    link: '/contact?topic=university',
    linkLabel: 'Discuss a campus',
  },
  {
    title: 'Cultural organisations',
    body: 'Turn festivals, community groups and heritage programmes into continuing, measurable language practice.',
    link: '/contact?topic=culture',
    linkLabel: 'Plan a programme',
  },
  {
    title: 'Government initiatives',
    body: 'Support policy-aligned education, youth development and cultural continuity without inventing a parallel platform.',
    link: '/contact?topic=government',
    linkLabel: 'Explore delivery',
  },
  {
    title: 'Telecom partners',
    body: 'Extend affordable access through connected devices and configurable daily-pay or sponsored distribution models.',
    link: '/contact?topic=telecom',
    linkLabel: 'Discuss access',
  },
]

export function FamiliesPage() {
  return (
    <PublicPageShell mainId="families-main" overlayHeader>
      <section className="relative min-h-[36rem] overflow-hidden bg-navy-950 pt-[4.75rem] text-white">
        <img
          src="/images/community-multigenerational.webp"
          alt="A multigenerational Nigerian family sharing stories together outdoors"
          className="motion-hero-media absolute inset-0 size-full object-cover object-[64%_center]"
        />
        <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(6,26,60,0.99)_0%,rgba(6,26,60,0.92)_32%,rgba(6,26,60,0.33)_60%,rgba(6,26,60,0.05)_100%)]" />
        <div className="relative z-10 mx-auto flex min-h-[31.25rem] max-w-[90rem] items-center px-4 py-8 sm:px-6 lg:px-10">
          <div className="motion-hero-copy max-w-[38rem]">
            <h1 className="max-w-[11ch] text-wrap-balance font-display text-[clamp(3.2rem,5.7vw,5.5rem)] font-extrabold leading-[0.98] tracking-[-0.035em]">
              Hear your child say it in your language.
            </h1>
            <p className="mt-5 max-w-[32rem] text-lg leading-relaxed text-white/86">
              MAHADUM.360 helps families speak, listen, and learn together—through the languages that make your home, yours.
            </p>
            <div className="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center">
              <LinkButton to="/register" variant="accent" size="lg">Start your family free <span aria-hidden="true">→</span></LinkButton>
              <a href="/#try" className="inline-flex min-h-12 items-center gap-3 font-display font-extrabold text-white hover:underline">
                <span className="flex size-11 items-center justify-center rounded-full border-2 border-white"><Icon name="chevron" className="size-5 -rotate-90" /></span>
                <span>Try a lesson<small className="block font-sans text-xs font-semibold text-white/70">See how it works</small></span>
              </a>
            </div>
            <div className="mt-7 grid max-w-[36rem] gap-4 sm:grid-cols-3">
              {[
                ['shield', 'Safe & private', 'Built for children'],
                ['users', 'Made for families', 'All generations'],
                ['sparkles', 'Your languages', 'Your culture'],
              ].map(([icon, title, body]) => (
                <div key={title} className="flex gap-3">
                  <Icon name={icon as 'shield' | 'users' | 'sparkles'} className="size-6 text-white" />
                  <p className="text-xs leading-snug"><strong className="block font-display text-sm">{title}</strong><span className="text-white/72">{body}</span></p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      <section className="relative bg-[#55c8eb] pb-10 pt-10 text-navy-950 sm:pb-14 sm:pt-12">
        <div aria-hidden="true" className="absolute left-1/2 top-0 flex size-18 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-4 border-white bg-[#e7f7ff] text-chore-700">
          <Icon name="sparkles" className="size-8" />
        </div>
        <div className="mx-auto max-w-[90rem] px-4 sm:px-6 lg:px-10">
          <Reveal>
            <h2 className="text-wrap-balance font-display text-3xl font-extrabold leading-tight sm:text-4xl">Five joyful minutes can travel through a whole family.</h2>
            <p className="mt-3 text-sm font-semibold text-navy-800">Short lessons. Simple routines. Real conversations—in the languages you love.</p>
          </Reveal>
          <div className="mt-7 grid gap-7 lg:grid-cols-3">
            {[
              { number: '1', title: 'Child learns', body: 'Fun, age-right lessons in their home language.', image: '/images/archive/campaign-reading-challenge.webp', alt: 'A child enjoying a short language lesson', ui: ['Lesson', 'Let’s greet!', 'Ndewo', 'Hello'] },
              { number: '2', title: 'Parent reviews', body: 'Track progress, celebrate wins, and reinforce together.', image: '/images/archive/campaign-confidence.webp', alt: 'A parent reviewing language-learning progress', ui: ['Today’s progress', '72%', 'New words', 'Great practice!'] },
              { number: '3', title: 'Family connects', body: 'Conversations become memories in your own words.', image: '/images/archive/campaign-family-anywhere.webp', alt: 'A grandmother joining a family language conversation', ui: ['Conversation', 'Ndewo, nwanne!', 'Hello, my dear!', 'Speak together'] },
            ].map((step) => (
              <Reveal key={step.title} className="relative">
                <div className="grid aspect-[1.72/1] grid-cols-[1.15fr_0.85fr] overflow-hidden rounded-[0.75rem] bg-white">
                  <img src={step.image} alt={step.alt} className="size-full object-cover" />
                  <div className="flex flex-col justify-center p-3">
                    <p className="text-[0.65rem] font-bold text-navy-600">{step.ui[0]}</p>
                    <p className="mt-2 font-display text-base font-extrabold">{step.ui[1]}</p>
                    <div className="mt-3 rounded-lg bg-[#e7f7ff] p-2 text-xs font-bold text-navy-800">{step.ui[2]}</div>
                    <p className="mt-1 text-[0.65rem] text-navy-600">{step.ui[3]}</p>
                    <span className="mt-3 flex size-8 items-center justify-center self-end rounded-full bg-chore-100 text-chore-700"><Icon name="sparkles" className="size-4" /></span>
                  </div>
                </div>
                <div className="mt-3 flex gap-3">
                  <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-chore-600 font-display font-extrabold text-white">{step.number}</span>
                  <div><h3 className="font-display text-xl font-extrabold">{step.title}</h3><p className="mt-1 text-sm leading-relaxed text-navy-800">{step.body}</p></div>
                </div>
              </Reveal>
            ))}
          </div>
          <Reveal className="mt-8 grid overflow-hidden rounded-[0.75rem] bg-white sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1.1fr]">
            {[
              ['shield', 'Child safety by design', 'Kid-safe experiences with parent controls.'],
              ['shield', 'Privacy you can trust', 'Your data stays private and protected.'],
              ['users', 'Made for all generations', 'Simple for kids. Helpful for parents.'],
            ].map(([icon, title, body]) => (
              <div key={title} className="flex gap-3 border-b border-chore-100 p-5 sm:border-r lg:border-b-0">
                <Icon name={icon as 'shield' | 'users'} className="size-8 text-chore-700" />
                <p><strong className="block font-display">{title}</strong><span className="mt-1 block text-xs leading-relaxed text-navy-600">{body}</span></p>
              </div>
            ))}
            <div className="flex flex-col justify-center gap-3 p-5 sm:flex-row sm:items-center">
              <p className="font-display text-sm font-extrabold">Start your family’s language journey today.</p>
              <LinkButton to="/pricing" variant="accent" size="sm">See family plans →</LinkButton>
            </div>
          </Reveal>
        </div>
      </section>

      <AudiencePath
        heading="One language journey. A place for every generation."
        intro="Children may begin the lesson, but teenagers, parents and extended family keep the language moving."
        items={FAMILY_AUDIENCES}
      />

      <section className="bg-white py-16 sm:py-24">
        <div className="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:items-center lg:px-8">
          <Reveal>
            <p className="font-display text-xl font-extrabold text-[#a93403]">Kids get the adventure. Parents keep the controls.</p>
            <h2 className="mt-3 max-w-[14ch] font-display text-4xl font-extrabold leading-tight sm:text-5xl">A child can learn without managing an adult account.</h2>
            <p className="mt-5 max-w-2xl text-lg leading-relaxed text-navy-700">
              Under-13 learners use supervised profiles. Parents manage consent, see progress, review recordings and approve chore rewards before coins are released.
            </p>
            <div className="mt-8 divide-y divide-chore-200 border-y border-chore-200">
              {[
                ['Speaking review', 'Hear submitted practice and encourage the next attempt.'],
                ['Approve 50 coins', 'Rewards stay pending until the parent approves the completed chore.'],
                ['Weekly progress', 'See lessons completed and words added without hovering over every session.'],
              ].map(([title, body]) => (
                <div key={title} className="grid gap-2 py-5 sm:grid-cols-[12rem_1fr]">
                  <h3 className="font-display text-xl font-extrabold">{title}</h3>
                  <p className="leading-relaxed text-navy-700">{body}</p>
                </div>
              ))}
            </div>
            <LinkButton to="/child-safety" variant="parent" className="mt-7">Understand child safety</LinkButton>
          </Reveal>
          <Reveal delay={80}>
            <Figure
              src="/images/archive/campaign-family-anywhere.webp"
              alt="A mother and two teenagers learning with their grandmother over a video call"
              className="aspect-[16/10] rounded-[1rem]"
            />
          </Reveal>
        </div>
      </section>

      <PublicClosing
        heading="Bring one more voice back into the family conversation."
        body="Start with a language, a learner profile and five minutes. No card is required."
        primary={{ label: 'Start your family free', to: '/register' }}
        secondary={{ label: 'View family pricing', to: '/pricing' }}
      />
    </PublicPageShell>
  )
}

export function InstitutionsPage() {
  return (
    <PublicPageShell mainId="institutions-main">
      <section className="relative min-h-[calc(100svh-4.75rem)] overflow-hidden bg-navy-950 text-white lg:min-h-[40rem]!">
        <img
          src="/images/school-teen-culture-club.webp"
          alt="Nigerian teenagers and facilitators taking part in a language and culture programme"
          className="motion-hero-media absolute inset-0 size-full object-cover object-[66%_center]"
        />
        <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(6,26,60,0.99)_0%,rgba(6,26,60,0.94)_33%,rgba(6,26,60,0.4)_61%,rgba(6,26,60,0.08)_100%)]" />
        <div className="relative z-10 mx-auto flex min-h-[calc(100svh-4.75rem)] max-w-[90rem] items-center px-4 py-14 sm:px-6 lg:min-h-[40rem]! lg:px-10">
          <div className="motion-hero-copy max-w-[43rem]">
            <p className="font-display text-xl font-extrabold text-[#ffb277]">Built for programmes with real reach</p>
            <h1 className="mt-4 max-w-[11ch] text-wrap-balance font-display text-[clamp(3.2rem,6vw,5.7rem)] font-extrabold leading-[0.98] tracking-[-0.035em]">
              Take Nigerian languages further.
            </h1>
            <p className="mt-6 max-w-[38rem] text-lg leading-relaxed text-white/86 sm:text-xl">
              Launch culturally grounded learning across campuses, communities, public initiatives and connected channels—with one adaptable platform behind it.
            </p>
            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <LinkButton to="/contact?topic=partnership" variant="accent" size="lg">Discuss a partnership</LinkButton>
              <a href="#institution-flow" className="inline-flex min-h-12 items-center justify-center gap-3 px-5 font-display font-extrabold text-white hover:underline">
                <span className="flex size-11 items-center justify-center rounded-full border-2 border-white">
                  <Icon name="chevron" className="size-5" />
                </span>
                See how delivery connects
              </a>
            </div>
            <div className="mt-10 grid max-w-[42rem] gap-4 border-t border-white/25 pt-5 sm:grid-cols-3">
              {[
                ['building', 'Adaptable delivery', 'Campus to community'],
                ['shield', 'Responsible oversight', 'Roles and safeguarding'],
                ['layers', 'Built to scale', 'One programme foundation'],
              ].map(([icon, title, body]) => (
                <div key={title} className="flex items-start gap-3">
                  <Icon name={icon as 'building' | 'shield' | 'layers'} className="mt-0.5 size-6 text-[#78d7f5]" />
                  <p className="text-sm leading-snug"><strong className="block font-display">{title}</strong><span className="text-white/72">{body}</span></p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      <section id="institution-flow" className="relative bg-[#55c8eb] py-14 text-navy-950 sm:py-20">
        <div aria-hidden="true" className="absolute left-1/2 top-0 flex size-20 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-[5px] border-white bg-[#e7f7ff] text-chore-700 shadow-sm">
          <Icon name="layers" className="size-9" />
        </div>
        <div className="mx-auto max-w-[90rem] px-4 sm:px-6 lg:px-10">
          <Reveal>
            <h2 className="max-w-[24ch] text-wrap-balance font-display text-4xl font-extrabold leading-tight sm:text-5xl">
              One programme can move from curriculum to community.
            </h2>
            <p className="mt-4 max-w-3xl text-lg leading-relaxed text-navy-800">
              Mahadum connects the learning experience, local delivery and programme oversight so each partner can extend access without losing quality.
            </p>
          </Reveal>

          <div className="mt-10 grid gap-8 lg:grid-cols-[1fr_auto_1fr_auto_1fr] lg:items-center">
            {[
              {
                number: '1',
                title: 'Programme takes shape',
                body: 'Choose audiences, languages, pathways and the outcomes that matter.',
                image: '/images/school-classroom.webp',
                alt: 'Students beginning a structured Nigerian-language programme',
              },
              {
                number: '2',
                title: 'Learning reaches people',
                body: 'Deliver through facilitated groups, clubs, campaigns or connected access.',
                image: '/images/school-teen-culture-club.webp',
                alt: 'Teenagers participating in a language and culture club',
              },
              {
                number: '3',
                title: 'Leaders see progress',
                body: 'Use roles, rosters, assignments and reporting to guide the programme.',
                image: '/images/landing-v5-teacher-dashboard.webp',
                alt: 'A teacher reviewing language-learning progress in Mahadum',
              },
            ].map((step, index) => (
              <div key={step.title} className="contents">
                <Reveal delay={index * 70}>
                  <div className="overflow-hidden rounded-[1rem] bg-white">
                    <img src={step.image} alt={step.alt} className="aspect-[16/10] w-full object-cover" />
                    <div className="p-5">
                      <div className="flex items-start gap-3">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-chore-600 font-display font-extrabold text-white">{step.number}</span>
                        <div>
                          <h3 className="font-display text-2xl font-extrabold">{step.title}</h3>
                          <p className="mt-2 leading-relaxed text-navy-700">{step.body}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </Reveal>
                {index < 2 && (
                  <Icon name="arrow-left" className="hidden size-8 rotate-180 text-white lg:block" />
                )}
              </div>
            ))}
          </div>

          <Reveal className="mt-10 grid overflow-hidden rounded-[1rem] bg-white sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto]">
            {INSTITUTION_FLOW.slice(0, 3).map((item) => (
              <div key={item.title} className="flex gap-3 border-b border-chore-100 p-5 sm:border-r lg:border-b-0">
                <Icon name={item.icon} className="size-7 text-chore-700" />
                <p><strong className="block font-display">{item.title}</strong><span className="text-sm text-navy-600">{item.body}</span></p>
              </div>
            ))}
            <div className="flex items-center p-5">
              <LinkButton to="/contact?topic=partnership" variant="accent">Plan a programme</LinkButton>
            </div>
          </Reveal>
        </div>
      </section>

      <section className="bg-white py-14 text-navy-950 sm:py-20">
        <div className="mx-auto max-w-[90rem] px-4 sm:px-6 lg:px-10">
          <Reveal>
            <div className="grid gap-10 xl:grid-cols-[minmax(18rem,0.72fr)_minmax(0,2.28fr)] xl:items-start xl:gap-14">
              <div className="xl:pt-4">
                <p className="font-display text-base font-extrabold text-chore-700">How we work together</p>
                <h2 className="mt-3 max-w-[19ch] text-wrap-balance font-display text-[clamp(2.4rem,3vw,3.2rem)] font-extrabold leading-[1.06] tracking-[-0.025em]">
                  One platform. Four capabilities. Measurable impact across communities.
                </h2>
                <p className="mt-6 max-w-[38rem] text-base leading-relaxed text-navy-700 xl:max-w-sm">
                  We combine pedagogy, technology and local partnerships to help institutions teach, enable and embed Nigerian languages with confidence.
                </p>
              </div>

              <div className="relative">
                <div aria-hidden="true" className="absolute left-[10%] right-[10%] top-10 hidden h-0.5 bg-chore-500 xl:block" />
                <div className="relative grid gap-x-5 gap-y-6 md:grid-cols-2 xl:grid-cols-5">
                  {[
                    { title: 'Campus learning', body: 'Curricula, courses and certifications for students and staff.', icon: 'cap' as const, color: 'bg-[#32a6ed]' },
                    { title: 'Mahadum delivery', body: 'One connected foundation for learning, delivery, oversight and reach.', icon: 'sparkles' as const, color: 'bg-white' },
                    { title: 'Community programmes', body: 'Clubs, festivals and continuing learning for real-world use.', icon: 'users' as const, color: 'bg-[#f45b16]' },
                    { title: 'Public initiatives', body: 'Policy-aligned programmes for education, culture and youth development.', icon: 'building' as const, color: 'bg-chore-600' },
                    { title: 'Connected access', body: 'Partners and telcos extend access and inclusion where it matters most.', icon: 'layers' as const, color: 'bg-navy-950' },
                  ].map((node, index) => (
                    <article
                      key={node.title}
                      className={`relative grid min-h-full grid-cols-[4.5rem_1fr] gap-4 border-b border-chore-200 pb-6 md:grid-cols-[5rem_1fr] xl:block xl:border-0 xl:pb-0 xl:text-center ${index === 1 ? 'md:col-span-2 xl:col-span-1' : ''}`}
                    >
                      <span
                        className={`relative z-10 flex size-16 items-center justify-center rounded-full text-white md:size-20 xl:mx-auto ${
                          index === 1
                            ? 'border-[5px] border-chore-600 border-r-[#f45b16] text-chore-700'
                            : node.color
                        }`}
                      >
                        {index === 1 ? (
                          <span className="flex h-10 items-end gap-1">{[15, 28, 38, 31, 22, 12].map((height) => <span key={height} className="w-1.5 rounded-full bg-[#55c8eb]" style={{ height }} />)}</span>
                        ) : (
                          <Icon name={node.icon} className="size-8" />
                        )}
                      </span>
                      <div className="pt-1 xl:pt-4">
                        <h3 className={`font-display text-lg font-extrabold leading-tight ${index === 2 ? 'text-[#d53d00]' : 'text-navy-950'}`}>{node.title}</h3>
                        <p className="mt-2 text-sm leading-relaxed text-navy-700">{node.body}</p>
                      </div>

                      {index === 1 && (
                        <div className="col-span-2 mt-2 grid grid-cols-2 gap-x-4 gap-y-3 rounded-[0.75rem] bg-[#eef6ff] p-4 text-left xl:mt-5 xl:-mx-8">
                          {[
                            ['Learning', 'Adaptive paths'],
                            ['Delivery', 'Tools and practice'],
                            ['Oversight', 'Trusted reporting'],
                            ['Reach', 'Every channel'],
                          ].map(([label, detail]) => (
                            <p key={label} className="text-xs leading-snug text-navy-700">
                              <strong className="block font-display text-chore-700">{label}</strong>
                              {detail}
                            </p>
                          ))}
                        </div>
                      )}
                    </article>
                  ))}
                </div>
              </div>
            </div>
          </Reveal>

          <Reveal className="mt-10 grid overflow-hidden rounded-[0.75rem] border border-chore-200 bg-[#eef6ff] lg:grid-cols-[1.35fr_auto_0.8fr_0.8fr] lg:items-center">
            <div className="flex gap-4 p-5">
              <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-navy-950 text-white">
                <Icon name="users" className="size-6" />
              </span>
              <p><strong className="block font-display text-xl">Let’s build what’s next—together.</strong><span className="mt-1 block text-sm text-navy-700">Tell us about your goals and we’ll propose a pathway for your mission and community.</span></p>
            </div>
            <div className="px-5 pb-5 lg:pb-0">
              <LinkButton to="/contact?topic=partnership" variant="accent" size="lg">Discuss a partnership →</LinkButton>
            </div>
            <div className="flex gap-3 border-t border-chore-200 p-5 lg:border-l lg:border-t-0">
              <Icon name="shield" className="size-7 text-chore-700" />
              <p><strong className="block font-display">Safe and inclusive by design</strong><span className="mt-1 block text-xs leading-relaxed text-navy-600">Child-safe, privacy-first and accessibility-aligned.</span></p>
            </div>
            <div className="flex gap-3 border-t border-chore-200 p-5 lg:border-l lg:border-t-0">
              <Icon name="sparkles" className="size-7 text-chore-700" />
              <p><strong className="block font-display">Accessibility matters</strong><span className="mt-1 block text-xs leading-relaxed text-navy-600">Built to support diverse learners and inclusive participation.</span></p>
            </div>
          </Reveal>
        </div>
      </section>

      <AudiencePath
        heading="Different institutions. One adaptable learning foundation."
        intro="The programme shape changes by audience; the commitment to native voices, child safety, accessibility and measurable delivery does not."
        items={INSTITUTION_AUDIENCES}
      />

      <section className="bg-white py-16 sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <Reveal className="grid gap-8 lg:grid-cols-[0.72fr_1.28fr] lg:items-end">
            <div>
              <p className="font-display text-xl font-extrabold text-[#a93403]">A partnership begins with the delivery question</p>
              <h2 className="mt-3 max-w-[13ch] font-display text-4xl font-extrabold leading-tight sm:text-5xl">What should learners be able to do next?</h2>
            </div>
            <p className="max-w-2xl text-lg leading-relaxed text-navy-700">
              We start with audience, language, access conditions and programme goals. From there, we can discuss curriculum pathways, facilitator roles, enrolment, reporting, safeguarding and distribution.
            </p>
          </Reveal>
          <div className="mt-10 divide-y divide-chore-200 border-y border-chore-200">
            {[
              ['01', 'Define', 'Audience, languages, outcomes, locations and access constraints.'],
              ['02', 'Configure', 'Learning path, delivery model, roles, reporting and safeguarding.'],
              ['03', 'Launch', 'Onboarding, enrolment, communications and facilitator preparation.'],
              ['04', 'Improve', 'Review participation and learning signals, then refine the programme.'],
            ].map(([number, title, body]) => (
              <Reveal key={number} className="grid gap-3 py-6 sm:grid-cols-[4rem_10rem_1fr] sm:items-center">
                <span className="font-display text-2xl font-extrabold text-chore-600">{number}</span>
                <h3 className="font-display text-2xl font-extrabold">{title}</h3>
                <p className="leading-relaxed text-navy-700">{body}</p>
              </Reveal>
            ))}
          </div>
          <div className="mt-8 flex flex-wrap gap-4">
            <LinkButton to="/contact?topic=partnership" variant="parent">Discuss a partnership</LinkButton>
            <a href="mailto:partnerships@mahadum360.app" className="inline-flex min-h-11 items-center font-display font-extrabold text-chore-700 hover:underline">
              partnerships@mahadum360.app
            </a>
          </div>
        </div>
      </section>

      <PublicClosing
        heading="Build a programme people can carry into real life."
        body="Tell us who you want to reach, what access looks like and where language should lead."
        primary={{ label: 'Discuss a partnership', to: '/contact?topic=partnership' }}
        secondary={{ label: 'Explore schools', to: '/schools' }}
      />
    </PublicPageShell>
  )
}
