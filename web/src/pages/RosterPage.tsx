import { useRef, useState } from 'react'
import { Alert, Button, Card, CardBody, CardHeader, CardTitle, Icon } from '@/components/ui'
import { ApiError, type RosterImportResult } from '@/lib/api'
import { SchoolGate } from '@/components/school/SchoolGate'
import { useImportRoster } from '@/lib/school/queries'

export function RosterPage() {
  return <SchoolGate>{(orgId) => <Roster orgId={orgId} />}</SchoolGate>
}

function Roster({ orgId }: { orgId: number }) {
  const importRoster = useImportRoster(orgId)
  const [file, setFile] = useState<File | null>(null)
  const [result, setResult] = useState<RosterImportResult | null>(null)
  const [error, setError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  async function submit() {
    if (!file) return
    setError(null)
    setResult(null)
    try {
      const res = await importRoster.mutateAsync({ file })
      setResult(res)
      setFile(null)
      if (inputRef.current) inputRef.current.value = ''
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Import failed. Please check the file and try again.')
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-bold text-foreground">Import roster</h1>
        <p className="mt-1 text-muted">Bulk-add students from a CSV file.</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Upload CSV</CardTitle>
        </CardHeader>
        <CardBody className="flex flex-col gap-4">
          <Alert variant="info" title="CSV format">
            One student per row with a header: <code>display_name,level</code>. The <code>level</code> column is optional.
          </Alert>

          {error && <Alert variant="danger">{error}</Alert>}

          <label className="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-border-strong p-8 text-center hover:bg-surface-muted">
            <Icon name="clipboard" className="size-8 text-muted" />
            <span className="text-sm font-medium text-foreground">
              {file ? file.name : 'Choose a .csv file'}
            </span>
            <input
              ref={inputRef}
              type="file"
              accept=".csv,text/csv"
              className="hidden"
              onChange={(e) => {
                setFile(e.target.files?.[0] ?? null)
                setResult(null)
              }}
            />
          </label>

          <Button variant="parent" loading={importRoster.isPending} disabled={!file} onClick={submit}>
            Import students
          </Button>
        </CardBody>
      </Card>

      {result && (
        <Alert variant={result.errors.length ? 'warning' : 'success'} title={`${result.created} student${result.created === 1 ? '' : 's'} imported`}>
          {result.errors.length === 0 ? (
            'All rows imported successfully.'
          ) : (
            <ul className="mt-1 list-disc pl-5 text-sm">
              {result.errors.map((e, i) => (
                <li key={i}>
                  Row {e.row}: {e.error}
                </li>
              ))}
            </ul>
          )}
        </Alert>
      )}
    </div>
  )
}
