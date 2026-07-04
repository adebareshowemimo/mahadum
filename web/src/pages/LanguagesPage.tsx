import { useState } from 'react'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Badge, Card, CardBody, Icon, Skeleton, Switch } from '@/components/ui'
import { ApiError, type AdminLanguage } from '@/lib/api'
import { useAdminLanguages, useReorderLanguages, useSetLanguageActive } from '@/lib/admin/queries'

export function LanguagesPage() {
  const { data, isLoading, isError } = useAdminLanguages()
  const setActive = useSetLanguageActive()
  const reorder = useReorderLanguages()
  const [error, setError] = useState<string | null>(null)

  async function toggle(lang: AdminLanguage, isActive: boolean) {
    setError(null)
    try {
      await setActive.mutateAsync({ languageId: lang.id, isActive })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not update the language.')
    }
  }

  // Move the language at `index` one slot up (dir -1) or down (dir +1) and persist
  // the whole new order. Guarded against running off either end.
  async function move(index: number, dir: -1 | 1) {
    if (!data) return
    const next = index + dir
    if (next < 0 || next >= data.length) return
    setError(null)
    const ids = data.map((l) => l.id)
    ;[ids[index], ids[next]] = [ids[next], ids[index]]
    try {
      await reorder.mutateAsync(ids)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not reorder languages.')
    }
  }

  if (isLoading) return <Skeleton className="h-64" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load languages.</Alert>

  return (
    <div className="flex max-w-2xl flex-col gap-6">
      <AdminPageHeader
        title="Languages"
        description="Control which languages learners can see and enrol in, and the order they appear. Deactivating a language hides it from the app without touching its courses."
      />

      {error && <Alert variant="danger">{error}</Alert>}

      <div className="flex flex-col gap-2">
        {data.map((lang, index) => (
          <Card key={lang.id}>
            <CardBody className="flex flex-wrap items-center justify-between gap-3 py-4">
              <div className="flex items-center gap-3">
                <div className="flex flex-col">
                  <button
                    type="button"
                    aria-label={`Move ${lang.name} up`}
                    disabled={index === 0 || reorder.isPending}
                    onClick={() => move(index, -1)}
                    className="text-muted transition-colors hover:text-foreground disabled:opacity-30"
                  >
                    <Icon name="chevron" className="size-4 rotate-180" />
                  </button>
                  <button
                    type="button"
                    aria-label={`Move ${lang.name} down`}
                    disabled={index === data.length - 1 || reorder.isPending}
                    onClick={() => move(index, 1)}
                    className="text-muted transition-colors hover:text-foreground disabled:opacity-30"
                  >
                    <Icon name="chevron" className="size-4" />
                  </button>
                </div>
                <div>
                  <p className="font-semibold text-foreground">
                    {lang.name} <span className="font-mono text-xs text-muted">{lang.code}</span>
                  </p>
                  <p className="text-xs capitalize text-muted">
                    {lang.script}
                    {lang.rtl ? ' · RTL' : ''} · {lang.courses_published}/{lang.courses_total} courses published
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-3">
                <Badge variant={lang.is_active ? 'success' : 'neutral'}>{lang.is_active ? 'Live' : 'Hidden'}</Badge>
                <Switch checked={lang.is_active} disabled={setActive.isPending} onChange={(v) => toggle(lang, v)} />
              </div>
            </CardBody>
          </Card>
        ))}
      </div>
    </div>
  )
}
