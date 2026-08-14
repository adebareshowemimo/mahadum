import { useState } from 'react'
import { Figure } from '@/components/landing/Figure'
import { Reveal } from '@/components/landing/Reveal'
import { LinkButton } from '@/components/ui'
import { cn } from '@/lib/cn'
import { ConceptFooter, ConceptHeader } from './LandingVariantsPage'

const JOURNEY = [
  {
    when: 'The spark',
    title: '“Mum, Igbo is boring.”',
    body: 'A child’s honest comment exposed the real problem: language was being experienced as a burden, not as a bridge to family, identity and belonging.',
  },
  {
    when: 'Built with families',
    title: 'A curriculum became a living test.',
    body: 'The first learners were the founder’s children, followed by their school, Nigerian families in Aberdeen and alumni families raising children across the diaspora.',
  },
  {
    when: '2018',
    title: 'MAHADUM.360 opened its online school.',
    body: 'The early Learn Easy and Read Easy series helped children speak, read and follow culturally rooted stories without a heavy time commitment.',
  },
  {
    when: 'Relaunched in 2026',
    title: 'Shorter lessons. More ways to practise.',
    body: 'Years of feedback shaped today’s self-paced platform: joyful lessons, authentic audio, speaking practice, quizzes, games and tools that bring parents and schools into the learning circle.',
  },
]

const PRINCIPLES = [
  {
    title: 'Language lives between people',
    body: 'A lesson matters when it helps a child greet a grandparent, follow a family story or answer back with confidence.',
  },
  {
    title: 'Culture is part of the curriculum',
    body: 'Words arrive with voices, situations, stories and everyday Nigerian life—not as isolated vocabulary lists.',
  },
  {
    title: 'Access comes before upgrades',
    body: 'Every learning lesson remains available on Free. Paid plans add convenience, not permission to learn.',
  },
]

const LEARNING_PATHS = [
  { language: 'Igbo', title: 'Mụta Igbo!', line: 'Build everyday speaking confidence and reconnect words to home.' },
  { language: 'Hausa', title: 'Koyi Hausa', line: 'Hear, repeat and use practical Hausa in familiar situations.' },
  { language: 'Yorùbá', title: 'Àlà Yorùbá', line: 'Learn with accurate diacritics, native voices and living culture.' },
  { language: 'English', title: 'Everyday English', line: 'Build confident speaking, listening and connection through everyday English.' },
]

const ARCHIVE_MOMENTS = [
  {
    image: '/images/archive/campaign-family-anywhere.webp',
    alt: 'A Nigerian mother and two teenagers sharing a language lesson with their grandmother over a video call',
    label: 'Family learning',
    caption: 'The early promise was simple: children could feel at home in their language wherever they lived.',
  },
  {
    image: '/images/archive/campaign-confidence.webp',
    alt: 'A confident young Nigerian woman holding a microphone against a bright blue and orange backdrop',
    label: 'Speaking confidence',
    caption: 'Campaigns made language visible as confidence, identity and everyday expression.',
  },
  {
    image: '/images/archive/campaign-reading-challenge.webp',
    alt: 'Four older Nigerian learners reading a story aloud together in a bright school library',
    label: 'Reading challenges',
    caption: 'Public challenges invited learners to read, respond and practise beyond a lesson.',
  },
  {
    image: '/images/archive/campaign-food-language.webp',
    alt: 'A Nigerian father and two teenagers practising food words together over a shared family meal',
    label: 'Culture in daily life',
    caption: 'Food, stories and family moments kept language attached to the culture that gives it meaning.',
  },
]

