import { useEffect, useRef, useState } from 'react'
import { AdminPageHeader, DataTable, type Column } from '@/components/admin'
import { Alert, Badge, Button, Modal, Skeleton, Textarea } from '@/components/ui'
import { ApiError, type EmailTemplateContent, type EmailTemplateSummary } from '@/lib/api'
import {
  useEmailTemplate,
  useEmailTemplatePreview,
  useEmailTemplates,
  useResetEmailTemplate,
  useUpdateEmailTemplate,
} from '@/lib/admin/queries'

const CATEGORY_TONE: Record<string, 'primary' | 'gold' | 'success' | 'neutral'> = {
  Auth: 'neutral',
  Billing: 'gold',
  Family: 'success',
  Schools: 'primary',
  Referrals: 'primary',
  Support: 'neutral',
}

export function EmailTemplatesPage() {
  const { data, isLoading, isError } = useEmailTemplates()
  const [previewKey, setPreviewKey] = useState<string | null>(null)
  const [editKey, setEditKey] = useState<string | null>(null)

  if (isError) return <Alert variant="danger">Couldn't load the email templates.</Alert>

  const columns: Column<EmailTemplateSummary>[] = [
    {
      key: 'template',
      header: 'Template',
      render: (t) => (
        <div>
          <p className="font-semibold text-foreground">{t.label}</p>
          <p className="text-xs text-muted">{t.trigger}</p>
        </div>
      ),
    },
    {
      key: 'category',
      header: 'Category',
      hideOnMobile: true,
      render: (t) => <Badge variant={CATEGORY_TONE[t.category] ?? 'neutral'}>{t.category}</Badge>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (t) =>
        !t.customizable ? (
          <Badge variant="neutral">Framework-managed</Badge>
        ) : t.customized ? (
          <Badge variant="gold">Customized</Badge>
        ) : (
          <Badge variant="neutral">Default</Badge>
        ),
    },
    {
      key: 'actions',
      header: '',
      className: 'text-right',
      render: (t) => (
        <div className="flex justify-end gap-2">
          <Button size="sm" variant="outline" onClick={() => setPreviewKey(t.key)}>
            Preview
          </Button>
          {t.customizable && (
            <Button size="sm" variant="parent" onClick={() => setEditKey(t.key)}>
              Edit
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Email templates"
        description="Every transactional email the platform sends. Preview renders the exact code path a recipient's mail gets; edit to customize subject, greeting, body, and action button using the placeholders available for that event."
      />

      <DataTable
        columns={columns}
        rows={data ?? []}
        getRowId={(t) => t.key}
        isLoading={isLoading}
        empty="No templates found."
      />

      {previewKey && <TemplatePreviewModal templateKey={previewKey} onClose={() => setPreviewKey(null)} />}
      {editKey && <TemplateEditModal templateKey={editKey} onClose={() => setEditKey(null)} />}
    </div>
  )
}

function TemplatePreviewModal({ templateKey, onClose }: { templateKey: string; onClose: () => void }) {
  const { data, isLoading, isError } = useEmailTemplatePreview(templateKey)

  return (
    <Modal open onClose={onClose} title={data?.subject ?? 'Preview'} description="Rendered live from the real notification template." className="max-w-3xl">
      {isLoading && <Skeleton className="h-96" />}
      {isError && <Alert variant="danger">Couldn't render this template.</Alert>}
      {data && (
        <iframe
          title={data.subject}
          srcDoc={data.html}
          className="h-[70vh] w-full rounded-xl border border-border bg-white"
          sandbox=""
        />
      )}
    </Modal>
  )
}

function TemplateEditModal({ templateKey, onClose }: { templateKey: string; onClose: () => void }) {
  const { data, isLoading, isError } = useEmailTemplate(templateKey)
  const update = useUpdateEmailTemplate()
  const reset = useResetEmailTemplate()

  const [form, setForm] = useState<EmailTemplateContent | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)
  const [confirmingReset, setConfirmingReset] = useState(false)
  const lastFocused = useRef<HTMLTextAreaElement | HTMLInputElement | null>(null)

  useEffect(() => {
    if (!data) return
    const source = data.override ?? data.default
    setForm(
      source
        ? {
            subject: source.subject,
            greeting: source.greeting,
            body: source.body,
            action_text: source.action_text,
            action_url: source.action_url,
          }
        : null,
    )
  }, [data])

  function insertPlaceholder(token: string) {
    const el = lastFocused.current
    if (!el || !form) return
    const field = el.name as keyof EmailTemplateContent
    const start = el.selectionStart ?? el.value.length
    const end = el.selectionEnd ?? el.value.length
    const current = form[field] ?? ''
    const next = current.slice(0, start) + token + current.slice(end)
    setForm({ ...form, [field]: next })
  }

  async function onSave() {
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

  async function onReset() {
    setError(null)
    try {
      await reset.mutateAsync(templateKey)
      setConfirmingReset(false)
      setSaved(false)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not reset this template.')
    }
  }

  return (
    <Modal open onClose={onClose} title={data?.label ?? 'Edit template'} description={data?.trigger} className="max-w-3xl">
      {isLoading && <Skeleton className="h-96" />}
      {isError && <Alert variant="danger">Couldn't load this template.</Alert>}

      {data && form && (
        <div className="flex flex-col gap-5">
          {error && <Alert variant="danger">{error}</Alert>}
          {saved && !error && <Alert variant="success">Saved. New sends will use this content.</Alert>}

          <div className="grid gap-4">
            <label className="flex flex-col gap-1.5">
              <span className="text-sm font-semibold text-foreground">Subject</span>
              <input
                name="subject"
                value={form.subject}
                onFocus={(e) => (lastFocused.current = e.currentTarget)}
                onChange={(e) => setForm({ ...form, subject: e.target.value })}
                className="h-11 w-full rounded-xl border border-border-strong bg-surface px-3.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              />
            </label>

            <label className="flex flex-col gap-1.5">
              <span className="text-sm font-semibold text-foreground">Greeting (optional)</span>
              <input
                name="greeting"
                value={form.greeting ?? ''}
                onFocus={(e) => (lastFocused.current = e.currentTarget)}
                onChange={(e) => setForm({ ...form, greeting: e.target.value || null })}
                className="h-11 w-full rounded-xl border border-border-strong bg-surface px-3.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              />
            </label>

            <Textarea
              name="body"
              label="Body"
              hint="Separate paragraphs with a blank line."
              rows={6}
              value={form.body}
              onFocus={(e) => (lastFocused.current = e.currentTarget)}
              onChange={(e) => setForm({ ...form, body: e.target.value })}
            />

            <div className="grid gap-4 sm:grid-cols-2">
              <label className="flex flex-col gap-1.5">
                <span className="text-sm font-semibold text-foreground">Action button text (optional)</span>
                <input
                  name="action_text"
                  value={form.action_text ?? ''}
                  onFocus={(e) => (lastFocused.current = e.currentTarget)}
                  onChange={(e) => setForm({ ...form, action_text: e.target.value || null })}
                  className="h-11 w-full rounded-xl border border-border-strong bg-surface px-3.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                />
              </label>
              <label className="flex flex-col gap-1.5">
                <span className="text-sm font-semibold text-foreground">Action button URL (optional)</span>
                <input
                  name="action_url"
                  value={form.action_url ?? ''}
                  onFocus={(e) => (lastFocused.current = e.currentTarget)}
                  onChange={(e) => setForm({ ...form, action_url: e.target.value || null })}
                  className="h-11 w-full rounded-xl border border-border-strong bg-surface px-3.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                />
              </label>
            </div>
          </div>

          {Object.keys(data.placeholders).length > 0 && (
            <div className="rounded-xl border border-border bg-surface-muted p-4">
              <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">
                Placeholders — click a field above, then click a token to insert it
              </p>
              <div className="flex flex-wrap gap-2">
                {Object.entries(data.placeholders).map(([token, desc]) => (
                  <button
                    key={token}
                    type="button"
                    title={desc}
                    onClick={() => insertPlaceholder(token)}
                    className="rounded-lg border border-border bg-surface px-2.5 py-1 font-mono text-xs text-foreground hover:border-gold-400 hover:bg-gold-50"
                  >
                    {token}
                  </button>
                ))}
              </div>
            </div>
          )}

          <div className="flex items-center justify-between gap-3 border-t border-border pt-4">
            <div>
              {data.override !== null &&
                (confirmingReset ? (
                  <div className="flex items-center gap-2">
                    <span className="text-sm text-muted">Reset to the default content?</span>
                    <Button size="sm" variant="danger" loading={reset.isPending} onClick={onReset}>
                      Confirm reset
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => setConfirmingReset(false)}>
                      Cancel
                    </Button>
                  </div>
                ) : (
                  <Button size="sm" variant="ghost" onClick={() => setConfirmingReset(true)}>
                    Reset to default
                  </Button>
                ))}
            </div>
            <div className="flex gap-2">
              <Button variant="ghost" onClick={onClose}>
                Close
              </Button>
              <Button variant="parent" loading={update.isPending} onClick={onSave}>
                Save
              </Button>
            </div>
          </div>
        </div>
      )}
    </Modal>
  )
}
