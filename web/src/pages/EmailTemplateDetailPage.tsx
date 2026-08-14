import { useEffect, useRef, useState } from 'react'
import { useParams } from 'react-router-dom'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Badge, Button, Card, Skeleton, Textarea } from '@/components/ui'
import { ApiError, type EmailTemplateContent } from '@/lib/api'
import {
  useEmailTemplate,
  useEmailTemplatePreview,
  useResetEmailTemplate,
  useUpdateEmailTemplate,
} from '@/lib/admin/queries'

export function EmailTemplateDetailPage() {
  const templateKey = useParams().templateKey ?? ''
  const detail = useEmailTemplate(templateKey || null)
  const preview = useEmailTemplatePreview(templateKey || null)
  const update = useUpdateEmailTemplate()
  const reset = useResetEmailTemplate()
  const [form, setForm] = useState<EmailTemplateContent | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)
  const [confirmingReset, setConfirmingReset] = useState(false)
  const lastFocused = useRef<HTMLTextAreaElement | HTMLInputElement | null>(null)

  useEffect(() => {
    const source = detail.data?.override ?? detail.data?.default
    if (!source) return
    setForm({ subject: source.subject, greeting: source.greeting, body: source.body, action_text: source.action_text, action_url: source.action_url })
  }, [detail.data])

  if (!templateKey) return <Alert variant="danger">This email template link is invalid.</Alert>
  if (detail.isError) return <Alert variant="danger">Couldn't load this email template.</Alert>

  function insertPlaceholder(token: string) {
    const element = lastFocused.current
    if (!element || !form) return
    const field = element.name as keyof EmailTemplateContent
    const start = element.selectionStart ?? element.value.length
    const end = element.selectionEnd ?? element.value.length
    const current = form[field] ?? ''
    setForm({ ...form, [field]: current.slice(0, start) + token + current.slice(end) })
  }

  async function save() {
    if (!form) return
    setError(null)
    setSaved(false)
    try {
      await update.mutateAsync({ key: templateKey, input: form })
      setSaved(true)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not save this template.')
    }
  }

  async function resetToDefault() {
    setError(null)
    try {
      await reset.mutateAsync(templateKey)
      setConfirmingReset(false)
      setSaved(false)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not reset this template.')
    }
  }

  const template = detail.data
  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title={template?.label ?? 'Email template'}
        description={template?.trigger}
        backTo="/admin/emails/templates"
        backLabel="Back to templates"
        actions={template && <Badge variant={template.customizable ? (template.override ? 'gold' : 'neutral') : 'neutral'}>{template.customizable ? (template.override ? 'Customized' : 'Default') : 'Framework-managed'}</Badge>}
      />
      {error && <Alert variant="danger">{error}</Alert>}
      {saved && !error && <Alert variant="success">Saved. New sends will use this content.</Alert>}

      <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(420px,0.9fr)]">
        <Card className="p-5 sm:p-6">
          {detail.isLoading && <Skeleton className="h-96" />}
          {template && form && template.customizable && (
            <div className="flex flex-col gap-5">
              <div className="grid gap-4">
                <EditorInput label="Subject" name="subject" value={form.subject} onFocus={(element) => (lastFocused.current = element)} onChange={(value) => setForm({ ...form, subject: value })} />
                <EditorInput label="Greeting (optional)" name="greeting" value={form.greeting ?? ''} onFocus={(element) => (lastFocused.current = element)} onChange={(value) => setForm({ ...form, greeting: value || null })} />
                <Textarea name="body" label="Body" hint="Separate paragraphs with a blank line." rows={8} value={form.body} onFocus={(event) => (lastFocused.current = event.currentTarget)} onChange={(event) => setForm({ ...form, body: event.target.value })} />
                <div className="grid gap-4 sm:grid-cols-2">
                  <EditorInput label="Action button text" name="action_text" value={form.action_text ?? ''} onFocus={(element) => (lastFocused.current = element)} onChange={(value) => setForm({ ...form, action_text: value || null })} />
                  <EditorInput label="Action button URL" name="action_url" value={form.action_url ?? ''} onFocus={(element) => (lastFocused.current = element)} onChange={(value) => setForm({ ...form, action_url: value || null })} />
                </div>
              </div>

              {Object.keys(template.placeholders).length > 0 && (
                <div className="rounded-xl border border-border bg-surface-muted p-4">
                  <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Choose a field, then insert a placeholder</p>
                  <div className="flex flex-wrap gap-2">
                    {Object.entries(template.placeholders).map(([token, description]) => (
                      <button key={token} type="button" title={description} onClick={() => insertPlaceholder(token)} className="min-h-10 rounded-lg border border-border bg-surface px-2.5 py-1 font-mono text-xs text-foreground hover:border-gold-400 hover:bg-gold-50">{token}</button>
                    ))}
                  </div>
                </div>
              )}

              <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                <div>
                  {template.override && (confirmingReset ? (
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="text-sm text-muted">Reset to default?</span>
                      <Button size="sm" variant="danger" loading={reset.isPending} onClick={() => void resetToDefault()}>Confirm reset</Button>
                      <Button size="sm" variant="ghost" onClick={() => setConfirmingReset(false)}>Cancel</Button>
                    </div>
                  ) : <Button size="sm" variant="ghost" onClick={() => setConfirmingReset(true)}>Reset to default</Button>)}
                </div>
                <Button variant="parent" loading={update.isPending} onClick={() => void save()}>Save changes</Button>
              </div>
            </div>
          )}
          {template && !template.customizable && <p className="text-sm text-muted">This message is managed by the authentication framework and is available as a read-only preview.</p>}
        </Card>

        <Card className="overflow-hidden p-4 sm:p-5">
          <h2 className="mb-3 font-semibold text-foreground">Live preview</h2>
          {preview.isLoading && <Skeleton className="h-[32rem]" />}
          {preview.isError && <Alert variant="danger">Couldn't render this template.</Alert>}
          {preview.data && <iframe title={preview.data.subject} srcDoc={preview.data.html} className="h-[70vh] min-h-[32rem] w-full rounded-xl border border-border bg-white" sandbox="" />}
        </Card>
      </div>
    </div>
  )
}

function EditorInput({ label, name, value, onFocus, onChange }: { label: string; name: string; value: string; onFocus: (element: HTMLInputElement) => void; onChange: (value: string) => void }) {
  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-sm font-semibold text-foreground">{label}</span>
      <input name={name} value={value} onFocus={(event) => onFocus(event.currentTarget)} onChange={(event) => onChange(event.target.value)} className="h-11 w-full rounded-xl border border-border-strong bg-surface px-3.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring" />
    </label>
  )
}
