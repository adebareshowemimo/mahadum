import { useState } from 'react'
import { AdminPageHeader, DataTable, type Column } from '@/components/admin'
import { Alert, Button } from '@/components/ui'
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
  const [page, setPage] = useState(1)
  const { data, isLoading, isError, isFetching } = useSchoolLeads(page)

  if (isError) return <Alert variant="danger">Couldn’t load school leads.</Alert>

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader title="School leads" description="Contact details submitted via the pricing page's Get Quote flow, for manual sales follow-up." />
      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        getRowId={(l) => l.id}
        isLoading={isLoading}
        empty="No school leads yet."
      />
      {data?.meta && data.meta.total > 0 && (
        <div className="flex items-center justify-between text-sm text-muted">
          <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} leads</span>
          <div className="flex gap-2">
            <Button size="sm" variant="ghost" disabled={page <= 1 || isFetching} onClick={() => setPage((current) => current - 1)}>Previous</Button>
            <Button size="sm" variant="ghost" disabled={page >= data.meta.last_page || isFetching} onClick={() => setPage((current) => current + 1)}>Next</Button>
          </div>
        </div>
      )}
    </div>
  )
}
