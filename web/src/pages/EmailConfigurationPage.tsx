import { useEffect, useState, type FormEvent } from 'react'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Badge, Button, Card, CardBody, Input, Skeleton } from '@/components/ui'
import { ApiError, type EmailConfigurationInput, type GatewayTestResult } from '@/lib/api'
import {
  useEmailConfiguration,
  useTestEmailConfiguration,
  useUpdateEmailConfiguration,
} from '@/lib/admin/queries'

const initialForm: EmailConfigurationInput = {
  mailer: 'log',
  host: '',
  port: 587,
  scheme: 'tls',
  username: '',
  password: '',
  from_address: '',
  from_name: 'MAHADUM.360',
}

export function EmailConfigurationPage() {
  const configuration = useEmailConfiguration()
  const update = useUpdateEmailConfiguration()
  const test = useTestEmailConfiguration()
  const [form, setForm] = useState(initialForm)
  const [testEmail, setTestEmail] = useState('')
  const [result, setResult] = useState<GatewayTestResult | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})

  useEffect(() => {
    if (!configuration.data) return
    const value = configuration.data
    setForm({
      mailer: value.mailer,
      host: value.host,
      port: value.port,
      scheme: value.scheme,
      username: value.username ?? '',
      password: '',
      from_address: value.from_address,
      from_name: value.from_name,
    })
  }, [configuration.data])

  function change<K extends keyof EmailConfigurationInput>(key: K, value: EmailConfigurationInput[K]) {
    setForm((current) => ({ ...current, [key]: value }))
    setErrors((current) => ({ ...current, [key]: '' }))
    setResult(null)
  }

  async function save(event: FormEvent) {
    event.preventDefault()
    setErrors({})
    setResult(null)
    try {
      await update.mutateAsync(form)
      setForm((current) => ({ ...current, password: '' }))
      setResult({ ok: true, message: 'Email configuration saved securely.' })
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.fieldErrors)
        if (!Object.keys(error.fieldErrors).length) setResult({ ok: false, message: error.message })
      } else {
        setResult({ ok: false, message: 'Could not save the email configuration.' })
      }
    }
  }

  async function sendTest() {
    setResult(null)
    try {
      setResult(await test.mutateAsync(testEmail))
    } catch (error) {
      setResult({ ok: false, message: error instanceof ApiError ? error.message : 'Could not send the test email.' })
    }
  }

  if (configuration.isLoading) return <Skeleton className="h-96" />
  if (configuration.isError || !configuration.data) return <Alert variant="danger">Couldn’t load email configuration.</Alert>
  const status = configuration.data

  return (
    <div className="flex max-w-4xl flex-col gap-6">
      <AdminPageHeader
        title="Email configuration"
        description="Configure the SMTP transport used by password resets, invitations, receipts, campaigns, and other system messages. Credentials are encrypted and never displayed after saving."
      />

      <div className="flex flex-wrap items-center gap-2">
        <Badge variant={status.delivery_enabled ? 'success' : 'warning'}>
          {status.delivery_enabled ? 'Delivery enabled' : 'Delivery disabled'}
        </Badge>
        <span className="text-sm text-muted">
          {status.delivery_enabled ? `${status.host}:${status.port}` : 'Messages currently write to the application log.'}
        </span>
      </div>

      {!status.delivery_enabled && (
        <Alert variant="warning" title="System email is not reaching inboxes">
          The active mailer is <code>{status.mailer}</code>. Choose SMTP below, enter your provider credentials, save, then send a test email.
        </Alert>
      )}

      {status.queue_connection === 'database' && (status.pending_jobs > 0 || status.failed_jobs > 0) && (
        <Alert variant={status.failed_jobs > 0 ? 'danger' : 'info'} title="Email queue status">
          {status.pending_jobs} pending job{status.pending_jobs === 1 ? '' : 's'} and {status.failed_jobs} failed job{status.failed_jobs === 1 ? '' : 's'}.
          A queue worker must remain running for queued system emails.
        </Alert>
      )}

      {result && <Alert variant={result.ok ? 'success' : 'danger'}>{result.message}</Alert>}

      <form onSubmit={save} className="flex flex-col gap-6">
        <Card>
          <CardBody className="flex flex-col gap-5">
            <div>
              <h2 className="text-lg font-bold text-foreground">Delivery transport</h2>
              <p className="mt-1 text-sm text-muted">Use the SMTP credentials supplied by Mailtrap, SendGrid, or your mail provider.</p>
            </div>

            <label className="flex flex-col gap-1.5 text-sm font-semibold text-foreground">
              Mailer
              <select
                value={form.mailer}
                onChange={(event) => change('mailer', event.target.value as 'smtp' | 'log')}
                className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              >
                <option value="smtp">SMTP — deliver real email</option>
                <option value="log">Log only — no inbox delivery</option>
              </select>
            </label>

            {form.mailer === 'smtp' && (
              <div className="grid gap-4 sm:grid-cols-2">
                <Input label="SMTP host" value={form.host} error={errors.host} onChange={(event) => change('host', event.target.value)} placeholder="smtp.sendgrid.net" />
                <Input label="SMTP port" type="number" min={1} max={65535} value={form.port} error={errors.port} onChange={(event) => change('port', Number(event.target.value))} />
                <label className="flex flex-col gap-1.5 text-sm font-semibold text-foreground">
                  Encryption
                  <select
                    value={form.scheme ?? ''}
                    onChange={(event) => change('scheme', (event.target.value || null) as 'tls' | 'ssl' | null)}
                    className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                  >
                    <option value="tls">TLS</option>
                    <option value="ssl">SSL</option>
                    <option value="">None</option>
                  </select>
                </label>
                <Input label="SMTP username" autoComplete="off" value={form.username} error={errors.username} onChange={(event) => change('username', event.target.value)} />
                <div className="sm:col-span-2">
                  <Input
                    label="SMTP password or API key"
                    type="password"
                    autoComplete="new-password"
                    value={form.password ?? ''}
                    error={errors.password}
                    onChange={(event) => change('password', event.target.value)}
                    placeholder={status.password_set ? 'Saved securely — enter a value only to replace it' : 'Enter SMTP password'}
                  />
                </div>
              </div>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardBody className="flex flex-col gap-5">
            <div>
              <h2 className="text-lg font-bold text-foreground">Sender identity</h2>
              <p className="mt-1 text-sm text-muted">The From address must be verified with your email provider.</p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <Input label="From address" type="email" value={form.from_address} error={errors.from_address} onChange={(event) => change('from_address', event.target.value)} />
              <Input label="From name" value={form.from_name} error={errors.from_name} onChange={(event) => change('from_name', event.target.value)} />
            </div>
            <div className="flex justify-end">
              <Button type="submit" variant="parent" loading={update.isPending}>Save email configuration</Button>
            </div>
          </CardBody>
        </Card>
      </form>

      <Card>
        <CardBody className="flex flex-col gap-4">
          <div>
            <h2 className="text-lg font-bold text-foreground">Send a test email</h2>
            <p className="mt-1 text-sm text-muted">This sends immediately and does not wait for the background queue.</p>
          </div>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div className="flex-1">
              <Input label="Destination email" type="email" value={testEmail} onChange={(event) => setTestEmail(event.target.value)} placeholder="admin@example.com" />
            </div>
            <Button variant="secondary" disabled={!status.delivery_enabled || !testEmail} loading={test.isPending} onClick={sendTest}>
              Send test email
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  )
}
