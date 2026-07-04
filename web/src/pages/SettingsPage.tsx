import { useEffect, useState } from 'react'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Button, Card, CardBody, Input, Skeleton, Switch } from '@/components/ui'
import { ApiError, type SettingItem, type SettingValue } from '@/lib/api'
import { useSettings, useUpdateSettings } from '@/lib/admin/queries'

export function SettingsPage() {
  const { data, isLoading, isError } = useSettings()
  const update = useUpdateSettings()
  const [form, setForm] = useState<Record<string, SettingValue>>({})
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saved, setSaved] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  // Seed the form once the settings load (and re-seed if the server data changes).
  useEffect(() => {
    if (!data) return
    const seed: Record<string, SettingValue> = {}
    for (const group of data.groups) for (const s of group.settings) seed[s.key] = s.value
    setForm(seed)
  }, [data])

  function setValue(key: string, value: SettingValue) {
    setForm((f) => ({ ...f, [key]: value }))
    setSaved(false)
  }

  async function onSave() {
    setErrors({})
    setFormError(null)
    setSaved(false)
    try {
      await update.mutateAsync(form)
      setSaved(true)
    } catch (err) {
      if (err instanceof ApiError) {
        setErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setFormError(err.message)
      } else {
        setFormError('Could not save settings.')
      }
    }
  }

  if (isLoading) return <Skeleton className="h-96" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load settings.</Alert>

  return (
    <div className="flex max-w-2xl flex-col gap-6">
      <AdminPageHeader
        title="System settings"
        description="Platform configuration. Changes take effect immediately (no redeploy) and are audited."
        actions={
          <Button variant="parent" loading={update.isPending} onClick={onSave}>
            Save changes
          </Button>
        }
      />

      {saved && <Alert variant="success">Settings saved.</Alert>}
      {formError && <Alert variant="danger">{formError}</Alert>}

      {data.groups.map((group) => (
        <Card key={group.key}>
          <CardBody className="flex flex-col gap-5">
            <h2 className="font-display text-lg font-bold text-foreground">{group.label}</h2>
            {group.settings.map((s) => (
              <SettingField
                key={s.key}
                setting={s}
                value={form[s.key] ?? s.value}
                error={errors[s.key]}
                onChange={(v) => setValue(s.key, v)}
              />
            ))}
          </CardBody>
        </Card>
      ))}
    </div>
  )
}

function SettingField({
  setting,
  value,
  error,
  onChange,
}: {
  setting: SettingItem
  value: SettingValue
  error?: string
  onChange: (value: SettingValue) => void
}) {
  if (setting.type === 'bool') {
    return (
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-sm font-semibold text-foreground">{setting.label}</p>
          {setting.help && <p className="text-xs text-muted">{setting.help}</p>}
        </div>
        <Switch checked={Boolean(value)} onChange={onChange} />
      </div>
    )
  }

  return (
    <div>
      <Input
        label={setting.label}
        type={setting.type === 'int' ? 'number' : 'text'}
        value={String(value ?? '')}
        min={setting.min ?? undefined}
        max={setting.max ?? undefined}
        error={error}
        onChange={(e) => onChange(setting.type === 'int' ? Number(e.target.value) : e.target.value)}
      />
      {setting.help && !error && <p className="mt-1 text-xs text-muted">{setting.help}</p>}
    </div>
  )
}
