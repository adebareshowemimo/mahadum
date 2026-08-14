import { useNavigate, useParams } from 'react-router-dom'
import { Alert, Avatar, Badge, Button, Card, CardBody, CardHeader, CardTitle, LinkButton, Skeleton } from '@/components/ui'
import { useChild } from '@/lib/family/queries'
import { usePath } from '@/lib/learning/queries'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'

export function ChildPage() {
  const { learnerId } = useParams()
  const id = Number(learnerId)
  const validId = Number.isInteger(id) && id > 0 ? id : null
  const child = useChild(validId)
  const path = usePath(validId)
  const { setActiveLearner } = useActiveProfile()
  const navigate = useNavigate()

  if (validId === null) {
    return <Alert variant="danger">This child profile link is invalid.</Alert>
  }

  if (child.isLoading) return <ChildSkeleton />
  if (child.isError || !child.data) {
    return <Alert variant="danger">We couldn’t load this child profile. It may not belong to your family.</Alert>
  }

  const learner = child.data
  const nodes = path.data?.units.flatMap((unit) => unit.nodes) ?? []
  const completed = nodes.filter((node) => node.state === 'completed').length

  function openLearning() {
    setActiveLearner(learner.id)
    navigate('/learn')
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <LinkButton to="/family" variant="ghost" size="sm">← Back to family</LinkButton>
        <div className="mt-4 flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <Avatar name={learner.display_name} size="lg" />
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="font-display text-2xl font-bold text-foreground">{learner.display_name}</h1>
                <Badge variant="info">Child</Badge>
              </div>
              <p className="mt-1 text-sm text-muted">
                {learner.target_language ? `Learning ${learner.target_language.toUpperCase()}` : 'Learning language not selected'}
              </p>
            </div>
          </div>
          <Button onClick={openLearning}>View learning</Button>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard label="Child’s coins" value={`🪙 ${learner.coin_balance.toLocaleString()}`} detail="Learner wallet balance" />
        <StatCard label="Lessons completed" value={completed.toLocaleString()} detail={`${nodes.length.toLocaleString()} lessons on the journey`} />
        <StatCard label="Profile PIN" value={learner.pin_protected ? 'Protected' : 'Not set'} detail="Managed from the family page" />
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Coin activity</CardTitle>
        </CardHeader>
        <CardBody>
          {learner.coin_transactions.length === 0 ? (
            <p className="text-sm text-muted">No coins have been added to or spent by this child yet.</p>
          ) : (
            <ul className="divide-y divide-border">
              {learner.coin_transactions.map((transaction) => (
                <li key={transaction.id} className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                  <div>
                    <p className="text-sm font-semibold capitalize text-foreground">{transaction.source.replaceAll('_', ' ')}</p>
                    <p className="text-xs text-muted">{new Date(transaction.created_at).toLocaleDateString()}</p>
                  </div>
                  <div className="text-right">
                    <p className={`font-bold ${transaction.type === 'credit' ? 'text-success' : 'text-danger'}`}>
                      {transaction.type === 'credit' ? '+' : '−'}{transaction.amount.toLocaleString()} coins
                    </p>
                    {transaction.balance_after !== null && (
                      <p className="text-xs text-muted">Balance {transaction.balance_after.toLocaleString()}</p>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          )}
        </CardBody>
      </Card>
    </div>
  )
}

function StatCard({ label, value, detail }: { label: string; value: string; detail: string }) {
  return (
    <Card>
      <CardBody>
        <p className="text-xs font-semibold uppercase tracking-wide text-subtle">{label}</p>
        <p className="mt-2 font-display text-2xl font-bold text-foreground">{value}</p>
        <p className="mt-1 text-xs text-muted">{detail}</p>
      </CardBody>
    </Card>
  )
}

function ChildSkeleton() {
  return (
    <div className="flex flex-col gap-6">
      <Skeleton className="h-20" />
      <div className="grid gap-4 sm:grid-cols-3">
        <Skeleton className="h-32" />
        <Skeleton className="h-32" />
        <Skeleton className="h-32" />
      </div>
      <Skeleton className="h-48" />
    </div>
  )
}
