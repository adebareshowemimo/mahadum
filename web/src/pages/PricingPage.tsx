import { useState, type FormEvent } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Link, Navigate } from 'react-router-dom'
import { Alert, Button, Input, LinkButton, Modal, Skeleton } from '@/components/ui'
import { Logo } from '@/components/Logo'
import { ApiError, pricingApi, type CreateSchoolLeadInput, type PricingBand, type PricingConsumerPlan, type PricingInfo } from '@/lib/api'
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
  const [quoteOpen, setQuoteOpen] = useState(false)

  if (status === 'authenticated') return <Navigate to="/billing" replace />

  return (
    <div className="min-h-screen bg-background">
      <header className="sticky top-0 z-20 border-b border-border/60 bg-background/80 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
          <Link to="/">
            <Logo className="h-8" />
          </Link>
          <div className="flex items-center gap-2">
            <LinkButton to="/login" variant="ghost" size="sm">
              Sign in
            </LinkButton>
            <LinkButton to="/register" size="sm">
              Get started
            </LinkButton>
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
        {data && <PricingBody data={data} onOpenQuote={() => setQuoteOpen(true)} />}
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

      <GetQuoteModal open={quoteOpen} onClose={() => setQuoteOpen(false)} />
    </div>
  )
}

function PricingBody({ data, onOpenQuote }: { data: PricingInfo; onOpenQuote: () => void }) {
  const individualMonthly = pick(data.consumer, 'individual', 'month')
  const individualAnnual = pick(data.consumer, 'individual', 'year')
  const familyMonthly = pick(data.consumer, 'family', 'month')
  const familyAnnual = pick(data.consumer, 'family', 'year')

  return (
    <div className="flex flex-col gap-16">
      <div className="grid gap-5 lg:grid-cols-4">
        <PlanCard
          name={data.free.name}
          price="FREE"
          cadence="— ₦0/month"
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
        <PlanCard
          name="School"
          price="Custom"
          cadence="per school"
          blurb="For classrooms and whole schools."
          features={['Ad-free learning', 'Offline lesson dashboards', 'Class and school dashboards']}
          ctaLabel="Get Quote"
          onCtaClick={onOpenQuote}
        />
      </div>

      <SchoolPricing bands={data.school.bands} termMonths={data.school.term_months} />

      <section className="rounded-3xl border border-border bg-surface p-8 text-center sm:p-12">
        <h2 className="font-display text-2xl font-bold text-foreground">Ready to start?</h2>
        <p className="mx-auto mt-2 max-w-lg text-muted">Free to begin — no card required. Schools can request a quote in minutes.</p>
        <div className="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
          <LinkButton to="/register" size="lg">
            Get started free
          </LinkButton>
          <Button size="lg" variant="outline" onClick={onOpenQuote}>
            Talk to us about schools
          </Button>
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
  ctaLabel,
  onCtaClick,
}: {
  name: string
  price: string
  cadence: string
  annual?: string
  daily?: string
  blurb?: string
  features: string[]
  highlight?: boolean
  ctaLabel?: string
  onCtaClick?: () => void
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
      {onCtaClick ? (
        <Button fullWidth variant={highlight ? 'premium' : 'outline'} className="mt-auto" onClick={onCtaClick}>
          {ctaLabel}
        </Button>
      ) : (
        <LinkButton to="/register" fullWidth variant={highlight ? 'premium' : 'outline'} className="mt-auto">
          {name === 'Free' ? 'Start free' : `Choose ${name}`}
        </LinkButton>
      )}
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

const SCHOOL_SIZES = ['1–99 students', '100–249 students', '250–500 students', 'Above 500 students']

function GetQuoteModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [values, setValues] = useState({ school_name: '', contact_name: '', email: '', phone: '', school_size: '', city: '' })
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [submitted, setSubmitted] = useState(false)

  const submit = useMutation({
    mutationFn: (input: CreateSchoolLeadInput) => pricingApi.submitSchoolLead(input),
  })

  function update<K extends keyof typeof values>(key: K, val: (typeof values)[K]) {
    setValues((v) => ({ ...v, [key]: val }))
  }

  function handleClose() {
    onClose()
    // Reset after the close animation so the form doesn't visibly reset first.
    setTimeout(() => {
      setSubmitted(false)
      setValues({ school_name: '', contact_name: '', email: '', phone: '', school_size: '', city: '' })
      setFieldErrors({})
      setFormError(null)
    }, 200)
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setFieldErrors({})
    setFormError(null)
    try {
      await submit.mutateAsync({
        school_name: values.school_name.trim(),
        contact_name: values.contact_name.trim(),
        email: values.email.trim(),
        phone: values.phone.trim() || undefined,
        school_size: values.school_size || undefined,
        city: values.city.trim() || undefined,
      })
      setSubmitted(true)
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setFormError(err.message)
      } else {
        setFormError('Something went wrong. Please try again.')
      }
    }
  }

  return (
    <Modal open={open} onClose={handleClose} title="Tell us about your school" description="We'll follow up with a quote tailored to your school size.">
      {submitted ? (
        <div className="flex flex-col items-center gap-3 py-6 text-center">
          <span className="text-4xl" aria-hidden="true">🎉</span>
          <p className="font-display text-lg font-bold text-foreground">Thanks — we've got it!</p>
          <p className="text-sm text-muted">Our team will reach out with a quote shortly.</p>
          <Button variant="outline" onClick={handleClose}>Close</Button>
        </div>
      ) : (
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          {formError && <Alert variant="danger">{formError}</Alert>}
          <Input
            label="School name"
            value={values.school_name}
            onChange={(e) => update('school_name', e.target.value)}
            error={fieldErrors.school_name}
            autoFocus
            required
          />
          <Input
            label="Your name"
            value={values.contact_name}
            onChange={(e) => update('contact_name', e.target.value)}
            error={fieldErrors.contact_name}
            required
          />
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Email"
              type="email"
              value={values.email}
              onChange={(e) => update('email', e.target.value)}
              error={fieldErrors.email}
              required
            />
            <Input
              label="Phone (optional)"
              value={values.phone}
              onChange={(e) => update('phone', e.target.value)}
              error={fieldErrors.phone}
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1.5">
              <span className="text-sm font-semibold text-foreground">School size (optional)</span>
              <select
                value={values.school_size}
                onChange={(e) => update('school_size', e.target.value)}
                className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              >
                <option value="">Choose…</option>
                {SCHOOL_SIZES.map((s) => (
                  <option key={s} value={s}>{s}</option>
                ))}
              </select>
            </label>
            <Input
              label="City (optional)"
              value={values.city}
              onChange={(e) => update('city', e.target.value)}
              error={fieldErrors.city}
            />
          </div>
          <Button type="submit" fullWidth loading={submit.isPending}>
            Request a quote
          </Button>
        </form>
      )}
    </Modal>
  )
}
