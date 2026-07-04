import { useState } from 'react'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Badge, Button, Card, CardBody, Skeleton } from '@/components/ui'
import { ApiError, type GatewayProvider, type GatewayTestResult } from '@/lib/api'
import { usePaymentGateways, useTestGateway } from '@/lib/admin/queries'

export function GatewaysPage() {
  const { data, isLoading, isError } = usePaymentGateways()

  if (isLoading) return <Skeleton className="h-64" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load gateway configuration.</Alert>

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Payment gateways"
        description="Monnify (default), Paystack & Flutterwave. Keys are set in the server environment (never stored in-app or shown here) — this console reports status and validates the connection."
      />

      <Alert variant={data.live ? 'success' : 'warning'}>
        {data.live ? (
          <>
            <strong>Live mode is ON.</strong> Outbound checkouts hit the real gateways. Default:{' '}
            <span className="capitalize">{data.default}</span>.
          </>
        ) : (
          <>
            <strong>Live mode is OFF</strong> (<code>PAYMENT_GATEWAY_LIVE=false</code>). Checkouts use a no-op gateway
            and return no checkout URL. Set it to <code>true</code> in the environment to go live.
          </>
        )}
      </Alert>

      <div className="grid gap-4 md:grid-cols-2">
        {data.providers.map((p) => (
          <GatewayCard key={p.key} provider={p} isDefault={p.is_default} />
        ))}
      </div>
    </div>
  )
}

function GatewayCard({ provider, isDefault }: { provider: GatewayProvider; isDefault: boolean }) {
  const test = useTestGateway()
  const [result, setResult] = useState<GatewayTestResult | null>(null)
  const [copied, setCopied] = useState(false)

  async function onTest() {
    setResult(null)
    try {
      setResult(await test.mutateAsync(provider.key))
    } catch (err) {
      setResult({ ok: false, message: err instanceof ApiError ? err.message : 'Test failed.' })
    }
  }

  async function copyWebhook() {
    try {
      await navigator.clipboard.writeText(provider.webhook_url)
      setCopied(true)
      setTimeout(() => setCopied(false), 1500)
    } catch {
      // Clipboard may be unavailable; the URL is visible to copy manually.
    }
  }

  return (
    <Card>
      <CardBody className="flex flex-col gap-4">
        <div className="flex items-center justify-between">
          <h2 className="font-display text-lg font-bold text-foreground">{provider.label}</h2>
          <div className="flex items-center gap-2">
            {isDefault && <Badge variant="primary">Default</Badge>}
            <Badge variant={provider.configured ? 'success' : 'neutral'}>
              {provider.configured ? 'Configured' : 'Not configured'}
            </Badge>
          </div>
        </div>

        <div className="flex flex-col gap-1.5">
          {provider.requirements.map((r) => (
            <div key={r.env} className="flex items-center justify-between text-sm">
              <span className="text-foreground">
                {r.label} <code className="text-xs text-muted">{r.env}</code>
              </span>
              <span className={r.set ? 'font-semibold text-leaf-600' : 'font-semibold text-danger'}>
                {r.set ? '✓ set' : 'missing'}
              </span>
            </div>
          ))}
        </div>

        <div>
          <p className="mb-1 text-xs uppercase tracking-wide text-muted">Webhook URL</p>
          <div className="flex items-center gap-2">
            <code className="flex-1 truncate rounded-lg bg-surface-muted px-3 py-2 text-xs text-foreground">
              {provider.webhook_url}
            </code>
            <Button size="sm" variant="ghost" onClick={copyWebhook}>
              {copied ? 'Copied' : 'Copy'}
            </Button>
          </div>
          <p className="mt-1 text-xs text-muted">Register this in the {provider.label} dashboard.</p>
        </div>

        {result && (
          <Alert variant={result.ok ? 'success' : 'danger'}>{result.message}</Alert>
        )}

        <div className="flex justify-end">
          <Button variant="parent" loading={test.isPending} onClick={onTest}>
            Test connection
          </Button>
        </div>
      </CardBody>
    </Card>
  )
}
