import { useEffect, useState, type FormEvent } from 'react'
import { useQueries, useQuery } from '@tanstack/react-query'
import { Alert, Badge, Button, Card, CardBody, CardHeader, CardTitle, Icon, Input, Skeleton } from '@/components/ui'
import type { IconName } from '@/components/ui'
import { schoolApi } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import { schoolKeys, useMyClasses } from '@/lib/school/queries'
import { ReferralActivity } from '@/components/referral/ReferralActivity'

export function TeacherProfilePage() {
  const { user } = useAuth()
  const classes = useMyClasses()
  const profile = useQuery({ queryKey: ['teacher-profile', user?.user.id], queryFn: schoolApi.teacherProfile })
  const [experience, setExperience] = useState('')
  const [language, setLanguage] = useState('')
  const [state, setState] = useState('')
  const [dialects, setDialects] = useState('')
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState('')
  useEffect(() => {
    if (!profile.data) return
    setExperience(profile.data.years_experience?.toString() ?? '')
    setLanguage(profile.data.native_language ?? '')
    setState(profile.data.state ?? '')
    setDialects(profile.data.dialects?.join(', ') ?? '')
  }, [profile.data])

  async function saveProfile(event: FormEvent) {
    event.preventDefault()
    setSaving(true)
    setMessage('')
    try {
      const result = await schoolApi.updateTeacherProfile({
        years_experience: experience === '' ? null : Number(experience),
        native_language: language.trim() || null,
        state: state.trim() || null,
        dialects: dialects.split(',').map((part) => part.trim()).filter(Boolean),
      })
      setMessage('Teaching profile saved.')
      await profile.refetch()
      setDialects(result.dialects?.join(', ') ?? '')
    } catch {
      setMessage('Could not save your teaching profile. Check the details and try again.')
    } finally {
      setSaving(false)
    }
  }

  const assignmentQueries = useQueries({
    queries: (classes.data ?? []).map((c) => ({
      queryKey: schoolKeys.assignments(c.id),
      queryFn: () => schoolApi.classAssignments(c.id),
      enabled: !!classes.data,
    })),
  })

  const assignmentsCreated = assignmentQueries.reduce((sum, q) => sum + (q.data?.length ?? 0), 0)
  const totalStudents = (classes.data ?? []).reduce((sum, c) => sum + c.students, 0)
  const loadingStats = classes.isLoading || assignmentQueries.some((q) => q.isLoading)

  return (
    <div className="flex flex-col gap-8">
      <h1 className="font-display text-2xl font-bold text-foreground">Your teaching profile</h1>

      <div className="grid gap-4 sm:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Your account</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            <Row label="Email" value={user?.user.email} />
            <Row label="Verified" value={user?.user.email_verified ? 'Yes' : 'No'} />
            <Row
              label="Institution"
              value={user?.organizations.length ? user.organizations.map((o) => o.name).filter(Boolean).join(', ') : undefined}
            />
          </CardBody>
        </Card>

        <div className="grid grid-cols-3 gap-3">
          <Kpi icon="cap" label="Classes" value={classes.data?.length ?? 0} loading={classes.isLoading} />
          <Kpi icon="users" label="Students" value={totalStudents} loading={loadingStats} />
          <Kpi icon="clipboard" label="Assignments" value={assignmentsCreated} loading={loadingStats} />
        </div>
      </div>

      <Card>
        <CardHeader><CardTitle>About your teaching</CardTitle></CardHeader>
        <CardBody>
          <p className="mb-4 text-sm text-muted">These details are visible to your school and to parents of children in your classes.</p>
          {profile.isError && <Alert variant="danger">Couldn’t load your teaching details.</Alert>}
          {message && <Alert variant={message.startsWith('Could') ? 'danger' : 'success'}>{message}</Alert>}
          <form onSubmit={saveProfile} className="grid gap-4 sm:grid-cols-2">
            <Input label="Years of teaching experience" type="number" min={0} max={80} value={experience} onChange={(event) => setExperience(event.target.value)} />
            <Input label="Native language" value={language} onChange={(event) => setLanguage(event.target.value)} />
            <Input label="State" value={state} onChange={(event) => setState(event.target.value)} />
            <Input label="Dialects spoken (comma separated)" value={dialects} onChange={(event) => setDialects(event.target.value)} />
            <div><Button type="submit" loading={saving} disabled={profile.isLoading}>Save profile</Button></div>
          </form>
        </CardBody>
      </Card>

      <ReferralActivity key={user?.user.id} hideWhenEmpty />

      <section>
        <h2 className="mb-3 font-display text-lg font-bold text-foreground">Classes you teach</h2>
        {classes.isLoading ? (
          <Skeleton className="h-24" />
        ) : classes.isError ? (
          <Alert variant="danger">Couldn’t load your classes.</Alert>
        ) : (classes.data?.length ?? 0) === 0 ? (
          <Card>
            <CardBody className="py-8 text-center text-sm text-muted">You aren’t teaching any classes yet.</CardBody>
          </Card>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {classes.data?.map((c) => (
              <Card key={c.id}>
                <CardBody className="flex flex-col gap-1">
                  <div className="flex items-center justify-between">
                    <p className="font-semibold text-foreground">{c.name}</p>
                    {c.level && <Badge variant="info">{c.level}</Badge>}
                  </div>
                  <p className="text-sm text-muted">{c.students} students</p>
                </CardBody>
              </Card>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}

function Row({ label, value }: { label: string; value?: string }) {
  return (
    <div className="flex items-center justify-between">
      <span className="text-muted">{label}</span>
      <span className="font-medium text-foreground">{value ?? '—'}</span>
    </div>
  )
}

function Kpi({ icon, label, value, loading }: { icon: IconName; label: string; value: number; loading?: boolean }) {
  return (
    <Card>
      <CardBody className="flex flex-col items-center gap-1.5 text-center">
        <span className="flex size-9 items-center justify-center rounded-xl bg-primary-soft text-primary">
          <Icon name={icon} />
        </span>
        <p className="font-display text-xl font-bold text-foreground">{loading ? '—' : value}</p>
        <p className="text-xs text-muted">{label}</p>
      </CardBody>
    </Card>
  )
}
