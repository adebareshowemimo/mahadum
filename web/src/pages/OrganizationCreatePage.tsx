import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Button, Card, CardBody, Input } from '@/components/ui'
import { ApiError, type CreateOrgInput } from '@/lib/api'
import { useCreateOrg } from '@/lib/admin/queries'

export function OrganizationCreatePage() {
  const navigate = useNavigate()
  const create = useCreateOrg()
  const [form, setForm] = useState<CreateOrgInput>({ name: '', type: 'school', status: 'active' })
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  function set<K extends keyof CreateOrgInput>(key: K, value: CreateOrgInput[K]) {
    setForm((f) => ({ ...f, [key]: value }))
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setFieldErrors({})
    try {
      const org = await create.mutateAsync({
        ...form,
        name: form.name.trim(),
        contact_email: form.contact_email?.trim() || undefined,
        domain: form.domain?.trim() || undefined,
        cac_number: form.cac_number?.trim() || undefined,
        address: form.address?.trim() || undefined,
      })
      navigate(`/admin/orgs/${org.id}`)
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setError(err.message)
      } else {
        setError('Could not create the organization.')
      }
    }
  }

  return (
    <div className="flex max-w-2xl flex-col gap-6">
      <AdminPageHeader
        title="New organization"
        description="Create a school or partner directly, bypassing self-registration."
        backTo="/admin/orgs"
        backLabel="Organizations"
      />

      {error && <Alert variant="danger">{error}</Alert>}

      <Card>
        <CardBody>
          <form onSubmit={onSubmit} className="flex flex-col gap-4">
            <Input
              label="Name"
              required
              value={form.name}
              onChange={(e) => set('name', e.target.value)}
              error={fieldErrors.name}
            />
            <div className="grid gap-4 sm:grid-cols-2">
              <label className="flex flex-col gap-1.5 text-sm">
                <span className="font-semibold text-foreground">Type</span>
                <select
                  value={form.type}
                  onChange={(e) => set('type', e.target.value)}
                  className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                >
                  <option value="school">School</option>
                  <option value="district">District</option>
                  <option value="ngo">NGO</option>
                  <option value="business">Business</option>
                </select>
              </label>
              <label className="flex flex-col gap-1.5 text-sm">
                <span className="font-semibold text-foreground">Initial status</span>
                <select
                  value={form.status}
                  onChange={(e) => set('status', e.target.value as CreateOrgInput['status'])}
                  className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                >
                  <option value="active">Active</option>
                  <option value="pending">Pending</option>
                </select>
              </label>
            </div>
            <Input
              label="Contact email"
              type="email"
              value={form.contact_email ?? ''}
              onChange={(e) => set('contact_email', e.target.value)}
              error={fieldErrors.contact_email}
            />
            <div className="grid gap-4 sm:grid-cols-2">
              <Input
                label="Domain"
                placeholder="school.edu.ng"
                value={form.domain ?? ''}
                onChange={(e) => set('domain', e.target.value)}
                error={fieldErrors.domain}
              />
              <Input
                label="CAC number"
                value={form.cac_number ?? ''}
                onChange={(e) => set('cac_number', e.target.value)}
                error={fieldErrors.cac_number}
              />
            </div>
            <Input
              label="Address"
              value={form.address ?? ''}
              onChange={(e) => set('address', e.target.value)}
              error={fieldErrors.address}
            />
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="ghost" onClick={() => navigate('/admin/orgs')}>
                Cancel
              </Button>
              <Button type="submit" variant="parent" loading={create.isPending} disabled={!form.name.trim()}>
                Create organization
              </Button>
            </div>
          </form>
        </CardBody>
      </Card>
    </div>
  )
}
