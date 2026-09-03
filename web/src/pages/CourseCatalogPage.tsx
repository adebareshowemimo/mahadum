import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Alert, Avatar, Button, Icon, Input, learnerAvatarPresetId, LinkButton, Skeleton } from '@/components/ui'
import { ApiError, type CourseCatalogQuery } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import { useConfig } from '@/lib/config/useConfig'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'
import { useCourses, useEnroll, useStartCourseAsSelf } from '@/lib/learning/queries'

const PAGE_SIZE = 12
const selectClass = 'h-11 w-full rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring'

export function CourseCatalogPage() {
  const { activeLearner, learners, setActiveLearner } = useActiveProfile()
  const { hasRole } = useAuth()
  const { data: config } = useConfig()
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [language, setLanguage] = useState('')
  const [level, setLevel] = useState('')
  const [page, setPage] = useState(1)
  const parentCanLearn = hasRole('parent')

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setSearch(searchInput.trim())
      setPage(1)
    }, 300)
    return () => window.clearTimeout(timer)
  }, [searchInput])

  const query = useMemo<CourseCatalogQuery>(() => ({
    ...(activeLearner ? { learner_id: activeLearner.id } : {}),
    ...(search ? { q: search } : {}),
    ...(language ? { language } : {}),
    ...(level ? { level } : {}),
    page,
    per_page: PAGE_SIZE,
  }), [activeLearner, search, language, level, page])

  const coursesQuery = useCourses(query)
  const enroll = useEnroll(activeLearner?.id ?? 0)
  const startAsSelf = useStartCourseAsSelf()
  const navigate = useNavigate()
  const response = coursesQuery.data
  const courses = response?.data ?? []
  const meta = response?.meta
  const levels = response?.filters.levels ?? []
  const hasFilters = Boolean(searchInput || language || level)

  function clearFilters() {
    setSearchInput('')
    setSearch('')
    setLanguage('')
    setLevel('')
    setPage(1)
  }

  return (
    <div className="flex flex-col gap-6" aria-busy={coursesQuery.isFetching}>
      <header className="flex flex-col gap-4 border-b border-border pb-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p className="text-sm font-semibold text-primary">Learning library</p>
          <h1 className="mt-1 font-display text-3xl font-bold text-foreground">Find the next course</h1>
          <p className="mt-2 max-w-2xl text-sm text-muted">
            Search the full catalogue by language or level. Courses are shown in manageable pages as the library grows.
          </p>
        </div>
        {activeLearner ? (
          <div className="flex items-center gap-3 rounded-2xl bg-surface-muted px-4 py-3">
            <Avatar
              name={activeLearner.display_name}
              src={activeLearner.avatar_url ?? undefined}
              avatarId={activeLearner.avatar_id ?? learnerAvatarPresetId(activeLearner.id)}
              size="md"
            />
            <div>
              <p className="text-xs text-muted">Choosing for</p>
              <p className="font-semibold text-foreground">{activeLearner.display_name}</p>
            </div>
          </div>
        ) : parentCanLearn ? (
          <div className="max-w-sm rounded-2xl bg-primary-soft px-4 py-3 text-sm text-foreground">
            <strong>Learning as yourself.</strong> Choose a child from Profiles to browse for them instead.
          </div>
        ) : null}
      </header>

      {!activeLearner && !parentCanLearn && (
        <Alert variant="info" title="Browse first, choose a learner later">
          {learners.length > 0 ? (
            <>Select a learner from <strong>Profiles</strong> in the top bar when you’re ready to begin.</>
          ) : (
            <span className="flex flex-wrap items-center gap-2">
              Add a learner profile before starting a course.
              <LinkButton to="/family" size="sm" variant="secondary">Add a learner</LinkButton>
            </span>
          )}
        </Alert>
      )}

      <section className="rounded-2xl border border-border bg-surface-muted p-4" aria-label="Course filters">
        <div className="grid gap-3 md:grid-cols-[minmax(16rem,1fr)_12rem_12rem_auto] md:items-end">
          <Input
            label="Search courses"
            type="search"
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
            placeholder="Search by title or topic"
            leftIcon={<Icon name="search" className="size-4" />}
          />
          <label className="flex flex-col gap-1.5 text-sm font-semibold text-foreground">
            Language
            <select value={language} onChange={(event) => { setLanguage(event.target.value); setPage(1) }} className={selectClass}>
              <option value="">All languages</option>
              {(config?.languages ?? []).map((item) => <option key={item.code} value={item.code}>{item.name}</option>)}
            </select>
          </label>
          <label className="flex flex-col gap-1.5 text-sm font-semibold text-foreground">
            Level
            <select value={level} onChange={(event) => { setLevel(event.target.value); setPage(1) }} className={selectClass}>
              <option value="">All levels</option>
              {levels.map((item) => <option key={item} value={item}>{item}</option>)}
            </select>
          </label>
          <Button variant="secondary" onClick={clearFilters} disabled={!hasFilters}>Clear</Button>
        </div>
      </section>

      {enroll.isError && (
        <Alert variant="danger">{enroll.error instanceof ApiError ? enroll.error.message : 'Could not enroll. Please try again.'}</Alert>
      )}
      {startAsSelf.isError && (
        <Alert variant="danger">{startAsSelf.error instanceof ApiError ? startAsSelf.error.message : 'Could not start this course. Please try again.'}</Alert>
      )}
      {coursesQuery.isError && (
        <Alert variant="danger">We couldn’t load the course catalogue. Please refresh and try again.</Alert>
      )}

      {coursesQuery.isLoading ? (
        <div className="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-surface">
          {Array.from({ length: 5 }, (_, index) => <Skeleton key={index} className="m-4 h-20" />)}
        </div>
      ) : !coursesQuery.isError && courses.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-border-strong bg-surface px-6 py-14 text-center">
          <Icon name="search" className="mx-auto size-8 text-subtle" />
          <h2 className="mt-3 font-display text-lg font-bold text-foreground">No matching courses</h2>
          <p className="mt-1 text-sm text-muted">Try a different search, language, or level.</p>
          {hasFilters && <Button className="mt-4" variant="secondary" onClick={clearFilters}>Clear filters</Button>}
        </div>
      ) : !coursesQuery.isError ? (
        <section>
          <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p className="text-sm text-muted">
              {meta && meta.total > 0
                ? `Showing ${(meta.current_page - 1) * meta.per_page + 1}–${Math.min(meta.current_page * meta.per_page, meta.total)} of ${meta.total} courses`
                : `${courses.length} courses`}
            </p>
            {coursesQuery.isFetching && !coursesQuery.isLoading && <span className="text-xs text-muted">Updating…</span>}
          </div>

          <div className="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-surface">
            {courses.map((course) => (
              <article key={course.id} className="flex flex-col gap-4 p-4 transition-colors hover:bg-surface-muted sm:flex-row sm:items-center sm:p-5">
                <div className="flex min-w-0 flex-1 gap-4">
                  <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary">
                    <Icon name="book" />
                  </div>
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 className="font-display text-base font-bold text-foreground">{course.title}</h2>
                      {course.is_enrolled && <span className="rounded-full bg-leaf-100 px-2 py-0.5 text-xs font-semibold text-leaf-700">Enrolled</span>}
                    </div>
                    {course.description && <p className="mt-1 line-clamp-2 max-w-3xl text-sm text-muted">{course.description}</p>}
                    <div className="mt-2 flex flex-wrap gap-2 text-xs font-medium text-subtle">
                      {course.language && <span>{languageName(course.language, config?.languages)}</span>}
                      {course.language && course.level_band && <span aria-hidden="true">•</span>}
                      {course.level_band && <span>{course.level_band}</span>}
                      {typeof course.levels_count === 'number' && <span>• {course.levels_count} levels</span>}
                    </div>
                  </div>
                </div>
                <Button
                  size="sm"
                  className="w-full shrink-0 sm:w-auto"
                  disabled={!activeLearner && !parentCanLearn}
                  variant={course.is_enrolled ? 'secondary' : 'primary'}
                  loading={
                    (!activeLearner && startAsSelf.isPending && startAsSelf.variables === course.id)
                    || (!!activeLearner && !course.is_enrolled && enroll.isPending && enroll.variables === course.id)
                  }
                  onClick={() => {
                    if (!activeLearner && parentCanLearn) {
                      startAsSelf.mutate(course.id, {
                        onSuccess: (learner) => {
                          setActiveLearner(learner.id)
                          navigate('/learn')
                        },
                      })
                    } else if (course.is_enrolled) {
                      navigate('/learn')
                    } else {
                      enroll.mutate(course.id, { onSuccess: () => navigate('/learn') })
                    }
                  }}
                >
                  {!activeLearner ? (parentCanLearn ? 'Start course' : 'Choose a learner') : course.is_enrolled ? 'Continue' : 'Start course'}
                </Button>
              </article>
            ))}
          </div>

          {meta && meta.last_page > 1 && (
            <nav className="mt-5 flex items-center justify-between gap-3" aria-label="Course pages">
              <Button variant="secondary" disabled={meta.current_page <= 1 || coursesQuery.isFetching} onClick={() => setPage((current) => current - 1)}>
                Previous
              </Button>
              <p className="text-sm font-medium text-muted">Page {meta.current_page} of {meta.last_page}</p>
              <Button variant="secondary" disabled={meta.current_page >= meta.last_page || coursesQuery.isFetching} onClick={() => setPage((current) => current + 1)}>
                Next
              </Button>
            </nav>
          )}
        </section>
      ) : null}
    </div>
  )
}

function languageName(code: string, languages: Array<{ code: string; name: string }> | undefined): string {
  return languages?.find((language) => language.code === code)?.name ?? code.toUpperCase()
}
