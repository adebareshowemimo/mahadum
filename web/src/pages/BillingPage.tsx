import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import {
  Alert,
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  CardTitle,
  Icon,
  Input,
  LinkButton,
  Skeleton,
} from '@/components/ui'
import { ApiError, billingApi, type Plan, type PromoPreview } from '@/lib/api'
import { cn } from '@/lib/cn'
import { formatMoney } from '@/lib/format'
import { useAuth } from '@/lib/auth/AuthProvider'
import { useConfig } from '@/lib/config/useConfig'
import { useEntitlements } from '@/lib/billing/entitlements'
import { planFeatures } from '@/lib/billing/planFeatures'
import {
  useCancelSubscription,
  useChangeSubscription,
  useCreateSubscription,
  usePlans,
  useRetrySubscription,
  useSubscriptions,
  useTelcoStatus,
} from '@/lib/billing/queries'
import { useFamily } from '@/lib/family/queries'
import { TelcoOptInModal } from '@/components/billing/TelcoOptInModal'
import { DataBundleModal } from '@/components/billing/DataBundleModal'

/**
 * A subscription id the SPA has cached (via /me) can go stale if it was acted
 * on elsewhere (e.g. an admin/ops action) — the row itself is never hard-deleted
 * in normal use, only cancelled, but defend against a dangling reference anyway
 * rather than surfacing the raw "No query results for model [...]" 404 text.
 */
function friendlyBillingError(err: unknown, fallback: string): string {
  if (err instanceof ApiError) {
    if (err.status === 404) return 'Your subscription info was out of date — we’ve refreshed it. Please try again.'
    return err.message
  }
  return fallback
}

