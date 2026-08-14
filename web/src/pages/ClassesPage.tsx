import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { Alert, Badge, Button, Card, CardBody, Input, Modal, Skeleton } from '@/components/ui'
import { ApiError } from '@/lib/api'
import { useCreateClass, useManageableClasses } from '@/lib/school/queries'

export function ClassesPage() {
  const { data, isLoading, isError } = useManageableClasses()
  const [showCreate, setShowCreate] = useState(false)

  if (isLoading) return <Skeleton className="h-48" />
  if (isError || !data) return <Alert variant="danger">We couldn’t load your classes. Please refresh and try again.</Alert>

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
          <h1 className="font-display text-2xl font-bold text-foreground">My classes</h1>
          <p className="mt-1 text-muted">Open a class to manage learners, courses, assignments, and progress.</p>
        </div>
        <Button onClick={() => setShowCreate(true)}>New class</Button>
      </div>

      {data.length === 0 ? (
        <div className="flex flex-col items-start gap-3 rounded-xl bg-surface-muted px-5 py-8">
          <div>
            <h2 className="font-semibold text-foreground">Create your first class</h2>
            <p className="mt-1 max-w-xl text-sm text-muted">Classes keep your learner roster, assigned courses, assignments, and analytics together.</p>
          </div>
          <Button onClick={() => setShowCreate(true)}>Create class</Button>
        </div>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {data.map((classroom) => (
            <li key={classroom.id}>
              <Link to={`/classes/${classroom.id}`} className="group block h-full rounded-2xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                <Card className="h-full transition-colors group-hover:bg-surface-muted">
                  <CardBody className="flex h-full flex-col gap-4">
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <h2 className="font-semibold text-foreground">{classroom.name}</h2>
                        <p className="mt-1 text-sm text-muted">{classroom.students} learner{classroom.students === 1 ? '' : 's'}</p>
                      </div>
                      {classroom.level && <Badge variant="info">{classroom.level}</Badge>}
                    </div>
                    <span className="mt-auto text-sm font-semibold text-primary">Open class →</span>
                  </CardBody>
                </Card>
              </Link>
            </li>
          ))}
        </ul>
      )}

      <CreateClassModal open={showCreate} onClose={() => setShowCreate(false)} />
    </div>
  )
}

function CreateClassModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const createClass = useCreateClass()
  const [name, setName] = useState('')
  const [level, setLevel] = useState('')
  const [error, setError] = useState<string | null>(null)

  async function submit(event: FormEvent) {
    event.preventDefault()
    setError(null)
    try {
      await createClass.mutateAsync({ name: name.trim(), level: level.trim() || undefined })
      setName('')
      setLevel('')
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not create the class.')
    }
  }

  return (
    <Modal open={open} onClose={onClose} title="Create class" description="You will be assigned as this class’s teacher.">
      <form className="flex flex-col gap-4" onSubmit={submit}>
        {error && <Alert variant="danger">{error}</Alert>}
        <Input label="Class name" value={name} onChange={(event) => setName(event.target.value)} required />
        <Input label="Level (optional)" value={level} onChange={(event) => setLevel(event.target.value)} />
        <Button type="submit" loading={createClass.isPending} disabled={!name.trim()}>Create class</Button>
      </form>
    </Modal>
  )
}
