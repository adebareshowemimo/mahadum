import { Alert, Button, Modal } from '@/components/ui'
import { ApiError } from '@/lib/api'

export function LibraryError({ error }: { error: unknown }) {
  if (!error) return null
  const details = error instanceof ApiError && Array.isArray(error.details) ? error.details.filter((value): value is string => typeof value === 'string') : []
  return <Alert variant="danger"><p>{error instanceof Error ? error.message : 'Could not save your changes.'}</p>{details.length > 0 && <ul className="mt-2 list-disc pl-5">{details.map((detail, index) => <li key={index}>{detail}</li>)}</ul>}</Alert>
}

export function LibraryDeleteModal({ title, open, busy, error, onClose, onConfirm }: { title: string; open: boolean; busy: boolean; error: unknown; onClose: () => void; onConfirm: () => void }) {
  return <Modal open={open} onClose={() => { if (!busy) onClose() }} title={`Delete ${title}?`} description="This item will be permanently removed. Deleting a set also removes its levels and lessons.">
    <div className="space-y-4"><LibraryError error={error} /><div className="flex gap-2"><Button variant="secondary" fullWidth disabled={busy} onClick={onClose}>Cancel</Button><Button variant="danger" fullWidth loading={busy} onClick={onConfirm}>Delete</Button></div></div>
  </Modal>
}
