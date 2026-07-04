import { Card } from '@/components/ui'

interface SeriesRow {
  key: string
  label: string
  by_month: Record<string, number>
  total: number
}

/** '2026-06' → 'Jun 26'. */
export function monthLabel(key: string): string {
  const [y, m] = key.split('-').map(Number)
  const name = new Date(y, (m ?? 1) - 1, 1).toLocaleString(undefined, { month: 'short' })
  return `${name} ${String(y).slice(2)}`
}

interface MonthlyTableProps {
  months: string[]
  rows: SeriesRow[]
  /** Cell/total formatter (defaults to a localized integer). */
  format?: (value: number) => string
  /** Header for the leading label column. */
  rowHeader?: string
  totalHeader?: string
}

/** Compact "series × month" table used across the admin reports. */
export function MonthlyTable({ months, rows, format = (n) => n.toLocaleString(), rowHeader = '', totalHeader = 'Total' }: MonthlyTableProps) {
  return (
    <Card>
      <div className="overflow-x-auto">
        <table className="w-full text-right text-sm">
          <thead>
            <tr className="border-b border-border text-xs uppercase tracking-wide text-muted">
              <th className="px-4 py-2.5 text-left font-semibold">{rowHeader}</th>
              {months.map((m) => (
                <th key={m} className="px-4 py-2.5 font-semibold tabular-nums">
                  {monthLabel(m)}
                </th>
              ))}
              <th className="px-4 py-2.5 font-semibold">{totalHeader}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.key} className="border-b border-border last:border-0">
                <td className="px-4 py-3 text-left font-semibold text-foreground">{r.label}</td>
                {months.map((m) => (
                  <td key={m} className="px-4 py-3 tabular-nums text-muted">
                    {r.by_month[m] ? format(r.by_month[m]) : '—'}
                  </td>
                ))}
                <td className="px-4 py-3 font-semibold tabular-nums text-foreground">{format(r.total)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  )
}
