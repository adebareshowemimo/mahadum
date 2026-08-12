import { Alert, Badge, Icon } from '@/components/ui'
import { Column, DataTable } from '@/components/admin/DataTable'
import type { CoursePerformance } from '@/lib/api'
import { useCoursesPerformance } from '@/lib/content/queries'

export function CoursePerformancePage() {
  const { data, isLoading, isError } = useCoursesPerformance()

  const columns: Column<CoursePerformance>[] = [
    {
      key: 'title',
      header: 'Course',
      render: (c) => (
        <div>
          <p className="font-semibold text-foreground">{c.title}</p>
          <p className="text-xs text-muted">{c.language}</p>
        </div>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (c) => <Badge variant={c.is_published ? 'success' : 'neutral'}>{c.is_published ? 'Published' : 'Draft'}</Badge>,
      hideOnMobile: true,
    },
    {
      key: 'structure',
      header: 'Levels / lessons',
      render: (c) => `${c.levels_count} / ${c.lessons_count}`,
      hideOnMobile: true,
    },
    {
      key: 'enrollments',
      header: 'Enrollments',
      render: (c) => <span className="font-semibold">{c.enrollments}</span>,
    },
    {
      key: 'completions',
      header: 'Lesson completions',
      render: (c) => c.lesson_completions,
    },
    {
      key: 'accuracy',
      header: 'Quiz accuracy',
      render: (c) => (c.quiz_accuracy == null ? '—' : `${Math.round(c.quiz_accuracy * 100)}%`),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-bold text-foreground">Content performance</h1>
        <p className="mt-1 text-muted">How your courses are being learned — enrollments, completions and quiz accuracy.</p>
      </div>

      <Alert variant="info" icon={<Icon name="sparkles" className="size-4" />}>
        MAHADUM.360 sells platform subscriptions, not individual courses, so revenue and subscription counts aren’t
        attributed per course — that view stays with platform-wide reporting. This shows the engagement data that is
        genuinely tracked per course.
      </Alert>

      {isError && <Alert variant="danger">Couldn’t load course performance.</Alert>}

      <DataTable
        columns={columns}
        rows={data ?? []}
        getRowId={(c) => c.id}
        isLoading={isLoading}
        empty="No courses yet."
      />
    </div>
  )
}
