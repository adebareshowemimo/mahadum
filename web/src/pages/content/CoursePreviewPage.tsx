import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useQueries } from '@tanstack/react-query'
import { Alert, Button3D, Icon, Skeleton } from '@/components/ui'
import { cn } from '@/lib/cn'
import { contentApi, type AuthorLesson, type AuthorLevel } from '@/lib/api'
import {
  contentKeys,
  useAuthorCourses,
  useCourseLevels,
  useLevelLessons,
} from '@/lib/content/queries'
import {
  SlideDeck,
  authorToSlides,
  createPreviewService,
  type DeckStats,
} from '@/components/learning/player'

/**
 * Learner-styled preview that opens in its own tab (outside the authoring shell).
 *
 *   /courses/:courseId/preview                 → unit start screens (pick one)
 *   /courses/:courseId/levels/:levelId/preview → the unit's lesson path + player
 *
 * Each lesson is played as its own slide deck; finishing one marks it done on
 * the unit path so authors get the same sense of progress a learner would.
 * Quizzes are graded locally against the authoring answer key; nothing persists.
 */
export function CoursePreviewPage() {
  const { courseId, levelId } = useParams()
  const id = Number(courseId)

  if (levelId != null) return <UnitView courseId={id} levelId={Number(levelId)} />
  return <CourseOverview courseId={id} />
}

// ---- Course scope: a start card per unit ----

function CourseOverview({ courseId }: { courseId: number }) {
  const courses = useAuthorCourses()
  const levels = useCourseLevels(courseId)
  const course = courses.data?.find((c) => c.id === courseId)

  return (
    <div className="min-h-screen bg-background">
      <PreviewHeader kind="Course preview" title={course?.title ?? 'Preview'} closeTo={`/courses/${courseId}`} />

      <main className="mx-auto w-full max-w-2xl px-4 py-6">
        <p className="mb-6 rounded-xl bg-surface-muted px-4 py-2.5 text-center text-xs text-muted">
          This is how learners play your content. Pick a unit to step through it. Draft lessons are included for review.
        </p>

        {levels.isLoading ? (
          <div className="flex flex-col gap-4">
            <Skeleton className="h-28" />
            <Skeleton className="h-28" />
          </div>
        ) : levels.isError ? (
          <Alert variant="danger">Couldn’t load this course.</Alert>
        ) : (levels.data?.length ?? 0) === 0 ? (
          <Alert variant="info">Nothing to preview yet — add a unit and some lessons first.</Alert>
        ) : (
          <div className="flex flex-col gap-4">
            {levels.data?.map((level) => (
              <UnitStartCard key={level.id} courseId={courseId} level={level} />
            ))}
          </div>
        )}
      </main>
    </div>
  )
}

function UnitStartCard({ courseId, level }: { courseId: number; level: AuthorLevel }) {
  const navigate = useNavigate()
  const lessons = useLevelLessons(level.id)
  const count = lessons.data?.length ?? 0

  return (
    <div className="flex flex-col gap-4 rounded-3xl border border-border bg-surface p-6 text-center shadow-sm">
      <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-primary-soft text-2xl" aria-hidden="true">
        🚀
      </span>
      <div>
        <p className="text-[11px] font-bold uppercase tracking-wide text-subtle">Unit {level.position}</p>
        <h2 className="font-display text-xl font-bold text-foreground">{level.title}</h2>
        <p className="mt-1 text-sm text-muted">
          {lessons.isLoading ? 'Loading…' : `${count} lesson${count === 1 ? '' : 's'}`}
        </p>
      </div>
      <Button3D
        variant="primary"
        size="lg"
        fullWidth
        disabled={count === 0}
        onClick={() => navigate(`/courses/${courseId}/levels/${level.id}/preview`)}
      >
        {count === 0 ? 'No lessons yet' : 'Start unit'}
      </Button3D>
    </div>
  )
}

// ---- Unit scope: lesson path + per-lesson player ----

