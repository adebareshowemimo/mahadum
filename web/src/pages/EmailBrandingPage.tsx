import { useEffect, useRef, useState } from 'react'
import { AdminPageHeader, RichHtmlEmailEditor, type RichHtmlEditorView } from '@/components/admin'
import { Alert, Button, Card, CardBody, Skeleton, Switch } from '@/components/ui'
import { ApiError, type EmailBrandingInput } from '@/lib/api'
import { useEmailBranding, useUpdateEmailBranding } from '@/lib/admin/queries'

export function EmailBrandingPage() {
  const branding = useEmailBranding()
  const update = useUpdateEmailBranding()
  const [form, setForm] = useState<EmailBrandingInput | null>(null)
  const [headerView, setHeaderView] = useState<RichHtmlEditorView>('visual')
  const [footerView, setFooterView] = useState<RichHtmlEditorView>('visual')
  const headerEditor = useRef<HTMLDivElement>(null)
  const footerEditor = useRef<HTMLDivElement>(null)
  const [saved, setSaved] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!branding.data) return
    const { preview_html: _preview, ...settings } = branding.data
    setForm(settings)
  }, [branding.data])

  async function save() {
    if (!form) return
    setError(null)
    setSaved(false)
    try {
      await update.mutateAsync(form)
      setSaved(true)
    } catch (reason) {
      setError(reason instanceof ApiError ? reason.message : 'Could not save email branding.')
    }
  }

  if (branding.isLoading) return <Skeleton className="h-96" />
  if (branding.isError || !branding.data) return <Alert variant="danger">Couldn’t load email branding.</Alert>
  if (!form) return <Skeleton className="h-96" />

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Email branding"
        description="Manage the shared system header and footer used by transactional emails and campaigns. Individual templates can opt out."
        backTo="/admin/emails/templates"
        backLabel="Email templates"
      />

      {saved && <Alert variant="success">Email branding saved. New messages will use these settings.</Alert>}
      {error && <Alert variant="danger">{error}</Alert>}

      <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(420px,0.9fr)]">
        <div className="flex flex-col gap-5">
          <Card>
            <CardBody className="flex flex-col gap-4">
              <div>
                <h2 className="font-semibold text-foreground">Branding controls</h2>
                <p className="mt-1 text-sm text-muted">The global switch removes both managed sections. Compliance elements such as campaign unsubscribe links remain visible.</p>
              </div>
              <Switch checked={form.enabled} onChange={(enabled) => setForm({ ...form, enabled })} label="Enable system email branding" />
              <div className="grid gap-3 border-t border-border pt-3 sm:grid-cols-2">
                <Switch checked={form.header_enabled} disabled={!form.enabled} onChange={(header_enabled) => setForm({ ...form, header_enabled })} label="Show system header" />
                <Switch checked={form.footer_enabled} disabled={!form.enabled} onChange={(footer_enabled) => setForm({ ...form, footer_enabled })} label="Show system footer" />
              </div>
            </CardBody>
          </Card>

          <Card>
            <CardBody className="flex flex-col gap-3">
              <div>
                <h2 className="font-semibold text-foreground">Header content</h2>
                <p className="mt-1 text-xs text-muted">Use the visual editor or edit the sanitized HTML source directly.</p>
              </div>
              <RichHtmlEmailEditor editorRef={headerEditor} value={form.header_html} view={headerView} onViewChange={setHeaderView} onChange={(header_html) => setForm({ ...form, header_html })} />
            </CardBody>
          </Card>

          <Card>
            <CardBody className="flex flex-col gap-3">
              <div>
                <h2 className="font-semibold text-foreground">Footer content</h2>
                <p className="mt-1 text-xs text-muted">Include the company identity and physical address required for your messages.</p>
              </div>
              <RichHtmlEmailEditor editorRef={footerEditor} value={form.footer_html} view={footerView} onViewChange={setFooterView} onChange={(footer_html) => setForm({ ...form, footer_html })} />
            </CardBody>
          </Card>

          <div className="flex justify-end">
            <Button variant="parent" loading={update.isPending} onClick={() => void save()}>Save email branding</Button>
          </div>
        </div>

        <Card className="overflow-hidden p-4 sm:sticky sm:top-4 sm:p-5">
          <div className="mb-3">
            <h2 className="font-semibold text-foreground">Saved branding preview</h2>
            <p className="text-xs text-muted">Save changes to refresh this inbox-style preview.</p>
          </div>
          <iframe title="Email branding preview" srcDoc={branding.data.preview_html} className="h-[70vh] min-h-[32rem] w-full rounded-xl border border-border bg-white" sandbox="" />
        </Card>
      </div>
    </div>
  )
}
