import { PurchasePolicyNotice } from '@/components/billing/PurchasePolicyNotice'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  CardTitle,
  Icon,
  Input,
  Skeleton,
} from '@/components/ui'
import { ApiError } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useConfirmWalletFunding, useFamily, useFundWallet, useTransfer, useWallet } from '@/lib/family/queries'

export function WalletPage() {
  const { data: wallet, isLoading, isError } = useWallet()
  const { data: family } = useFamily()

  if (isLoading) return <Skeleton className="h-40 max-w-md" />
  if (isError || !wallet) {
    return <Alert variant="danger">We couldn’t load your wallet. Please refresh and try again.</Alert>
  }

  const learners = family?.learners ?? []
  const returnedFundingId = Number(new URLSearchParams(window.location.search).get('funding_id'))

  return (
    <div className="flex flex-col gap-8">
      <div>
        <h1 className="font-display text-2xl font-bold text-foreground">Wallet</h1>
        <p className="mt-1 text-muted">Fund your wallet and reward your children with coins.</p>
      </div>

      <Card className="overflow-hidden">
        <CardBody className="flex flex-wrap items-center justify-between gap-4 bg-primary-soft">
          <div>
            <p className="text-sm font-medium text-primary">Coin balance</p>
            <p className="flex items-center gap-2 font-display text-4xl font-extrabold text-foreground">
              <Icon name="coin" className="size-8 text-gold-600" />
              {wallet.coin_balance.toLocaleString()}
            </p>
          </div>
          <div className="text-right">
            <p className="text-sm text-muted">Cash balance</p>
            <p className="font-display text-xl font-bold text-foreground">
              {formatMoney(wallet.currency_minor, wallet.currency)}
            </p>
          </div>
        </CardBody>
      </Card>

      {Number.isSafeInteger(returnedFundingId) && returnedFundingId > 0 && <FundingResult fundingId={returnedFundingId} />}

      <div className="grid gap-4 lg:grid-cols-2">
        <TransferCard learners={learners} maxCoins={wallet.coin_balance} />
        {wallet.funding_gateways?.length
          ? <FundCard currency={wallet.currency} gateways={wallet.funding_gateways} />
          : <Alert variant="info">Wallet funding is currently unavailable. Please try again later.</Alert>}
      </div>
      {!!wallet.funding_transactions?.length && <Card><CardHeader><CardTitle>Recent wallet payments</CardTitle></CardHeader><CardBody className="space-y-3">{wallet.funding_transactions.map((payment) => <div key={payment.id} className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-3 text-sm last:border-0 last:pb-0"><span>{formatMoney(payment.amount_minor, payment.currency)} · {payment.gateway} · {new Date(payment.created_at).toLocaleDateString()}</span><span className="font-medium capitalize">{payment.status}</span>{payment.status === 'pending' && <a className="font-semibold text-primary underline" href={`/wallet?funding_id=${payment.id}`}>Check status</a>}</div>)}</CardBody></Card>}
    </div>
  )
}

function FundingResult({ fundingId }: { fundingId: number }) {
  const confirm = useConfirmWalletFunding()
  const started = useRef(false)
  const [result, setResult] = useState<{ status: string; message: string; gateway_ref: string } | null>(null)
  const [error, setError] = useState<string | null>(null)

  async function check() {
    setError(null)
    try { setResult(await confirm.mutateAsync(fundingId)) }
    catch (err) { setError(err instanceof ApiError ? err.message : 'We could not verify this payment. Please do not pay again; check later or contact support.') }
  }

  useEffect(() => {
    if (started.current) return
    started.current = true
    void check()
  }, [fundingId])

  return <div className="space-y-3" aria-live="polite">
    {confirm.isPending && <Alert variant="info">Checking your payment with the provider…</Alert>}
    {error && <Alert variant="warning">{error}</Alert>}
    {result && <Alert variant={result.status === 'success' ? 'success' : result.status === 'failed' ? 'danger' : 'info'} title="Wallet payment result">{result.message} {result.status !== 'success' && <span>Reference: {result.gateway_ref}</span>}</Alert>}
    {!confirm.isPending && <Button variant="secondary" onClick={() => { void check() }}>Check payment status</Button>}
  </div>
}

function TransferCard({
  learners,
  maxCoins,
}: {
  learners: { id: number; display_name: string }[]
  maxCoins: number
}) {
  const transfer = useTransfer()
  const [toId, setToId] = useState('')
  const [coins, setCoins] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setSuccess(null)
    try {
      const result = await transfer.mutateAsync({ to_learner_id: Number(toId), coins: Number(coins) })
      setSuccess(`Sent! Their balance is now ${result.learner_balance.toLocaleString()} coins.`)
      setCoins('')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Transfer failed.')
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Send coins to a child</CardTitle>
      </CardHeader>
      <CardBody>
        {learners.length === 0 ? (
          <p className="text-sm text-muted">Add a child first to send coins.</p>
        ) : (
          <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
            {error && <Alert variant="danger">{error}</Alert>}
            {success && <Alert variant="success">{success}</Alert>}
            <label className="flex flex-col gap-1.5">
              <span className="text-sm font-semibold text-foreground">Child</span>
              <select
                value={toId}
                onChange={(e) => setToId(e.target.value)}
                required
                className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              >
                <option value="">Select a child</option>
                {learners.map((l) => (
                  <option key={l.id} value={l.id}>
                    {l.display_name}
                  </option>
                ))}
              </select>
            </label>
            <Input
              label="Coins"
              type="number"
              min={1}
              max={maxCoins}
              value={coins}
              onChange={(e) => setCoins(e.target.value)}
              hint={`You have ${maxCoins.toLocaleString()} coins.`}
              required
            />
            <Button type="submit" variant="parent" loading={transfer.isPending} disabled={!toId || !coins}>
              Send coins
            </Button>
          </form>
        )}
      </CardBody>
    </Card>
  )
}

type FundingGateway = 'flutterwave' | 'monnify' | 'paystack'
function FundCard({ currency, gateways }: { currency: string; gateways: FundingGateway[] }) {
  const fund = useFundWallet()
  const [amount, setAmount] = useState('')
  const [selectedGateway, setGateway] = useState<FundingGateway | ''>('')
  const gateway = gateways.includes(selectedGateway as FundingGateway) ? selectedGateway as FundingGateway : gateways[0]
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState<{ ref: string; url: string | null } | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setPending(null)
    if (!gateway) return
    try {
      const result = await fund.mutateAsync({ amount: Math.round(Number(amount) * 100), gateway })
      if (result.checkout_url?.startsWith('https://')) {
        window.location.assign(result.checkout_url)
        return
      }
      setPending({ ref: result.gateway_ref, url: null })
      setError('Secure checkout is unavailable. No money was added. Please contact support with this reference.')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not start funding.')
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Add money</CardTitle>
      </CardHeader>
      <CardBody>
        <PurchasePolicyNotice />
        {!gateways.length ? <Alert variant="info">Wallet funding is currently unavailable. Please try again later.</Alert> : <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          {error && <Alert variant="danger">{error}</Alert>}
          {pending && (
            <Alert variant="info" title="Checkout started">
              {pending.url ? (
                <a href={pending.url} className="font-semibold underline" target="_blank" rel="noreferrer">
                  Open secure checkout
                </a>
              ) : (
                <>Checkout could not open. No money was added (ref {pending.ref.slice(0, 8)}).</>
              )}
            </Alert>
          )}
          <Input
            label={`Amount (${currency})`}
            type="number"
            min={1}
            step="0.01"
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            required
          />
          <label className="flex flex-col gap-1.5">
            <span className="text-sm font-semibold text-foreground">Payment method</span>
            <select
              value={gateway}
              onChange={(e) => setGateway(e.target.value as 'flutterwave' | 'monnify' | 'paystack')}
              className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            >
              {gateways.map(key => <option key={key} value={key}>{({ monnify: 'Monnify', paystack: 'Paystack', flutterwave: 'Flutterwave' })[key]}</option>)}
            </select>
          </label>
          <Button type="submit" variant="billing" loading={fund.isPending} disabled={!amount || !gateway}>
            Continue to payment
          </Button>
        </form>}
      </CardBody>
    </Card>
  )
}