function UnitView({ courseId, levelId }: { courseId: number; levelId: number }) {
  const levels = useCourseLevels(courseId)
  const lessons = useLevelLessons(levelId)
  const lessonList = lessons.data ?? []

  const details = useQueries({
    queries: lessonList.map((l) => ({
      queryKey: contentKeys.lesson(l.id),
      queryFn: () => contentApi.lesson(l.id),
    })),
  })

  const level = levels.data?.find((l) => l.id === levelId)
  const detailsLoading = details.some((d) => d.isLoading)
  const detailsError = details.some((d) => d.isError)
  const allLoaded = lessonList.length > 0 && details.length === lessonList.length && details.every((d) => d.data)

  const [completed, setCompleted] = useState<Set<number>>(new Set())
  const [playingId, setPlayingId] = useState<number | null>(null)
  // Bumped to remount the deck for a fresh replay of the same lesson.
  const [lessonRun, setLessonRun] = useState(0)
  const playLesson = (id: number) => {
    setPlayingId(id)
    setLessonRun(0)
  }

  // One built deck (slides + answer key) per lesson, keyed by lesson id.
  const lessonIds = lessonList.map((l) => l.id).join(',')
  const decks = useMemo(() => {
    const map = new Map<number, ReturnType<typeof authorToSlides>>()
    details.forEach((d, i) => {
      if (d.data) map.set(lessonList[i].id, authorToSlides(d.data as AuthorLesson))
    })
    return map
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [allLoaded, lessonIds])

  const backToPath = `/courses/${courseId}/preview`

  if (lessons.isLoading || detailsLoading) {
    return (
      <Shell kind="Unit preview" title={level?.title ?? 'Loading…'} closeTo={backToPath}>
        <Skeleton className="h-72" />
      </Shell>
    )
  }
  if (lessons.isError || detailsError) {
    return (
      <Shell kind="Unit preview" title={level?.title ?? 'Preview'} closeTo={backToPath}>
        <Alert variant="danger">Couldn’t load this unit’s lessons.</Alert>
      </Shell>
    )
  }

  // Playing a single lesson.
  if (playingId != null) {
    const built = decks.get(playingId)
    const idx = lessonList.findIndex((l) => l.id === playingId)
    const lesson = lessonList[idx]
    const next = lessonList[idx + 1]
    if (built && lesson) {
      return (
        <LessonDeck
          key={`${playingId}-${lessonRun}`}
          lesson={lesson}
          built={built}
          nextTitle={next?.title}
          onComplete={() => setCompleted((s) => new Set(s).add(playingId))}
          onExit={() => setPlayingId(null)}
          onReplay={() => setLessonRun((r) => r + 1)}
          onNext={next ? () => playLesson(next.id) : undefined}
        />
      )
    }
  }

  // The unit's lesson path.
  return (
    <Shell kind="Unit preview" title={level?.title ?? 'Unit'} closeTo={`/courses/${courseId}`}>
      <UnitPath lessons={lessonList} completed={completed} onPick={playLesson} />
    </Shell>
  )
}

function UnitPath({
  lessons,
  completed,
  onPick,
}: {
  lessons: AuthorLesson[]
  completed: Set<number>
  onPick: (lessonId: number) => void
}) {
  const doneCount = lessons.filter((l) => completed.has(l.id)).length
  const activeId = lessons.find((l) => !completed.has(l.id))?.id

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center justify-between gap-3">
        <p className="text-sm text-muted">
          <span className="font-bold text-foreground">{doneCount}</span> of {lessons.length} lessons
        </p>
        <div className="h-2.5 w-32 overflow-hidden rounded-full bg-surface-muted">
          <div
            className="h-full rounded-full bg-leaf-400 transition-all duration-300"
            style={{ width: `${lessons.length ? Math.round((doneCount / lessons.length) * 100) : 0}%` }}
          />
        </div>
      </div>

      <ol className="relative flex flex-col gap-3 before:absolute before:left-5 before:top-5 before:bottom-5 before:w-px before:bg-border">
        {lessons.map((lesson) => {
          const isDone = completed.has(lesson.id)
          const isActive = lesson.id === activeId
          return (
            <li key={lesson.id} className="relative z-10">
              <button
                onClick={() => onPick(lesson.id)}
                className={cn(
                  'flex w-full items-center gap-4 rounded-2xl border p-3 text-left transition-colors',
                  isActive ? 'border-primary bg-surface' : 'border-border bg-surface hover:bg-surface-muted',
                )}
              >
                <span
                  className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-full',
                    isDone && 'bg-leaf-100 text-leaf-700 ring-2 ring-leaf-400',
                    isActive && !isDone && 'bg-primary text-primary-fg ring-4 ring-primary-soft',
                    !isDone && !isActive && 'bg-surface-muted text-subtle',
                  )}
                >
                  {isDone ? '✓' : <Icon name="book" className="size-5" />}
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate font-semibold text-foreground">
                    {lesson.position}. {lesson.title}
                  </span>
                  <span className="text-xs text-muted">
                    {isDone ? 'Completed' : lesson.est_minutes ? `${lesson.est_minutes} min` : 'Lesson'}
                  </span>
                </span>
                <span className="text-sm font-semibold text-primary">
                  {isDone ? 'Replay' : isActive ? 'Start →' : 'Open'}
                </span>
              </button>
            </li>
          )
        })}
      </ol>
    </div>
  )
}

