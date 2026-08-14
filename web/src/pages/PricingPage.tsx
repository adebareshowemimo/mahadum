import { useState, type FormEvent } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Navigate } from 'react-router-dom'
import { Figure } from '@/components/landing/Figure'
import { Reveal } from '@/components/landing/Reveal'
import { Alert, Button, Input, LinkButton, Modal, Skeleton } from '@/components/ui'
import {
  ApiError,
  pricingApi,
  type CreateSchoolLeadInput,
  type PricingBand,
  type PricingConsumerPlan,
  type PricingInfo,
} from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import { cn } from '@/lib/cn'
import { ConceptFooter, ConceptHeader } from './LandingVariantsPage'

type BillingInterval = 'month' | 'year'

/** Whole-naira formatting for marketing (no kobo). */
function naira(minor: number): string {
  return `₦${Math.round(minor / 100).toLocaleString()}`
}

function pick(plans: PricingConsumerPlan[], audience: string, interval: BillingInterval) {
  return plans.find((plan) => plan.audience === audience && plan.interval === interval) ?? null
}

export function PricingPage() {
  const { status } = useAuth()
  const { data, isLoading, isError } = useQuery({ queryKey: ['pricing'], queryFn: pricingApi.get })
  const [quoteOpen, setQuoteOpen] = useState(false)

  if (status === 'authenticated') return <Navigate to="/billing" replace />

  return (
    <div className="variant-page min-h-screen bg-white text-navy-950">
      <a href="#pricing-main" className="variant-skip-link">Skip to main content</a>
      <ConceptHeader />

      <main id="pricing-main">
        <PricingHero />

        <section id="plans" className="scroll-mt-24 bg-white py-16 sm:py-24">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            {isLoading && <Skeleton className="h-[34rem] rounded-[1rem]" />}
            {isError && (
              <Alert variant="danger">
                We couldn’t load the latest prices. Please refresh the page or try again shortly.
              </Alert>
            )}
            {data && <PricingBody data={data} onOpenQuote={() => setQuoteOpen(true)} />}
          </div>
        </section>
      </main>

      <ConceptFooter />
      <GetQuoteModal open={quoteOpen} onClose={() => setQuoteOpen(false)} />
    </div>
  )
}

function PricingHero() {
  return (
    <section className="relative overflow-hidden bg-[#dff3ff]">
      <div className="mx-auto grid max-w-[90rem] items-center gap-10 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[0.88fr_1.12fr] lg:gap-14 lg:px-10 lg:py-24">
        <Reveal className="relative z-10">
          <p className="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-sm font-bold text-chore-700 shadow-sm">
            <span aria-hidden="true" className="size-2 rounded-full bg-rainbow-orange" />
            Straightforward pricing for families and schools
          </p>
          <h1 className="mt-6 max-w-[12ch] font-display text-[clamp(3rem,6vw,5.5rem)] font-extrabold leading-[0.98] tracking-[-0.035em] text-navy-950">
            Every lesson is free. Choose how you learn.
          </h1>
          <p className="mt-6 max-w-[38rem] text-lg font-semibold leading-relaxed text-navy-700 sm:text-xl">
            Start with the complete learning experience at no cost. Upgrade for convenience—no ads, offline lessons,
            unlimited hearts, and family tools—not for access to your language.
          </p>
          <div className="mt-8 flex flex-col gap-3 sm:flex-row">
            <a
              href="#plans"
              className="inline-flex h-12 items-center justify-center rounded-xl bg-chore-500 px-6 font-display font-bold text-white shadow-sm transition-colors hover:bg-chore-600"
            >
              Compare family plans
            </a>
            <a
              href="#school-pricing"
              className="inline-flex h-12 items-center justify-center rounded-xl border border-chore-200 bg-white px-6 font-display font-bold text-chore-700 transition-colors hover:bg-chore-50"
            >
              See school pricing
            </a>
          </div>
          <div className="mt-7 flex flex-wrap gap-x-6 gap-y-2 text-sm font-bold text-navy-700">
            <span>✓ No card to start</span>
            <span>✓ Cancel anytime</span>
            <span>✓ Learning never locked</span>
          </div>
        </Reveal>

        <Reveal delay={80} className="relative">
          <div aria-hidden="true" className="absolute -left-7 -top-7 size-28 rounded-full bg-rainbow-orange/15" />
          <Figure
            src="/images/landing-v4-kitchen-hero.webp"
            alt="A Nigerian family learning everyday language together while preparing food at home"
            className="relative aspect-[16/11] w-full rounded-[1rem]"
            imgClassName="object-[63%_center]"
            priority
          />
          <div className="absolute bottom-4 left-4 max-w-[15rem] rounded-xl bg-white p-4 shadow-md sm:bottom-6 sm:left-6">
            <p className="font-display text-lg font-extrabold text-navy-950">Free means every lesson.</p>
            <p className="mt-1 text-sm font-semibold text-navy-600">Paying adds comfort, never access.</p>
          </div>
        </Reveal>
      </div>
    </section>
  )
}

