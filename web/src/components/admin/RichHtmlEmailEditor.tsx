import { useEffect, useRef, type MouseEvent as ReactMouseEvent, type RefObject } from 'react'

export type RichHtmlEditorView = 'visual' | 'source'

type RichHtmlEmailEditorProps = {
  editorRef?: RefObject<HTMLDivElement>
  value: string
  view: RichHtmlEditorView
  onViewChange: (view: RichHtmlEditorView) => void
  onFocusSource?: (element: HTMLTextAreaElement) => void
  onFocusVisual?: () => void
  onChange: (value: string) => void
}

export function RichHtmlEmailEditor({
  editorRef,
  value,
  view,
  onViewChange,
  onFocusSource,
  onFocusVisual,
  onChange,
}: RichHtmlEmailEditorProps) {
  const localRef = useRef<HTMLDivElement>(null)
  const activeRef = editorRef ?? localRef

  useEffect(() => {
    if (view === 'visual' && activeRef.current && activeRef.current.innerHTML !== value && document.activeElement !== activeRef.current) {
      activeRef.current.innerHTML = value
    }
  }, [activeRef, value, view])

  function command(event: ReactMouseEvent<HTMLButtonElement>, name: string, argument?: string) {
    event.preventDefault()
    activeRef.current?.focus()
    document.execCommand(name, false, argument)
    if (activeRef.current) onChange(activeRef.current.innerHTML)
  }

  return (
    <div className="overflow-hidden rounded-xl border border-border-strong bg-surface">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-surface-muted p-2">
        <div className="flex flex-wrap gap-1" aria-label="Formatting controls">
          {view === 'visual' && (
            <>
              <FormatButton label="Bold" short="B" onMouseDown={(event) => command(event, 'bold')} />
              <FormatButton label="Italic" short="I" onMouseDown={(event) => command(event, 'italic')} />
              <FormatButton label="Underline" short="U" onMouseDown={(event) => command(event, 'underline')} />
              <FormatButton label="Heading" short="H2" onMouseDown={(event) => command(event, 'formatBlock', 'h2')} />
              <FormatButton label="Bulleted list" short="• List" onMouseDown={(event) => command(event, 'insertUnorderedList')} />
              <FormatButton label="Numbered list" short="1. List" onMouseDown={(event) => command(event, 'insertOrderedList')} />
              <FormatButton label="Add link" short="Link" onMouseDown={(event) => {
                const url = window.prompt('Link URL')
                if (url) command(event, 'createLink', url)
                else event.preventDefault()
              }} />
              <FormatButton label="Remove formatting" short="Clear" onMouseDown={(event) => command(event, 'removeFormat')} />
            </>
          )}
        </div>
        <div className="flex rounded-lg border border-border bg-surface p-0.5" aria-label="Editor view">
          {(['visual', 'source'] as const).map((option) => (
            <button key={option} type="button" aria-pressed={view === option} onClick={() => onViewChange(option)} className={`min-h-9 rounded-md px-3 text-xs font-semibold ${view === option ? 'bg-ink-900 text-white' : 'text-muted hover:text-foreground'}`}>
              {option === 'visual' ? 'Visual' : 'HTML'}
            </button>
          ))}
        </div>
      </div>
      {view === 'visual' ? (
        <div
          ref={activeRef}
          contentEditable
          suppressContentEditableWarning
          role="textbox"
          aria-multiline="true"
          aria-label="Rich HTML email body"
          onFocus={onFocusVisual}
          onInput={(event) => onChange(event.currentTarget.innerHTML)}
          className="prose prose-sm min-h-80 max-w-none px-4 py-3 text-foreground focus:outline-none focus:ring-2 focus:ring-inset focus:ring-ring [&_a]:text-primary [&_a]:underline [&_h2]:text-xl [&_h2]:font-bold [&_li]:ml-5"
        />
      ) : (
        <textarea
          name="html_body"
          aria-label="HTML email source"
          value={value}
          onFocus={(event) => onFocusSource?.(event.currentTarget)}
          onChange={(event) => onChange(event.target.value)}
          rows={16}
          spellCheck={false}
          className="min-h-80 w-full resize-y bg-ink-950 px-4 py-3 font-mono text-xs leading-6 text-emerald-200 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-ring"
        />
      )}
    </div>
  )
}

function FormatButton({ label, short, onMouseDown }: { label: string; short: string; onMouseDown: (event: ReactMouseEvent<HTMLButtonElement>) => void }) {
  return <button type="button" title={label} aria-label={label} onMouseDown={onMouseDown} className="min-h-9 rounded-md border border-border bg-surface px-2.5 text-xs font-semibold text-foreground hover:border-gold-400 hover:bg-gold-50">{short}</button>
}
