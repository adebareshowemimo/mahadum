import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ReactNode } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { RosterPage } from './RosterPage'

const mocks = vi.hoisted(() => ({ mutateAsync: vi.fn(), createObjectURL: vi.fn(), revokeObjectURL: vi.fn() }))

vi.mock('@/components/school/SchoolGate', () => ({
  SchoolGate: ({ children }: { children: (orgId: number) => ReactNode }) => children(1),
}))
vi.mock('@/lib/school/queries', () => ({
  useImportRoster: () => ({ mutateAsync: mocks.mutateAsync, isPending: false }),
}))

describe('Roster import', () => {
  beforeEach(() => {
    mocks.mutateAsync.mockReset()
    mocks.createObjectURL.mockReset().mockReturnValue('blob:roster-template')
    mocks.revokeObjectURL.mockReset()
    vi.stubGlobal('URL', class extends URL {
      static createObjectURL = mocks.createObjectURL
      static revokeObjectURL = mocks.revokeObjectURL
    })
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  it('downloads an Email-capable CSV without requiring email for school-managed profiles', async () => {
    render(<RosterPage />)
    await userEvent.click(screen.getByRole('button', { name: 'Download template' }))
    const blob = mocks.createObjectURL.mock.calls[0][0] as Blob
    const csv = await new Promise<string>((resolve, reject) => {
      const reader = new FileReader()
      reader.onload = () => resolve(String(reader.result))
      reader.onerror = () => reject(reader.error)
      reader.readAsText(blob)
    })
    const rows = csv.split('\n').map(row => row.split(','))
    expect(rows[0]).toEqual(['Firstname', 'Lastname', 'Email', 'Level'])
    expect(rows.slice(1).every(row => row.length === 4 && row[2] === '')).toBe(true)
    expect(screen.getByText(/existing login already linked to this school/)).toBeInTheDocument()
    expect(mocks.revokeObjectURL).toHaveBeenCalledWith('blob:roster-template')
  })

  it('uploads the file and distinguishes new profiles, matched rows and rejected CSV rows', async () => {
    mocks.mutateAsync.mockResolvedValue({
      created: 1, matched: 1, errors: [{ row: 3, error: 'The email field must be a valid email address.' }],
    })
    const { container } = render(<RosterPage />)
    const button = screen.getByRole('button', { name: 'Import students' })
    expect(button).toBeDisabled()
    const file = new File(['Firstname,Lastname,Email,Level\nLocal,Learner,,A1'], 'roster.csv', { type: 'text/csv' })
    await userEvent.upload(container.querySelector('input[type=file]') as HTMLInputElement, file)
    await userEvent.click(button)
    expect(mocks.mutateAsync).toHaveBeenCalledWith({ file })
    expect(await screen.findByText('2 rows imported')).toBeInTheDocument()
    expect(screen.getByText('1 new profile created. 1 existing learner row matched.')).toBeInTheDocument()
    expect(screen.getByText('Row 3: The email field must be a valid email address.')).toBeInTheDocument()
    expect(screen.getByText('Choose a .csv file')).toBeInTheDocument()
  })

  it('supports the previous API response without matched while deployment is coordinated', async () => {
    mocks.mutateAsync.mockResolvedValue({ created: 1, errors: [] })
    const { container } = render(<RosterPage />)
    const file = new File(['Firstname,Lastname,Level\nLegacy,Learner,A1'], 'legacy.csv', { type: 'text/csv' })
    await userEvent.upload(container.querySelector('input[type=file]') as HTMLInputElement, file)
    await userEvent.click(screen.getByRole('button', { name: 'Import students' }))
    expect(await screen.findByText('1 row imported')).toBeInTheDocument()
    expect(screen.getByText('1 new profile created. 0 existing learner rows matched.')).toBeInTheDocument()
    expect(screen.getByText('All rows imported successfully.')).toBeInTheDocument()
  })
})
