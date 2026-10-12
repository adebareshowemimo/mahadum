import { useState, type FormEvent } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Button, Input, Textarea } from '@/components/ui'
import { api } from '@/lib/api/client'
import { ApiError } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'

type Row = Record<string, string>
interface Content { language: string | null; playlist_url?: string | null; phrases: Row[]; faqs: Row[]; videos: Row[]; levels: Row[] }
interface WebsiteDocument { id?: number; path: string; kind: string; title: string; description: string; heading: string; intro: string | null; body: string | null; author: string | null; published: boolean; content: Content }
const empty = (): WebsiteDocument => ({ path: '/guides/', kind: 'guide', title: '', description: '', heading: '', intro: '', body: '', author: '', published: false, content: { language: null, phrases: [], faqs: [], videos: [], levels: [] } })

export function WebsiteEditorPage() {
  const { hasRole } = useAuth()
  const qc = useQueryClient()
  const { data, isError } = useQuery({ queryKey: ['website-documents'], queryFn: async () => (await api.get<{ data: WebsiteDocument[] }>('/website/documents')).data.data })
  const { data: reports } = useQuery({ queryKey: ['website-reports'], enabled: hasRole('super_admin'), queryFn: async () => (await api.get<{ data: { sources: Array<{ utm_source: string | null; utm_medium: string | null; utm_campaign: string | null; name: string; total: number }> } }>('/website/reports')).data.data })
  const [document, setDocument] = useState<WebsiteDocument | null>(null)
  const [error, setError] = useState('')
  const [saved, setSaved] = useState(false)
  const [saving, setSaving] = useState(false)
  function field(key: keyof WebsiteDocument, value: unknown) { setDocument((d) => d ? { ...d, [key]: value } : d); setSaved(false) }
  async function save(event: FormEvent) {
    event.preventDefault()
    if (!document) return
    setSaving(true); setError(''); setSaved(false)
    try {
      const { data: result } = document.id ? await api.put(`/website/documents/${document.id}`, document) : await api.post('/website/documents', document)
      setDocument(result.data); setSaved(true)
      await qc.invalidateQueries({ queryKey: ['website-documents'] })
    } catch (err) { setError(err instanceof ApiError ? Object.values(err.fieldErrors).join(' ') || err.message : 'The page could not be saved. Please try again.') }
    finally { setSaving(false) }
  }
  async function exportVotes() {
    try {
      const response = await api.get('/website/votes.csv', { responseType: 'blob' })
      const url = URL.createObjectURL(response.data)
      const link = window.document.createElement('a'); link.href = url; link.download = 'language-votes.csv'; link.click()
      URL.revokeObjectURL(url)
    } catch { setError('The vote export could not be downloaded.') }
  }
  return <div className="space-y-6">
    <AdminPageHeader title="Website content" description="Publish language pages and guides, and update page titles and descriptions." />
    {isError && <Alert variant="danger">Website content could not be loaded.</Alert>}
    {error && <Alert variant="danger">{error}</Alert>}
    <div className="flex flex-wrap gap-3"><Button onClick={() => { setDocument(empty()); setSaved(false); setError('') }}>New guide</Button>{hasRole('super_admin') && <Button variant="outline" onClick={exportVotes}>Export language votes</Button>}</div>
    {reports && <div className="overflow-auto rounded-xl border border-border"><table className="w-full text-left text-sm"><caption className="p-3 text-left font-bold">Sign-ups and first lessons by source</caption><thead><tr>{['Source', 'Medium', 'Campaign', 'Event', 'Accounts'].map((label) => <th key={label} className="border-b p-3">{label}</th>)}</tr></thead><tbody>{reports.sources.map((row, i) => <tr key={i}><td className="p-3">{row.utm_source || 'Direct / unknown'}</td><td className="p-3">{row.utm_medium || '—'}</td><td className="p-3">{row.utm_campaign || '—'}</td><td className="p-3">{row.name === 'sign_up' ? 'Sign-up' : 'First lesson completed'}</td><td className="p-3">{row.total}</td></tr>)}</tbody></table></div>}
    <label className="block font-semibold">Choose a page
      <select className="mt-2 block min-h-11 w-full rounded-xl border border-border-strong bg-surface px-3" value={document?.id ?? ''} onChange={(e) => { const selected = data?.find((d) => d.id === Number(e.target.value)); setDocument(selected ? structuredClone(selected) : null); setError(''); setSaved(false) }}>
        <option value="">Select a page or create a new guide</option>{data?.map((d) => <option key={d.id} value={d.id}>{d.path}{d.published ? '' : ' — draft'}</option>)}
      </select>
    </label>
    {document && <form onSubmit={save} className="space-y-5 rounded-2xl border border-border bg-surface p-5 sm:p-8">
      {!document.id && <Input label="Guide address" value={document.path} onChange={(e) => field('path', e.target.value)} hint="For example /guides/hausa-greetings" required />}
      <Input label="Page title for search results" value={document.title} onChange={(e) => field('title', e.target.value)} maxLength={60} hint={`${document.title.length}/60 characters`} required />
      <Textarea label="Description for search results" value={document.description} onChange={(e) => field('description', e.target.value)} maxLength={155} hint={`${document.description.length}/155 characters`} required />
      <Input label="Page heading" value={document.heading} onChange={(e) => field('heading', e.target.value)} required={document.kind !== 'page'} hint={document.kind === 'page' ? 'Leave blank to keep the current heading.' : undefined} />
      <Textarea label="Introduction" value={document.intro ?? ''} onChange={(e) => field('intro', e.target.value)} hint={document.kind === 'page' ? 'Leave blank to keep the current introduction.' : undefined} />
      {document.kind !== 'page' && <>
        <Textarea label="Article or page text" value={document.body ?? ''} onChange={(e) => field('body', e.target.value)} rows={8} />
        {document.kind === 'guide' && <Input label="Author" value={document.author ?? ''} onChange={(e) => field('author', e.target.value)} required={document.published} />}
        <label className="block font-semibold">Related language<select className="mt-2 block min-h-11 w-full rounded-xl border bg-surface px-3" value={document.content.language ?? ''} onChange={(e) => field('content', { ...document.content, language: e.target.value || null })}><option value="">Choose a language</option><option value="hausa">Hausa</option><option value="igbo">Igbo</option><option value="yoruba">Yorùbá</option></select></label>
        <Input label="YouTube playlist link" value={document.content.playlist_url ?? ''} onChange={(e) => field('content', { ...document.content, playlist_url: e.target.value || null })} />
        <Rows title="Approved phrases and native audio" fields={{ text: 'Phrase', meaning: 'English meaning', pronunciation: 'Pronunciation', audio_url: 'Audio link (https)' }} rows={document.content.phrases} onChange={(phrases) => field('content', { ...document.content, phrases })} />
        <Rows title="FAQ" fields={{ question: 'Question', answer: 'Approved answer' }} rows={document.content.faqs} onChange={(faqs) => field('content', { ...document.content, faqs })} />
        <Rows title="Levels" fields={{ title: 'Level name', description: 'What learners will learn' }} rows={document.content.levels} onChange={(levels) => field('content', { ...document.content, levels })} />
        <Rows title="YouTube lessons" fields={{ youtube_id: 'YouTube video ID', title: 'Video title', description: 'Video description', upload_date: 'Original publication date' }} rows={document.content.videos} onChange={(videos) => field('content', { ...document.content, videos })} />
      </>}
      {document.kind === 'guide' && <label className="flex min-h-11 items-center gap-3"><input type="checkbox" checked={document.published} onChange={(e) => field('published', e.target.checked)} />Publish this guide</label>}
      {saved && <Alert variant="success">Saved. Published pages and the sitemap update immediately.</Alert>}
      <div className="flex flex-wrap items-center gap-4"><Button type="submit" disabled={saving}>{saving ? 'Saving…' : 'Save changes'}</Button>{document.published && document.id && <a href={document.path} target="_blank" rel="noreferrer" className="font-bold underline">Open public page</a>}</div>
    </form>}
  </div>
}

function Rows({ title, fields, rows, onChange }: { title: string; fields: Row; rows: Row[]; onChange: (rows: Row[]) => void }) {
  return <fieldset className="space-y-3"><legend className="font-display text-xl font-bold">{title}</legend>
    {rows.map((row, index) => <div key={index} className="space-y-3 rounded-xl border border-border p-4">
      {Object.entries(fields).map(([key, label]) => <Input key={key} label={label} value={row[key] ?? ''} type={key === 'upload_date' ? 'date' : 'text'} onChange={(e) => onChange(rows.map((item, i) => i === index ? { ...item, [key]: e.target.value } : item))} required />)}
      <Button type="button" variant="outline" onClick={() => onChange(rows.filter((_, i) => i !== index))}>Remove entry {index + 1}</Button>
    </div>)}
    <Button type="button" variant="outline" onClick={() => onChange([...rows, Object.fromEntries(Object.keys(fields).map((key) => [key, '']))])}>Add entry</Button>
  </fieldset>
}
