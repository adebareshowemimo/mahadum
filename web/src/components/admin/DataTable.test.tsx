import { useMemo, useState } from 'react'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { AdminToolbar, DataTable, FilterSelect, type Column } from './index'
import { expectNoA11yViolations } from '@/test/a11y'

interface Org {
  id: number
  name: string
  type: string
}

const ROWS: Org[] = [
  { id: 1, name: 'Sunrise Academy', type: 'school' },
  { id: 2, name: 'Diaspora Family', type: 'family' },
  { id: 3, name: 'Lagos Grammar', type: 'school' },
]

const COLUMNS: Column<Org>[] = [
  { key: 'name', header: 'Name', render: (o) => o.name },
  { key: 'type', header: 'Type', render: (o) => o.type },
]

describe('DataTable', () => {
  it('renders a row per item with all columns', () => {
    render(<DataTable columns={COLUMNS} rows={ROWS} getRowId={(o) => o.id} />)
    expect(screen.getByText('Sunrise Academy')).toBeInTheDocument()
    expect(screen.getByText('Lagos Grammar')).toBeInTheDocument()
    // 3 data rows + 1 header row.
    expect(screen.getAllByRole('row')).toHaveLength(ROWS.length + 1)
  })

  it('shows the empty state (not the table) when there are no rows', () => {
    render(<DataTable columns={COLUMNS} rows={[]} getRowId={(o) => o.id} empty="No organizations." />)
    expect(screen.getByText('No organizations.')).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('renders loading skeletons instead of rows or empty state while loading', () => {
    render(<DataTable columns={COLUMNS} rows={[]} getRowId={(o) => o.id} isLoading empty="No organizations." />)
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
    expect(screen.queryByText('No organizations.')).not.toBeInTheDocument()
  })

  it('invokes onRowClick with the clicked row', () => {
    const onRowClick = vi.fn()
    render(<DataTable columns={COLUMNS} rows={ROWS} getRowId={(o) => o.id} onRowClick={onRowClick} />)
    fireEvent.click(screen.getByText('Diaspora Family'))
    expect(onRowClick).toHaveBeenCalledWith(ROWS[1])
  })

  it('has no detectable a11y violations', async () => {
    const { container } = render(<DataTable columns={COLUMNS} rows={ROWS} getRowId={(o) => o.id} />)
    await expectNoA11yViolations(container)
  })
})

/**
 * Mirrors how the real admin list pages wire the toolbar to the table: search +
 * a type filter narrow the rows client-side via useMemo.
 */
function FilterableList() {
  const [search, setSearch] = useState('')
  const [type, setType] = useState('')

  const rows = useMemo(
    () =>
      ROWS.filter(
        (o) =>
          o.name.toLowerCase().includes(search.trim().toLowerCase()) && (type === '' || o.type === type),
      ),
    [search, type],
  )

  return (
    <DataTable
      columns={COLUMNS}
      rows={rows}
      getRowId={(o) => o.id}
      empty="No matches."
      toolbar={
        <AdminToolbar search={search} onSearch={setSearch} searchPlaceholder="Search orgs">
          <FilterSelect
            label="Type"
            value={type}
            onChange={setType}
            options={[
              { label: 'School', value: 'school' },
              { label: 'Family', value: 'family' },
            ]}
          />
        </AdminToolbar>
      }
    />
  )
}

describe('AdminToolbar + FilterSelect filtering', () => {
  it('narrows rows as the user types in search', () => {
    render(<FilterableList />)
    fireEvent.change(screen.getByLabelText('Search orgs'), { target: { value: 'lagos' } })
    expect(screen.getByText('Lagos Grammar')).toBeInTheDocument()
    expect(screen.queryByText('Sunrise Academy')).not.toBeInTheDocument()
    expect(screen.queryByText('Diaspora Family')).not.toBeInTheDocument()
  })

  it('narrows rows via the type dropdown', () => {
    render(<FilterableList />)
    fireEvent.change(screen.getByLabelText('Type'), { target: { value: 'family' } })
    expect(screen.getByText('Diaspora Family')).toBeInTheDocument()
    expect(screen.queryByText('Sunrise Academy')).not.toBeInTheDocument()
    expect(screen.queryByText('Lagos Grammar')).not.toBeInTheDocument()
  })

  it('combines search and filter, falling back to the empty state', () => {
    render(<FilterableList />)
    fireEvent.change(screen.getByLabelText('Search orgs'), { target: { value: 'sunrise' } })
    fireEvent.change(screen.getByLabelText('Type'), { target: { value: 'family' } })
    // "Sunrise" is a school, so the family filter leaves nothing.
    expect(screen.getByText('No matches.')).toBeInTheDocument()
  })

  it('renders an "All" option that clears the filter', () => {
    render(<FilterableList />)
    const select = screen.getByLabelText('Type')
    expect(within(select).getByRole('option', { name: 'All' })).toBeInTheDocument()
  })
})
