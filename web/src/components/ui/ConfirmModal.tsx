import { Alert } from './Alert'
import { Button } from './Button'
import { Modal } from './Modal'

export function ConfirmModal({ open, onClose, onConfirm, title, description, confirmLabel, pending = false, error }: {
  open: boolean
  onClose: () => void
  onConfirm: () => void
  title: string
  description: string
  confirmLabel: string
  pending?: boolean
  error?: string | null
}) {
  return (
    <Modal open={open} onClose={() => { if (!pending) onClose() }} title={title} description={description}>
      {error && <Alert variant="danger" className="mb-4">{error}</Alert>}
      <div className="flex justify-end gap-3">
        <Button variant="outline" disabled={pending} onClick={onClose}>Cancel</Button>
        <Button variant="danger" loading={pending} onClick={onConfirm}>{confirmLabel}</Button>
      </div>
    </Modal>
  )
}
