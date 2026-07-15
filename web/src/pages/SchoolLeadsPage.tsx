import { AdminPageHeader, DataTable, type Column } from '@/components/admin'
import { Alert } from '@/components/ui'
import type { SchoolLead } from '@/lib/api'
import { useSchoolLeads } from '@/lib/admin/queries'

const columns: Column<SchoolLead>[] = [
  {
    key: 'school',
    header: 'School',
    render: (l) => (
      <div>
        <p className="font-semibold text-foreground">{l.school_name}</p>
        {l.city && <p className="text-xs text-muted">{l.city}</p>}
      </div>
    ),
  },
  {
    key: 'contact',
    header: 'Contact',
    render: (l) => (
      <div>
        <p className="text-foreground">{l.contact_name}</p>
        <p className="text-xs text-muted">{l.email}</p>
      </div>
    ),
  },
  { key: 'phone', header: 'Phone', render: (l) => l.phone ?? '—', hideOnMobile: true },
  { key: 'size', header: 'School size', render: (l) => l.school_size ?? '—', hideOnMobile: true },
  {
    key: 'received',
    header: 'Received',
    render: (l) => (l.created_at ? new Date(l.created_at).toLocaleDateString() : '—'),
  },
]

export function SchoolLeadsPage() {
  const { data, isLoading, isError } = useSchoolLeads()

  if (isError) return <Alert variant="danger">Couldn’t load school leads.</Alert>

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader title="School leads" description="Contact details submitted via the pricing page's Get Quote flow, for manual sales follow-up." />
      <DataTable
        columns={columns}
        rows={data ?? []}
        getRowId={(l) => l.id}
        isLoading={isLoading}
        empty="No school leads yet."
      />
    </div>
  )
}
