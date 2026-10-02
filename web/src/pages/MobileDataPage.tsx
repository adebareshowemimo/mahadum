import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Alert, Button, Icon, Input, LinkButton, Skeleton } from '@/components/ui'
import type { DataBundle } from '@/lib/api'
import { cn } from '@/lib/cn'
import { formatMoney } from '@/lib/format'
import { dataStoreError, useDataStore } from '@/lib/billing/useDataStore'

function validity(plan: DataBundle): string {
  if (!plan.duration || !plan.duration_unit) return 'Validity not specified'
  const raw = plan.duration_unit.toLowerCase()
  const unit = ({ daily: 'day', weekly: 'week', monthly: 'month', yearly: 'year' } as Record<string, string>)[raw] ?? raw.replace(/s$/, '')
  return `${plan.duration} ${unit}${plan.duration === 1 ? '' : 's'}`
}
function days(plan: DataBundle): number | null {
  if (!plan.duration || !plan.duration_unit) return null
  const multiplier = ({ day: 1, days: 1, daily: 1, week: 7, weeks: 7, weekly: 7, month: 30, months: 30, monthly: 30, year: 365, yearly: 365 } as Record<string, number>)[plan.duration_unit.toLowerCase()]
  return multiplier ? plan.duration * multiplier : null
}
function size(plan: DataBundle): string {
  const match = plan.name.match(/(\d+(?:\.\d+)?)\s*(MB|GB|TB)\b/i)
  return match ? `${match[1]} ${match[2].toUpperCase()}` : plan.name.replace(/_/g, ' ')
}
function networkStyle(code: string): string {
  if (/mtn/i.test(code)) return 'bg-yellow-400 text-zinc-950'
  if (/airtel/i.test(code)) return 'bg-red-600 text-white'
  if (/glo/i.test(code)) return 'bg-green-700 text-white'
  return 'bg-emerald-900 text-white'
}

