import { Link, useParams, useSearchParams } from 'react-router-dom'
import { Alert } from '@/components/ui'
import { AssignmentDetailContent } from './AssignmentsPage'

export function AssignmentDetailPage() {
  const assignmentId = Number(useParams().assignmentId)
  const [searchParams] = useSearchParams()
  const classId = Number(searchParams.get('class'))

  if (!Number.isInteger(assignmentId) || assignmentId < 1 || !Number.isInteger(classId) || classId < 1) {
    return <Alert variant="danger">This assignment link is incomplete. Open it again from a class workspace.</Alert>
  }

  return (
    <div className="flex flex-col gap-4">
      <Link to={`/classes/${classId}?tab=assignments`} className="inline-flex min-h-11 items-center self-start text-sm font-semibold text-primary hover:underline">← Back to class assignments</Link>
      <AssignmentDetailContent classId={classId} assignmentId={assignmentId} />
    </div>
  )
}
