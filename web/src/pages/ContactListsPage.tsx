import { useRef, useState, type FormEvent } from 'react'
import { AdminPageHeader, DataTable, type Column } from '@/components/admin'
import { Alert, Badge, Button, Card, CardBody, Input, Modal, Skeleton } from '@/components/ui'
import { ApiError, adminApi, type ContactRow, type ImportPreview } from '@/lib/api'
import { useContactList, useContactLists, useCreateContactList, useDeleteContact, useImportContacts } from '@/lib/admin/queries'

export function ContactListsPage() {
  const { data: lists, isLoading } = useContactLists()
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [creating, setCreating] = useState(false)

  if (isLoading) return <Skeleton className="h-64" />

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Contact lists"
        description="Mailing lists of contacts (registered or not). Upload emails, then target a list from a campaign."
        actions={<Button variant="parent" onClick={() => setCreating(true)}>New list</Button>}
      />

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {(lists ?? []).map((l) => (
          <button key={l.id} type="button" onClick={() => setSelectedId(l.id)} className="text-left">
            <Card className={selectedId === l.id ? 'border-primary' : ''}>
              <CardBody className="py-4">
                <p className="font-semibold text-foreground">{l.name}</p>
                <p className="text-xs text-muted">{l.description ?? '—'}</p>
                <p className="mt-2 text-sm text-muted">
                  <span className="font-semibold text-foreground">{l.subscribed}</span> subscribed · {l.contacts} total
                </p>
              </CardBody>
            </Card>
          </button>
        ))}
        {(lists ?? []).length === 0 && (
          <Card><CardBody className="py-8 text-center text-sm text-muted">No lists yet. Create one to start uploading.</CardBody></Card>
        )}
      </div>

      {selectedId && <ListDetail listId={selectedId} />}
      {creating && <CreateListModal onClose={() => setCreating(false)} onCreated={setSelectedId} />}
    </div>
  )
}

function CreateListModal({ onClose, onCreated }: { onClose: () => void; onCreated: (id: number) => void }) {
  const create = useCreateContactList()
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [error, setError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    try {
      const list = await create.mutateAsync({ name: name.trim(), description: description.trim() || undefined })
      onCreated(list.id)
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not create the list.')
    }
  }

  return (
    <Modal open onClose={onClose} title="New contact list">
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        {error && <Alert variant="danger">{error}</Alert>}
        <Input label="Name" value={name} onChange={(e) => setName(e.target.value)} required />
        <Input label="Description" value={description} onChange={(e) => setDescription(e.target.value)} />
        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
          <Button type="submit" variant="parent" loading={create.isPending} disabled={!name.trim()}>Create</Button>
        </div>
      </form>
    </Modal>
  )
}

function ListDetail({ listId }: { listId: number }) {
  const [page, setPage] = useState(1)
  const { data, isLoading, isFetching } = useContactList(listId, page)
  const remove = useDeleteContact()

  const columns: Column<ContactRow>[] = [
    { key: 'email', header: 'Email', render: (c) => <span className="text-foreground">{c.email}</span> },
    { key: 'name', header: 'Name', hideOnMobile: true, render: (c) => c.name ?? '—' },
    {
      key: 'status',
      header: 'Status',
      render: (c) => <Badge variant={c.status === 'subscribed' ? 'success' : 'neutral'}>{c.status}</Badge>,
    },
    { key: 'source', header: 'Source', hideOnMobile: true, render: (c) => <span className="text-xs text-muted">{c.source ?? '—'}</span> },
    {
      key: 'actions',
      header: '',
      render: (c) => (
        <Button size="sm" variant="ghost" onClick={() => remove.mutate({ listId, contactId: c.id })} aria-label={`Remove ${c.email}`}>
          Remove
        </Button>
      ),
    },
  ]

  const meta = data?.meta

  return (
    <div className="flex flex-col gap-4">
      <UploadPanel listId={listId} />

      <section className="flex flex-col gap-2">
        <h2 className="font-display text-lg font-bold text-foreground">
          {data?.list.name ?? 'Contacts'} <span className="text-muted">({meta?.total ?? 0})</span>
        </h2>
        <DataTable columns={columns} rows={data?.data ?? []} getRowId={(c) => c.id} isLoading={isLoading} empty="No contacts yet — upload some above." />
        {meta && meta.total > 0 && (
          <div className="flex items-center justify-between text-sm text-muted">
            <span>Page {meta.current_page} of {meta.last_page}</span>
            <div className="flex gap-2">
              <Button size="sm" variant="ghost" disabled={meta.current_page <= 1 || isFetching} onClick={() => setPage((p) => p - 1)}>Previous</Button>
              <Button size="sm" variant="ghost" disabled={meta.current_page >= meta.last_page || isFetching} onClick={() => setPage((p) => p + 1)}>Next</Button>
            </div>
          </div>
        )}
      </section>
    </div>
  )
}

