import { useEffect, useRef, useState } from 'react'
import { Alert, Avatar, AVATAR_PRESETS, Button, Icon, Modal } from '@/components/ui'
import { ApiError, type LearnerProfile } from '@/lib/api'
import { cn } from '@/lib/cn'
import { useUpdateLearnerAvatar } from '@/lib/family/queries'

type LearnerPicture = Pick<LearnerProfile, 'id' | 'display_name' | 'avatar_id' | 'avatar_url'>

export function ProfilePictureModal({
  learner,
  open,
  onClose,
}: {
  learner: LearnerPicture
  open: boolean
  onClose: () => void
}) {
  const update = useUpdateLearnerAvatar()
  const inputRef = useRef<HTMLInputElement>(null)
  const [avatarId, setAvatarId] = useState<number | null>(learner.avatar_id)
  const [photo, setPhoto] = useState<File | null>(null)
  const [preview, setPreview] = useState<string | null>(learner.avatar_url)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!open) return
    setAvatarId(learner.avatar_id)
    setPhoto(null)
    setPreview(learner.avatar_url)
    setError(null)
  }, [open, learner.avatar_id, learner.avatar_url])

  useEffect(() => {
    if (!photo) return
    const url = URL.createObjectURL(photo)
    setPreview(url)
    return () => URL.revokeObjectURL(url)
  }, [photo])

  function chooseAvatar(id: number) {
    setAvatarId(id)
    setPhoto(null)
    setPreview(null)
    setError(null)
    if (inputRef.current) inputRef.current.value = ''
  }

  function choosePhoto(file?: File) {
    if (!file) return
    if (file.size > 5 * 1024 * 1024) {
      setError('Choose a JPG, PNG, or WebP image up to 5 MB.')
      return
    }
    setPhoto(file)
    setAvatarId(null)
    setError(null)
  }

  async function save() {
    if (!photo && !avatarId) return
    setError(null)
    try {
      await update.mutateAsync({ learnerId: learner.id, avatarId: avatarId ?? undefined, photo: photo ?? undefined })
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not update the profile picture.')
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Choose a profile picture"
      description={`Personalise ${learner.display_name}’s learner profile.`}
      className="max-w-xl"
    >
      <div className="flex flex-col gap-5">
        {error && <Alert variant="danger">{error}</Alert>}

        <div className="flex items-center gap-4 rounded-xl bg-surface-muted p-3">
          <Avatar
            name={learner.display_name}
            src={preview ?? undefined}
            avatarId={avatarId}
            size="lg"
            className="size-16"
          />
          <div>
            <p className="font-semibold text-foreground">Preview</p>
            <p className="text-sm text-muted">Square photos work best.</p>
          </div>
        </div>

        <fieldset>
          <legend className="mb-2 text-sm font-semibold text-foreground">Pick an avatar</legend>
          <div className="grid grid-cols-4 gap-3 sm:grid-cols-8">
            {AVATAR_PRESETS.map((preset) => {
              const selected = avatarId === preset.id && !photo
              return (
                <button
                  key={preset.id}
                  type="button"
                  onClick={() => chooseAvatar(preset.id)}
                  className={cn(
                    'relative rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                    selected && 'ring-2 ring-primary ring-offset-2 ring-offset-background',
                  )}
                  aria-label={`Choose ${preset.label} avatar`}
                  aria-pressed={selected}
                >
                  <Avatar name={preset.label} avatarId={preset.id} size="lg" className="size-12" />
                  {selected && (
                    <span className="absolute -bottom-1 -right-1 flex size-5 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-fg" aria-hidden="true">
                      ✓
                    </span>
                  )}
                </button>
              )
            })}
          </div>
        </fieldset>

        <div>
          <p className="mb-2 text-sm font-semibold text-foreground">Or upload a photo</p>
          <button
            type="button"
            onClick={() => inputRef.current?.click()}
            className={cn(
              'flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-border-strong bg-surface px-4 text-sm font-semibold text-foreground transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
              photo && 'border-primary bg-primary-soft text-primary',
            )}
          >
            <Icon name="plus" className="size-4" />
            {photo ? photo.name : 'Choose photo'}
          </button>
          <input
            ref={inputRef}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            className="sr-only"
            onChange={(event) => choosePhoto(event.target.files?.[0])}
          />
          <p className="mt-1.5 text-xs text-muted">JPG, PNG, or WebP. Maximum 5 MB.</p>
        </div>

        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="button" variant="parent" fullWidth loading={update.isPending} disabled={!photo && !avatarId} onClick={save}>
            Save picture
          </Button>
        </div>
      </div>
    </Modal>
  )
}
