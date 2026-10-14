import { useQuery } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { Alert, Button, Icon, LinkButton } from '@/components/ui'
import { ApiError, billingApi } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import { formatMoney } from '@/lib/format'
import { dataStoreError } from '@/lib/billing/useDataStore'

export function DataPurchaseStatusPage() {
  const { user } = useAuth()
  const [params] = useSearchParams()
  const reference = params.get('paymentReference') ?? ''
  const validReference = reference.length === 69 && /^data_[a-f0-9]{64}$/.test(reference)
  const purchase = useQuery({
    queryKey: ['data-purchase-reference', user?.user.id, reference],
    queryFn: () => billingApi.verifyDataBundlePurchase(reference),
    enabled: validReference && !!user?.user.id,
    retry: false,
    refetchInterval: (query) => !query.state.error && ['awaiting_payment', 'processing'].includes(query.state.data?.status ?? '')
      ? query.state.data?.check_issue ? 30000 : 5000 : false,
  })
  const order = purchase.data
  const unavailable = purchase.error instanceof ApiError && [403, 404].includes(purchase.error.status)
  function startAnother() {
    sessionStorage.removeItem(`mahadum:data-purchase:${user?.user.id}`)
  }

  return <div className="mx-auto max-w-2xl space-y-6">
    <header><p className="text-sm font-medium text-primary">Mobile data</p><h1 className="mt-2 font-display text-3xl font-bold">Your purchase status</h1><p className="mt-3 text-sm text-muted">Track your payment and data delivery here.</p></header>
    <section className="space-y-5 rounded-3xl border border-border bg-surface p-5 sm:p-6" aria-label="Data purchase status">
      <Icon name={order?.status === 'success' ? 'check' : 'card'} className="size-10 text-primary" />
      {!validReference && <Alert variant="danger" title="Purchase reference missing or invalid">Open the payment return link again, or find your order in Recent purchases on the Buy data page.</Alert>}
      {purchase.isLoading && <p role="status">Verifying your payment with Monnify…</p>}
      {purchase.error && <Alert variant="danger" title={unavailable ? 'Purchase unavailable' : 'Could not check your purchase'}>{unavailable ? 'This reference is not available for your account. Sign in with the account used to buy the data.' : dataStoreError(purchase.error)}</Alert>}
      {order && <>
        <h2 className="font-display text-xl font-bold">{order.product_name.replace(/_/g, ' ')}</h2>
        <dl className="space-y-3 text-sm">
          <div className="flex justify-between gap-4"><dt className="text-muted">Recipient</dt><dd>{order.phone_number}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-muted">Amount</dt><dd>{formatMoney(order.amount_minor, 'NGN')}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-muted">Payment</dt><dd>{order.payment_confirmed ? 'Confirmed' : order.status === 'payment_failed' ? 'Unsuccessful' : order.status === 'needs_review' ? 'Needs review' : 'Awaiting confirmation'}</dd></div>
          <div className="flex justify-between gap-4"><dt className="text-muted">Data delivery</dt><dd>{order.status === 'success' ? 'Delivered' : order.status === 'failed' ? 'Failed' : order.delivery_started ? order.status === 'needs_review' ? 'Needs review' : 'In progress' : 'Not started'}</dd></div>
        </dl>
        {order.status === 'awaiting_payment' && <Alert variant="info" title="Awaiting payment confirmation">Monnify has not yet confirmed this payment. We’ll check again automatically.</Alert>}
        {order.check_issue && <Alert variant="danger" title="Delivery check delayed">{order.payment_confirmed ? 'Your payment is confirmed, but Monnify could not complete or check data delivery right now. Keep this reference and do not pay again for this order.' : 'Monnify could not confirm this order right now. Check again shortly.'}</Alert>}
        {order.status === 'processing' && !order.check_issue && <Alert variant="info" title="Delivering your data">Your payment is confirmed. Your network is processing the bundle. This page will update automatically.</Alert>}
        {order.status === 'success' && <Alert variant="success" title="Data delivered">Payment and data delivery have been confirmed.</Alert>}
        {order.status === 'payment_failed' && <Alert variant="danger" title="Payment unsuccessful">Monnify did not confirm a successful payment. You can choose another bundle.</Alert>}
        {order.status === 'needs_review' && !order.payment_confirmed && <Alert variant="danger" title="Payment needs review">Monnify has not confirmed the payment details for this order, so data has not been sent. Check again or contact support with the reference below.</Alert>}
        {(order.status === 'failed' || (order.status === 'needs_review' && order.payment_confirmed)) && <Alert variant="danger" title="Your purchase needs attention">Data delivery could not be confirmed. Contact support with the reference below to resolve this order.</Alert>}
      </>}
      {reference && <p className="break-all text-xs text-muted">Reference: {reference}</p>}
      <div className="flex flex-wrap gap-3">
        {validReference && <Button variant="outline" loading={purchase.isFetching} onClick={() => void purchase.refetch()}>Check status</Button>}
        <LinkButton to="/billing/data" onClick={startAnother}>Buy another bundle</LinkButton>
        <LinkButton to="/support" variant="ghost">Get help</LinkButton>
      </div>
      <p className="text-xs leading-5 text-muted">A new bundle is a separate purchase. Your existing order remains in Recent purchases.</p>
    </section>
  </div>
}
