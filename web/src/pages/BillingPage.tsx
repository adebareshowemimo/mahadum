import { useState } from 'react'
import {
  Alert,
  Badge,
  Button,
  Card,
  CardBody,
  Icon,
  Input,
  Skeleton,
} from '@/components/ui'
import { ApiError, billingApi, type Plan, type PromoPreview } from '@/lib/api'
import { cn } from '@/lib/cn'
import { formatMoney } from '@/lib/format'
import { useAuth } from '@/lib/auth/AuthProvider'
import { useEntitlements } from '@/lib/billing/entitlements'
import {
  useCancelSubscription,
  useCreateSubscription,
  usePlans,
  useSubscriptions,
  useTelcoStatus,
} from '@/lib/billing/queries'
import { TelcoOptInModal } from '@/components/billing/TelcoOptInModal'
import { DataBundleModal } from '@/components/billing/DataBundleModal'

/** Build a readable feature list for a plan card. */
function planFeatures(plan: Plan): string[] {
  const f = plan.features ?? {}
  const out: string[] = []
  out.push(plan.max_profiles == null ? 'Unlimited profiles (per seat)' : `Up to ${plan.max_profiles} profile${plan.max_profiles === 1 ? '' : 's'}`)
  if (f.ads === false) out.push('Ad-free')
  else out.push('Ad-supported')
  if (f.unlimited_hearts) out.push('Unlimited hearts')
  if (f.offline_download) out.push('Offline downloads')
  if (f.family_dashboard) out.push('Family dashboard, chores & monitoring')
  if (f.teacher_analytics) out.push('Teacher analytics')
  if (f.seats) out.push('Classroom seats')
  return out
}

