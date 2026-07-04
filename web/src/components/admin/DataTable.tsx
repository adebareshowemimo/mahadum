import type { ReactNode } from 'react'
import { Card, CardBody, Skeleton } from '@/components/ui'
import { cn } from '@/lib/cn'

export interface Column<T> {
  /** Stable key for the column. */
  key: string
  header: ReactNode
  /** Cell renderer. Receives the whole row. */
  render: (row: T) => ReactNode
  /** Optional extra classes for the cell + header (alignment, width). */
  className?: string
  /** Hide below the `sm` breakpoint to keep mobile readable. */
  hideOnMobile?: boolean
}

interface DataTableProps<T> {
  columns: Column<T>[]
  rows: T[]
  getRowId: (row: T) => string | number
  onRowClick?: (row: T) => void
  isLoading?: boolean
  /** Shown when there are no rows (after loading). */
  empty?: ReactNode
  /** Rendered above the table (search + filters). */
  toolbar?: ReactNode
}

/**
 * Presentational, generic table for admin list screens. Filtering/search live in
 * the parent (pass a `toolbar` and already-filtered `rows`); this component owns
 * layout, loading skeletons, empty state, and row-click affordance.
 */
export function DataTable<T>({
  columns,
  rows,
  getRowId,
  onRowClick,
  isLoading,
  empty,
  toolbar,
}: DataTableProps<T>) {
  return (
    <div className="flex flex-col gap-3">
      {toolbar}
      <Card>
        {isLoading ? (
          <CardBody className="flex flex-col gap-2">
            {Array.from({ length: 5 }).map((_, i) => (
              <Skeleton key={i} className="h-10" />
            ))}
          </CardBody>
        ) : rows.length === 0 ? (
          <CardBody className="py-12 text-center text-sm text-muted">
            {empty ?? 'Nothing to show.'}
          </CardBody>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border text-xs uppercase tracking-wide text-muted">
                  {columns.map((c) => (
                    <th
                      key={c.key}
                      className={cn('px-4 py-2.5 font-semibold', c.hideOnMobile && 'hidden sm:table-cell', c.className)}
                    >
                      {c.header}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr
                    key={getRowId(row)}
                    onClick={onRowClick ? () => onRowClick(row) : undefined}
                    className={cn(
                      'border-b border-border last:border-0',
                      onRowClick && 'cursor-pointer transition-colors hover:bg-surface-muted',
                    )}
                  >
                    {columns.map((c) => (
                      <td
                        key={c.key}
                        className={cn('px-4 py-3 text-foreground', c.hideOnMobile && 'hidden sm:table-cell', c.className)}
                      >
                        {c.render(row)}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  )
}