export function BillingPage() {
  const { user, hasRole } = useAuth()
  const queryClient = useQueryClient()
  const entitlements = useEntitlements()
  const config = useConfig()
  const telcoBillingEnabled = config.data?.feature_flags.telco_billing === true
  const plans = usePlans()
  const history = useSubscriptions()
  const createSub = useCreateSubscription()
  const cancelSub = useCancelSubscription()
  const changeSub = useChangeSubscription()
  const retrySub = useRetrySubscription()

  const telco = useTelcoStatus()
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busyPlan, setBusyPlan] = useState<number | null>(null)
  const [retryingId, setRetryingId] = useState<number | null>(null)
  const [telcoPlan, setTelcoPlan] = useState<Plan | null>(null)
  const [bundleOpen, setBundleOpen] = useState(false)
  const [promoInput, setPromoInput] = useState('')
  const [promo, setPromo] = useState<{ code: string; byPlan: Record<number, PromoPreview> } | null>(null)
  const [promoError, setPromoError] = useState<string | null>(null)
  const [promoBusy, setPromoBusy] = useState(false)

  const sub = user?.subscription ?? null
  const telcoState = telco.data?.state
  const hasActiveSub = sub != null && sub.status !== 'cancelled'

  // Individual and Family Premium both allow a parental PIN — surfaced here too
  // (not just on /family) so a premium subscriber can find it right away.
  const isPremiumPlan = entitlements.tier.startsWith('premium_')
  const family = useFamily(isPremiumPlan && hasRole('parent'))

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
      setError(friendlyBillingError(err, 'Could not cancel the subscription.'))
      if (err instanceof ApiError && err.status === 404) void queryClient.invalidateQueries({ queryKey: ['me'] })
    }
  }

  /** Switch the current subscription straight to a different plan (no manual cancel-then-resubscribe). */
  async function changePlan(plan: Plan) {
    if (!sub) return
    setBusyPlan(plan.id)
    setError(null)
    setNotice(null)
    try {
      const promoCode = promo?.byPlan[plan.id] ? promo.code : undefined
      const res = await changeSub.mutateAsync({ id: sub.id, input: { plan_id: plan.id, method: 'card', promo_code: promoCode } })
      if (res.checkout_url) {
        window.location.href = res.checkout_url
      } else {
        setNotice('Plan changed. Complete payment at checkout to activate — your new plan unlocks once payment is confirmed.')
      }
    } catch (err) {
      setError(friendlyBillingError(err, 'Could not change your plan.'))
      if (err instanceof ApiError && err.status === 404) void queryClient.invalidateQueries({ queryKey: ['me'] })
    } finally {
      setBusyPlan(null)
    }
  }

  /**
   * Resume a still-pending card subscription. The server checks the gateway
   * directly first — covers a completed payment whose webhook hasn't arrived
   * (e.g. it can't reach this environment) — and activates right away, or
   * opens a fresh checkout so the subscriber can pay again.
   */
  async function retryPayment(subscriptionId: number) {
    setRetryingId(subscriptionId)
    setError(null)
    setNotice(null)
    try {
      const res = await retrySub.mutateAsync(subscriptionId)
      if (res.checkout_url) {
        window.location.href = res.checkout_url
      } else if (res.status === 'active') {
        setNotice('Payment confirmed — your plan is now active.')
      } else {
        setNotice('Still waiting on payment confirmation. Please try again shortly.')
      }
    } catch (err) {
      setError(friendlyBillingError(err, 'Could not retry the payment.'))
      if (err instanceof ApiError && err.status === 404) void queryClient.invalidateQueries({ queryKey: ['me'] })
    } finally {
      setRetryingId(null)
    }
  }

  // Personal plans only (school tiers are sold per-seat in the school console).
  const personalPlans = (plans.data ?? []).filter(
    (p) => p.audience !== 'school' && p.audience !== 'teacher' && !p.code.startsWith('school'),
  )

  /** Months saved vs. the equivalent monthly plan, for an annual plan's "N months free" badge. */
  function annualMonthsFree(plan: Plan): number | null {
    if (plan.interval !== 'year' || !plan.code.endsWith('_annual')) return null
    const monthly = personalPlans.find((p) => p.code === plan.code.replace(/_annual$/, '') && p.interval === 'month')
    if (!monthly || monthly.price_minor <= 0) return null
    const monthsFree = Math.round(12 - plan.price_minor / monthly.price_minor)
    return monthsFree > 0 ? monthsFree : null
  }

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
            const monthsFree = annualMonthsFree(plan)
            return (
              <Card key={plan.id} className={cn(isCurrent && 'border-primary ring-1 ring-primary')}>
                <CardBody className="flex flex-col gap-4">
                  <div>
                    <div className="flex items-center justify-between">
                      <h2 className="font-display text-lg font-bold text-foreground">{plan.name}</h2>
                      <div className="flex items-center gap-1.5">
                        {monthsFree != null && <Badge variant="gold">Get {monthsFree} months free</Badge>}
                        {isCurrent && <Badge variant="primary">Current</Badge>}
                      </div>
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
                    ) : hasActiveSub ? (
                      <Button variant="premium" fullWidth loading={busyPlan === plan.id} onClick={() => changePlan(plan)}>
                        Switch to this plan
                      </Button>
                    ) : (
                      <div className="flex flex-col gap-2">
                        <Button variant="premium" fullWidth loading={busyPlan === plan.id} onClick={() => subscribe(plan)}>
                          Choose {plan.name}
                        </Button>
                        {telcoBillingEnabled && plan.audience === 'individual' && plan.interval === 'month' && (
                          <Button variant="ghost" size="sm" fullWidth onClick={() => setTelcoPlan(plan)}>
                            or pay with airtime
                          </Button>
                        )}
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

      {isPremiumPlan && hasRole('parent') && family.data && (
        <Card>
          <CardHeader>
            <CardTitle>Parental PIN</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-2">
              <Icon name="shield" className="text-muted" />
              <div>
                <Badge variant={family.data.learners.some((l) => l.pin_protected) ? 'success' : 'warning'}>
                  {family.data.learners.filter((l) => l.pin_protected).length} of {family.data.learners.length} profiles PIN'd
                </Badge>
                <p className="mt-1 text-sm text-muted">
                  {entitlements.tier_name} includes a unique parental PIN per child to protect profile switching.
                </p>
              </div>
            </div>
            <LinkButton to="/family" variant="outline" size="sm">
              Manage on Family page
            </LinkButton>
          </CardBody>
        </Card>
      )}

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
                    {s.status === 'pending' && s.method === 'card' && (
                      <Button
                        variant="outline"
                        size="sm"
                        loading={retryingId === s.id}
                        onClick={() => retryPayment(s.id)}
                      >
                        Retry payment
                      </Button>
                    )}
                  </div>
                </CardBody>
              </Card>
            ))}
          </div>
        )}
      </section>

      {telcoBillingEnabled && (
        <TelcoOptInModal plan={telcoPlan} open={telcoPlan != null} onClose={() => setTelcoPlan(null)} />
      )}
      <DataBundleModal open={bundleOpen} onClose={() => setBundleOpen(false)} />
    </div>
  )
}