export function BillingPage() {
  const { user } = useAuth()
  const entitlements = useEntitlements()
  const plans = usePlans()
  const history = useSubscriptions()
  const createSub = useCreateSubscription()
  const cancelSub = useCancelSubscription()

  const telco = useTelcoStatus()
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busyPlan, setBusyPlan] = useState<number | null>(null)
  const [telcoPlan, setTelcoPlan] = useState<Plan | null>(null)
  const [bundleOpen, setBundleOpen] = useState(false)
  const [promoInput, setPromoInput] = useState('')
  const [promo, setPromo] = useState<{ code: string; byPlan: Record<number, PromoPreview> } | null>(null)
  const [promoError, setPromoError] = useState<string | null>(null)
  const [promoBusy, setPromoBusy] = useState(false)

  const sub = user?.subscription ?? null
  const telcoState = telco.data?.state

  // Preview a promo code against every paid plan; keep the tiers it applies to.
  async function applyPromo() {
    const code = promoInput.trim()
    if (!code) return
    setPromoBusy(true)
    setPromoError(null)
    const paid = (personalPlans ?? []).filter((p) => p.price_minor > 0)
    const entries = await Promise.all(
      paid.map(async (p): Promise<[number, PromoPreview] | null> => {
        try {
          return [p.id, await billingApi.previewPromo(p.id, code)]
        } catch {
          return null // not applicable to this plan
        }
      }),
    )
    const byPlan = Object.fromEntries(entries.filter((e): e is [number, PromoPreview] => e !== null))
    setPromoBusy(false)
    if (Object.keys(byPlan).length === 0) {
      setPromo(null)
      setPromoError('That promo code isn’t valid for any of these plans.')
    } else {
      setPromo({ code, byPlan })
    }
  }

  async function subscribe(plan: Plan) {
    setBusyPlan(plan.id)
    setError(null)
    setNotice(null)
    try {
      const promoCode = promo?.byPlan[plan.id] ? promo.code : undefined
      const res = await createSub.mutateAsync({ plan_id: plan.id, method: 'card', promo_code: promoCode })
      if (res.checkout_url) {
        window.location.href = res.checkout_url
      } else {
        setNotice('Subscription created. Complete payment at checkout to activate — your plan unlocks once payment is confirmed.')
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not start the subscription.')
    } finally {
      setBusyPlan(null)
    }
  }

  async function cancel() {
    if (!sub) return
    setError(null)
    try {
      const res = await cancelSub.mutateAsync(sub.id)
      setNotice(res.message)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not cancel the subscription.')
    }
  }

  // Personal plans only (school tiers are sold per-seat in the school console).
  const personalPlans = (plans.data ?? []).filter(
    (p) => p.audience !== 'school' && p.audience !== 'teacher' && !p.code.startsWith('school'),
  )

  return (
    <div className="flex flex-col gap-8">
      <div>
        <h1 className="font-display text-2xl font-bold text-foreground">Plans & billing</h1>
        <p className="mt-1 text-muted">You’re on the {entitlements.tier_name} plan.</p>
      </div>

      {sub?.status === 'grace' && (
        <Alert variant="grace" title="Payment needed">
          Your subscription is in its grace period. Update payment to keep premium features.
        </Alert>
      )}
      {sub?.status === 'pending' && (
        <Alert variant="info" title="Payment pending">
          We’re waiting for your payment to confirm. Premium unlocks automatically once it does.
        </Alert>
      )}
      {(telcoState === 'grace' || telcoState === 'soft_downgrade') && (
        <Alert variant="grace" title="Low airtime balance">
          Your airtime subscription is in its grace period — top up your line to keep premium active.
        </Alert>
      )}
      {notice && <Alert variant="info">{notice}</Alert>}
      {error && <Alert variant="danger">{error}</Alert>}

      {/* Promo code — previews the discount against the plans it applies to. */}
      <div className="flex flex-wrap items-end gap-3">
        <div className="min-w-[12rem] flex-1">
          <Input
            label="Have a promo code?"
            value={promoInput}
            onChange={(e) => { setPromoInput(e.target.value); setPromoError(null) }}
            placeholder="e.g. WELCOME20"
          />
        </div>
        <Button variant="secondary" loading={promoBusy} disabled={!promoInput.trim()} onClick={applyPromo}>
          Apply
        </Button>
        {promo && (
          <span className="pb-2.5 text-sm font-semibold text-leaf-600">Code “{promo.code}” applied ✓</span>
        )}
      </div>
      {promoError && <Alert variant="danger">{promoError}</Alert>}

      {plans.isLoading ? (
        <Skeleton className="h-64" />
      ) : (
        <div className="grid gap-4 md:grid-cols-3">
          {personalPlans.map((plan) => {
            const isCurrent = plan.code === entitlements.tier
            const isFree = plan.price_minor === 0
            const applied = promo?.byPlan[plan.id]
            return (
              <Card key={plan.id} className={cn(isCurrent && 'border-primary ring-1 ring-primary')}>
                <CardBody className="flex flex-col gap-4">
                  <div>
                    <div className="flex items-center justify-between">
                      <h2 className="font-display text-lg font-bold text-foreground">{plan.name}</h2>
                      {isCurrent && <Badge variant="primary">Current</Badge>}
                    </div>
                    <p className="mt-1 font-display text-2xl font-extrabold text-foreground">
                      {isFree ? (
                        'Free'
                      ) : applied ? (
                        <>
                          <span className="mr-2 text-base font-normal text-muted line-through">
                            {formatMoney(plan.price_minor, plan.currency)}
                          </span>
                          {formatMoney(applied.final_minor, plan.currency)}
                        </>
                      ) : (
                        formatMoney(plan.price_minor, plan.currency)
                      )}
                      {!isFree && <span className="text-sm font-normal text-muted">/{plan.interval}</span>}
                    </p>
                    {applied && (
                      <p className="mt-0.5 text-xs font-semibold text-leaf-600">
                        {promo?.code} · {formatMoney(applied.discount_minor, plan.currency)} off
                      </p>
                    )}
                  </div>

                  <ul className="flex flex-col gap-1.5 text-sm">
                    {planFeatures(plan).map((feat) => (
                      <li key={feat} className="flex items-start gap-2 text-foreground">
                        <Icon name="sparkles" className="mt-0.5 size-4 shrink-0 text-primary" />
                        {feat}
                      </li>
                    ))}
                  </ul>

                  <div className="mt-auto">
                    {isCurrent ? (
                      !isFree ? (
                        <Button variant="outline" fullWidth loading={cancelSub.isPending} onClick={cancel}>
                          Cancel plan
                        </Button>
                      ) : (
                        <Button variant="secondary" fullWidth disabled>
                          Current plan
                        </Button>
                      )
                    ) : isFree ? (
                      <Button variant="secondary" fullWidth disabled>
                        Included
                      </Button>
                    ) : (
                      <div className="flex flex-col gap-2">
                        <Button variant="premium" fullWidth loading={busyPlan === plan.id} onClick={() => subscribe(plan)}>
                          Choose {plan.name}
                        </Button>
                        <Button variant="ghost" size="sm" fullWidth onClick={() => setTelcoPlan(plan)}>
                          or pay with airtime
                        </Button>
                      </div>
                    )}
                  </div>
                </CardBody>
              </Card>
            )
          })}
        </div>
      )}

      <Card>
        <CardBody className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="font-display text-lg font-bold text-foreground">Mobile data</p>
            <p className="text-sm text-muted">Top up data, charged to your airtime balance.</p>
          </div>
          <Button variant="billing" onClick={() => setBundleOpen(true)}>
            Buy data
          </Button>
        </CardBody>
      </Card>

      <section>
        <h2 className="mb-3 font-display text-lg font-bold text-foreground">Billing history</h2>
        {history.isLoading ? (
          <Skeleton className="h-24" />
        ) : (history.data?.length ?? 0) === 0 ? (
          <Card>
            <CardBody className="py-8 text-center text-sm text-muted">No subscriptions yet.</CardBody>
          </Card>
        ) : (
          <div className="flex flex-col gap-2">
            {history.data?.map((s) => (
              <Card key={s.id}>
                <CardBody className="flex flex-wrap items-center justify-between gap-3 py-3">
                  <div>
                    <p className="font-semibold text-foreground">{s.plan_name ?? s.plan_code}</p>
                    <p className="text-xs capitalize text-muted">
                      {s.method} · {s.started_at ? new Date(s.started_at).toLocaleDateString() : 'not started'}
                    </p>
                  </div>
                  <div className="flex items-center gap-3">
                    {s.price_minor != null && (
                      <span className="text-sm text-muted">{formatMoney(s.price_minor, 'NGN')}</span>
                    )}
                    <Badge variant={s.status === 'active' ? 'success' : s.status === 'cancelled' ? 'neutral' : 'gold'}>
                      {s.status}
                    </Badge>
                  </div>
                </CardBody>
              </Card>
            ))}
          </div>
        )}
      </section>

      <TelcoOptInModal plan={telcoPlan} open={telcoPlan != null} onClose={() => setTelcoPlan(null)} />
      <DataBundleModal open={bundleOpen} onClose={() => setBundleOpen(false)} />
    </div>
  )
}
