import { useRef, useState } from 'react'
import { Alert, Button, Card, CardBody, CardHeader, CardTitle, Icon } from '@/components/ui'
import { ApiError, type RosterImportResult } from '@/lib/api'
import { SchoolGate } from '@/components/school/SchoolGate'
import { useClasses, useImportRoster } from '@/lib/school/queries'

const ROSTER_TEMPLATE = ['Firstname,Lastname,Email,Level,StudentId', 'Amara,Okafor,,L0,S-001', 'Bello,Musa,,L1,S-002', 'Chinwe,Eze,,,S-003'].join('\n')

function downloadRosterTemplate() {
  const blob = new Blob([ROSTER_TEMPLATE], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'roster-import-template.csv'
  a.click()
  URL.revokeObjectURL(url)
}

export function RosterPage() {
  return <SchoolGate>{(orgId) => <Roster orgId={orgId} />}</SchoolGate>
}

function Roster({ orgId }: { orgId: number }) {
  const importRoster = useImportRoster(orgId)
  const classes = useClasses()
  const [classId, setClassId] = useState('')
  const [file, setFile] = useState<File | null>(null)
  const [result, setResult] = useState<RosterImportResult | null>(null)
  const [error, setError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const matched = result?.matched ?? 0
  const imported = (result?.created ?? 0) + matched

  async function submit() {
    if (!file) return
    setError(null)
    setResult(null)
    try {
      const res = await importRoster.mutateAsync({ file, ...(classId ? { class_id: Number(classId) } : {}) })
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
            <p>
              One student per row with a header: <code>Firstname,Lastname,Email,Level</code>. <code>Email</code> and <code>Level</code> are optional.
              If provided, Level must be L0, L1, L2, L3, L4 or L5; it records school course placement and does not unlock paid lessons or mark lessons complete.
              If provided, Email must be the learner's existing login already linked to this school. Leave it blank for a school-managed profile without a login.
            </p>
            <p className="mt-2">You can safely upload the same roster again. For changing rosters, add an optional <code>StudentId</code> column with a stable school student number. Learners with the same name need distinct student numbers; names alone are never used to merge profiles.</p>
            <p className="mt-2">Every row must be valid before any students are imported. Fix the reported rows and upload the corrected file.</p>
            <button
              type="button"
              className="mt-2 text-sm font-medium text-primary hover:underline"
              onClick={downloadRosterTemplate}
            >
              Download template
            </button>
          </Alert>

          {error && <Alert variant="danger">{error}</Alert>}

          <label className="text-sm font-medium text-foreground">Assign imported students to class
            <select aria-label="Assign imported students to class" value={classId} onChange={e => setClassId(e.target.value)} disabled={classes.isLoading}
              className="mt-1 min-h-11 w-full rounded-xl border border-border bg-background px-3 text-foreground">
              <option value="">No class yet</option>
              {classes.data?.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </label>
          {classes.isError && <Alert variant="warning">Couldn’t load classes. You can import without a class or try again.</Alert>}

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
        <Alert variant={result.errors.length ? 'warning' : 'success'} title={result.errors.length && imported === 0 ? 'No students imported — fix the rows below' : `${imported} row${imported === 1 ? '' : 's'} imported`}>
          <p>{result.created} new profile{result.created === 1 ? '' : 's'} created. {matched} existing learner row{matched === 1 ? '' : 's'} matched.</p>
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
