import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Alert, Button, Card, CardBody, Input, Skeleton } from '@/components/ui'
import { SchoolGate } from '@/components/school/SchoolGate'
import { TeacherCsvImport } from '@/components/school/TeacherCsvImport'
import { useAuth } from '@/lib/auth/AuthProvider'
import { batch4Api } from '@/lib/batch4/api'

export function SchoolStudentsPage() {
  return <SchoolGate>{(org) => <Students org={org} />}</SchoolGate>
}
export function SchoolTeachersPage() {
  return <SchoolGate>{(org) => <Teachers org={org} />}</SchoolGate>
}
function Students({ org }: { org: number }) {
  const { user } = useAuth()
  const query = useQuery({
    queryKey: ['school-students', user?.user.id, org],
    queryFn: () => batch4Api.students(org),
  })
  const [search, setSearch] = useState('')
  const rows =
    query.data?.filter((row) =>
      `${row.name} ${row.email ?? ''} ${row.classes.map((c) => c.name).join(' ')}`
        .toLowerCase()
        .includes(search.toLowerCase()),
    ) ?? []
  return (
    <div className="space-y-6">
      <h1 className="font-display text-2xl font-bold">Students</h1>
      <p className="text-muted">
        Learners and their classes in your active school.
      </p>
      <Input
        label="Search students or classes"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
      />
      {query.isLoading ? (
        <Skeleton className="h-48" />
      ) : query.isError ? (
        <Alert variant="danger">Couldn’t load students.</Alert>
      ) : (
        <Card>
          <CardBody>
            <ul className="divide-y divide-border">
              {rows.map((row) => (
                <li
                  key={row.id}
                  className="flex flex-wrap justify-between gap-3 py-4"
                >
                  <div>
                    <p className="font-semibold">{row.name}</p>
                    <p className="break-all text-sm text-muted">
                      {row.email ?? 'Managed learner · no login'}
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-3">
                    {row.classes.length ? (
                      row.classes.map((c) => (
                        <Link
                          className="text-primary underline"
                          key={c.id}
                          to={`/classes/${c.id}`}
                        >
                          {c.name}
                        </Link>
                      ))
                    ) : (
                      <span className="text-sm text-muted">No class yet</span>
                    )}
                  </div>
                </li>
              ))}
            </ul>
            {!rows.length && (
              <p className="text-muted">No students match this search.</p>
            )}
          </CardBody>
        </Card>
      )}
    </div>
  )
}
function Teachers({ org }: { org: number }) {
  const { user } = useAuth()
  const cache = useQueryClient()
  const key = ['school-teacher-directory', user?.user.id, org]
  const query = useQuery({
    queryKey: key,
    queryFn: () => batch4Api.teachers(org),
  })
  const [name, setName] = useState(''),
    [email, setEmail] = useState(''),
    [notice, setNotice] = useState('')
  const invite = useMutation({
    mutationFn: () => batch4Api.invite(org, { name, email }),
    onSuccess: async (data) => {
      setNotice(
        data.delivery_status === 'not_configured'
          ? 'Invitation saved. Email delivery is not configured; ask your administrator to configure email before resending.'
          : 'Invitation queued for email delivery. The teacher must verify their email and accept it.',
      )
      setName('')
      setEmail('')
      await cache.invalidateQueries({ queryKey: key })
    },
  })
  const revoke = useMutation({
    mutationFn: (id: number) => batch4Api.revoke(org, id),
    onSuccess: () => cache.invalidateQueries({ queryKey: key }),
  })
  function submit(e: FormEvent) {
    e.preventDefault()
    setNotice('')
    invite.mutate()
  }
  return (
    <div className="space-y-6">
      <h1 className="font-display text-2xl font-bold">Teachers</h1>
      <p className="text-muted">
        Invite a teacher, then{' '}
        <Link className="text-primary underline" to="/school">
          assign them to a class
        </Link>{' '}
        after they accept.
      </p>
      {notice && <Alert>{notice}</Alert>}
      {(invite.error || revoke.error) && (
        <Alert variant="danger">
          {(invite.error || revoke.error)?.message}
        </Alert>
      )}
      <Card>
        <CardBody>
          <h2 className="mb-4 text-lg font-semibold">Invite a teacher</h2>
          <form
            onSubmit={submit}
            className="grid items-end gap-4 sm:grid-cols-3"
          >
            <Input
              label="Teacher name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              maxLength={255}
            />
            <Input
              label="Teacher email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              maxLength={255}
            />
            <Button type="submit" loading={invite.isPending}>
              Send invitation
            </Button>
          </form>
          <p className="mt-3 text-sm text-muted">
            Links expire after seven days. Resending replaces the previous
            invitation.
          </p>
        </CardBody>
      </Card>
      <TeacherCsvImport org={org} />
      {query.isLoading ? (
        <Skeleton className="h-48" />
      ) : query.isError ? (
        <Alert variant="danger">Couldn’t load teachers.</Alert>
      ) : (
        <>
          <Card>
            <CardBody>
              <h2 className="text-lg font-semibold">School teachers</h2>
              <ul className="divide-y divide-border">
                {query.data?.teachers.map((t) => (
                  <li
                    className="flex flex-wrap justify-between gap-3 py-4"
                    key={t.id}
                  >
                    <div>
                      <p className="font-semibold">{t.name}</p>
                      <p className="break-all text-sm text-muted">{t.email}</p>
                    </div>
                    <span className="capitalize text-muted">
                      {t.status} · account {t.account_status}
                    </span>
                  </li>
                ))}
              </ul>
              {!query.data?.teachers.length && (
                <p className="mt-3 text-muted">No teachers have joined yet.</p>
              )}
            </CardBody>
          </Card>
          <Card>
            <CardBody>
              <h2 className="text-lg font-semibold">Invitations</h2>
              <ul className="divide-y divide-border">
                {query.data?.invitations.map((t) => (
                  <li
                    className="flex flex-wrap items-center justify-between gap-3 py-4"
                    key={t.id}
                  >
                    <div>
                      <p className="font-semibold">{t.name}</p>
                      <p className="break-all text-sm text-muted">
                        {t.email} · {t.status}
                      </p>
                      <p className="text-xs text-muted">
                        Expires {new Date(t.expires_at).toLocaleDateString()}
                      </p>
                    </div>
                    <Button
                      variant="outline"
                      aria-label={`Revoke invitation for ${t.name}`}
                      loading={revoke.isPending}
                      onClick={() => revoke.mutate(t.id)}
                    >
                      Revoke
                    </Button>
                  </li>
                ))}
              </ul>
              {!query.data?.invitations.length && (
                <p className="mt-3 text-muted">No outstanding invitations.</p>
              )}
            </CardBody>
          </Card>
        </>
      )}
    </div>
  )
}
