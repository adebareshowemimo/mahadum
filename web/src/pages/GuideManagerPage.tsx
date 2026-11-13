import { useRef, useState, type FormEvent } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Button, Input, Modal, Textarea } from '@/components/ui'
import { api } from '@/lib/api/client'
import { ApiError } from '@/lib/api'

type Section = 'how_to' | 'sample' | 'faq'
type Source = 'youtube' | 'upload'
interface Video { id: number; section: 'how_to' | 'sample'; title: string; description: string | null; language: string | null; topic: string | null; source_type: Source; youtube_id: string | null; file_url: string | null; duration_seconds: number | null; sort_order: number; is_visible: boolean }
interface Faq { id: number; question: string; answer: string; sort_order: number; is_visible: boolean }
interface Page<T> { data: T[]; current_page: number; last_page: number; total: number; from: number | null; to: number | null }
type VideoDraft = Omit<Video, 'id' | 'youtube_id' | 'file_url'> & { id?: number; youtube_url: string; file_url?: string | null; file?: File }
type FaqDraft = Omit<Faq, 'id'> & { id?: number }
const newVideo = (section: 'how_to' | 'sample'): VideoDraft => ({ section, title: '', description: '', language: null, topic: section === 'how_to' ? 'getting_started' : null, source_type: 'youtube', youtube_url: '', duration_seconds: null, sort_order: 0, is_visible: false })
const newFaq = (): FaqDraft => ({ question: '', answer: '', sort_order: 0, is_visible: false })
const errorText = (error: unknown) => error instanceof ApiError ? Object.values(error.fieldErrors).join(' ') || error.message : 'The change could not be saved. Please try again.'

