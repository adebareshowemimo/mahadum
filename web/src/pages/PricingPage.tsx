import { useQuery } from '@tanstack/react-query'
import { Link, Navigate } from 'react-router-dom'
import { Alert, Button, Skeleton } from '@/components/ui'
import { Logo } from '@/components/Logo'
import { pricingApi, type PricingBand, type PricingConsumerPlan, type PricingInfo } from '@/lib/api'
import { TAGLINE, WORDMARK } from '@/lib/brand'
import { useAuth } from '@/lib/auth/AuthProvider'

/** Whole-naira formatting for marketing (no kobo). */
function naira(minor: number): string {
  return `₦${Math.round(minor / 100).toLocaleString()}`
}

function pick(plans: PricingConsumerPlan[], audience: string, interval: string) {
  return plans.find((p) => p.audience === audience && p.interval === interval) ?? null
}

export function PricingPage() {
  const { status } = useAuth()
  const { data, isLoading, isError } = useQuery({ queryKey: ['pricing'], queryFn: pricingApi.get })

  if (status === 'authenticated') return <Navigate to="/billing" replace />

  return (
    <div className="min-h-screen bg-background">
      <header className="sticky top-0 z-20 border-b border-border/60 bg-background/80 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
          <Link to="/">
            <Logo className="h-8" />
          </Link>
          <div className="flex items-center gap-2">
            <Link to="/login">
              <Button variant="ghost" size="sm">
                Sign in
              </Button>
            </Link>
            <Link to="/register">
              <Button size="sm">Get started</Button>
            </Link>
          </div>
        </div>
      </header>

      <section className="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6">
        <h1 className="font-display text-4xl font-extrabold tracking-tight text-foreground">Simple, family-friendly pricing</h1>
        <p className="mx-auto mt-4 max-w-xl text-lg text-muted">
          Learning is always free. Upgrade for an ad-free experience, offline lessons and the family dashboard — or bring
          your whole school on board.
        </p>
      </section>

      <div className="mx-auto max-w-6xl px-4 pb-24 sm:px-6 lg:px-8">
        {isLoading && <Skeleton className="h-96" />}
        {isError && <Alert variant="danger">Couldn’t load pricing. Please try again.</Alert>}
        {data && <PricingBody data={data} />}
      </div>

      <footer className="border-t border-border">
        <div className="mx-auto flex max-w-6xl flex-col items-center gap-3 px-4 py-10 text-center sm:px-6">
          <Logo className="h-7" />
          <p className="text-sm font-medium text-muted">{TAGLINE}</p>
          <p className="text-xs text-subtle">
            © {WORDMARK} · {new Date().getFullYear()}
          </p>
        </div>
      </footer>
    </div>
  )
}

function PricingBody({ data }: { data: PricingInfo }) {
  const individualMonthly = pick(data.consumer, 'individual', 'month')
  const individualAnnual = pick(data.consumer, 'individual', 'year')
  const familyMonthly = pick(data.consumer, 'family', 'month')
  const familyAnnual = pick(data.consumer, 'family', 'year')

  return (
    <div className="flex flex-col gap-16">
      <div className="grid gap-5 lg:grid-cols-3">
        <PlanCard
          name={data.free.name}
          price="Free"
          cadence="forever"
          blurb={data.free.blurb}
          features={['Full access to every lesson', 'Speaking practice & quizzes', 'Ad-supported']}
        />
        <PlanCard
          name="Individual"
          price={individualMonthly ? naira(individualMonthly.price_minor) : '—'}
          cadence="/ month"
          annual={individualAnnual ? `or ${naira(individualAnnual.price_minor)}/year` : undefined}
          daily={individualMonthly ? `≈ ${naira(individualMonthly.price_minor / 30)}/day with airtime` : undefined}
          features={['Ad-free learning', 'Offline lesson downloads', 'Unlimited hearts', '1 learner profile']}
        />
        <PlanCard
          name="Family"
          price={familyMonthly ? naira(familyMonthly.price_minor) : '—'}
          cadence="/ month"
          annual={familyAnnual ? `or ${naira(familyAnnual.price_minor)}/year` : undefined}
          daily={familyMonthly ? `≈ ${naira(familyMonthly.price_minor / 30)}/day with airtime` : undefined}
          highlight
          features={[
            'Everything in Individual',
            `Up to ${familyMonthly?.max_profiles ?? 6} learner profiles`,
            'Family dashboard, chores & coins',
          ]}
        />
      </div>

      <SchoolPricing bands={data.school.bands} termMonths={data.school.term_months} />

      <section className="rounded-3xl border border-border bg-surface p-8 text-center sm:p-12">
        <h2 className="font-display text-2xl font-bold text-foreground">Ready to start?</h2>
        <p className="mx-auto mt-2 max-w-lg text-muted">Free to begin — no card required. Schools can request a quote in minutes.</p>
        <div className="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
          <Link to="/register">
            <Button size="lg">Get started free</Button>
          </Link>
          <Link to="/register">
            <Button size="lg" variant="outline">
              Talk to us about schools
            </Button>
          </Link>
        </div>
      </section>
    </div>
  )
}

