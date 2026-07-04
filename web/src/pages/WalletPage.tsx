import { useState, type FormEvent } from 'react'
import {
  Alert,
  Button,
  Card,
  CardBody,
  CardHeader,
  CardTitle,
  Input,
  Skeleton,
} from '@/components/ui'
import { ApiError } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useFamily, useFundWallet, useTransfer, useWallet } from '@/lib/family/queries'

export function WalletPage() {
  const { data: wallet, isLoading, isError } = useWallet()
  const { data: family } = useFamily()

  if (isLoading) return <Skeleton className="h-40 max-w-md" />
  if (isError || !wallet) {
    return <Alert variant="danger">We couldn’t load your wallet. Please refresh and try again.</Alert>
  }

  const learners = family?.learners ?? []

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
            <p className="font-display text-4xl font-extrabold text-foreground">
              🪙 {wallet.coin_balance.toLocaleString()}
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

      <div className="grid gap-4 lg:grid-cols-2">
        <TransferCard learners={learners} maxCoins={wallet.coin_balance} />
        <FundCard currency={wallet.currency} />
      </div>
    </div>
  )
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

function FundCard({ currency }: { currency: string }) {
  const fund = useFundWallet()
  const [amount, setAmount] = useState('')
  const [gateway, setGateway] = useState<'flutterwave' | 'monnify' | 'paystack'>('monnify')
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState<{ ref: string; url: string | null } | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setPending(null)
    try {
      const result = await fund.mutateAsync({ amount: Math.round(Number(amount) * 100), gateway })
      setPending({ ref: result.gateway_ref, url: result.checkout_url })
      setAmount('')
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
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          {error && <Alert variant="danger">{error}</Alert>}
          {pending && (
            <Alert variant="info" title="Checkout started">
              {pending.url ? (
                <a href={pending.url} className="font-semibold underline" target="_blank" rel="noreferrer">
                  Open secure checkout
                </a>
              ) : (
                <>Your wallet will be credited once payment is confirmed (ref {pending.ref.slice(0, 8)}).</>
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
              <option value="monnify">Monnify</option>
              <option value="paystack">Paystack</option>
              <option value="flutterwave">Flutterwave</option>
            </select>
          </label>
          <Button type="submit" variant="billing" loading={fund.isPending} disabled={!amount}>
            Continue to payment
          </Button>
        </form>
      </CardBody>
    </Card>
  )
}
