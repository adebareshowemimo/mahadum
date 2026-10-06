import { useState, type FormEvent } from 'react'
import { Alert, Button, Input, Modal, Skeleton } from '@/components/ui'
import { ApiError, type SchoolClassDetail } from '@/lib/api'
import { useTeachers, useUpdateClass } from '@/lib/school/queries'

/** Controls follow the API's existing policy for this particular class. */
export function ClassManagementActions({ classroom }: { classroom: SchoolClassDetail }) {
  const [editing, setEditing] = useState(false)
  const [assigning, setAssigning] = useState(false)
  const [name, setName] = useState(classroom.name)
  const [level, setLevel] = useState(classroom.level ?? '')
  const [teacherId, setTeacherId] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [fields, setFields] = useState<Record<string, string>>({})
  const update = useUpdateClass()
  const teachers = useTeachers(classroom.capabilities?.assign_teacher ? classroom.organization_id ?? null : null, assigning)

  function open(mode: 'edit' | 'assign') {
    setError(null)
    setFields({})
    setName(classroom.name)
    setLevel(classroom.level ?? '')
    setTeacherId('')
    setEditing(mode === 'edit')
    setAssigning(mode === 'assign')
  }

  async function save(event: FormEvent, mode: 'edit' | 'assign') {
    event.preventDefault()
    setError(null)
    setFields({})
    try {
      await update.mutateAsync({ classId: classroom.id, input: mode === 'assign'
        ? { teacher_user_id: Number(teacherId) }
        : { name: name.trim(), level: level.trim() } })
      setEditing(false)
      setAssigning(false)
    } catch (err) {
      if (err instanceof ApiError) {
        setFields(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setError(err.message)
      } else setError('Could not update this class. Please try again.')
    }
  }

  if (!classroom.capabilities?.update) return null

  return <>
    <Button variant="outline" onClick={() => open('edit')}>Edit class</Button>
    {classroom.capabilities.assign_teacher && <Button variant="outline" onClick={() => open('assign')}>Assign teacher</Button>}
    <Modal open={editing} onClose={() => setEditing(false)} title="Edit class">
      <form className="flex flex-col gap-4" onSubmit={(event) => void save(event, 'edit')}>
        {error && <Alert variant="danger">{error}</Alert>}
        <Input label="Class name" value={name} onChange={(event) => setName(event.target.value)} error={fields.name} required />
        <Input label="Level (optional)" value={level} onChange={(event) => setLevel(event.target.value)} error={fields.level} />
        <Button type="submit" loading={update.isPending} disabled={!name.trim()}>Save class</Button>
      </form>
    </Modal>
    <Modal open={assigning} onClose={() => setAssigning(false)} title="Assign teacher" description={`Choose an active teacher for ${classroom.name}.`}>
      <form className="flex flex-col gap-4" onSubmit={(event) => void save(event, 'assign')}>
        {error && <Alert variant="danger">{error}</Alert>}
        {teachers.isLoading && <Skeleton className="h-11" />}
        {teachers.isError && <Alert variant="danger">Could not load this school's teachers. Check your school access and try again.</Alert>}
        {!teachers.isLoading && !teachers.isError && teachers.data?.length === 0 && <Alert>No active teachers are linked to this school. Ask your administrator to link an active teacher, then try again.</Alert>}
        <label className="flex flex-col gap-1.5">
          <span className="text-sm font-semibold text-foreground">Teacher</span>
          <select value={teacherId} onChange={(event) => setTeacherId(event.target.value)}
            disabled={teachers.isLoading || teachers.isError || !teachers.data?.length}
            aria-invalid={fields.teacher_user_id ? true : undefined}
            className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring">
            <option value="">Choose a teacher</option>
            {teachers.data?.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name}</option>)}
          </select>
          {fields.teacher_user_id && <span role="alert" className="text-xs text-danger">{fields.teacher_user_id}</span>}
        </label>
        <Button type="submit" loading={update.isPending} disabled={!teacherId || teachers.isError || teachers.isLoading}>Save teacher</Button>
      </form>
    </Modal>
  </>
}
