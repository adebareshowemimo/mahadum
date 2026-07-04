import { useMemo, useState } from 'react'
import { AdminPageHeader, AdminToolbar, DataTable, FilterSelect, type Column } from '@/components/admin'
import { Alert, Badge, Button } from '@/components/ui'
import { ApiError, type AdminCoursesQuery, type CourseSummary } from '@/lib/api'
import { useConfig } from '@/lib/config/useConfig'
import { useAdminCourses, useSetCoursePublished } from '@/lib/content/queries'

export function AdminCoursesPage() {
  const [search, setSearch] = useState('')
  const [language, setLanguage] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState<string | null>(null)
  const [actingId, setActingId] = useState<number | null>(null)

  const params: AdminCoursesQuery = useMemo(
    () => ({ q: search || undefined, language: language || undefined, status: status || undefined, page }),
    [search, language, status, page],
  )
  const { data, isLoading, isError, isFetching } = useAdminCourses(params)
  const setPublished = useSetCoursePublished()
  const config = useConfig()

  const languageOptions = (config.data?.languages ?? []).map((l) => ({ label: l.name, value: l.code }))

  function onFilter(setter: (v: string) => void) {
    return (v: string) => {
      setter(v)
      setPage(1)
    }
  }

  async function togglePublish(course: CourseSummary) {
    setError(null)
    setActingId(course.id)
    try {
      await setPublished.mutateAsync({ courseId: course.id, publish: !course.is_published })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not update the course.')
    } finally {
      setActingId(null)
    }
  }

  const columns: Column<CourseSummary>[] = [
    {
      key: 'title',
      header: 'Course',
      render: (c) => (
        <div>
          <p className="font-semibold text-foreground">{c.title}</p>
          <p className="text-xs uppercase text-muted">
            {c.language ?? '—'} · {c.level_band ?? 'no band'} · {c.levels_count ?? 0} levels
          </p>
        </div>
      ),
    },
    { key: 'owner', header: 'Owner', hideOnMobile: true, render: (c) => c.owner ?? '—' },
    {
      key: 'status',
      header: 'Status',
      render: (c) => (
        <Badge variant={c.is_published ? 'success' : 'neutral'}>{c.is_published ? 'published' : 'draft'}</Badge>
      ),
    },
    {
      key: 'action',
      header: '',
      className: 'text-right',
      render: (c) => (
        <Button
          size="sm"
          variant={c.is_published ? 'ghost' : 'parent'}
          loading={actingId === c.id && isFetching}
          onClick={() => togglePublish(c)}
        >
          {c.is_published ? 'Unpublish' : 'Publish'}
        </Button>
      ),
    },
  ]

  if (isError) return <Alert variant="danger">Couldn’t load courses.</Alert>

  const meta = data?.meta

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader title="Courses" description="Every course on the platform — control what learners can see." />

      {error && <Alert variant="danger">{error}</Alert>}

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        getRowId={(c) => c.id}
        isLoading={isLoading}
        empty="No courses match your filters."
        toolbar={
          <AdminToolbar search={search} onSearch={(v) => { setSearch(v); setPage(1) }} searchPlaceholder="Search course title…">
            <FilterSelect label="Language" value={language} onChange={onFilter(setLanguage)} options={languageOptions} allLabel="All languages" />
            <FilterSelect
              label="Status"
              value={status}
              onChange={onFilter(setStatus)}
              options={[
                { label: 'Published', value: 'published' },
                { label: 'Draft', value: 'draft' },
              ]}
              allLabel="All statuses"
            />
          </AdminToolbar>
        }
      />

      {meta && meta.total > 0 && (
        <div className="flex items-center justify-between text-sm text-muted">
          <span>
            Page {meta.current_page} of {meta.last_page} · {meta.total} courses
          </span>
          <div className="flex gap-2">
            <Button size="sm" variant="ghost" disabled={meta.current_page <= 1 || isFetching} onClick={() => setPage((p) => p - 1)}>
              Previous
            </Button>
            <Button size="sm" variant="ghost" disabled={meta.current_page >= meta.last_page || isFetching} onClick={() => setPage((p) => p + 1)}>
              Next
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