function PlanCard({
  name,
  price,
  cadence,
  annual,
  daily,
  blurb,
  features,
  highlight = false,
}: {
  name: string
  price: string
  cadence: string
  annual?: string
  daily?: string
  blurb?: string
  features: string[]
  highlight?: boolean
}) {
  return (
    <div
      className={`flex flex-col gap-5 rounded-3xl border bg-surface p-8 ${
        highlight ? 'border-gold-400 ring-2 ring-gold-300/50' : 'border-border'
      }`}
    >
      <div>
        <div className="flex items-center gap-2">
          <h3 className="font-display text-xl font-bold text-foreground">{name}</h3>
          {highlight && <span className="rounded-full bg-gold-100 px-2 py-0.5 text-xs font-bold text-gold-800">Popular</span>}
        </div>
        {blurb && <p className="mt-1 text-sm text-muted">{blurb}</p>}
      </div>
      <div>
        <div className="flex items-baseline gap-1.5">
          <span className="font-display text-4xl font-extrabold text-foreground">{price}</span>
          <span className="text-muted">{cadence}</span>
        </div>
        {annual && <p className="mt-1 text-sm font-semibold text-leaf-600">{annual}</p>}
        {daily && <p className="text-xs text-subtle">{daily}</p>}
      </div>
      <ul className="flex flex-col gap-2.5">
        {features.map((f) => (
          <li key={f} className="flex items-start gap-2 text-sm text-foreground">
            <span aria-hidden="true" className="mt-0.5 font-bold text-leaf-600">✓</span>
            {f}
          </li>
        ))}
      </ul>
      <Link to="/register" className="mt-auto">
        <Button fullWidth variant={highlight ? 'premium' : 'outline'}>
          {name === 'Free' ? 'Start free' : `Choose ${name}`}
        </Button>
      </Link>
    </div>
  )
}

function SchoolPricing({ bands, termMonths }: { bands: PricingBand[]; termMonths: number }) {
  return (
    <section className="rounded-3xl border border-border bg-surface-muted/40 p-8 sm:p-12">
      <div className="mx-auto max-w-2xl text-center">
        <span className="text-4xl" aria-hidden="true">
          🏫
        </span>
        <h2 className="mt-3 font-display text-3xl font-bold text-foreground">For schools</h2>
        <p className="mt-3 text-muted">
          An annual registration plus a per-student rate that steps down as your school grows — for a {termMonths}-month
          academic year. Includes the Language &amp; Culture club and automatic entry to the national competition.
        </p>
      </div>

      <div className="mx-auto mt-8 max-w-3xl overflow-x-auto">
        <table className="w-full min-w-[28rem] border-separate border-spacing-y-2 text-left">
          <thead>
            <tr className="text-xs uppercase tracking-wide text-subtle">
              <th className="px-4 py-2">School size</th>
              <th className="px-4 py-2">Annual registration</th>
              <th className="px-4 py-2">Per student</th>
            </tr>
          </thead>
          <tbody>
            {bands.map((b) => (
              <tr key={b.label} className="bg-surface">
                <td className="rounded-l-xl px-4 py-3 font-semibold text-foreground">{b.label}</td>
                <td className="px-4 py-3 text-foreground">{naira(b.registration_minor)}</td>
                <td className="rounded-r-xl px-4 py-3 text-foreground">{naira(b.per_student_minor)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}