const CHARACTER_STORIES = [
  {
    id: 'voice',
    tab: 'Native voices',
    name: 'Kene',
    age: '21',
    role: 'Young voice guide',
    title: 'A lesson should sound like someone from home.',
    body: 'Kene represents the young native speakers who bring pronunciation, rhythm and cultural context into a lesson. Learners hear a living voice—not a generic placeholder.',
    note: 'Native audio · speaking practice · cultural context',
    image: '/images/character-kene-native-voice.webp',
    alt: 'Kene, a young Igbo voice guide, recording a language lesson in a studio',
    imageClass: 'object-cover object-center',
  },
  {
    id: 'teen',
    tab: 'Teen learners',
    name: 'Aondo',
    age: '16',
    role: 'Mid-teen learner',
    title: 'Teenagers should be able to recognise themselves here.',
    body: 'Aondo expands the learning circle beyond younger children. Speaking challenges, school clubs and performances give teenagers a reason to practise with confidence and peers.',
    note: 'Age-respectful design · clubs · challenges · performance',
    image: '/images/character-aondo-teen-learner.webp',
    alt: 'Aondo, a confident mid-teen learner in modest black-and-white Tiv-inspired attire',
    imageClass: 'object-contain object-center',
  },
  {
    id: 'returner',
    tab: 'Young adults',
    name: 'Musa',
    age: '20',
    role: 'Young adult returner',
    title: 'Coming back to your language can begin anywhere.',
    body: 'Musa represents young adults rebuilding confidence from a phone, tablet or computer—then carrying each new word into the next call home.',
    note: 'Self-paced learning · any device · family connection',
    image: '/images/character-musa-young-adult.webp',
    alt: 'Musa, a Hausa young adult, smiling while completing a phone-based language lesson',
    imageClass: 'object-cover object-center',
  },
]

export function AboutPage() {
  return (
    <div className="variant-page min-h-screen bg-white text-navy-950">
      <a href="#about-main" className="variant-skip-link">Skip to main content</a>
      <ConceptHeader />

      <main id="about-main">
        <AboutHero />
        <OriginStory />
        <CampaignArchive />
        <CharacterStories />
        <MissionBand />
        <PrinciplesSection />
        <LearningPaths />
        <AboutClosing />
      </main>

      <ConceptFooter />
    </div>
  )
}

