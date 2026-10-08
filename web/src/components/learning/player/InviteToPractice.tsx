import { useState, type FormEvent } from 'react'
import { Alert, Button3D } from '@/components/ui'
import { ApiError, type PracticeContact } from '@/lib/api'
import type { PlayerService, SpeakingSlide, VideoSlide } from './types'

export function InviteToPractice({ slide, service }: { slide: SpeakingSlide | VideoSlide; service: PlayerService }) {
  const [open, setOpen] = useState(false)
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [contacts, setContacts] = useState<PracticeContact[]>([])
  const [loading, setLoading] = useState(false)
  if (service.isPreview) return null

  async function showContacts() {
    setOpen(true)
    setLoading(true)
    setError(null)
    try { setContacts(await service.practiceContacts()) }
    catch (err) { setError(err instanceof ApiError ? err.message : 'Could not load practice contacts. Please try again.') }
    finally { setLoading(false) }
  }

  async function invite(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await service.inviteTonePractice(slide, email.trim())
      setSent(true)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not send the invitation. Please try again.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <section aria-label="Invite to practice" className="rounded-2xl border border-border bg-surface-muted p-4">
      <h2 className="font-semibold text-foreground">No one available to practice with?</h2>
      <p className="my-2 text-sm text-muted">Invite a registered parent, guardian or teacher to practice the words and tones together. Their invitation includes a private link to the language video.</p>
      {sent ? <p role="status" className="text-sm font-semibold text-primary">Invitation sent. The video link expires in 48 hours. You can continue learning while you wait.</p> : !open ? (
        <Button3D variant="neutral" onClick={() => void showContacts()}>Invite to Practice</Button3D>
      ) : (
        <form onSubmit={invite} className="flex flex-col gap-3">
          <p className="text-sm text-foreground">Invitation message: “Please join me to practice {slide.lessonTitle ? `“${slide.lessonTitle}”` : 'this language lesson'}. Watch the linked video, then let’s repeat the words and match their tones together.”</p>
          <label className="text-sm text-foreground">Practice partner
            <select required value={email} disabled={busy || loading} onChange={(event) => setEmail(event.target.value)}
              className="mt-1 min-h-11 w-full rounded-xl border border-border bg-background px-3 text-foreground">
              <option value="">{loading ? 'Loading contacts…' : 'Choose someone from your family or school'}</option>
              {contacts.map((contact) => <option key={contact.id} value={contact.email}>{contact.name} · {contact.email}</option>)}
            </select>
          </label>
          <p className="text-xs text-muted">The recipient must sign in with their registered email. Both invitation flows use the same family and school contacts.</p>
          {!loading && contacts.length === 0 && !error && <p className="text-sm text-muted">No eligible adults are linked yet. Ask your parent or school administrator to review this learner’s family and school links.</p>}
          {error && <Alert variant="danger">{error}</Alert>}
          <Button3D type="submit" variant="neutral" disabled={busy || !email.trim()}>{busy ? 'Sending…' : 'Send invitation'}</Button3D>
        </form>
      )}
    </section>
  )
}
