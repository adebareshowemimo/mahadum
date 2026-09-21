import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Alert, Badge, Button, Card, CardBody, Icon, Input, Modal, Skeleton, Spinner } from '@/components/ui'
import { ApiError, type MediaAsset, type MediaQuery } from '@/lib/api'
import { cn } from '@/lib/cn'
import {
  useDeleteMedia,
  useMediaLibraryInfinite,
  useMediaOrphans,
  usePurgeMediaOrphans,
  useUploadMedia,
} from '@/lib/content/queries'

function useDebounced<T>(value: T, ms = 300): T {
  const [v, setV] = useState(value)
  useEffect(() => {
    const id = setTimeout(() => setV(value), ms)
    return () => clearTimeout(id)
  }, [value, ms])
  return v
}

export function MediaPage() {
  const [search, setSearch] = useState('')
  const [type, setType] = useState('')
  const [folder, setFolder] = useState('')
  const [view, setView] = useState<'list' | 'grid'>('list')
  const upload = useUploadMedia()
  const remove = useDeleteMedia()
  const inputRef = useRef<HTMLInputElement>(null)
  const folderInputRef = useRef<HTMLInputElement>(null)
  const [error, setError] = useState<string | null>(null)
  const [uploadStatus, setUploadStatus] = useState<string | null>(null)
  const [copied, setCopied] = useState<number | null>(null)
  const [cleanupOpen, setCleanupOpen] = useState(false)

  // Unreferenced assets — a lightweight bounded query drives the cleanup banner.
  const orphans = useMediaOrphans({ per_page: 100 })
  const orphanTotal = orphans.data?.meta.total ?? 0

  const q = useDebounced(search)
  const params: MediaQuery = useMemo(
    () => ({ q: q || undefined, type: type || undefined, folder: folder || undefined }),
    [q, type, folder],
  )
  const { data, isLoading, isError, fetchNextPage, hasNextPage, isFetchingNextPage } =
    useMediaLibraryInfinite(params)

  // Flatten the loaded pages into a single asset list; total comes from any page's meta.
  const assets = useMemo(() => data?.pages.flatMap((p) => p.data) ?? [], [data])
  const total = data?.pages[0]?.meta.total
  const typeCounts = data?.pages[0]?.meta.type_counts ?? {}
  const folders = data?.pages[0]?.folders ?? []
  const videoCount = typeCounts.video ?? 0

  // Auto-load the next page when a sentinel at the end of the grid scrolls into view.
  // Callback ref manages one IntersectionObserver; it must not *return* a cleanup
  // (React 18 warns on that), so we disconnect the prior observer imperatively.
  const observerRef = useRef<IntersectionObserver | null>(null)
  const observe = useCallback(
    (node: HTMLDivElement | null) => {
      observerRef.current?.disconnect()
      if (!node) return
      const io = new IntersectionObserver(
        (entries) => {
          if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) {
            void fetchNextPage()
          }
        },
        { rootMargin: '400px' },
      )
      io.observe(node)
      observerRef.current = io
    },
    [hasNextPage, isFetchingNextPage, fetchNextPage],
  )

  async function onPick(files: FileList | null, preserveFolders = false) {
    if (!files?.length) return
    setError(null)
    setUploadStatus(null)
    const selected = Array.from(files)
    let uploaded = 0
    try {
      for (const file of selected) {
        const relative = preserveFolders ? file.webkitRelativePath.replace(/\\/g, '/') : ''
        const parent = relative.includes('/') ? relative.slice(0, relative.lastIndexOf('/')) : undefined
        setUploadStatus(`Uploading ${uploaded + 1} of ${selected.length}: ${file.name}`)
        await upload.mutateAsync({ file, folder: parent })
        uploaded++
      }
      setUploadStatus(`${uploaded.toLocaleString()} ${uploaded === 1 ? 'file' : 'files'} uploaded.`)
    } catch (err) {
      setError(
        `${uploaded.toLocaleString()} of ${selected.length.toLocaleString()} uploaded. ${
          err instanceof ApiError ? err.message : 'Upload failed.'
        }`,
      )
    } finally {
      if (inputRef.current) inputRef.current.value = ''
      if (folderInputRef.current) folderInputRef.current.value = ''
    }
  }

  async function copy(asset: MediaAsset) {
    try {
      await navigator.clipboard.writeText(asset.url)
      setCopied(asset.id)
      setTimeout(() => setCopied(null), 1500)
    } catch {
      /* clipboard blocked */
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold text-foreground">Media library</h1>
          <p className="mt-1 text-muted">
            Upload and reuse videos, audio and images.
            {total !== undefined ? ` ${total.toLocaleString()} assets.` : ''}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" disabled={upload.isPending} onClick={() => folderInputRef.current?.click()}>
            <Icon name="folder" className="size-4" /> Browse folder
          </Button>
          <Button loading={upload.isPending} onClick={() => inputRef.current?.click()}>
            {upload.isPending ? 'Uploading…' : 'Upload media'}
          </Button>
        </div>
        <input
          ref={inputRef}
          type="file"
          accept="video/*,audio/*,image/*"
          className="hidden"
          multiple
          onChange={(e) => void onPick(e.target.files)}
        />
        <input
          ref={folderInputRef}
          type="file"
          accept="video/*,audio/*,image/*"
          multiple
          className="hidden"
          {...({ webkitdirectory: '', directory: '' } as Record<string, string>)}
          onChange={(e) => void onPick(e.target.files, true)}
        />
      </div>

      {uploadStatus && <Alert role="status">{uploadStatus}</Alert>}

      {/* Search + type filter — server-side so the library scales to any size. */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="min-w-[14rem] flex-1">
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search by filename…"
            leftIcon={<Icon name="search" />}
            aria-label="Search media by filename"
          />
        </div>
        <label className="flex items-center gap-2 text-sm">
          <span className="font-semibold text-muted">Type</span>
          <select
            value={type}
            onChange={(e) => setType(e.target.value)}
            className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
          >
            <option value="">All</option>
            <option value="video">Video</option>
            <option value="audio">Audio</option>
            <option value="image">Image</option>
          </select>
        </label>
        <div className="flex rounded-xl border border-border-strong bg-surface p-1" aria-label="Media view">
          <button
            type="button"
            className={cn(
              'flex h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-semibold',
              view === 'list' ? 'bg-primary text-primary-fg' : 'text-muted hover:bg-surface-muted',
            )}
            aria-pressed={view === 'list'}
            onClick={() => setView('list')}
          >
            <Icon name="list" className="size-4" /> List
          </button>
          <button
            type="button"
            className={cn(
              'flex h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-semibold',
              view === 'grid' ? 'bg-primary text-primary-fg' : 'text-muted hover:bg-surface-muted',
            )}
            aria-pressed={view === 'grid'}
            onClick={() => setView('grid')}
          >
            <Icon name="grid" className="size-4" /> Grid
          </button>
        </div>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}

      {orphanTotal > 0 && (
        <Alert variant="warning">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <span>
              <strong>{orphanTotal.toLocaleString()}</strong> unused{' '}
              {orphanTotal === 1 ? 'asset is' : 'assets are'} not referenced by any lesson, quiz, or submission.
            </span>
            <Button size="sm" variant="secondary" onClick={() => setCleanupOpen(true)}>
              Review &amp; clean up
            </Button>
          </div>
        </Alert>
      )}

      {!isLoading && data && (
        <div className="grid gap-3 sm:grid-cols-2">
          <Card>
            <CardBody>
              <p className="text-sm text-muted">Assets shown</p>
              <p className="mt-1 text-2xl font-bold text-foreground">{(total ?? 0).toLocaleString()}</p>
            </CardBody>
          </Card>
          <Card>
            <CardBody>
              <p className="text-sm text-muted">Matching videos</p>
              <p className="mt-1 text-2xl font-bold text-foreground">{videoCount.toLocaleString()}</p>
            </CardBody>
          </Card>
        </div>
      )}

      <div className="grid items-start gap-5 lg:grid-cols-[17rem_minmax(0,1fr)]">
        <Card className="overflow-hidden lg:sticky lg:top-24">
          <CardBody className="p-3">
            <div className="px-2 pb-2">
              <p className="text-sm font-bold text-foreground">Browse folders</p>
              <p className="text-xs text-muted">Choose a folder to filter its files.</p>
            </div>
            <nav className="flex flex-col gap-1" aria-label="Media folders">
              <FolderButton
                active={folder === ''}
                label="All media"
                count={folders.reduce((sum, item) => sum + item.total, 0)}
                videos={folders.reduce((sum, item) => sum + item.video_count, 0)}
                onClick={() => setFolder('')}
              />
              {folders.map((item) => (
                <FolderButton
                  key={item.name ?? '__unfiled'}
                  active={folder === (item.name ?? '__unfiled')}
                  label={item.name ?? 'Unfiled'}
                  count={item.total}
                  videos={item.video_count}
                  onClick={() => setFolder(item.name ?? '__unfiled')}
                />
              ))}
            </nav>
          </CardBody>
        </Card>

        <div className="min-w-0">
          {isLoading ? (
            <Skeleton className="h-48" />
          ) : isError || !data ? (
            <Alert variant="danger">Couldn’t load the media library.</Alert>
          ) : assets.length === 0 ? (
            <Card>
              <CardBody className="py-10 text-center text-sm text-muted">
                {q || type || folder ? 'No media matches your filters.' : 'No media yet. Upload a file to get started.'}
              </CardBody>
            </Card>
          ) : view === 'grid' ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {assets.map((asset) => (
            <Card key={asset.id} className="overflow-hidden">
              <Preview asset={asset} />
              <CardBody className="flex flex-col gap-3">
                <div className="flex items-center justify-between gap-2">
                  <p className="truncate text-sm font-medium text-foreground" title={asset.original_name ?? undefined}>
                    {asset.original_name ?? `Asset #${asset.id}`}
                  </p>
                  <Badge variant="neutral">{asset.type}</Badge>
                </div>
                <p className="truncate text-xs text-muted">{asset.folder ?? 'Unfiled'}</p>
                <div className="flex gap-2">
                  <Button size="sm" variant="secondary" className="flex-1" onClick={() => copy(asset)}>
                    {copied === asset.id ? 'Copied ✓' : 'Copy URL'}
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    loading={remove.isPending && remove.variables === asset.id}
                    onClick={() => remove.mutate(asset.id)}
                    aria-label="Delete"
                  >
                    <Icon name="close" className="size-4" />
                  </Button>
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      ) : (
        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[760px] text-left text-sm">
              <thead className="border-b border-border bg-surface-muted text-xs uppercase tracking-wide text-muted">
                <tr><th className="px-4 py-3">Video / file</th><th className="px-4 py-3">Folder</th><th className="px-4 py-3">Type</th><th className="px-4 py-3">Uploaded</th><th className="px-4 py-3 text-right">Actions</th></tr>
              </thead>
              <tbody className="divide-y divide-border">
                {assets.map((asset) => (
                  <tr key={asset.id} className="hover:bg-surface-muted/70">
                    <td className="px-4 py-3"><div className="flex items-center gap-3"><MiniPreview asset={asset} /><span className="max-w-xs truncate font-medium text-foreground" title={asset.original_name ?? undefined}>{asset.original_name ?? `Asset #${asset.id}`}</span></div></td>
                    <td className="max-w-[15rem] truncate px-4 py-3 text-muted" title={asset.folder ?? 'Unfiled'}>{asset.folder ?? 'Unfiled'}</td>
                    <td className="px-4 py-3"><Badge variant="neutral">{asset.type}</Badge></td>
                    <td className="whitespace-nowrap px-4 py-3 text-muted">{asset.created_at ? new Date(asset.created_at).toLocaleDateString() : '—'}</td>
                    <td className="px-4 py-3"><div className="flex justify-end gap-2"><Button size="sm" variant="secondary" onClick={() => copy(asset)}>{copied === asset.id ? 'Copied ✓' : 'Copy URL'}</Button><Button size="sm" variant="outline" loading={remove.isPending && remove.variables === asset.id} onClick={() => remove.mutate(asset.id)} aria-label={`Delete ${asset.original_name ?? `asset ${asset.id}`}`}><Icon name="close" className="size-4" /></Button></div></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
          )}

      {/* Infinite-scroll sentinel: loads the next page as it nears the viewport. */}
          {assets.length > 0 && (
            <div ref={observe} className="flex justify-center py-4 text-sm text-muted">
              {isFetchingNextPage ? (
                <span className="flex items-center gap-2">
                  <Spinner className="size-4" /> Loading more…
                </span>
              ) : hasNextPage ? (
                <span aria-hidden="true">&nbsp;</span>
              ) : (
                <span>All {total?.toLocaleString()} assets loaded.</span>
              )}
            </div>
          )}
        </div>
      </div>

      {cleanupOpen && (
        <CleanupModal
          orphans={orphans.data?.data ?? []}
          total={orphanTotal}
          isLoading={orphans.isLoading}
          onClose={() => setCleanupOpen(false)}
        />
      )}
    </div>
  )
}

function FolderButton({
  active,
  label,
  count,
  videos,
  onClick,
}: {
  active: boolean
  label: string
  count: number
  videos: number
  onClick: () => void
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left',
        active ? 'bg-primary-soft text-primary' : 'text-foreground hover:bg-surface-muted',
      )}
    >
      <Icon name="folder" className="size-4" />
      <span className="min-w-0 flex-1 truncate text-sm font-semibold" title={label}>
        {label}
      </span>
      <span className="text-xs text-muted">
        {videos} video{videos === 1 ? '' : 's'} · {count}
      </span>
    </button>
  )
}

function MiniPreview({ asset }: { asset: MediaAsset }) {
  if (asset.type === 'image') {
    return <img src={asset.url} alt="" className="size-12 rounded-lg object-cover" loading="lazy" />
  }
  if (asset.type === 'video') {
    return <video src={asset.url} className="size-12 rounded-lg bg-charcoal-900 object-cover" muted preload="metadata" />
  }
  return (
    <div className="flex size-12 items-center justify-center rounded-lg bg-surface-muted" aria-hidden="true">
      {asset.type === 'audio' ? '🎵' : '📄'}
    </div>
  )
}

function CleanupModal({
  orphans,
  total,
  isLoading,
  onClose,
}: {
  orphans: MediaAsset[]
  total: number
  isLoading: boolean
  onClose: () => void
}) {
  const purge = usePurgeMediaOrphans()
  const [selected, setSelected] = useState<Set<number>>(() => new Set())
  const [error, setError] = useState<string | null>(null)

  function toggle(id: number) {
    setSelected((prev) => {
      const next = new Set(prev)
      next.has(id) ? next.delete(id) : next.add(id)
      return next
    })
  }
  const allSelected = orphans.length > 0 && selected.size === orphans.length
  function toggleAll() {
    setSelected(allSelected ? new Set() : new Set(orphans.map((o) => o.id)))
  }

  async function onDelete() {
    if (selected.size === 0) return
    setError(null)
    try {
      await purge.mutateAsync([...selected])
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not delete the selected assets.')
    }
  }

  return (
    <Modal open onClose={onClose} title="Clean up unused media" description={`${total.toLocaleString()} unreferenced assets`}>
      <div className="flex flex-col gap-4">
        {error && <Alert variant="danger">{error}</Alert>}
        <p className="text-sm text-muted">
          These assets aren’t linked to any lesson, quiz, flashcard, submission, or invoice. Deleting them removes the
          files permanently.
          {total > orphans.length ? ` Showing the first ${orphans.length}.` : ''}
        </p>

        {isLoading ? (
          <Skeleton className="h-40" />
        ) : (
          <>
            <div className="flex items-center justify-between">
              <label className="flex items-center gap-2 text-sm font-semibold text-foreground">
                <input type="checkbox" checked={allSelected} onChange={toggleAll} />
                Select all ({orphans.length})
              </label>
              <span className="text-sm text-muted">{selected.size} selected</span>
            </div>

            <ul className="flex max-h-72 flex-col gap-1 overflow-y-auto">
              {orphans.map((asset) => (
                <li key={asset.id}>
                  <label className="flex items-center gap-3 rounded-lg px-2 py-1.5 text-sm hover:bg-surface-muted">
                    <input type="checkbox" checked={selected.has(asset.id)} onChange={() => toggle(asset.id)} />
                    <Badge variant="neutral">{asset.type}</Badge>
                    <span className="truncate text-foreground">{asset.original_name ?? `Asset #${asset.id}`}</span>
                  </label>
                </li>
              ))}
            </ul>
          </>
        )}

        <div className="flex justify-end gap-2 border-t border-border pt-4">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button variant="danger" loading={purge.isPending} disabled={selected.size === 0} onClick={onDelete}>
            Delete {selected.size > 0 ? selected.size : ''} selected
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function Preview({ asset }: { asset: MediaAsset }) {
  const base = 'flex aspect-video w-full items-center justify-center bg-charcoal-900 text-3xl text-white/70'
  if (asset.type === 'image') {
    return <img src={asset.url} alt="" className="aspect-video w-full object-cover" loading="lazy" />
  }
  if (asset.type === 'video') {
    return <video src={asset.url} className="aspect-video w-full bg-charcoal-900" muted preload="none" />
  }
  return <div className={cn(base)} aria-hidden="true">{asset.type === 'audio' ? '🎵' : '📄'}</div>
}