function PricingBody({ data, onOpenQuote }: { data: PricingInfo; onOpenQuote: () => void }) {
  const [interval, setInterval] = useState<BillingInterval>('month')
  const individual = pick(data.consumer, 'individual', interval)
  const family = pick(data.consumer, 'family', interval)

  return (
    <div>
      <Reveal className="flex flex-col gap-7 sm:flex-row sm:items-end sm:justify-between">
        <div className="max-w-2xl">
          <h2 className="font-display text-3xl font-extrabold leading-tight text-navy-950 sm:text-5xl">
            Begin free. Add what your household needs.
          </h2>
          <p className="mt-4 text-lg font-semibold leading-relaxed text-navy-600">
            One learner or six, every plan begins with the same complete curriculum.
          </p>
        </div>
        <div className="inline-flex w-fit rounded-xl bg-chore-50 p-1" role="group" aria-label="Billing period">
          {(['month', 'year'] as const).map((option) => (
            <button
              key={option}
              type="button"
              aria-pressed={interval === option}
              onClick={() => setInterval(option)}
              className={cn(
                'min-h-11 rounded-lg px-5 font-display text-sm font-bold transition-colors',
                interval === option ? 'bg-white text-chore-700 shadow-sm' : 'text-navy-600 hover:text-navy-950',
              )}
            >
              {option === 'month' ? 'Monthly' : 'Annual'}
            </button>
          ))}
        </div>
      </Reveal>

      <div className="mt-10 grid gap-5 lg:grid-cols-3">
        <PlanCard
          name={data.free.name}
          price="₦0"
          cadence="forever"
          blurb={data.free.blurb}
          features={['Every lesson and quiz', 'Speaking practice', 'XP, streaks and badges', 'Supported by age-appropriate ads']}
        />
        <PlanCard
          name="Individual"
          plan={individual}
          interval={interval}
          features={['Everything in Free', 'Ad-free learning', 'Offline lesson downloads', 'Unlimited hearts', '1 learner profile']}
        />
        <PlanCard
          name="Family"
          plan={family}
          interval={interval}
          highlight
          features={[
            'Everything in Individual',
            `Up to ${family?.max_profiles ?? 6} learner profiles`,
            'Family progress dashboard',
            'Chores, coins and parent approvals',
          ]}
        />
      </div>

      <Reveal className="mt-8 grid overflow-hidden rounded-[1rem] border border-chore-100 bg-[#f7fbff] md:grid-cols-3">
        <PromisePoint title="The curriculum stays open">
          Free learners can complete the same language lessons as paying learners.
        </PromisePoint>
        <PromisePoint title="Upgrade for convenience">
          Paid plans remove interruptions and make learning easier on journeys and weak connections.
        </PromisePoint>
        <PromisePoint title="Parents stay in control">
          Family tools let adults review progress and approve chore rewards before coins are released.
        </PromisePoint>
      </Reveal>

      <SchoolPricing
        bands={data.school.bands}
        termMonths={data.school.term_months}
        onOpenQuote={onOpenQuote}
      />

      <Reveal className="mt-16 overflow-hidden rounded-[1rem] bg-chore-700 px-6 py-12 text-center text-white sm:px-10 sm:py-16">
        <p className="font-display text-lg font-bold text-[#ffb277]">Your first lesson takes about five minutes.</p>
        <h2 className="mx-auto mt-3 max-w-3xl font-display text-3xl font-extrabold leading-tight sm:text-5xl">
          Start with a word your family can use today.
        </h2>
        <p className="mx-auto mt-4 max-w-xl text-lg font-semibold text-chore-50">
          No card. No locked course. Just Yorùbá, Igbo, Hausa and English made joyful.
        </p>
        <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
          <LinkButton to="/register" size="lg" variant="accent">Create a free account</LinkButton>
          <Button size="lg" variant="ghost" className="text-white hover:bg-white/10" onClick={onOpenQuote}>
            Talk to us about schools
          </Button>
        </div>
      </Reveal>
    </div>
  )
}

