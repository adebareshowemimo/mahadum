import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Button, Card, CardBody, FileUpload, Input, Switch } from '@/components/ui'
import { ApiError, contentApi, type AdvertPosition, type CreateAdvertPlacementInput } from '@/lib/api'
import { useCreateAdvertPlacement } from '@/lib/admin/queries'

export function AdvertCreatePage() {
  const navigate = useNavigate()
  const create = useCreateAdvertPlacement()
  const [form, setForm] = useState<Omit<CreateAdvertPlacementInput, 'media_asset_id'>>({
    name: '',
    position: 'leaderboard',
    size: '',
    target_url: '',
    is_active: false,
  })
  const [file, setFile] = useState<File | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [uploading, setUploading] = useState(false)

  function set<K extends keyof typeof form>(key: K, value: (typeof form)[K]) {
    setForm((f) => ({ ...f, [key]: value }))
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setFieldErrors({})

    if (!file) {
      setError('Choose a creative image to upload.')
      return
    }

    setUploading(true)
    let mediaAssetId: number
    try {
      const asset = await contentApi.uploadMedia(file)
      mediaAssetId = asset.id
    } catch {
      setUploading(false)
      setError('Could not upload the creative image.')
      return
    }
    setUploading(false)

    try {
      const advert = await create.mutateAsync({
        ...form,
        name: form.name.trim(),
        target_url: form.target_url.trim(),
        size: form.size?.trim() || undefined,
        media_asset_id: mediaAssetId,
      })
      navigate(`/admin/adverts/${advert.id}`)
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setError(err.message)
      } else {
        setError('Could not create the advert.')
      }
    }
  }

  const pending = uploading || create.isPending

  return (
    <div className="flex max-w-2xl flex-col gap-6">
      <AdminPageHeader
        title="New advert"
        description="Upload a banner creative and set where it appears."
        backTo="/admin/adverts"
        backLabel="Adverts"
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
                <span className="font-semibold text-foreground">Position</span>
                <select
                  value={form.position}
                  onChange={(e) => set('position', e.target.value as AdvertPosition)}
                  className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                >
                  <option value="leaderboard">Leaderboard (top banner)</option>
                  <option value="inline">Inline (in-content card)</option>
                </select>
              </label>
              <Input
                label="Size"
                placeholder="e.g. 970x250"
                value={form.size ?? ''}
                onChange={(e) => set('size', e.target.value)}
                error={fieldErrors.size}
              />
            </div>
            <Input
              label="Target URL"
              required
              type="url"
              placeholder="https://…"
              value={form.target_url}
              onChange={(e) => set('target_url', e.target.value)}
              error={fieldErrors.target_url}
            />
            <div className="flex flex-col gap-1.5 text-sm">
              <span className="font-semibold text-foreground">Creative image</span>
              <FileUpload accept="image/*" hint="PNG or JPG" onFile={setFile} />
            </div>
            <Switch checked={!!form.is_active} onChange={(v) => set('is_active', v)} label="Active immediately" />
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="ghost" onClick={() => navigate('/admin/adverts')}>
                Cancel
              </Button>
              <Button type="submit" variant="parent" loading={pending} disabled={!form.name.trim() || !form.target_url.trim() || !file}>
                Create advert
              </Button>
            </div>
          </form>
        </CardBody>
      </Card>
    </div>
  )
}
