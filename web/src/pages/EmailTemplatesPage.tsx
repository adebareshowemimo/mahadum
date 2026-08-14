import { Link } from 'react-router-dom'
import { AdminPageHeader, DataTable, type Column } from '@/components/admin'
import { Alert, Badge } from '@/components/ui'
import type { EmailTemplateSummary } from '@/lib/api'
import { useEmailTemplates } from '@/lib/admin/queries'

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

  if (isError) return <Alert variant="danger">Couldn't load the email templates.</Alert>

  const columns: Column<EmailTemplateSummary>[] = [
    {
      key: 'template',
      header: 'Template',
      render: (template) => (
        <div>
          <Link to={`/admin/emails/templates/${template.key}`} className="font-semibold text-foreground hover:text-primary hover:underline">
            {template.label}
          </Link>
          <p className="text-xs text-muted">{template.trigger}</p>
        </div>
      ),
    },
    {
      key: 'category',
      header: 'Category',
      hideOnMobile: true,
      render: (template) => <Badge variant={CATEGORY_TONE[template.category] ?? 'neutral'}>{template.category}</Badge>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (template) => !template.customizable
        ? <Badge variant="neutral">Framework-managed</Badge>
        : template.customized
          ? <Badge variant="gold">Customized</Badge>
          : <Badge variant="neutral">Default</Badge>,
    },
    {
      key: 'action',
      header: '',
      className: 'text-right',
      render: (template) => (
        <Link to={`/admin/emails/templates/${template.key}`} className="inline-flex min-h-11 items-center text-sm font-semibold text-primary hover:underline">
          {template.customizable ? 'Open editor' : 'View preview'}
        </Link>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Email templates"
        description="Preview and customize the transactional messages sent by the platform."
      />
      <DataTable columns={columns} rows={data ?? []} getRowId={(template) => template.key} isLoading={isLoading} empty="No templates found." />
    </div>
  )
}