function PromisePoint({ title, children }: { title: string; children: string }) {
  return (
    <div className="border-b border-chore-100 p-6 last:border-b-0 md:border-b-0 md:border-r md:last:border-r-0">
      <p className="font-display text-lg font-extrabold text-navy-950">{title}</p>
      <p className="mt-2 text-sm font-semibold leading-relaxed text-navy-600">{children}</p>
    </div>
  )
}

function PlanCard({
  name,
  plan,
  interval,
  price,
  cadence,
  blurb,
  features,
  highlight = false,
}: {
  name: string
  plan?: PricingConsumerPlan | null
  interval?: BillingInterval
  price?: string
  cadence?: string
  blurb?: string
  features: string[]
  highlight?: boolean
}) {
  const displayPrice = plan ? naira(plan.price_minor) : price ?? '—'
  const displayCadence = plan ? (interval === 'year' ? 'per year' : 'per month') : cadence
  const monthlyEquivalent = plan && interval === 'year' ? `${naira(plan.price_minor / 12)}/month, billed annually` : null
  const dailyEquivalent = plan && interval === 'month' ? `About ${naira(plan.price_minor / 30)} a day` : null

  return (
    <Reveal>
      <article
        className={cn(
          'flex h-full flex-col rounded-[1rem] p-7 sm:p-8',
          highlight ? 'bg-navy-950 text-white shadow-md' : 'border border-chore-100 bg-white',
        )}
      >
        <div className="flex items-center justify-between gap-3">
          <h3 className={cn('font-display text-2xl font-extrabold', highlight ? 'text-white' : 'text-navy-950')}>
            {name}
          </h3>
          {highlight && (
            <span className="rounded-full bg-[#ffeadb] px-3 py-1 text-xs font-bold text-[#9f3400]">For households</span>
          )}
        </div>
        {blurb && <p className={cn('mt-2 text-sm font-semibold', highlight ? 'text-navy-100' : 'text-navy-600')}>{blurb}</p>}

        <div className="mt-7">
          <div className="flex flex-wrap items-baseline gap-x-2">
            <span className={cn('font-display text-4xl font-extrabold', highlight ? 'text-white' : 'text-navy-950')}>
              {displayPrice}
            </span>
            {displayCadence && (
              <span className={cn('text-sm font-semibold', highlight ? 'text-navy-100' : 'text-navy-500')}>
                {displayCadence}
              </span>
            )}
          </div>
          {(monthlyEquivalent || dailyEquivalent) && (
            <p className={cn('mt-2 text-sm font-bold', highlight ? 'text-[#ffb277]' : 'text-leaf-700')}>
              {monthlyEquivalent ?? dailyEquivalent}
            </p>
          )}
        </div>

        <ul className="my-7 flex flex-1 flex-col gap-3">
          {features.map((feature) => (
            <li key={feature} className={cn('flex items-start gap-3 text-sm font-semibold', highlight ? 'text-white' : 'text-navy-700')}>
              <span
                aria-hidden="true"
                className={cn(
                  'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full text-xs font-extrabold',
                  highlight ? 'bg-[#ffeadb] text-[#9f3400]' : 'bg-leaf-100 text-leaf-700',
                )}
              >
                ✓
              </span>
              {feature}
            </li>
          ))}
        </ul>

        <LinkButton
          to="/register"
          fullWidth
          size="lg"
          variant={highlight ? 'accent' : name === 'Free' ? 'outline' : 'parent'}
        >
          {name === 'Free' ? 'Start free' : `Choose ${name}`}
        </LinkButton>
      </article>
    </Reveal>
  )
}