function UploadPanel({ listId }: { listId: number }) {
  const importContacts = useImportContacts()
  const fileRef = useRef<HTMLInputElement>(null)
  const [emails, setEmails] = useState('')
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<ImportPreview | null>(null)
  const [previewing, setPreviewing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [result, setResult] = useState<string | null>(null)

  async function onPreview() {
    setError(null)
    setResult(null)
    setPreviewing(true)
    try {
      setPreview(await adminApi.previewContacts(listId, { emails: emails || undefined, file: file ?? undefined }))
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not read the upload.')
    } finally {
      setPreviewing(false)
    }
  }

  async function onImport() {
    if (!preview) return
    setError(null)
    try {
      const res = await importContacts.mutateAsync({ id: listId, contacts: preview.valid })
      setResult(`Imported ${res.imported} contact${res.imported === 1 ? '' : 's'}${res.skipped ? `, skipped ${res.skipped}` : ''}.`)
      setPreview(null)
      setEmails('')
      setFile(null)
      if (fileRef.current) fileRef.current.value = ''
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Import failed.')
    }
  }

  return (
    <Card>
      <CardBody className="flex flex-col gap-3">
        <p className="text-sm font-semibold text-foreground">Upload emails to this list</p>
        {error && <Alert variant="danger">{error}</Alert>}
        {result && <Alert variant="success">{result}</Alert>}

        <textarea
          value={emails}
          onChange={(e) => { setEmails(e.target.value); setPreview(null) }}
          rows={3}
          placeholder="Paste addresses — one per line, or comma/semicolon separated"
          aria-label="Paste email addresses"
          className="w-full rounded-xl border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
        />
        <div className="flex flex-wrap items-center gap-3">
          <input
            ref={fileRef}
            type="file"
            accept=".csv,text/csv,text/plain"
            onChange={(e) => { setFile(e.target.files?.[0] ?? null); setPreview(null) }}
            className="text-sm text-muted"
            aria-label="Upload a CSV file"
          />
          <Button size="sm" variant="secondary" loading={previewing} disabled={!emails.trim() && !file} onClick={onPreview}>
            Preview
          </Button>
        </div>

        {preview && (
          <div className="flex flex-col gap-3 rounded-xl border border-border bg-surface-muted p-3">
            <div className="flex flex-wrap gap-2 text-sm">
              <Badge variant="success">{preview.counts.valid} valid</Badge>
              <Badge variant="neutral">{preview.counts.duplicate} duplicate</Badge>
              <Badge variant="danger">{preview.counts.invalid} invalid</Badge>
              <Badge variant="gold">{preview.counts.suppressed} suppressed</Badge>
              <span className="text-muted">of {preview.counts.total}</span>
            </div>
            <div className="flex justify-end">
              <Button variant="parent" loading={importContacts.isPending} disabled={preview.counts.valid === 0} onClick={onImport}>
                Import {preview.counts.valid} contact{preview.counts.valid === 1 ? '' : 's'}
              </Button>
            </div>
          </div>
        )}
      </CardBody>
    </Card>
  )
}
