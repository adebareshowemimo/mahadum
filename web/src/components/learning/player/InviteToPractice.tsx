import { useState, type FormEvent } from 'react'
import { Alert, Button3D } from '@/components/ui'
import { ApiError } from '@/lib/api'
import type { PlayerService, SpeakingSlide, VideoSlide } from './types'

export function InviteToPractice({ slide, service }: { slide: SpeakingSlide | VideoSlide; service: PlayerService }) {
  const [open, setOpen] = useState(false)
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  if (service.isPreview) return null

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
        <Button3D variant="neutral" onClick={() => setOpen(true)}>Invite to Practice</Button3D>
      ) : (
        <form onSubmit={invite} className="flex flex-col gap-3">
          <p className="text-sm text-foreground">Invitation message: “Please join me to practice {slide.lessonTitle ? `“${slide.lessonTitle}”` : 'this language lesson'}. Watch the linked video, then let’s repeat the words and match their tones together.”</p>
          <label className="text-sm text-foreground">Recipient email
            <input type="email" required value={email} disabled={busy} onChange={(event) => setEmail(event.target.value)}
              autoComplete="email" placeholder="adult@example.com"
              className="mt-1 min-h-11 w-full rounded-xl border border-border bg-background px-3 text-foreground" />
          </label>
          <p className="text-xs text-muted">The recipient must sign in with this email. Teacher invitations are limited to your school.</p>
          {error && <Alert variant="danger">{error}</Alert>}
          <Button3D type="submit" variant="neutral" disabled={busy || !email.trim()}>{busy ? 'Sending…' : 'Send invitation'}</Button3D>
        </form>
      )}
    </section>
  )
}