function LessonDeck({
  lesson,
  built,
  nextTitle,
  onComplete,
  onExit,
  onReplay,
  onNext,
}: {
  lesson: AuthorLesson
  built: ReturnType<typeof authorToSlides>
  nextTitle?: string
  onComplete: () => void
  onExit: () => void
  onReplay: () => void
  onNext?: () => void
}) {
  // Fresh hearts holder for this lesson's run.
  const hearts = useRef({ value: 5 })
  const service = useMemo(() => createPreviewService(built.key, hearts.current), [built.key])

  return (
    <SlideDeck
      title={lesson.title}
      subtitle={lesson.est_minutes ? `About ${lesson.est_minutes} min` : undefined}
      startIcon="📚"
      startCta="Start lesson"
      slides={built.slides}
      service={service}
      initialHearts={5}
      onExit={onExit}
      renderComplete={(stats) => (
        <LessonEnd
          stats={stats}
          nextTitle={nextTitle}
          onComplete={onComplete}
          onExit={onExit}
          onReplay={onReplay}
          onNext={onNext}
        />
      )}
    />
  )
}

function LessonEnd({
  stats,
  nextTitle,
  onComplete,
  onExit,
  onReplay,
  onNext,
}: {
  stats: DeckStats
  nextTitle?: string
  onComplete: () => void
  onExit: () => void
  onReplay: () => void
  onNext?: () => void
}) {
  // Mark the lesson complete on the path the moment its end screen appears.
  useEffect(() => {
    onComplete()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Lead with quiz accuracy; the questions count (e.g. 3) is distinct from the
  // total step count (e.g. 5, which also includes watch/speak steps).
  const accuracy = stats.quizTotal > 0 ? Math.round((stats.correct / stats.quizTotal) * 100) : null
  const nonQuiz = stats.total - stats.quizTotal

  return (
    <div className="flex flex-col items-center gap-5 text-center animate-pop-in">
      <span className="text-7xl" aria-hidden="true">🎉</span>
      <h1 className="font-display text-3xl font-bold text-foreground">Lesson complete</h1>

      <div className="flex flex-wrap justify-center gap-3">
        {accuracy !== null ? (
          <>
            <Stat label="Accuracy" value={`${accuracy}%`} />
            <Stat label="Questions" value={`${stats.correct}/${stats.quizTotal}`} />
          </>
        ) : (
          <Stat label="Steps done" value={String(stats.total)} />
        )}
        {stats.hearts !== null && <Stat label="Hearts left" value={`❤️ ${stats.hearts}`} />}
      </div>

      <p className="text-sm text-muted">
        {stats.quizTotal > 0
          ? `${stats.total} steps · ${stats.quizTotal} question${stats.quizTotal === 1 ? '' : 's'}${
              nonQuiz > 0 ? ` + ${nonQuiz} watch/speak` : ''
            }. Preview only — nothing saved.`
          : 'Preview only — nothing was saved.'}
      </p>

      <div className="flex w-full flex-col gap-2">
        {onNext && (
          <Button3D variant="reward" size="lg" fullWidth onClick={onNext}>
            Next lesson: {nextTitle}
          </Button3D>
        )}
        <Button3D variant="neutral" size="lg" fullWidth onClick={onReplay}>
          Replay lesson
        </Button3D>
        <Button3D variant="neutral" size="lg" fullWidth onClick={onExit}>
          Back to unit path
        </Button3D>
      </div>
    </div>
  )
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl bg-surface-muted px-5 py-4">
      <p className="font-display text-2xl font-bold text-foreground">{value}</p>
      <p className="text-xs text-muted">{label}</p>
    </div>
  )
}

// ---- Shared chrome ----

function Shell({
  kind,
  title,
  closeTo,
  children,
}: {
  kind: string
  title: string
  closeTo: string
  children: React.ReactNode
}) {
  return (
    <div className="min-h-screen bg-background">
      <PreviewHeader kind={kind} title={title} closeTo={closeTo} />
      <main className="mx-auto w-full max-w-2xl px-4 py-6">{children}</main>
    </div>
  )
}

function PreviewHeader({ kind, title, closeTo }: { kind: string; title: string; closeTo: string }) {
  return (
    <header className="sticky top-0 z-20 border-b border-border bg-surface/90 backdrop-blur">
      <div className="mx-auto flex max-w-2xl items-center gap-3 px-4 py-3">
        <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-lg" aria-hidden="true">
          👁
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-[11px] font-bold uppercase tracking-wide text-primary">{kind}</p>
          <p className="truncate font-display font-bold text-foreground">{title}</p>
        </div>
        <Link
          to={closeTo}
          className="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-muted hover:bg-surface-muted"
        >
          <Icon name="close" className="size-4" /> Close
        </Link>
      </div>
    </header>
  )
}
