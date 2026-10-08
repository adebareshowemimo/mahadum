import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Card, CardBody, Input } from '@/components/ui'
import { batch4Api, type TeacherImportResult } from '@/lib/batch4/api'

export function TeacherCsvImport({ org }: { org: number }) {
  const cache = useQueryClient()
  const [file, setFile] = useState<File | null>(null)
  const [result, setResult] = useState<TeacherImportResult | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function run(preview: boolean) {
    if (!file) return
    setBusy(true)
    setError(null)
    try {
      const next = await batch4Api.importTeachers(org, file, preview)
      setResult(next)
      if (!preview) await cache.invalidateQueries({ queryKey: ['school-teacher-directory'] })
    } catch (err) {
      setResult(null)
      setError(err instanceof Error ? err.message : 'Could not import teachers. Please try again.')
    } finally { setBusy(false) }
  }

  return <Card><CardBody className="flex flex-col gap-4">
    <h2 className="text-lg font-semibold">Invite teachers from CSV</h2>
    <p className="text-sm text-muted">Use Firstname, Lastname, Email and Phone. Nigerian local phone numbers are supported; include + and the country code for other countries. Preview up to 500 teachers before sending invitations. Existing teachers and pending invitations are skipped.</p>
    <a className="font-semibold text-primary underline" href={`data:text/csv;charset=utf-8,${encodeURIComponent('Firstname,Lastname,Email,Phone\r\nAda,Okafor,ada@example.com,+2348031234567\r\n')}`} download="teacher-invitations-template.csv">Download teacher CSV template</a>
    <Input label="Teacher CSV file" type="file" accept=".csv,text/csv" disabled={busy} onChange={(event) => { setFile(event.target.files?.[0] ?? null); setResult(null); setError(null) }} />
    <div className="flex flex-wrap gap-3">
      <Button variant="outline" disabled={!file || busy} onClick={() => void run(true)}>Preview teachers</Button>
      <Button loading={busy} disabled={busy || !result?.preview || result.errors.length > 0} onClick={() => void run(false)}>Send teacher invitations</Button>
    </div>
    {error && <Alert variant="danger">{error}</Alert>}
    {result?.errors.length ? <Alert variant="danger"><p>No invitations were sent. Correct the file and preview again.</p><ul>{result.errors.map((entry, index) => <li key={index}>Row {entry.row}: {entry.message}</li>)}</ul></Alert> : result && !result.preview && <Alert variant="success">{result.created} invitations saved; {result.skipped} existing teachers or invitations skipped. {result.delivery_status === 'not_configured' ? 'Email delivery is not configured. Ask your administrator to configure email before resending.' : 'Email delivery is queued. Each teacher must verify their email and accept.'}</Alert>}
    {result?.preview && result.errors.length === 0 && <div className="overflow-x-auto"><table className="w-full text-left text-sm"><caption className="text-left font-semibold">Teacher invitation preview</caption><thead><tr>{['Row', 'Name', 'Email', 'Phone', 'Action'].map((heading) => <th key={heading} className="p-2">{heading}</th>)}</tr></thead><tbody>{result.rows.map((row) => <tr key={row.row}><td className="p-2">{row.row}</td><td className="p-2">{row.name}</td><td className="p-2">{row.email}</td><td className="p-2">{row.phone}</td><td className="p-2">{row.action === 'skip' ? 'Skip existing' : 'Invite'}</td></tr>)}</tbody></table></div>}
  </CardBody></Card>
}
