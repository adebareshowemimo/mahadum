import type { ReactNode } from 'react'
import { Icon, Input } from '@/components/ui'
import { cn } from '@/lib/cn'

export interface FilterOption {
  label: string
  value: string
}

interface FilterSelectProps {
  label: string
  value: string
  onChange: (value: string) => void
  options: FilterOption[]
  /** Label for the "no filter" option. */
  allLabel?: string
}

/** A single labelled dropdown filter. Empty value = "all". */
export function FilterSelect({ label, value, onChange, options, allLabel = 'All' }: FilterSelectProps) {
  return (
    <label className="flex items-center gap-2 text-sm">
      <span className="font-semibold text-muted">{label}</span>
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
      >
        <option value="">{allLabel}</option>
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </label>
  )
}

interface AdminToolbarProps {
  search: string
  onSearch: (value: string) => void
  searchPlaceholder?: string
  /** Filter dropdowns, typically <FilterSelect />. */
  children?: ReactNode
  className?: string
}

/** Search box + filter row shown above an admin DataTable. */
export function AdminToolbar({ search, onSearch, searchPlaceholder = 'Search…', children, className }: AdminToolbarProps) {
  return (
    <div className={cn('flex flex-wrap items-center gap-3', className)}>
      <div className="min-w-[14rem] flex-1">
        <Input
          value={search}
          onChange={(e) => onSearch(e.target.value)}
          placeholder={searchPlaceholder}
          leftIcon={<Icon name="search" />}
          aria-label={searchPlaceholder}
        />
      </div>
      {children}
    </div>
  )
}
