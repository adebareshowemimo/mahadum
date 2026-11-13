import { useState, type ReactNode } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import { Alert, Avatar, Badge, Button, Card, CardBody, CardHeader, CardTitle, Icon, learnerAvatarPresetId, LinkButton, Skeleton } from '@/components/ui'
import { useChild } from '@/lib/family/queries'
import { usePath } from '@/lib/learning/queries'
import { formatMoney } from '@/lib/format'
import { ProfilePictureModal } from '@/components/learner/ProfilePictureModal'
import { ApiError, familyApi } from '@/lib/api'

export function ChildPage() {
  const { learnerId } = useParams()
  const id = Number(learnerId)
  const validId = Number.isInteger(id) && id > 0 ? id : null
  const child = useChild(validId)
  const path = usePath(validId)
  const [pictureOpen, setPictureOpen] = useState(false)

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

  return (
    <div className="flex flex-col gap-6">
      <div>
        <LinkButton to="/family" variant="ghost" size="sm">← Back to family</LinkButton>
        <div className="mt-4 flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <Avatar name={learner.display_name} src={learner.avatar_url ?? undefined} avatarId={learner.avatar_id ?? learnerAvatarPresetId(learner.id)} size="lg" />
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="font-display text-2xl font-bold text-foreground">{learner.display_name}</h1>
                <Badge variant="info">Child</Badge>
              </div>
              <p className="mt-1 text-sm text-muted">
                {learner.target_language ? `Learning ${learner.target_language.toUpperCase()}` : 'Learning language not selected'}
              </p>
              <LinkButton to={`/learning-goal?learner=${learner.id}`} variant="ghost" size="sm">{learner.learning_goal ? 'Change learning goal' : 'Choose learning goal'}</LinkButton>
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" onClick={() => setPictureOpen(true)}>Change picture</Button>
            <span className="text-sm text-muted">Use Profiles in the top bar and enter the parent PIN to open learning as {learner.display_name}.</span>
          </div>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="Child’s coins"
          value={<span className="inline-flex items-center gap-2"><Icon name="coin" className="size-6 text-gold-600" />{learner.coin_balance.toLocaleString()}</span>}
          detail="Learner wallet balance"
        />
        <StatCard label="Allowance" value={formatMoney(learner.currency_minor, 'NGN')} detail="Not available for child payments" />
        <StatCard label="Lessons completed" value={completed.toLocaleString()} detail={`${nodes.length.toLocaleString()} lessons on the journey`} />
        <StatCard label="Profile PIN" value={learner.pin_protected ? 'Protected' : 'Not set'} detail="Managed from the family page" />
      </div>

      <StartingLevelControl learnerId={learner.id} current={learner.starting_level_position ?? 0} options={learner.starting_level_options ?? []} started={completed > 0} />

      {!!learner.teachers?.length && <Card><CardHeader><CardTitle>School teachers</CardTitle></CardHeader><CardBody className="space-y-2 text-sm">{learner.teachers.map((teacher) => <p key={teacher.name}><strong>{teacher.name}</strong> · {[teacher.years_experience != null ? `${teacher.years_experience} years teaching` : null, teacher.native_language, teacher.state, ...teacher.dialects].filter(Boolean).join(' · ')}</p>)}</CardBody></Card>}

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

      <ProfilePictureModal learner={learner} open={pictureOpen} onClose={() => setPictureOpen(false)} />
    </div>
  )
}

function StartingLevelControl({ learnerId, current, options, started }: { learnerId: number; current: number; options: number[]; started: boolean }) {
  const queryClient = useQueryClient()
  const [position, setPosition] = useState(current)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  if (options.length === 0) return null

  async function save() {
    setBusy(true); setError(null)
    try {
      await familyApi.setStartingLevel(learnerId, position)
      await queryClient.invalidateQueries({ queryKey: ['family', 'child', learnerId] })
      await queryClient.invalidateQueries({ queryKey: ['learner-path', learnerId] })
    } catch (err) { setError(err instanceof ApiError ? err.message : 'Could not change the starting level.') }
    finally { setBusy(false) }
  }

  return <Card><CardHeader><CardTitle>Starting level</CardTitle></CardHeader><CardBody className="space-y-3">
    <p className="text-sm text-muted">Choose where this child begins in their enrolled courses. Earlier lessons stay available only if you return to an earlier starting level.</p>
    {error && <Alert variant="danger">{error}</Alert>}
    <div className="flex flex-wrap items-center gap-3"><select aria-label="Starting level" className="h-11 rounded-xl border border-border-strong bg-surface px-3" value={position} onChange={(event) => setPosition(Number(event.target.value))} disabled={started || busy}>{options.map((option) => <option key={option} value={option}>Level {option}</option>)}</select><Button variant="parent" onClick={() => void save()} disabled={started || busy || position === current}>{busy ? 'Saving…' : 'Save starting level'}</Button></div>
    {started && <p className="text-sm text-muted">Starting level is locked after learning begins.</p>}
  </CardBody></Card>
}

function StatCard({ label, value, detail }: { label: string; value: ReactNode; detail: string }) {
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
