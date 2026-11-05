import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { useParams } from 'react-router-dom'
import { Alert, Button, Icon, LinkButton } from '@/components/ui'
import { billingApi, familyApi, learningApi, schoolApi } from '@/lib/api'
import { formatMoney } from '@/lib/format'

type ResultState = 'success' | 'pending' | 'failed' | 'review'
type Detail = { label: string; value: string }

function date(value: string | null | undefined): string {
  return value ? new Date(value).toLocaleString() : '—'
}

function Result({ item, state, message, details, onCheck, checking, destination, destinationLabel, extraAction }: {
  item: string
  state: ResultState
  message: string
  details: Detail[]
  onCheck: () => void
  checking: boolean
  destination: string
  destinationLabel: string
  extraAction?: ReactNode
}) {
  const title = ({ success: 'Payment confirmed', pending: 'Payment being checked', failed: 'Payment not completed', review: 'Payment needs review' } as const)[state]
  return <div className="mx-auto max-w-2xl space-y-6">
    <header><p className="text-sm font-semibold text-primary">{item}</p><h1 className="mt-2 font-display text-3xl font-bold">{title}</h1><p className="mt-2 text-muted">{message}</p></header>
    <section aria-label="Payment details" className="space-y-5 rounded-3xl border border-border bg-surface p-5 sm:p-7">
      <Icon name={state === 'success' ? 'check' : 'card'} className="size-10 text-primary" />
      <Alert variant={state === 'success' ? 'success' : state === 'failed' ? 'danger' : state === 'review' ? 'warning' : 'info'}>{message}</Alert>
      <dl className="space-y-3 text-sm">{details.map(({ label, value }) => <div key={label} className="flex flex-wrap justify-between gap-2 border-b border-border pb-2"><dt className="text-muted">{label}</dt><dd className="break-all font-medium text-foreground">{value}</dd></div>)}</dl>
      <div className="flex flex-wrap gap-3"><Button variant="outline" loading={checking} onClick={onCheck}>Check status</Button>{extraAction}<LinkButton to={destination}>{destinationLabel}</LinkButton><LinkButton to="/support" variant="ghost">Get help</LinkButton></div>
      {state !== 'success' && <p className="text-xs text-muted">Please check this payment before trying again. Keep the reference above if you contact support.</p>}
    </section>
  </div>
}

function Problem({ message }: { message: string }) {
  return <div className="mx-auto max-w-2xl"><Alert variant="danger">{message}</Alert><div className="mt-4"><LinkButton to="/support">Get help</LinkButton></div></div>
}

function WalletResult({ id }: { id: number }) {
  const qc = useQueryClient()
  const payment = useQuery({
    queryKey: ['payment-result', 'wallet', id],
    queryFn: async () => { const result = await familyApi.confirmWalletFunding(id); if (result.status === 'success') { void qc.invalidateQueries({ queryKey: ['wallet'] }); void qc.invalidateQueries({ queryKey: ['family'] }) } return result },
    retry: false,
    refetchInterval: (query) => query.state.data?.status === 'pending' ? 5000 : false,
  })
  if (payment.isPending) return <p role="status">Checking your wallet payment…</p>
  if (payment.isError || !payment.data) return <Problem message="We could not check this wallet payment. Try again from your wallet or contact support." />
  const p = payment.data
  const state: ResultState = p.status === 'success' ? 'success' : p.status === 'failed' ? 'failed' : p.status === 'pending' ? 'pending' : 'review'
  return <Result item="Wallet top-up" state={state} message={p.message} details={[
    { label: 'Amount', value: formatMoney(p.amount_minor, p.currency) },
    { label: 'Payment provider', value: p.gateway },
    { label: 'Payment reference', value: p.gateway_ref },
    { label: 'Wallet payment', value: `#${p.funding_id}` },
    { label: 'Started', value: date(p.created_at) },
    ...(p.confirmed_at ? [{ label: 'Confirmed', value: date(p.confirmed_at) }] : []),
  ]} onCheck={() => void payment.refetch()} checking={payment.isFetching} destination="/wallet" destinationLabel="View wallet" />
}

function SubscriptionResult({ id }: { id: number }) {
  const qc = useQueryClient()
  const payment = useQuery({
    queryKey: ['payment-result', 'subscription', id],
    queryFn: async () => { const result = await billingApi.confirmSubscription(id); if (result.status === 'active') { void qc.invalidateQueries({ queryKey: ['me'] }); void qc.invalidateQueries({ queryKey: ['subscriptions'] }) } return result },
    retry: false,
    refetchInterval: (query) => query.state.data?.status === 'pending' ? 5000 : false,
  })
  if (payment.isPending) return <p role="status">Checking your plan payment…</p>
  if (payment.isError || !payment.data) return <Problem message="We could not check this plan payment. Open Billing to check your plan or contact support." />
  const p = payment.data
  const state: ResultState = p.status === 'active' ? 'success' : p.status === 'pending' ? 'pending' : p.status === 'expired' ? 'failed' : 'review'
  const message = state === 'success' ? 'Your plan is active. You can now use its included learning content.' : state === 'pending' ? 'The provider has not confirmed payment yet. Your plan will unlock after confirmation.' : 'This plan is not active. Check the payment status or contact support.'
  return <Result item="Learning plan" state={state} message={message} details={[
    { label: 'Plan', value: p.plan_name }, { label: 'Amount paid or due', value: formatMoney(p.amount_minor, p.currency) },
    { label: 'Payment provider', value: p.gateway ?? '—' }, { label: 'Payment reference', value: p.reference },
    ...(p.receipt_id ? [{ label: 'Receipt', value: `#${p.receipt_id}` }] : []),
    ...(p.paid_at ? [{ label: 'Confirmed', value: date(p.paid_at) }] : []),
    ...(p.renews_at ? [{ label: 'Renews', value: date(p.renews_at) }] : []),
  ]} onCheck={() => void payment.refetch()} checking={payment.isFetching} destination="/billing" destinationLabel="View billing" />
}