function CampaignArchive() {
  return (
    <section className="bg-navy-950 py-16 text-white sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid gap-8 lg:grid-cols-[0.72fr_1.28fr] lg:items-end">
          <Reveal>
            <p className="font-display text-xl font-extrabold text-[#ffb277]">Ideas from the MAHADUM archive, reimagined</p>
            <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight sm:text-5xl">
              The platform grew from years of learning in public.
            </h2>
          </Reveal>
          <Reveal delay={60}>
            <p className="max-w-2xl text-lg font-semibold leading-relaxed text-navy-100">
              Before today’s platform, campaigns, summer programmes and family challenges tested a lasting idea:
              Nigerian language becomes easier to keep when it appears in the life a learner already enjoys.
            </p>
          </Reveal>
        </div>

        <div className="mt-12 grid gap-6 sm:grid-cols-2">
          {ARCHIVE_MOMENTS.map((moment, index) => (
            <Reveal key={moment.image} delay={index * 55}>
              <figure className="group flex h-full flex-col overflow-hidden rounded-[1rem] bg-white text-navy-950">
                <div className="overflow-hidden bg-[#eef6ff]">
                  <img
                    src={moment.image}
                    alt={moment.alt}
                    loading="lazy"
                    decoding="async"
                    className="aspect-[4/3] size-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.025]"
                  />
                </div>
                <figcaption className="flex flex-1 flex-col p-5 sm:p-6">
                  <p className="font-display text-lg font-extrabold text-[#a93403]">{moment.label}</p>
                  <p className="mt-1 max-w-xl text-sm font-semibold leading-relaxed text-navy-700">{moment.caption}</p>
                </figcaption>
              </figure>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  )
}

function CharacterStories() {
  const [activeIndex, setActiveIndex] = useState(0)
  const story = CHARACTER_STORIES[activeIndex]

  return (
    <section className="bg-[#eef6ff] py-16 sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Reveal className="max-w-4xl">
          <p className="font-display text-xl font-extrabold text-[#a93403]">The learning circle is growing</p>
          <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight text-navy-950 sm:text-6xl">
            Children begin the journey. Teenagers and young adults keep it moving.
          </h2>
          <p className="mt-5 max-w-3xl text-lg font-semibold leading-relaxed text-navy-700">
            MAHADUM.360 is designed for the different moments when language matters: a child’s first greeting, a
            teenager finding confidence with peers, or a young adult returning to words they once knew.
          </p>
        </Reveal>

        <div role="tablist" aria-label="People in the Mahadum learning circle" className="mt-9 flex flex-wrap gap-2">
          {CHARACTER_STORIES.map((item, index) => (
            <button
              key={item.id}
              type="button"
              role="tab"
              aria-selected={activeIndex === index}
              aria-controls="about-character-panel"
              onClick={() => setActiveIndex(index)}
              className={cn(
                'motion-press min-h-11 rounded-full px-5 font-display text-sm font-extrabold transition-colors',
                activeIndex === index
                  ? 'bg-navy-950 text-white'
                  : 'bg-white text-navy-700 hover:bg-chore-50 hover:text-chore-800',
              )}
            >
              {item.tab}
            </button>
          ))}
        </div>

        <div
          id="about-character-panel"
          role="tabpanel"
          key={story.id}
          className="motion-panel-swap mt-5 grid overflow-hidden rounded-[1rem] bg-navy-950 text-white lg:grid-cols-[0.82fr_1.18fr]"
        >
          <div className="relative min-h-[30rem] bg-[#55c8eb] lg:min-h-[40rem]">
            <img
              src={story.image}
              alt={story.alt}
              loading="lazy"
              decoding="async"
              className={cn('absolute inset-0 size-full', story.imageClass)}
            />
          </div>
          <div className="flex flex-col justify-center p-7 sm:p-10 lg:p-14">
            <div className="flex flex-wrap items-center gap-3">
              <p className="font-display text-3xl font-extrabold">{story.name}</p>
              <span className="rounded-full bg-white/12 px-3 py-1 text-sm font-bold text-[#ffb277]">
                Age {story.age} · {story.role}
              </span>
            </div>
            <h3 className="mt-7 max-w-[18ch] font-display text-4xl font-extrabold leading-tight sm:text-5xl">
              {story.title}
            </h3>
            <p className="mt-5 max-w-2xl text-lg font-semibold leading-relaxed text-navy-100">{story.body}</p>
            <p className="mt-8 border-t border-white/15 pt-5 font-display text-sm font-extrabold text-[#ffb277]">
              {story.note}
            </p>
            <LinkButton to="/register" size="lg" variant="accent" className="mt-8 self-start">
              Start your learning journey
            </LinkButton>
          </div>
        </div>
      </div>
    </section>
  )
}

function AboutHero() {
  return (
    <section className="relative overflow-hidden bg-[#dff3ff]">
      <div className="mx-auto grid max-w-[90rem] items-center gap-10 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[0.9fr_1.1fr] lg:gap-14 lg:px-10 lg:py-24">
        <Reveal className="relative z-10">
          <p className="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-sm font-bold text-chore-700 shadow-sm">
            <span aria-hidden="true" className="size-2 rounded-full bg-rainbow-orange" />
            Why MAHADUM.360 exists
          </p>
          <h1 className="mt-6 max-w-[13ch] font-display text-[clamp(3rem,6vw,5.5rem)] font-extrabold leading-[0.98] tracking-[-0.035em] text-navy-950">
            A language should feel like home, not homework.
          </h1>
          <p className="mt-6 max-w-[39rem] text-lg font-semibold leading-relaxed text-navy-700 sm:text-xl">
            MAHADUM.360 helps Nigerian children, young adults and families rebuild the everyday language that connects
            generations—through short lessons, native voices and culture they can recognise themselves in.
          </p>
          <p className="mt-5 max-w-[34rem] font-display text-xl font-extrabold leading-relaxed text-chore-700">
            We are building digital infrastructure for Nigerian identity.
          </p>
          <div className="mt-8 flex flex-col gap-3 sm:flex-row">
            <LinkButton to="/register" size="lg" variant="parent">Start your family free</LinkButton>
            <LinkButton to="/pricing" size="lg" variant="outline">Explore plans</LinkButton>
          </div>
        </Reveal>

        <Reveal delay={80} className="grid grid-cols-[1.18fr_0.82fr] items-end gap-3 sm:gap-4">
          <Figure
            src="/images/culture-storytelling.webp"
            alt="Children and Iya sharing a Nigerian folktale together at blue hour"
            className="aspect-[4/5] rounded-[1rem]"
            imgClassName="object-center"
            priority
          />
          <div className="space-y-3 sm:space-y-4">
            <Figure
              src="/images/hero-grandmother-child.webp"
              alt="Iya and her granddaughter Amara laughing together while learning on a tablet"
              className="aspect-[4/5] rounded-[1rem]"
              imgClassName="object-center"
              priority
            />
            <div className="rounded-[1rem] bg-navy-950 p-4 text-white sm:p-6">
              <p className="font-display text-2xl font-extrabold text-[#ffb277]">4 languages</p>
              <p className="mt-1 text-sm font-semibold text-navy-100">Yorùbá, Igbo, Hausa and English—one growing home.</p>
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  )
}

function OriginStory() {
  return (
    <section className="bg-white py-16 sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid gap-12 lg:grid-cols-[0.78fr_1.22fr] lg:gap-20">
          <Reveal>
            <p className="font-display text-xl font-extrabold text-[#a93403]">The origin story</p>
            <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight text-navy-950 sm:text-5xl">
              It began with one mother trying to make language matter.
            </h2>
            <p className="mt-6 text-lg font-semibold leading-relaxed text-navy-700">
              As a busy mum in Nigeria, our founder watched her children struggle to speak Nigerian languages fluently.
              The challenge was not ability; it was relevance. Language felt separate from the life they enjoyed.
            </p>
            <blockquote className="mt-7 border-y border-chore-100 py-6 font-display text-2xl font-extrabold leading-snug text-chore-700">
              “Speaking the language is not a stand-alone concept. It is inseparable from identity and culture.”
            </blockquote>
            <p className="mt-7 text-lg font-semibold leading-relaxed text-navy-700">
              Conversations with other parents revealed the same worry. A career break became a curriculum, and the
              curriculum became a platform designed to make Nigerian language learning engaging, adaptable and rooted.
            </p>
          </Reveal>

          <ol className="border-t border-chore-100">
            {JOURNEY.map((item, index) => (
              <Reveal key={item.when} as="li" delay={index * 60}>
                <div className="grid gap-3 border-b border-chore-100 py-7 sm:grid-cols-[9rem_1fr] sm:gap-8">
                  <p className="font-display text-sm font-extrabold text-[#a93403]">{item.when}</p>
                  <div>
                    <h3 className="font-display text-2xl font-extrabold text-navy-950">{item.title}</h3>
                    <p className="mt-2 max-w-2xl font-semibold leading-relaxed text-navy-600">{item.body}</p>
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

function MissionBand() {
  return (
    <section className="relative min-h-[34rem] overflow-hidden bg-navy-950 text-white">
      <img
        src="/images/community-multigenerational.webp"
        alt="A child, teenager, young adults, parents and grandmother sharing a joyful language conversation outdoors"
        loading="lazy"
        decoding="async"
        className="absolute inset-0 size-full object-cover object-center"
      />
      <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(6,26,60,0.98)_0%,rgba(6,26,60,0.88)_38%,rgba(6,26,60,0.2)_72%)]" />
      <div className="relative mx-auto flex min-h-[34rem] max-w-7xl items-center px-4 py-16 sm:px-6 lg:px-8">
        <Reveal className="max-w-2xl">
          <p className="font-display text-xl font-extrabold text-[#ffb277]">The long view</p>
          <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight sm:text-6xl">
            Keep the language moving forward.
          </h2>
          <p className="mt-6 text-lg font-semibold leading-relaxed text-navy-100 sm:text-xl">
            Our vision is a digital home for Nigerian identity: a place where families reconnect, schools teach with
            confidence, and diaspora communities carry language and cultural understanding into the next generation.
          </p>
        </Reveal>
      </div>
    </section>
  )
}

function PrinciplesSection() {
  return (
    <section className="bg-[#f7fbff] py-16 sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Reveal className="max-w-3xl">
          <h2 className="font-display text-4xl font-extrabold leading-tight text-navy-950 sm:text-5xl">
            What guides every lesson we build
          </h2>
        </Reveal>
        <div className="mt-10 overflow-hidden rounded-[1rem] border border-chore-100 bg-white md:grid md:grid-cols-3">
          {PRINCIPLES.map((principle, index) => (
            <Reveal key={principle.title} delay={index * 70}>
              <div className="h-full border-b border-chore-100 p-7 last:border-b-0 md:border-b-0 md:border-r md:last:border-r-0">
                <span aria-hidden="true" className="block h-1.5 w-12 rounded-full bg-rainbow-orange" />
                <h3 className="mt-6 font-display text-2xl font-extrabold text-navy-950">{principle.title}</h3>
                <p className="mt-3 font-semibold leading-relaxed text-navy-600">{principle.body}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  )
}

function LearningPaths() {
  return (
    <section className="bg-white py-16 sm:py-24">
      <div className="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:items-center lg:gap-16 lg:px-8">
        <Reveal>
          <Figure
            src="/images/landing-v2-market-world.webp"
            alt="Amara exploring Nigerian language through a lively market adventure"
            className="aspect-[5/4] rounded-[1rem]"
            imgClassName="object-center"
          />
        </Reveal>
        <Reveal delay={80}>
          <p className="font-display text-xl font-extrabold text-[#a93403]">Learn in your language</p>
          <h2 className="mt-3 font-display text-4xl font-extrabold leading-tight text-navy-950 sm:text-5xl">
            Four paths into one connected culture.
          </h2>
          <p className="mt-5 text-lg font-semibold leading-relaxed text-navy-600">
            Each course begins from zero and builds toward words a learner can recognise, say and use with real people.
          </p>
          <div className="mt-8 border-t border-chore-100">
            {LEARNING_PATHS.map((path) => (
              <div key={path.language} className="grid gap-2 border-b border-chore-100 py-5 sm:grid-cols-[7rem_1fr] sm:gap-6">
                <p className="font-display font-extrabold text-chore-700">{path.language}</p>
                <div>
                  <h3 className="font-display text-xl font-extrabold text-navy-950">{path.title}</h3>
                  <p className="mt-1 text-sm font-semibold leading-relaxed text-navy-600">{path.line}</p>
                </div>
              </div>
            ))}
          </div>
        </Reveal>
      </div>
    </section>
  )
}

function AboutClosing() {
  return (
    <section className="bg-chore-700 py-20 text-white sm:py-28">
      <Reveal className="mx-auto max-w-3xl px-4 text-center sm:px-6">
        <h2 className="font-display text-4xl font-extrabold leading-tight sm:text-5xl">
          Make the next family conversation feel closer.
        </h2>
        <p className="mx-auto mt-5 max-w-2xl text-lg font-semibold leading-relaxed text-chore-50">
          Start with five joyful minutes. Keep every lesson free. Let the language become part of everyday life again.
        </p>
        <LinkButton to="/register" size="lg" variant="accent" className="mt-8">Start learning free</LinkButton>
      </Reveal>
    </section>
  )
}
