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
  const upload = useUploadMedia()
  const remove = useDeleteMedia()
  const inputRef = useRef<HTMLInputElement>(null)
  const [error, setError] = useState<string | null>(null)
  const [copied, setCopied] = useState<number | null>(null)
  const [cleanupOpen, setCleanupOpen] = useState(false)

  // Unreferenced assets — a lightweight bounded query drives the cleanup banner.
  const orphans = useMediaOrphans({ per_page: 100 })
  const orphanTotal = orphans.data?.meta.total ?? 0

  const q = useDebounced(search)
  const params: MediaQuery = useMemo(() => ({ q: q || undefined, type: type || undefined }), [q, type])
  const { data, isLoading, isError, fetchNextPage, hasNextPage, isFetchingNextPage } =
    useMediaLibraryInfinite(params)

  // Flatten the loaded pages into a single asset list; total comes from any page's meta.
  const assets = useMemo(() => data?.pages.flatMap((p) => p.data) ?? [], [data])
  const total = data?.pages[0]?.meta.total

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

  async function onPick(file: File | undefined) {
    if (!file) return
    setError(null)
    try {
      await upload.mutateAsync(file) // invalidates the library → refetches from page 1
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Upload failed.')
    } finally {
      if (inputRef.current) inputRef.current.value = ''
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
        <Button loading={upload.isPending} onClick={() => inputRef.current?.click()}>
          {upload.isPending ? 'Uploading…' : 'Upload media'}
        </Button>
        <input
          ref={inputRef}
          type="file"
          accept="video/*,audio/*,image/*"
          className="hidden"
          onChange={(e) => onPick(e.target.files?.[0])}
        />
      </div>

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

      {isLoading ? (
        <Skeleton className="h-48" />
      ) : isError || !data ? (
        <Alert variant="danger">Couldn’t load the media library.</Alert>
      ) : assets.length === 0 ? (
        <Card>
          <CardBody className="py-10 text-center text-sm text-muted">
            {q || type ? 'No media matches your filters.' : 'No media yet. Upload a file to get started.'}
          </CardBody>
        </Card>
      ) : (
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
