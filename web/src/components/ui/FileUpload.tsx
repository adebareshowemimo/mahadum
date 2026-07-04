import { useRef, useState, type DragEvent } from 'react'
import { cn } from '@/lib/cn'

export interface FileUploadProps {
  accept?: string
  hint?: string
  onFile?: (file: File) => void
}

/** Drag-and-drop file field — e.g. CSV roster import for school admins. */
export function FileUpload({ accept = '.csv', hint = 'CSV up to 2 MB', onFile }: FileUploadProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [name, setName] = useState<string | null>(null)
  const [over, setOver] = useState(false)

  const take = (file?: File) => {
    if (!file) return
    setName(file.name)
    onFile?.(file)
  }

  const onDrop = (e: DragEvent) => {
    e.preventDefault()
    setOver(false)
    take(e.dataTransfer.files?.[0])
  }

  return (
    <button
      type="button"
      onClick={() => inputRef.current?.click()}
      onDragOver={(e) => { e.preventDefault(); setOver(true) }}
      onDragLeave={() => setOver(false)}
      onDrop={onDrop}
      className={cn(
        'flex w-full flex-col items-center gap-1.5 rounded-2xl border-2 border-dashed p-6 text-center transition-colors',
        over ? 'border-primary bg-primary-soft' : 'border-border-strong bg-surface-muted hover:bg-surface-sunken',
      )}
    >
      <span className="text-2xl" aria-hidden>📄</span>
      {name ? (
        <span className="text-sm font-semibold text-foreground">{name}</span>
      ) : (
        <>
          <span className="text-sm font-semibold text-foreground">Drop a file or click to browse</span>
          <span className="text-xs text-muted">{hint}</span>
        </>
      )}
      <input ref={inputRef} type="file" accept={accept} className="hidden" onChange={(e) => take(e.target.files?.[0])} />
    </button>
  )
}