function StoryResult({ id }: { id: number }) {
  const qc = useQueryClient()
  const payment = useQuery({
    queryKey: ['payment-result', 'story', id],
    queryFn: async () => { const result = await learningApi.confirmStoryPurchase(id); if (result.status === 'paid') { void qc.invalidateQueries({ queryKey: ['learning', 'stories'] }); void qc.invalidateQueries({ queryKey: ['learning', 'story', result.story_id] }) } return result },
    retry: false,
    refetchInterval: (query) => query.state.data?.status === 'pending' ? 5000 : false,
  })
  if (payment.isPending) return <p role="status">Checking your story purchase…</p>
  if (payment.isError || !payment.data) return <Problem message="We could not check this story purchase. Open Stories to check access or contact support." />
  const p = payment.data
  const state: ResultState = p.status === 'paid' ? 'success' : p.status === 'pending' ? 'pending' : p.status === 'refunded' ? 'failed' : 'review'
  const message = state === 'success' ? 'Your story is unlocked and ready to read.' : state === 'pending' ? 'The provider has not confirmed payment yet. Please do not pay again.' : 'This story purchase is not currently active.'
  return <Result item="Story purchase" state={state} message={message} details={[
    { label: 'Story', value: p.story_title ?? `Story #${p.story_id}` },
    { label: 'Amount', value: formatMoney(p.amount_minor, 'NGN') }, { label: 'Payment provider', value: p.provider },
    { label: 'Payment reference', value: p.reference ?? `Story purchase #${p.id}` },
    { label: 'Purchase', value: `#${p.id}` }, ...(p.paid_at ? [{ label: 'Confirmed', value: date(p.paid_at) }] : []),
  ]} onCheck={() => void payment.refetch()} checking={payment.isFetching} destination={`/learn/stories/${p.story_id}`} destinationLabel="Open story" />
}

function InvoiceResult({ orgId, id }: { orgId: number; id: number }) {
  const [downloading, setDownloading] = useState(false)
  const [downloadError, setDownloadError] = useState(false)
  const invoices = useQuery({
    queryKey: ['payment-result', 'invoice', orgId, id],
    queryFn: () => schoolApi.confirmInvoice(orgId, id),
    retry: false,
    refetchInterval: (query) => query.state.data?.status === 'unpaid' ? 5000 : false,
  })
  if (invoices.isPending) return <p role="status">Checking your invoice payment…</p>
  if (invoices.isError || !invoices.data) return <Problem message="We could not find this invoice for your school. Check Invoices or contact support." />
  const invoice = invoices.data
  const state: ResultState = invoice.status === 'paid' ? 'success' : invoice.verification === 'failed' || invoice.status === 'cancelled' ? 'failed' : invoice.verification === 'needs_review' ? 'review' : 'pending'
  const message = state === 'success' ? 'Your school invoice is paid. You can download its PDF below.' : state === 'pending' ? 'The provider has not confirmed this invoice yet. The page will update when the payment is recorded.' : 'This invoice needs review. Contact support with its number.'
  return <><Result item="School invoice" state={state} message={message} details={[
    { label: 'Invoice', value: `#${String(invoice.id).padStart(6, '0')}` },
    { label: 'Amount', value: formatMoney(invoice.amount_minor, 'NGN') },
    { label: 'Payment provider', value: invoice.payment_gateway ?? '—' },
    { label: 'Payment reference', value: invoice.payment_reference },
    { label: 'Issued', value: date(invoice.issued_at) },
    ...(invoice.paid_at ? [{ label: 'Paid', value: date(invoice.paid_at) }] : []),
  ]} onCheck={() => void invoices.refetch()} checking={invoices.isFetching} destination="/invoices" destinationLabel="View invoices" extraAction={state === 'success' ? <Button variant="secondary" loading={downloading} onClick={async () => { setDownloading(true); setDownloadError(false); try { await schoolApi.downloadInvoice(orgId, id) } catch { setDownloadError(true) } finally { setDownloading(false) } }}>Download invoice PDF</Button> : undefined} />
  {downloadError && <Alert variant="warning">Could not download this invoice. You can try again from Invoices.</Alert>}</>
}

export function PaymentResultPage() {
  const { kind: rawKind, id: rawId, orgId: rawOrgId } = useParams()
  const kind = rawOrgId ? 'invoice' : rawKind
  const id = Number(rawId)
  const orgId = Number(rawOrgId)
  if (!Number.isSafeInteger(id) || id <= 0) return <Problem message="This payment link is invalid." />
  if (kind === 'wallet') return <WalletResult id={id} />
  if (kind === 'subscription') return <SubscriptionResult id={id} />
  if (kind === 'story') return <StoryResult id={id} />
  if (kind === 'invoice' && Number.isSafeInteger(orgId) && orgId > 0) return <InvoiceResult orgId={orgId} id={id} />
  return <Problem message="This payment link is invalid." />
}
