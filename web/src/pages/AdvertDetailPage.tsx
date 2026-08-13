import { useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Badge, Button, Card, CardBody, Input, Skeleton, Switch } from '@/components/ui'
import { ApiError, type UpdateAdvertPlacementInput } from '@/lib/api'
import {
  useAdminAdvertPlacement,
  useDeleteAdvertPlacement,
  useToggleAdvertPlacement,
  useUpdateAdvertPlacement,
} from '@/lib/admin/queries'

function fmtDate(iso: string | null) {
  return iso ? new Date(iso).toLocaleString() : '—'
}

function Stat({ label, value }: { label: string; value: number | string }) {
  return (
    <Card>
      <CardBody className="py-4">
        <p className="text-xs uppercase tracking-wide text-muted">{label}</p>
        <p className="mt-1 text-2xl font-bold tabular-nums text-foreground">{value}</p>
      </CardBody>
    </Card>
  )
}

export function AdvertDetailPage() {
  const { advertId } = useParams()
  const id = Number(advertId)
  const navigate = useNavigate()
  const { data, isLoading, isError } = useAdminAdvertPlacement(id)
  const update = useUpdateAdvertPlacement()
  const toggle = useToggleAdvertPlacement()
  const del = useDeleteAdvertPlacement()

  const [editing, setEditing] = useState(false)
  const [form, setForm] = useState<UpdateAdvertPlacementInput>({})
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  if (isLoading) return <Skeleton className="h-96" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load this advert.</Alert>

  function startEditing() {
    setForm({ name: data!.name, size: data!.size ?? '', target_url: data!.target_url })
    setError(null)
    setFieldErrors({})
    setEditing(true)
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setFieldErrors({})
    try {
      await update.mutateAsync({ id, input: form })
      setEditing(false)
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setError(err.message)
      } else {
        setError('Could not update the advert.')
      }
    }
  }

  async function onDelete() {
    if (!confirm(`Delete “${data!.name}”? This cannot be undone.`)) return
    await del.mutateAsync(id)
    navigate('/admin/adverts')
  }

  return (
    <div className="flex max-w-2xl flex-col gap-6">
      <AdminPageHeader
        title={data.name}
        description={`${data.position} · ${data.size ?? 'no size set'}`}
        backTo="/admin/adverts"
        backLabel="Adverts"
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant={data.is_active ? 'success' : 'neutral'}>{data.is_active ? 'Active' : 'Inactive'}</Badge>
            <Button variant="ghost" onClick={startEditing}>
              Edit
            </Button>
            <Button variant="parent" loading={toggle.isPending} onClick={() => toggle.mutate(id)}>
              {data.is_active ? 'Deactivate' : 'Activate'}
            </Button>
          </div>
        }
      />

      {error && <Alert variant="danger">{error}</Alert>}

      <div className="grid gap-4 sm:grid-cols-2">
        <Stat label="Impressions" value={data.impressions_count.toLocaleString()} />
        <Stat label="Clicks" value={data.clicks_count.toLocaleString()} />
      </div>

      <Card>
        <CardBody className="flex flex-col gap-4">
          <img src={data.image_url} alt={data.name} className="max-h-64 w-full rounded-xl object-contain" />

          {editing ? (
            <form onSubmit={onSubmit} className="flex flex-col gap-4">
              <Input
                label="Name"
                required
                value={form.name ?? ''}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                error={fieldErrors.name}
              />
              <Input
                label="Size"
                value={form.size ?? ''}
                onChange={(e) => setForm((f) => ({ ...f, size: e.target.value }))}
                error={fieldErrors.size}
              />
              <Input
                label="Target URL"
                required
                type="url"
                value={form.target_url ?? ''}
                onChange={(e) => setForm((f) => ({ ...f, target_url: e.target.value }))}
                error={fieldErrors.target_url}
              />
              <Switch
                checked={!!form.is_active}
                onChange={(v) => setForm((f) => ({ ...f, is_active: v }))}
                label="Active"
              />
              <div className="flex justify-end gap-2">
                <Button type="button" variant="ghost" onClick={() => setEditing(false)}>
                  Cancel
                </Button>
                <Button type="submit" variant="parent" loading={update.isPending}>
                  Save changes
                </Button>
              </div>
            </form>
          ) : (
            <dl className="flex flex-col gap-2 text-sm">
              <Row label="Target URL" value={data.target_url} />
              <Row label="Activated" value={fmtDate(data.activated_at)} />
              <Row label="Starts" value={fmtDate(data.starts_at)} />
              <Row label="Ends" value={fmtDate(data.ends_at)} />
              <Row label="Created" value={fmtDate(data.created_at)} />
            </dl>
          )}
        </CardBody>
      </Card>

      <div className="flex justify-end">
        <Button variant="danger" loading={del.isPending} onClick={onDelete}>
          Delete advert
        </Button>
      </div>
    </div>
  )
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between">
      <dt className="text-muted">{label}</dt>
      <dd className="font-medium text-foreground">{value}</dd>
    </div>
  )
}