export function MobileDataPage() {
  const store = useDataStore()
  const [filter, setFilter] = useState('all')
  const [sort, setSort] = useState('low')
  const network = store.billers.data?.find((b) => b.code === store.billerCode)
  const plans = store.results.filter((p) => filter === 'all' || (days(p) !== null && (filter === 'short' ? days(p)! <= 7 : days(p)! > 7)))
    .sort((a, b) => sort === 'validity' ? (days(a) ?? Infinity) - (days(b) ?? Infinity) : sort === 'high'
      ? (b.amount_minor ?? -1) - (a.amount_minor ?? -1) : (a.amount_minor ?? Infinity) - (b.amount_minor ?? Infinity))
  const validPhone = /^0[789][01][0-9]{8}$/.test(store.phone)
  const canPay = store.selected && store.consent && validPhone && !store.catalogueError && !store.bundles.isFetching
  const panel = 'rounded-3xl border border-border bg-surface p-5 sm:p-6'

  return (
    <div className="space-y-6">
      <Link to="/billing" className="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-muted hover:text-foreground"><Icon name="arrow-left" className="size-4" /> Back to billing</Link>
      <header className="relative overflow-hidden rounded-3xl border border-emerald-800 bg-gradient-to-br from-emerald-950 via-emerald-900 to-teal-800 px-6 py-7 text-white sm:px-8 sm:py-8">
        <div className="relative z-10 max-w-xl">
          <p className="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-200"><Icon name="signal" className="size-4" /> Stay connected</p>
          <h1 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">A little data. More possibility.</h1>
          <p className="mt-3 max-w-md text-sm leading-6 text-emerald-100">Top up your line or someone you love. Choose a network, find your plan, and keep learning.</p>
          <ol className="mt-6 flex flex-wrap gap-x-5 gap-y-3 text-xs font-medium text-emerald-100" aria-label="Purchase steps">
            {['Choose a plan', 'Add a recipient', 'Pay securely'].map((step, i) => <li key={step} className="flex items-center gap-2"><span className="flex size-6 items-center justify-center rounded-full border border-white/25 bg-white/10 text-white">{i + 1}</span>{step}</li>)}
          </ol>
        </div>
        <div aria-hidden="true" className="pointer-events-none absolute -right-12 -top-16 size-80 rounded-full border-[40px] border-white/5" />
        <Icon name="signal" aria-hidden="true" className="pointer-events-none absolute bottom-7 right-10 hidden size-28 text-white/10 xl:block" />
      </header>

      {store.purchaseId !== null ? (
        <section className={cn(panel, 'mx-auto max-w-2xl space-y-5')} aria-label="Purchase status">
          <div className="flex size-14 items-center justify-center rounded-2xl bg-primary-soft text-primary"><Icon name={store.status === 'success' ? 'check' : 'card'} className="size-7" /></div>
          <h2 className="font-display text-2xl font-bold">{store.status === 'success' ? 'You’re connected.' : 'Your data purchase'}</h2>
          {store.purchase.isLoading && <p role="status">Checking your purchase…</p>}
          {store.purchase.error && <Alert variant="danger">{dataStoreError(store.purchase.error)}</Alert>}
          {store.purchase.data && <>
            <div className="rounded-2xl bg-surface-muted p-4">
              <p className="font-semibold">{store.purchase.data.product_name.replace(/_/g, ' ')}</p>
              <dl className="mt-3 space-y-2 text-sm"><div className="flex justify-between gap-3"><dt className="text-muted">Recipient</dt><dd>{store.purchase.data.phone_number}</dd></div><div className="flex justify-between gap-3"><dt className="text-muted">Total</dt><dd className="font-bold">{formatMoney(store.purchase.data.amount_minor, 'NGN')}</dd></div></dl>
            </div>
            {store.status === 'awaiting_payment' && <>
              <Alert variant="info" title="Ready for payment">Your checkout is ready. Pay by card or bank transfer through Monnify. Delivery starts after payment is confirmed.</Alert>
              {store.purchase.data.checkout_url ? <a className="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-gold-500 px-4 font-semibold text-charcoal-900 hover:bg-gold-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring" href={store.purchase.data.checkout_url} target="_blank" rel="noopener noreferrer">Open Monnify checkout <span className="sr-only">(opens a new tab)</span></a> : <Alert variant="danger">Checkout could not be opened. Contact support with the reference below.</Alert>}
            </>}
            {store.status === 'processing' && <Alert variant="info" title="Delivering your data">Payment confirmed. Your network is processing the bundle. You can leave this page and check back.</Alert>}
            {store.status === 'success' && <Alert variant="success" title="Data delivered">Monnify confirmed your purchase. You’re ready to keep going.</Alert>}
            {store.status === 'payment_failed' && <Alert variant="danger" title="Payment unsuccessful">Your data purchase was not submitted. You can choose a plan and try again.</Alert>}
            {(store.status === 'failed' || store.status === 'needs_review') && <Alert variant="danger" title="Let’s check your purchase">Delivery couldn’t be confirmed. Contact support before making another purchase.</Alert>}
            <p className="break-all text-xs text-muted">Reference: {store.purchase.data.payment_reference}</p>
          </>}
          <div className="flex flex-wrap gap-3"><Button variant="outline" loading={store.purchase.isFetching} onClick={() => void store.purchase.refetch()}>Check status</Button>{(store.status === 'success' || store.status === 'payment_failed') && <Button onClick={store.reset}>Choose another plan</Button>}<LinkButton to="/support" variant="ghost">Get help</LinkButton></div>
        </section>
      ) : (
        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_310px]">
          <div className="min-w-0 space-y-6">
            <section className={panel} aria-labelledby="network-heading">
              <div className="mb-5"><p className="text-xs font-semibold uppercase tracking-wider text-primary">Step 01</p><h2 id="network-heading" className="mt-1 font-display text-xl font-bold">Choose your network</h2><p className="mt-1 text-sm text-muted">Use the network of the number receiving the data.</p></div>
              {store.billers.isLoading && <div className="grid grid-cols-2 gap-3 sm:grid-cols-4" role="status" aria-label="Loading networks">{[1, 2, 3, 4].map((n) => <Skeleton key={n} className="h-24 rounded-2xl" />)}</div>}
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4" role="group" aria-label="Network">
                {store.billers.data?.map((b) => <button key={b.code} type="button" aria-pressed={store.billerCode === b.code} disabled={store.busy} onClick={() => { store.chooseNetwork(b.code); setFilter('all') }} className={cn('relative flex min-h-24 flex-col items-center justify-center gap-2 rounded-2xl border-2 p-3 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50', store.billerCode === b.code ? 'border-primary bg-primary-soft' : 'border-border bg-surface hover:border-border-strong hover:bg-surface-muted')}>
                  <span className={cn('flex size-10 items-center justify-center rounded-full text-[10px] font-bold tracking-tight', networkStyle(b.code))}>{b.name.slice(0, 7)}</span>
                  <span className="text-xs font-semibold">{b.name}</span>
                  {store.billerCode === b.code && <Icon name="check" className="absolute right-2 top-2 size-3.5 text-primary" />}
                </button>)}
              </div>
              {!store.billers.isLoading && !store.billers.error && !store.billers.data?.length && <p className="text-sm text-muted">No mobile networks are available right now.</p>}
            </section>

            <section id="data-plans" className={panel} aria-labelledby="plans-heading">
              <div className="mb-5 flex flex-wrap items-center justify-between gap-2"><div><h2 id="plans-heading" className="font-display text-xl font-bold">Find your fit</h2><p className="mt-1 text-sm text-muted">{network?.name ?? 'Mobile'} plans for the way you connect.</p></div><span className="rounded-full bg-surface-muted px-3 py-1 text-xs font-medium text-muted">{plans.length} {plans.length === 1 ? 'plan' : 'plans'}</span></div>
              <Input label="Find a plan" type="search" value={store.search} onChange={(e) => store.setSearch(e.target.value)} placeholder="Search data size or plan name" disabled={store.busy} leftIcon={<Icon name="search" className="size-4" />} />
              <div className="my-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap gap-2" role="group" aria-label="Plan validity">{[['all', 'All plans'], ['short', 'Up to 7 days'], ['long', '8 days or more']].map(([value, label]) => <button type="button" key={value} aria-pressed={filter === value} onClick={() => setFilter(value)} className={cn('min-h-9 rounded-full px-3 text-xs font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring', filter === value ? 'bg-primary text-primary-fg' : 'bg-surface-muted text-muted hover:text-foreground')}>{label}</button>)}</div>
                <label className="flex items-center gap-2 text-xs text-muted"><span className="sr-only">Sort plans</span><select value={sort} onChange={(e) => setSort(e.target.value)} className="min-h-9 max-w-full rounded-lg border border-border bg-surface px-2 text-xs text-foreground focus-visible:outline focus-visible:outline-ring"><option value="low">Price: low to high</option><option value="high">Price: high to low</option><option value="validity">Validity: shortest first</option></select></label>
              </div>
              {store.catalogueError && <div className="space-y-3"><Alert variant="danger">{dataStoreError(store.catalogueError)}</Alert><Button variant="outline" onClick={() => { void store.billers.refetch(); if (store.billerCode) void store.bundles.refetch() }}>Retry catalogue</Button></div>}
              {store.bundles.isFetching ? <div className="grid grid-cols-1 gap-3 sm:grid-cols-2" role="status" aria-label="Loading plans">{[1, 2].map((n) => <Skeleton key={n} className="h-44 rounded-2xl" />)}</div> : !store.catalogueError && plans.length === 0 ? <div className="rounded-2xl border border-dashed border-border-strong px-5 py-10 text-center"><Icon name="signal" className="mx-auto mb-3 size-8 text-subtle" /><p className="font-semibold">{store.search || filter !== 'all' ? 'No matching plans' : 'No plans available yet'}</p><p className="mt-2 text-sm leading-6 text-muted">{store.search || filter !== 'all' ? 'Try a different search or validity filter.' : 'There are no data bundles for this network right now. Try another network or check back later.'}</p>{(store.search || filter !== 'all') && <Button variant="ghost" className="mt-3" onClick={() => { store.setSearch(''); setFilter('all') }}>Clear filters</Button>}</div> : <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3" aria-label="Data plans">
                {plans.map((p) => {
                  const available = p.price_type === 'FIXED' && p.amount_minor !== null && p.amount_minor > 0
                  const active = store.selected?.product_code === p.product_code
                  return <button key={p.product_code} type="button" aria-label={`${p.name}, ${validity(p)}, ${available ? formatMoney(p.amount_minor!, p.currency) : 'unavailable'}`} aria-pressed={active} disabled={!available || store.busy} onClick={() => { store.setSelected(p); store.changed() }} className={cn('flex min-h-44 min-w-0 flex-col rounded-2xl border-2 p-4 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50', active ? 'border-primary bg-primary-soft' : 'border-border bg-surface hover:border-primary/50 hover:bg-surface-muted')}>
                    <span className="mb-4 flex items-center justify-between gap-2"><span className="rounded-lg bg-surface-muted px-2 py-1 text-xs font-medium text-muted">{validity(p)}</span><span className={cn('flex size-5 shrink-0 items-center justify-center rounded-full border', active ? 'border-primary bg-primary text-primary-fg' : 'border-border-strong')}>{active && <Icon name="check" className="size-3" />}</span></span>
                    <span className="break-words font-display text-2xl font-bold tracking-tight">{size(p)}</span>
                    <span className="mt-2 text-xs text-muted">One-time data bundle</span>
                    <span className="mt-auto pt-5 text-lg font-bold">{available ? formatMoney(p.amount_minor!, p.currency) : 'Unavailable'}</span>
                  </button>
                })}
              </div>}
              <p className="mt-4 text-xs leading-5 text-muted">Plans and prices are supplied by your network through Monnify.</p>
            </section>

            <section className={panel} aria-labelledby="recipient-heading"><p className="text-xs font-semibold uppercase tracking-wider text-primary">Step 02</p><h2 id="recipient-heading" className="mb-4 mt-1 font-display text-xl font-bold">Who’s receiving the data?</h2>
              <Input label="Recipient phone number" type="tel" inputMode="tel" autoComplete="tel-national" maxLength={11} placeholder="08012345678" hint="Enter the 11-digit Nigerian number. It can be yours or someone else’s." value={store.phone} error={store.fields.phone_number || (store.phone.length === 11 && !validPhone ? 'Check the number. Use an 11-digit Nigerian mobile number.' : undefined)} disabled={store.busy} onChange={(e) => { store.setPhone(e.target.value.replace(/[^0-9]/g, '')); store.changed() }} />
              {store.selected && <a href="#data-summary" className="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-primary lg:hidden">Review your purchase →</a>}
            </section>
          </div>

          <aside id="data-summary" className={cn(panel, 'scroll-mt-24 lg:sticky lg:top-24')} aria-labelledby="summary-heading">
            <div className="flex items-center gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-gold-400/15 text-gold-700 dark:text-gold-300"><Icon name="card" className="size-5" /></span><div><p className="text-xs font-medium text-muted">Step 03</p><h2 id="summary-heading" className="font-display text-lg font-bold">Your purchase</h2></div></div>
            <div className="my-5 rounded-2xl bg-surface-muted p-4"><p className="text-xs text-muted">{network?.name ?? 'Your network'}</p><p className="mt-2 font-display text-2xl font-bold">{store.selected ? size(store.selected) : 'Choose your plan'}</p><p className="mt-1 text-xs text-muted">{store.selected ? `Valid for ${validity(store.selected)}` : 'Your selected bundle will appear here.'}</p></div>
            <dl className="space-y-3 text-sm"><div className="flex justify-between gap-3"><dt className="text-muted">Recipient</dt><dd className="font-medium">{validPhone ? store.phone : 'Add a number'}</dd></div><div className="flex justify-between gap-3"><dt className="text-muted">Payment</dt><dd>Card or transfer</dd></div><div className="flex justify-between gap-3 border-t border-border pt-4"><dt className="font-semibold">Total to pay</dt><dd className="text-xl font-bold">{store.selected?.amount_minor ? formatMoney(store.selected.amount_minor, store.selected.currency) : '—'}</dd></div></dl>
            {store.selected && <details className="mt-4 text-xs text-muted"><summary className="cursor-pointer py-2">Plan details</summary><p className="mt-1 break-words leading-5">{store.selected.name.replace(/_/g, ' ')}</p></details>}
            <label className="my-5 flex items-start gap-3 text-xs leading-5 text-muted"><input type="checkbox" checked={store.consent} disabled={store.busy} onChange={(e) => store.setConsent(e.target.checked)} className="mt-0.5 size-4 shrink-0 accent-primary" /><span>I’ve checked the network, plan, and recipient number.</span></label>
            {store.error && <div className="mb-4"><Alert variant="danger">{store.error}</Alert></div>}
            <Button variant="billing" size="lg" fullWidth loading={store.busy} disabled={!canPay} onClick={store.buy}>{store.selected ? 'Continue to payment' : 'Choose a plan'}</Button>
            <p className="mt-3 flex items-center justify-center gap-1.5 text-[11px] text-muted"><Icon name="shield" className="size-3.5" /> Secure checkout with Monnify</p>
            <p className="mt-5 border-t border-border pt-4 text-xs leading-5 text-muted">We confirm your payment before delivering data. Double-check the number and network before paying.</p>
            <Link to="/support" className="mt-3 inline-flex min-h-9 items-center text-xs font-semibold text-primary">Need a hand? Get help</Link>
          </aside>
        </div>
      )}
    </div>
  )
}