export function GuideManagerPage() {
  const qc = useQueryClient()
  const fileInput = useRef<HTMLInputElement>(null)
  const [section, setSection] = useState<Section>('how_to')
  const [search, setSearch] = useState('')
  const [query, setQuery] = useState('')
  const [visibility, setVisibility] = useState('all')
  const [page, setPage] = useState(1)
  const [video, setVideo] = useState<VideoDraft | null>(null)
  const [faq, setFaq] = useState<FaqDraft | null>(null)
  const [busy, setBusy] = useState(false)
  const [uploadProgress, setUploadProgress] = useState<number | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const isFaq = section === 'faq'
  const key = ['guide-manager', section, query, visibility, page]
  const result = useQuery({ queryKey: key, queryFn: async () => {
    const endpoint = isFaq ? '/website/guide-faqs' : '/website/guide-videos'
    const { data } = await api.get<Page<Video | Faq>>(endpoint, { params: { ...(!isFaq ? { section, visibility: visibility === 'all' ? undefined : visibility } : {}), search: query || undefined, page } })
    return data
  } })
  const list = result.data?.data ?? []
  const closeEditor = () => { if (busy) return; setVideo(null); setFaq(null); setError(''); setUploadProgress(null) }
  function changeSection(next: Section) { setSection(next); setPage(1); setSearch(''); setQuery(''); setVisibility('all'); setVideo(null); setFaq(null); setError(''); setNotice('') }
  function editVideo(item: Video) { setFaq(null); setVideo({ ...item, description: item.description ?? '', youtube_url: item.youtube_id ? `https://www.youtube.com/watch?v=${item.youtube_id}` : '', file: undefined }); setError(''); setNotice(''); setUploadProgress(null) }
  function chooseVideoFile(file?: File) {
    if (!file) return
    const supported = ['video/mp4', 'video/webm', 'video/quicktime'].includes(file.type)
      || (file.type === '' && /\.(mp4|webm|mov)$/i.test(file.name))
    if (!supported || file.size > 100 * 1024 * 1024) {
      setError('Choose an MP4, WebM, or MOV video up to 100 MB.')
      return
    }
    setVideo((current) => current ? { ...current, file } : current)
    setError('')
    setUploadProgress(null)
  }
  async function saveVideo(event: FormEvent) {
    event.preventDefault(); if (!video) return
    if (video.source_type === 'upload' && !video.file && !(video.id && video.file_url)) {
      setError('Choose a video file before saving.')
      return
    }
    setBusy(true); setError(''); setNotice(''); setUploadProgress(video.source_type === 'upload' && video.file ? 0 : null)
    try {
      const body = new FormData()
      for (const [key, value] of Object.entries(video)) if (!['id', 'file', 'file_url'].includes(key)) body.append(key, key === 'is_visible' ? (value ? '1' : '0') : value == null ? '' : String(value))
      if (video.source_type === 'upload' && video.file) body.append('file', video.file)
      await api.post(video.id ? `/website/guide-videos/${video.id}` : '/website/guide-videos', body, {
        onUploadProgress: video.source_type === 'upload' && video.file ? (event) => {
          if (event.total && event.total > 0) setUploadProgress(Math.min(100, Math.round(event.loaded / event.total * 100)))
        } : undefined,
      })
      setVideo(null); setUploadProgress(null); setNotice('Video saved.'); await qc.invalidateQueries({ queryKey: ['guide-manager'] })
    } catch (err) { setError(errorText(err)); setUploadProgress(null) } finally { setBusy(false) }
  }
  async function saveFaq(event: FormEvent) {
    event.preventDefault(); if (!faq) return
    setBusy(true); setError(''); setNotice('')
    try {
      await (faq.id ? api.put(`/website/guide-faqs/${faq.id}`, faq) : api.post('/website/guide-faqs', faq))
      setFaq(null); setNotice('Question saved.'); await qc.invalidateQueries({ queryKey: ['guide-manager'] })
    } catch (err) { setError(errorText(err)) } finally { setBusy(false) }
  }
  async function remove(kind: 'video' | 'faq', id: number) {
    if (!window.confirm(`Remove this ${kind}? This cannot be undone.`)) return
    setBusy(true); setError(''); setNotice('')
    try {
      await api.delete(kind === 'video' ? `/website/guide-videos/${id}` : `/website/guide-faqs/${id}`)
      setVideo(null); setFaq(null); setNotice(`${kind === 'video' ? 'Video' : 'Question'} removed.`); await qc.invalidateQueries({ queryKey: ['guide-manager'] })
    } catch (err) { setError(errorText(err)) } finally { setBusy(false) }
  }
  return <div className="space-y-6">
    <AdminPageHeader title="Guide library" description="Manage the videos and questions shown on the public Guides page." />
    <div className="flex flex-wrap gap-2" role="tablist" aria-label="Guide content">
      {([['how_to', 'How-to videos'], ['sample', 'Sample videos'], ['faq', 'FAQ']] as const).map(([value, label]) => <button key={value} type="button" role="tab" aria-selected={section === value} onClick={() => changeSection(value)} className={`rounded-full px-5 py-2 font-bold ${section === value ? 'bg-blue-600 text-white' : 'border border-border bg-surface text-foreground'}`}>{label}</button>)}
    </div>
    {error && !video && !faq && <Alert variant="danger">{error}</Alert>}{notice && <Alert variant="success">{notice}</Alert>}
    <div className="flex flex-wrap items-end gap-3">
      <form className="flex flex-1 flex-wrap items-end gap-3" onSubmit={(event) => { event.preventDefault(); setPage(1); setQuery(search) }}>
        <div className="min-w-56 flex-1"><Input label={isFaq ? 'Search questions' : 'Search videos'} value={search} onChange={(event) => setSearch(event.target.value)} /></div>
        {!isFaq && <label className="font-semibold">Visibility<select className="mt-2 block min-h-11 rounded-xl border border-border bg-surface px-3" value={visibility} onChange={(event) => { setVisibility(event.target.value); setPage(1) }}><option value="all">All</option><option value="visible">Visible</option><option value="hidden">Hidden</option></select></label>}
        <Button type="submit">Search</Button>
      </form>
      <Button onClick={() => { if (isFaq) { setFaq(newFaq()); setVideo(null) } else { setVideo(newVideo(section)); setFaq(null) } setError(''); setNotice(''); setUploadProgress(null) }}>{isFaq ? 'Add question' : 'Add video'}</Button>
    </div>
    <div className="overflow-x-auto rounded-2xl border border-border bg-surface"><table className="w-full min-w-[620px] text-left text-sm"><thead className="bg-surface-muted"><tr><th className="p-4">{isFaq ? 'Question' : 'Video'}</th><th className="p-4">{isFaq ? 'Order' : 'Source / category'}</th><th className="p-4">Visibility</th><th className="p-4">Action</th></tr></thead><tbody>
      {list.map((entry) => isFaq ? <tr key={entry.id} className="border-t border-border"><td className="p-4 font-semibold">{(entry as Faq).question}</td><td className="p-4">{entry.sort_order}</td><td className="p-4">{entry.is_visible ? 'Shown' : 'Hidden'}</td><td className="p-4"><Button variant="outline" onClick={() => { setFaq({ ...(entry as Faq) }); setVideo(null) }}>Edit</Button></td></tr> : <tr key={entry.id} className="border-t border-border"><td className="p-4 font-semibold">{(entry as Video).title}</td><td className="p-4">{(entry as Video).source_type === 'youtube' ? 'YouTube' : 'Upload'} · {((entry as Video).language || (entry as Video).topic || 'General').replaceAll('_', ' ')}</td><td className="p-4">{entry.is_visible ? 'Shown' : 'Hidden'}</td><td className="p-4"><Button variant="outline" onClick={() => editVideo(entry as Video)}>Edit</Button></td></tr>)}
      {!result.isLoading && list.length === 0 && <tr><td className="p-5" colSpan={4}>No entries found.</td></tr>}
    </tbody></table></div>
    {result.isError && <Alert variant="danger">Guide content could not be loaded.</Alert>}
    {result.data && <div className="flex flex-wrap items-center justify-between gap-3 text-sm"><span>Showing {result.data.from ?? 0}–{result.data.to ?? 0} of {result.data.total}</span><div className="flex items-center gap-2"><Button variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button><span>Page {page} of {result.data.last_page}</span><Button variant="outline" disabled={page >= result.data.last_page} onClick={() => setPage(page + 1)}>Next</Button></div></div>}
    {video && <Modal open onClose={closeEditor} title={video.id ? 'Edit video' : 'Add video'} description="Set the video details and where it appears on the Guides page." className="sm:max-w-2xl"><form onSubmit={saveVideo} className="space-y-4">
      {error && <Alert variant="danger">{error}</Alert>}
      <Input label="Title" value={video.title} onChange={(e) => setVideo({ ...video, title: e.target.value })} required maxLength={255} />
      <Textarea label="Description" value={video.description ?? ''} onChange={(e) => setVideo({ ...video, description: e.target.value })} maxLength={1000} />
      <div className="grid gap-4 sm:grid-cols-2">{video.section === 'sample' ? <label className="font-semibold">Language<select className="mt-2 block min-h-11 w-full rounded-xl border border-border bg-surface px-3" value={video.language ?? ''} onChange={(e) => setVideo({ ...video, language: e.target.value || null })} required><option value="">Choose a language</option><option value="yoruba">Yorùbá</option><option value="igbo">Igbo</option><option value="hausa">Hausa</option><option value="english">English</option></select></label> : <label className="font-semibold">Topic<select className="mt-2 block min-h-11 w-full rounded-xl border border-border bg-surface px-3" value={video.topic ?? 'getting_started'} onChange={(e) => setVideo({ ...video, topic: e.target.value })}><option value="getting_started">Getting started</option><option value="parent_guide">Parent guide</option><option value="learning">Learning</option><option value="billing">Billing</option><option value="schools">Schools</option></select></label>}
      <label className="font-semibold">Video source<select className="mt-2 block min-h-11 w-full rounded-xl border border-border bg-surface px-3" value={video.source_type} onChange={(e) => setVideo({ ...video, source_type: e.target.value as Source })}><option value="youtube">YouTube URL</option><option value="upload">Upload file</option></select></label></div>
      {video.source_type === 'youtube' ? <Input label="YouTube video URL" type="url" value={video.youtube_url} onChange={(e) => setVideo({ ...video, youtube_url: e.target.value })} required /> : <div className="space-y-2"><span className="block font-semibold">Video file</span><input ref={fileInput} type="file" accept=".mp4,.webm,.mov,video/mp4,video/webm,video/quicktime" className="sr-only" aria-label="Choose video file" onChange={(e) => { chooseVideoFile(e.target.files?.[0]); e.target.value = '' }} /><button type="button" disabled={busy} onClick={() => fileInput.current?.click()} className="flex min-h-20 w-full flex-col items-center justify-center rounded-xl border-2 border-dashed border-border-strong bg-surface-muted px-4 py-3 text-center text-foreground transition-colors hover:border-primary hover:bg-primary-soft disabled:opacity-60"><span className="font-semibold">{video.file ? 'Change video file' : 'Choose video file'}</span><span className="text-sm text-muted">MP4, WebM or MOV · up to 100 MB</span></button>{video.file && <p className="text-sm text-foreground" role="status">Selected: <strong>{video.file.name}</strong> ({(video.file.size / (1024 * 1024)).toFixed(1)} MB)</p>}{!video.file && video.file_url && <p className="text-sm text-muted">The current video will stay unless you choose a replacement.</p>}{video.file_url && <a className="inline-block text-sm text-blue-600 underline" href={video.file_url} target="_blank" rel="noreferrer">View current file</a>}</div>}
      {uploadProgress !== null && <div className="space-y-2" role="status" aria-live="polite"><div className="flex justify-between text-sm font-semibold"><span>{uploadProgress < 100 ? 'Uploading video…' : 'Upload complete. Saving video…'}</span><span>{uploadProgress}%</span></div><div role="progressbar" aria-label="Video upload progress" aria-valuemin={0} aria-valuemax={100} aria-valuenow={uploadProgress} className="h-2.5 overflow-hidden rounded-full bg-surface-muted"><div className="h-full rounded-full bg-primary transition-[width]" style={{ width: `${uploadProgress}%` }} /></div></div>}
      <div className="grid gap-4 sm:grid-cols-2"><Input label="Duration in seconds" type="number" min={1} value={video.duration_seconds ?? ''} onChange={(e) => setVideo({ ...video, duration_seconds: e.target.value ? Number(e.target.value) : null })} /><Input label="Display order" type="number" min={0} value={video.sort_order} onChange={(e) => setVideo({ ...video, sort_order: Number(e.target.value) })} required /></div>
      <label className="flex items-center gap-2"><input type="checkbox" checked={video.is_visible} onChange={(e) => setVideo({ ...video, is_visible: e.target.checked })} />Show on public Guides page</label>
      <div className="flex flex-wrap gap-2 border-t border-border pt-4"><Button type="submit" disabled={busy}>{busy ? 'Saving…' : 'Save video'}</Button><Button type="button" variant="outline" disabled={busy} onClick={closeEditor}>Cancel</Button>{video.id && <Button type="button" variant="outline" disabled={busy} onClick={() => remove('video', video.id!)}>Delete</Button>}</div>
    </form></Modal>}
    {faq && <Modal open onClose={closeEditor} title={faq.id ? 'Edit question' : 'Add question'} description="Manage an answer on the public Guides page." className="sm:max-w-xl"><form onSubmit={saveFaq} className="space-y-4">{error && <Alert variant="danger">{error}</Alert>}<Input label="Question" value={faq.question} onChange={(e) => setFaq({ ...faq, question: e.target.value })} required maxLength={255} /><Textarea label="Answer" value={faq.answer} onChange={(e) => setFaq({ ...faq, answer: e.target.value })} required maxLength={3000} rows={5} /><Input label="Display order" type="number" min={0} value={faq.sort_order} onChange={(e) => setFaq({ ...faq, sort_order: Number(e.target.value) })} required /><label className="flex items-center gap-2"><input type="checkbox" checked={faq.is_visible} onChange={(e) => setFaq({ ...faq, is_visible: e.target.checked })} />Show on public Guides page</label><div className="flex flex-wrap gap-2 border-t border-border pt-4"><Button type="submit" disabled={busy}>{busy ? 'Saving…' : 'Save question'}</Button><Button type="button" variant="outline" disabled={busy} onClick={closeEditor}>Cancel</Button>{faq.id && <Button type="button" variant="outline" disabled={busy} onClick={() => remove('faq', faq.id!)}>Delete</Button>}</div></form></Modal>}
    <a href="/guides" target="_blank" rel="noreferrer" className="inline-block font-bold text-blue-600 underline">View public Guides page</a>
  </div>
}
