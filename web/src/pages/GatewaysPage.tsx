import { useState } from 'react'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Badge, Button, Card, CardBody, Skeleton } from '@/components/ui'
import {
  ApiError,
  type GatewayProvider,
  type GatewayRequirement,
  type GatewayTestResult,
  type TelcoGatewayStatus,
} from '@/lib/api'
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

      <TelcoCard telco={data.telco} />
    </div>
  )
}

/**
 * Airtime billing status. Separate from the card gateways: it has no test
 * endpoint, and when it isn't live the app silently *simulates* successful
 * charges — which is exactly the state an operator needs told, not hidden.
 */
function TelcoCard({ telco }: { telco: TelcoGatewayStatus }) {
  return (
    <Card>
      <CardBody className="flex flex-col gap-4">
        <div className="flex items-center justify-between">
          <h2 className="font-display text-lg font-bold text-foreground">{telco.label}</h2>
          <Badge variant={telco.configured ? 'success' : 'warning'}>
            {telco.configured ? 'Live' : 'Simulated'}
          </Badge>
        </div>

        {!telco.configured && (
          <Alert variant="warning">
            <strong>Airtime billing is simulated.</strong> Daily charges succeed automatically and no OTP SMS is
            delivered, so an end-to-end test will look like it worked without any money moving. Provision the operator
            SDP credentials below to bill for real.
          </Alert>
        )}

        <RequirementList requirements={telco.requirements} />

        <WebhookUrl url={telco.webhook_url} label="operator SDP" />
      </CardBody>
    </Card>
  )
}

function RequirementList({ requirements }: { requirements: GatewayRequirement[] }) {
  return (
    <div className="flex flex-col gap-1.5">
      {requirements.map((r) => (
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
  )
}

function WebhookUrl({ url, label }: { url: string; label: string }) {
  const [copied, setCopied] = useState(false)

  async function copy() {
    try {
      await navigator.clipboard.writeText(url)
      setCopied(true)
      setTimeout(() => setCopied(false), 1500)
    } catch {
      // Clipboard may be unavailable; the URL is visible to copy manually.
    }
  }

  return (
    <div>
      <p className="mb-1 text-xs uppercase tracking-wide text-muted">Webhook URL</p>
      <div className="flex items-center gap-2">
        <code className="flex-1 truncate rounded-lg bg-surface-muted px-3 py-2 text-xs text-foreground">{url}</code>
        <Button size="sm" variant="ghost" onClick={copy}>
          {copied ? 'Copied' : 'Copy'}
        </Button>
      </div>
      <p className="mt-1 text-xs text-muted">Register this in the {label} dashboard.</p>
    </div>
  )
}

function GatewayCard({ provider, isDefault }: { provider: GatewayProvider; isDefault: boolean }) {
  const test = useTestGateway()
  const [result, setResult] = useState<GatewayTestResult | null>(null)

  async function onTest() {
    setResult(null)
    try {
      setResult(await test.mutateAsync(provider.key))
    } catch (err) {
      setResult({ ok: false, message: err instanceof ApiError ? err.message : 'Test failed.' })
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

        <RequirementList requirements={provider.requirements} />

        <WebhookUrl url={provider.webhook_url} label={provider.label} />

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