function SchoolPricing({
  bands,
  termMonths,
  onOpenQuote,
}: {
  bands: PricingBand[]
  termMonths: number
  onOpenQuote: () => void
}) {
  return (
    <section id="school-pricing" className="scroll-mt-24 pt-16 sm:pt-24">
      <div className="overflow-hidden rounded-[1rem] bg-[#eef6ff]">
        <div className="grid lg:grid-cols-[0.86fr_1.14fr]">
          <Figure
            src="/images/school-classroom.webp"
            alt="A teacher guides three children through a Nigerian-language lesson on a shared tablet"
            className="min-h-[22rem] lg:min-h-full"
            imgClassName="object-center"
          />
          <div className="p-6 sm:p-10 lg:p-12">
            <p className="font-display text-lg font-extrabold text-[#a93403]">For schools and institutions</p>
            <h2 className="mt-3 max-w-[15ch] font-display text-3xl font-extrabold leading-tight text-navy-950 sm:text-5xl">
              One curriculum. Every learner visible.
            </h2>
            <p className="mt-5 max-w-2xl text-base font-semibold leading-relaxed text-navy-600 sm:text-lg">
              Annual registration plus a per-student rate that steps down as your school grows, covering a
              {` ${termMonths}`}-month academic year. Schools receive curriculum, rosters, assignments, reporting,
              billing tools, and the Language &amp; Culture club.
            </p>

            <div className="mt-8 overflow-x-auto rounded-xl bg-white">
              <table className="w-full min-w-[31rem] text-left text-sm">
                <caption className="sr-only">School pricing by number of students</caption>
                <thead className="bg-navy-950 text-white">
                  <tr>
                    <th scope="col" className="px-4 py-3 font-display font-bold">School size</th>
                    <th scope="col" className="px-4 py-3 font-display font-bold">Registration</th>
                    <th scope="col" className="px-4 py-3 font-display font-bold">Per student</th>
                  </tr>
                </thead>
                <tbody>
                  {bands.map((band) => (
                    <tr key={band.label} className="border-t border-chore-100">
                      <td className="px-4 py-3 font-bold text-navy-950">{band.label}</td>
                      <td className="px-4 py-3 font-semibold text-navy-700">{naira(band.registration_minor)}</td>
                      <td className="px-4 py-3 font-semibold text-navy-700">{naira(band.per_student_minor)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="mt-7 flex flex-col gap-3 sm:flex-row sm:items-center">
              <Button size="lg" variant="parent" onClick={onOpenQuote}>Request a school quote</Button>
              <p className="text-sm font-semibold text-navy-600">We’ll tailor the rollout to your school size.</p>
            </div>
          </div>
        </div>
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

  function update<K extends keyof typeof values>(key: K, value: (typeof values)[K]) {
    setValues((current) => ({ ...current, [key]: value }))
  }

  function handleClose() {
    onClose()
    setTimeout(() => {
      setSubmitted(false)
      setValues({ school_name: '', contact_name: '', email: '', phone: '', school_size: '', city: '' })
      setFieldErrors({})
      setFormError(null)
    }, 200)
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
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
    } catch (error) {
      if (error instanceof ApiError) {
        setFieldErrors(error.fieldErrors)
        if (!Object.keys(error.fieldErrors).length) setFormError(error.message)
      } else {
        setFormError('Something went wrong. Please try again.')
      }
    }
  }

  return (
    <Modal
      open={open}
      onClose={handleClose}
      title="Tell us about your school"
      description="We’ll follow up with a quote tailored to your school size."
    >
      {submitted ? (
        <div className="flex flex-col items-center gap-3 py-6 text-center">
          <span className="text-4xl" aria-hidden="true">🎉</span>
          <p className="font-display text-lg font-bold text-foreground">Thanks—we’ve got it.</p>
          <p className="text-sm text-muted">Our team will reach out with a quote shortly.</p>
          <Button variant="outline" onClick={handleClose}>Close</Button>
        </div>
      ) : (
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          {formError && <Alert variant="danger">{formError}</Alert>}
          <Input
            label="School name"
            value={values.school_name}
            onChange={(event) => update('school_name', event.target.value)}
            error={fieldErrors.school_name}
            autoFocus
            required
          />
          <Input
            label="Your name"
            value={values.contact_name}
            onChange={(event) => update('contact_name', event.target.value)}
            error={fieldErrors.contact_name}
            required
          />
          <div className="grid gap-3 sm:grid-cols-2">
            <Input
              label="Email"
              type="email"
              value={values.email}
              onChange={(event) => update('email', event.target.value)}
              error={fieldErrors.email}
              required
            />
            <Input
              label="Phone (optional)"
              value={values.phone}
              onChange={(event) => update('phone', event.target.value)}
              error={fieldErrors.phone}
            />
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="flex flex-col gap-1.5">
              <span className="text-sm font-semibold text-foreground">School size (optional)</span>
              <select
                value={values.school_size}
                onChange={(event) => update('school_size', event.target.value)}
                className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              >
                <option value="">Choose…</option>
                {SCHOOL_SIZES.map((size) => <option key={size} value={size}>{size}</option>)}
              </select>
            </label>
            <Input
              label="City (optional)"
              value={values.city}
              onChange={(event) => update('city', event.target.value)}
              error={fieldErrors.city}
            />
          </div>
          <Button type="submit" fullWidth loading={submit.isPending}>Request a quote</Button>
        </form>
      )}
    </Modal>
  )
}
