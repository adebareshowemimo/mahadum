import { useNavigate } from 'react-router-dom'
import { Alert, Button, Card, CardBody, Skeleton } from '@/components/ui'
import { ApiError, type CourseSummary } from '@/lib/api'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'
import { useCourses, useEnroll } from '@/lib/learning/queries'

/**
 * Learner-facing course catalog — browse and start any published course for
 * the active profile. Distinct from `/courses` (the content-owner/admin CMS
 * authoring list): parents/students/supervisors couldn't reach a course
 * catalog at all before this page existed, short of having no learning path
 * yet (the only other place courses were browsable was `LearnPage`'s
 * first-run `EnrollCard`, which disappears once a path exists).
 */
export function CourseCatalogPage() {
  const { activeLearner } = useActiveProfile()
  const { data: courses, isLoading, isError } = useCourses()
  const enroll = useEnroll(activeLearner?.id ?? 0)
  const navigate = useNavigate()

  if (!activeLearner) {
    return (
      <Card>
        <CardBody className="flex flex-col items-center gap-3 py-12 text-center">
          <span className="text-4xl" aria-hidden="true">
            🧑‍🎓
          </span>
          <h1 className="font-display text-xl font-bold text-foreground">Choose a learner</h1>
          <p className="max-w-xs text-sm text-muted">
            Pick a profile from the switcher in the top bar to browse courses.
          </p>
        </CardBody>
      </Card>
    )
  }

  if (isLoading) {
    return (
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <Skeleton className="h-40" />
        <Skeleton className="h-40" />
        <Skeleton className="h-40" />
      </div>
    )
  }

  if (isError) {
    return <Alert variant="danger">We couldn’t load the course catalog. Please refresh and try again.</Alert>
  }

  const available = (courses ?? []).filter((c: CourseSummary) => c.is_published)

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-bold text-foreground">Courses</h1>
        <p className="mt-1 text-muted">Start a new course for {activeLearner.display_name}.</p>
      </div>

      {enroll.isError && (
        <Alert variant="danger">
          {enroll.error instanceof ApiError ? enroll.error.message : 'Could not enroll. Please try again.'}
        </Alert>
      )}

      {available.length === 0 ? (
        <Card>
          <CardBody className="py-10 text-center text-sm text-muted">No courses are available yet.</CardBody>
        </Card>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {available.map((course) => (
            <Card key={course.id}>
              <CardBody className="flex h-full flex-col gap-3">
                <div>
                  <p className="font-semibold text-foreground">{course.title}</p>
                  {course.language && <p className="text-xs uppercase tracking-wide text-subtle">{course.language}</p>}
                  {course.description && <p className="mt-1 line-clamp-3 text-sm text-muted">{course.description}</p>}
                </div>
                <Button
                  size="sm"
                  className="mt-auto"
                  loading={enroll.isPending && enroll.variables === course.id}
                  onClick={() =>
                    enroll.mutate(course.id, {
                      onSuccess: () => navigate('/learn'),
                    })
                  }
                >
                  Start course
                </Button>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
