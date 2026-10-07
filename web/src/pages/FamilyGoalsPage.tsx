import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Card, CardBody, Input, Skeleton } from '@/components/ui'
import {
  batch4Api,
  type AlertPreferences,
  type FamilyGoals,
} from '@/lib/batch4/api'
import { familyKeys, useFamily, useWallet } from '@/lib/family/queries'
import { useAuth } from '@/lib/auth/AuthProvider'

const selectClass =
  'h-11 w-full rounded-xl border border-border-strong bg-surface px-3 text-foreground'
type Learner = { id: number; display_name: string }
export function FamilyGoalsPage() {
  const { user } = useAuth()
  const family = useFamily(),
    wallet = useWallet(),
    cache = useQueryClient()
  const key = ['family-goals', user?.user.id]
  const goals = useQuery({ queryKey: key, queryFn: batch4Api.goals })
  const [notice, setNotice] = useState('')
  const action = useMutation({
    mutationFn: (run: () => Promise<unknown>) => run(),
    onSuccess: async () => {
      setNotice('Saved.')
      await Promise.all([
        cache.invalidateQueries({ queryKey: key }),
        cache.invalidateQueries({ queryKey: familyKeys.wallet }),
        cache.invalidateQueries({ queryKey: familyKeys.family }),
        cache.invalidateQueries({ queryKey: ['family-cheers'] }),
      ])
    },
  })
  async function save(run: () => Promise<unknown>) {
    setNotice('')
    await action.mutateAsync(run)
  }
  if (goals.isLoading || family.isLoading) return <Skeleton className="h-64" />
  if (!goals.data || !family.data)
    return <Alert variant="danger">Couldn’t load your family goals.</Alert>
  const learners = family.data.learners
  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-display text-2xl font-bold">Family goals</h1>
        <p className="mt-2 text-muted">
          Learn together, encourage your children and manage shared coins.
        </p>
      </div>
      {notice && <Alert>{notice}</Alert>}
      {action.error && <Alert variant="danger">{action.error.message}</Alert>}
      <Card>
        <CardBody>
          <h2 className="text-lg font-semibold">Family challenges</h2>
          <p className="mt-2 text-sm text-muted">
            Count lessons completed by selected learners during the challenge.
            Challenges add no coins or XP.
          </p>
          <div className="my-4 space-y-3">
            {goals.data.challenges.map((c) => (
              <div key={c.id} className="rounded-xl border border-border p-4">
                <p className="font-semibold">{c.title}</p>
                <p className="text-sm text-muted">
                  {c.completed_lessons} / {c.target_lessons} lessons ·{' '}
                  {c.status} · ends {new Date(c.ends_at).toLocaleDateString()}
                </p>
                <progress
                  className="mt-2 w-full"
                  aria-label={`${c.title} progress`}
                  value={Math.min(c.completed_lessons, c.target_lessons)}
                  max={c.target_lessons}
                />
              </div>
            ))}
            {!goals.data.challenges.length && (
              <p className="text-muted">No challenges yet.</p>
            )}
          </div>
          <ChallengeForm
            learners={learners}
            save={save}
            busy={action.isPending}
          />
        </CardBody>
      </Card>
      <Card>
        <CardBody>
          <h2 className="text-lg font-semibold">Shared coin pools</h2>
          <p className="mt-2 text-sm text-muted">
            Only you can move coins. Contributions use your family wallet;
            returns go back to it. You can also allocate pool coins to a child.
          </p>
          <p className="mt-2 font-semibold">
            Family wallet: {wallet.data?.coin_balance ?? '—'} coins
          </p>
          <div className="my-5 grid gap-4 lg:grid-cols-2">
            {goals.data.pools.map((pool) => (
              <Pool
                key={pool.id}
                pool={pool}
                learners={learners}
                save={save}
                busy={action.isPending}
              />
            ))}
          </div>
          <PoolForm save={save} busy={action.isPending} />
        </CardBody>
      </Card>
      <Card>
        <CardBody>
          <h2 className="text-lg font-semibold">Cheer your family on</h2>
          <p className="mt-2 text-sm text-muted">
            One private cheer per learner each week. It appears on their
            leaderboard and adds no coins or XP.
          </p>
          <ul className="mt-4 space-y-3">
            {learners.map((l) => (
              <li
                key={l.id}
                className="flex flex-wrap items-center justify-between gap-3"
              >
                <span className="font-semibold">{l.display_name}</span>
                <Button
                  variant="soft"
                  loading={action.isPending}
                  onClick={() => {
                    void save(async () => {
                      const result = await batch4Api.cheer(
                        l.id,
                        'We are proud of you!',
                      )
                      setNotice(result.message)
                    }).catch(() => {})
                  }}
                >
                  Cheer {l.display_name}
                </Button>
              </li>
            ))}
          </ul>
        </CardBody>
      </Card>
      <Card>
        <CardBody>
          <h2 className="text-lg font-semibold">Family alerts</h2>
          <p className="mt-2 text-sm text-muted">
            Leave a threshold blank to keep that alert off. Alerts go to your
            verified parent account. Checks run every 15 minutes when the
            scheduler is running; delivery uses your configured channels.
          </p>
          <Preferences
            key={JSON.stringify(goals.data.alerts)}
            initial={goals.data.alerts}
            save={save}
            busy={action.isPending}
          />
        </CardBody>
      </Card>
    </div>
  )
}
function ChallengeForm({
  learners,
  save,
  busy,
}: {
  learners: Learner[]
  save: (run: () => Promise<unknown>) => Promise<void>
  busy: boolean
}) {
  const [title, setTitle] = useState(''),
    [target, setTarget] = useState(''),
    [end, setEnd] = useState(''),
    [selected, setSelected] = useState<number[]>([])
  async function submit(e: FormEvent) {
    e.preventDefault()
    try {
      await save(() =>
        batch4Api.challenge({
          title,
          target_lessons: Number(target),
          learner_ids: selected,
          ends_at: new Date(end).toISOString(),
        }),
      )
      setTitle('')
      setTarget('')
      setEnd('')
      setSelected([])
    } catch {
      /* Error is shown by the page. */
    }
  }
  return (
    <form className="space-y-4" onSubmit={submit}>
      <div className="grid gap-4 sm:grid-cols-3">
        <Input
          label="Challenge name"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          required
          maxLength={255}
        />
        <Input
          label="Lesson goal"
          type="number"
          value={target}
          onChange={(e) => setTarget(e.target.value)}
          min={1}
          max={10000}
          required
        />
        <Input
          label="Challenge ends"
          type="datetime-local"
          value={end}
          onChange={(e) => setEnd(e.target.value)}
          required
        />
      </div>
      <fieldset>
        <legend className="mb-2 text-sm font-semibold">
          Learners taking part
        </legend>
        <div className="flex flex-wrap gap-4">
          {learners.map((l) => (
            <label key={l.id} className="flex min-h-11 items-center gap-2">
              <input
                type="checkbox"
                checked={selected.includes(l.id)}
                onChange={(e) =>
                  setSelected((ids) =>
                    e.target.checked
                      ? [...ids, l.id]
                      : ids.filter((id) => id !== l.id),
                  )
                }
              />
              {l.display_name}
            </label>
          ))}
        </div>
      </fieldset>
      <Button type="submit" loading={busy} disabled={!selected.length}>
        Create challenge
      </Button>
    </form>
  )
}
function PoolForm({
  save,
  busy,
}: {
  save: (run: () => Promise<unknown>) => Promise<void>
  busy: boolean
}) {
  const [name, setName] = useState(''),
    [goal, setGoal] = useState('')
  async function submit(e: FormEvent) {
    e.preventDefault()
    try {
      await save(() => batch4Api.pool({ name, goal_coins: Number(goal) }))
      setName('')
      setGoal('')
    } catch {
      /* Page error. */
    }
  }
  return (
    <form onSubmit={submit} className="grid items-end gap-4 sm:grid-cols-3">
      <Input
        label="Pool name"
        value={name}
        onChange={(e) => setName(e.target.value)}
        required
        maxLength={255}
      />
      <Input
        label="Coin goal"
        type="number"
        min={1}
        max={1000000}
        value={goal}
        onChange={(e) => setGoal(e.target.value)}
        required
      />
      <Button type="submit" loading={busy}>
        Create empty pool
      </Button>
    </form>
  )
}
function Pool({
  pool,
  learners,
  save,
  busy,
}: {
  pool: FamilyGoals['pools'][number]
  learners: Learner[]
  save: (run: () => Promise<unknown>) => Promise<void>
  busy: boolean
}) {
  const [direction, setDirection] = useState('contribute'),
    [coins, setCoins] = useState(''),
    [learner, setLearner] = useState('')
  const [attempt, setAttempt] = useState<{
    payload: string
    key: string
  } | null>(null)
  async function submit(e: FormEvent) {
    e.preventDefault()
    const input = {
      direction,
      coins: Number(coins),
      ...(direction === 'distribute' ? { learner_id: Number(learner) } : {}),
    }
    const payload = JSON.stringify(input),
      key = attempt?.payload === payload ? attempt.key : crypto.randomUUID()
    setAttempt({ payload, key })
    try {
      await save(() => batch4Api.move(pool.id, input, key))
      setCoins('')
      setAttempt(null)
    } catch {
      /* Retain the key for a safe retry. */
    }
  }
  return (
    <section className="space-y-4 rounded-xl border border-border p-4">
      <h3 className="font-semibold">{pool.name}</h3>
      <p>
        {pool.coin_balance} / {pool.goal_coins} coins
      </p>
      <progress
        className="w-full"
        value={Math.min(pool.coin_balance, pool.goal_coins)}
        max={pool.goal_coins}
        aria-label={`${pool.name} coin goal`}
      />
      <form onSubmit={submit} className="space-y-3">
        <label className="block space-y-2 text-sm font-semibold">
          <span>Movement for {pool.name}</span>
          <select
            className={selectClass}
            value={direction}
            onChange={(e) => setDirection(e.target.value)}
          >
            <option value="contribute">Family wallet → pool</option>
            <option value="return">Pool → family wallet</option>
            <option value="distribute">Pool → child</option>
          </select>
        </label>
        {direction === 'distribute' && (
          <label className="block space-y-2 text-sm font-semibold">
            <span>Child receiving coins</span>
            <select
              className={selectClass}
              value={learner}
              onChange={(e) => setLearner(e.target.value)}
              required
            >
              <option value="">Choose a child</option>
              {learners.map((l) => (
                <option key={l.id} value={l.id}>
                  {l.display_name}
                </option>
              ))}
            </select>
          </label>
        )}
        <Input
          label={`Coins to move for ${pool.name}`}
          type="number"
          min={1}
          max={1000000}
          required
          value={coins}
          onChange={(e) => setCoins(e.target.value)}
        />
        <Button type="submit" variant="parent" loading={busy}>
          Approve coin movement
        </Button>
      </form>
      <details>
        <summary className="cursor-pointer py-2 text-sm font-semibold">
          Recent pool movements
        </summary>
        <ul className="space-y-2 text-sm">
          {pool.transactions.map((t) => (
            <li key={t.id}>
              {t.type === 'credit' ? '+' : '−'}
              {t.amount} · {t.source.replace('pool_', '').replaceAll('_', ' ')}{' '}
              · balance {t.balance_after} ·{' '}
              {new Date(t.created_at).toLocaleDateString()}
            </li>
          ))}
        </ul>
        {!pool.transactions.length && (
          <p className="text-sm text-muted">No coins moved yet.</p>
        )}
      </details>
    </section>
  )
}
function Preferences({
  initial,
  save,
  busy,
}: {
  initial: AlertPreferences
  save: (run: () => Promise<unknown>) => Promise<void>
  busy: boolean
}) {
  const [low, setLow] = useState(String(initial.low_balance_coins ?? '')),
    [days, setDays] = useState(String(initial.inactive_days ?? '')),
    [review, setReview] = useState(initial.review_alerts)
  async function submit(e: FormEvent) {
    e.preventDefault()
    try {
      await save(() =>
        batch4Api.alerts({
          low_balance_coins: low === '' ? null : Number(low),
          inactive_days: days === '' ? null : Number(days),
          review_alerts: review,
        }),
      )
    } catch {
      /* Page error. */
    }
  }
  return (
    <form className="mt-4 space-y-4" onSubmit={submit}>
      <div className="grid gap-4 sm:grid-cols-2">
        <Input
          label="Alert at or below this family coin balance"
          type="number"
          min={0}
          max={1000000}
          value={low}
          onChange={(e) => setLow(e.target.value)}
          hint="Blank means off. Zero alerts when the family wallet is empty."
        />
        <Input
          label="Alert after this many inactive days"
          type="number"
          min={1}
          max={365}
          value={days}
          onChange={(e) => setDays(e.target.value)}
          hint="Blank means off. Measured since learning activity or profile creation."
        />
      </div>
      <label className="flex min-h-11 items-center gap-2">
        <input
          type="checkbox"
          checked={review}
          onChange={(e) => setReview(e.target.checked)}
        />
        Alert me when submitted work needs parent review
      </label>
      <Button type="submit" loading={busy}>
        Save alert preferences
      </Button>
    </form>
  )
}
