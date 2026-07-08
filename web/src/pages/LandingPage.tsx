import { Link, Navigate } from 'react-router-dom'
import { Icon, LinkButton, type IconName } from '@/components/ui'
import { Logo } from '@/components/Logo'
import { cn } from '@/lib/cn'
import { TAGLINE, WORDMARK } from '@/lib/brand'
import { useAuth } from '@/lib/auth/AuthProvider'

const LANGUAGES = ['Yoruba', 'Igbo', 'Hausa', 'Pidgin']

// Tagline split into its three clauses for the hero treatment — derived from the
// single source so it can never drift from brand.ts.
const CLAUSES = TAGLINE.replace(/\.\s*$/, '')
  .split('. ')
  .map((c) => `${c}.`)
const CLAUSE_COLORS = ['text-rainbow-blue', 'text-rainbow-orange', 'text-rainbow-pink']

const FEATURES: { icon: IconName; color: string; title: string; body: string }[] = [
  {
    icon: 'book',
    color: 'bg-rainbow-blue',
    title: 'Real lessons',
    body: 'Video, speaking practice and playful quizzes in Yoruba, Igbo, Hausa and Pidgin.',
  },
  {
    icon: 'users',
    color: 'bg-rainbow-green',
    title: 'Family-powered',
    body: 'Kids learn, parents reward chores with coins, and everyone switches profiles with a PIN.',
  },
  {
    icon: 'sparkles',
    color: 'bg-rainbow-purple',
    title: 'Rooted in culture',
    body: 'Proverbs, folktales and festivals woven into every level — not just words, but heritage.',
  },
  {
    icon: 'trophy',
    color: 'bg-rainbow-orange',
    title: 'Made to stick',
    body: 'Streaks, XP, hearts and friendly leagues keep the whole family coming back.',
  },
]

export function LandingPage() {
  const { status } = useAuth()
  if (status === 'authenticated') return <Navigate to="/home" replace />

  return (
    <div className="min-h-screen bg-background">
      <SiteHeader />
      <Hero />
      <Features />
      <Audiences />
      <ClosingCta />
      <SiteFooter />
    </div>
  )
}

function SiteHeader() {
  return (
    <header className="sticky top-0 z-20 border-b border-border/60 bg-background/80 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <Logo className="h-8" />
        <div className="flex items-center gap-2">
          <LinkButton to="/pricing" variant="ghost" size="sm" className="hidden sm:block">
            Pricing
          </LinkButton>
          <LinkButton to="/login" variant="ghost" size="sm">
            Sign in
          </LinkButton>
          <LinkButton to="/register" size="sm">
            Get started
          </LinkButton>
        </div>
      </div>
    </header>
  )
}

function Hero() {
  return (
    <section className="relative overflow-hidden">
      {/* Soft rainbow glow */}
      <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10">
        <div className="absolute -left-24 top-0 size-72 rounded-full bg-rainbow-blue/20 blur-3xl" />
        <div className="absolute right-0 top-10 size-72 rounded-full bg-rainbow-orange/20 blur-3xl" />
        <div className="absolute bottom-0 left-1/3 size-72 rounded-full bg-rainbow-purple/20 blur-3xl" />
      </div>

      <div className="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 sm:py-28">
        <span className="inline-flex items-center gap-1.5 rounded-full border border-border bg-surface px-3 py-1 text-xs font-semibold text-muted">
          🌍 Nigerian languages for home & diaspora
        </span>

        <h1 className="mt-6 font-display text-4xl font-extrabold leading-tight tracking-tight text-foreground sm:text-5xl">
          {CLAUSES.map((clause, i) => (
            <span key={clause} className={cn('block', CLAUSE_COLORS[i % CLAUSE_COLORS.length])}>
              {clause}
            </span>
          ))}
        </h1>

        <p className="mx-auto mt-6 max-w-xl text-lg text-muted">
          The joyful way for families to learn Yoruba, Igbo, Hausa and Pidgin together — through
          stories, speaking and play.
        </p>

        <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
          <LinkButton to="/register" size="lg" fullWidth className="w-full sm:w-auto">
            Get started free
          </LinkButton>
          <LinkButton to="/login" size="lg" variant="outline" fullWidth className="w-full sm:w-auto">
            I have an account
          </LinkButton>
        </div>

        <div className="mt-10 flex flex-wrap items-center justify-center gap-2">
          <span className="text-sm text-subtle">Now teaching:</span>
          {LANGUAGES.map((lang) => (
            <span
              key={lang}
              className="rounded-full bg-surface-muted px-3 py-1 text-sm font-semibold text-foreground"
            >
              {lang}
            </span>
          ))}
        </div>
      </div>
    </section>
  )
}

function Features() {
  return (
    <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
      <div className="mx-auto max-w-2xl text-center">
        <h2 className="font-display text-3xl font-bold text-foreground">More than an app</h2>
        <p className="mt-3 text-muted">
          A learning home the whole family takes part in — built around how Nigerian languages are
          really passed on.
        </p>
      </div>

      <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        {FEATURES.map((f) => (
          <div
            key={f.title}
            className="flex flex-col gap-3 rounded-2xl border border-border bg-surface p-6"
          >
            <span
              className={cn(
                'flex size-12 items-center justify-center rounded-2xl text-white',
                f.color,
              )}
            >
              <Icon name={f.icon} className="size-6" />
            </span>
            <h3 className="font-display text-lg font-bold text-foreground">{f.title}</h3>
            <p className="text-sm text-muted">{f.body}</p>
          </div>
        ))}
      </div>
    </section>
  )
}

function Audiences() {
  const cards = [
    {
      emoji: '👨‍👩‍👧‍👦',
      title: 'For families',
      body: 'Set up child profiles, reward chores with coins, and keep heritage alive across generations — wherever you live.',
    },
    {
      emoji: '🏫',
      title: 'For schools',
      body: 'Roster classes, assign lessons, and track speaking progress with teacher and admin dashboards.',
    },
  ]
  return (
    <section className="border-y border-border bg-surface-muted/40">
      <div className="mx-auto grid max-w-6xl gap-5 px-4 py-16 sm:grid-cols-2 sm:px-6 lg:px-8">
        {cards.map((c) => (
          <div key={c.title} className="rounded-2xl border border-border bg-surface p-8">
            <span className="text-4xl" aria-hidden="true">
              {c.emoji}
            </span>
            <h3 className="mt-4 font-display text-xl font-bold text-foreground">{c.title}</h3>
            <p className="mt-2 text-muted">{c.body}</p>
          </div>
        ))}
      </div>
    </section>
  )
}

function ClosingCta() {
  return (
    <section className="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6">
      <h2 className="font-display text-3xl font-bold text-foreground sm:text-4xl">
        Start your family’s journey today
      </h2>
      <p className="mx-auto mt-3 max-w-lg text-muted">
        Free to begin. No card required. Your first lesson is minutes away.
      </p>
      <LinkButton to="/register" size="lg" className="mt-8">
        Create your free account
      </LinkButton>
    </section>
  )
}

function SiteFooter() {
  return (
    <footer className="border-t border-border">
      <div className="mx-auto flex max-w-6xl flex-col items-center gap-3 px-4 py-10 text-center sm:px-6">
        <Logo className="h-7" />
        <p className="text-sm font-medium text-muted">{TAGLINE}</p>
        <Link to="/pricing" className="text-sm font-semibold text-muted hover:text-foreground">
          See pricing
        </Link>
        <p className="text-xs text-subtle">
          © {WORDMARK} · {new Date().getFullYear()}
        </p>
      </div>
    </footer>
  )
}
