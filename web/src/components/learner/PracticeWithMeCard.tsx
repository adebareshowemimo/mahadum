import { useEffect, useState } from 'react'
import { ApiError, type PracticeContact } from '@/lib/api'
import { usePracticeContacts, useInvitePractice } from '@/lib/learning/queries'
import { Alert, Avatar, Badge, Icon, Input, Spinner } from '@/components/ui'

const RELATION_LABEL: Record<PracticeContact['relation'], string> = {
  family: 'Family',
  school: 'School staff',
}

function useDebounced<T>(value: T, ms = 300): T {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const id = setTimeout(() => setDebounced(value), ms)
    return () => clearTimeout(id)
  }, [value, ms])
  return debounced
}

export function PracticeWithMeCard({ learnerId }: { learnerId: number }) {
  const [expanded, setExpanded] = useState(false)
  const [query, setQuery] = useState('')
  const debouncedQuery = useDebounced(query)
  const contacts = usePracticeContacts(learnerId, debouncedQuery)
  const invite = useInvitePractice()
  const [sentTo, setSentTo] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  async function send(contact: PracticeContact) {
    setError(null)
    try {
      await invite.mutateAsync({ learnerId, recipientUserId: contact.id })
      setSentTo(contact.name)
      setQuery('')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not send this invitation.')
    }
  }

  return (
    <div className="flex flex-col gap-3 rounded-2xl border border-border bg-surface-muted p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-display font-bold text-foreground">Practice with me</h2>
          <p className="mt-1 text-sm text-muted">Invite someone from this learner’s family or school to practice the current lesson together.</p>
        </div>
        {!expanded && (
          <button
            type="button"
            onClick={() => setExpanded(true)}
            className="inline-flex items-center gap-2 rounded-xl border border-border-strong bg-surface px-4 py-2 text-sm font-semibold text-foreground hover:bg-surface-muted"
          >
            <Icon name="users" className="size-4" />
            Invite someone
          </button>
        )}
      </div>

      {expanded && (
        <div className="flex flex-col gap-3">
          <Input
            autoFocus
            placeholder="Search by name or email…"
            value={query}
            onChange={(e) => {
              setQuery(e.target.value)
              setSentTo(null)
            }}
            leftIcon={<Icon name="users" className="size-4" />}
          />

          {sentTo && <Alert variant="success">Invitation sent to {sentTo}.</Alert>}
          {error && <Alert variant="danger">{error}</Alert>}

          {query.trim().length >= 2 && (
            <div className="flex flex-col gap-2">
              {contacts.isLoading && (
                <div className="flex items-center gap-2 py-2 text-sm text-muted">
                  <Spinner className="size-4" /> Searching…
                </div>
              )}
              {!contacts.isLoading && contacts.data?.length === 0 && (
                <p className="py-2 text-sm text-muted">No one found in this learner’s family or school yet.</p>
              )}
              {contacts.data?.map((contact) => (
                <button
                  key={contact.id}
                  type="button"
                  disabled={invite.isPending}
                  onClick={() => void send(contact)}
                  className="flex items-center gap-3 rounded-xl border border-border bg-surface p-3 text-left transition-colors hover:bg-surface-muted disabled:opacity-60"
                >
                  <Avatar name={contact.name} size="sm" />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-semibold text-foreground">{contact.name}</span>
                    <span className="block truncate text-xs text-muted">{contact.email}</span>
                  </span>
                  <Badge variant={contact.relation === 'family' ? 'info' : 'neutral'}>{RELATION_LABEL[contact.relation]}</Badge>
                </button>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
