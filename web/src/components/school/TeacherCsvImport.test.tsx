import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { batch4Api, type TeacherImportResult } from '@/lib/batch4/api'
import { TeacherCsvImport } from './TeacherCsvImport'

afterEach(() => vi.restoreAllMocks())
const preview: TeacherImportResult = { preview: true, rows: [{ row: 2, name: 'Ada Okafor', email: 'ada@example.com', phone: '+2348031234567', action: 'invite' }], errors: [], created: 0, skipped: 0, delivery_status: 'not_configured' }
function show() {
  const cache = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  cache.setQueryData(['school-teacher-directory', 1, 7], {})
  render(<QueryClientProvider client={cache}><TeacherCsvImport org={7} /></QueryClientProvider>)
  return cache
}
describe('Teacher CSV invitations', () => {
  it('requires a valid preview, sends the same file and refreshes the directory', async () => {
    const user = userEvent.setup()
    const request = vi.spyOn(batch4Api, 'importTeachers').mockResolvedValueOnce(preview).mockResolvedValue({ ...preview, preview: false, created: 1 })
    const cache = show()
    const file = new File(['Firstname,Lastname,Email,Phone\nAda,Okafor,ada@example.com,+2348031234567'], 'teachers.csv', { type: 'text/csv' })
    expect(screen.getByRole('button', { name: 'Send teacher invitations' })).toBeDisabled()
    await user.upload(screen.getByLabelText('Teacher CSV file'), file)
    await user.click(screen.getByRole('button', { name: 'Preview teachers' }))
    expect(await screen.findByText('Ada Okafor')).toBeInTheDocument()
    expect(request).toHaveBeenCalledWith(7, file, true)
    await user.click(screen.getByRole('button', { name: 'Send teacher invitations' }))
    expect(await screen.findByText(/1 invitations saved/)).toHaveTextContent('Email delivery is not configured')
    expect(request).toHaveBeenLastCalledWith(7, file, false)
    expect(cache.getQueryState(['school-teacher-directory', 1, 7])?.isInvalidated).toBe(true)
    expect(screen.getByRole('button', { name: 'Send teacher invitations' })).toBeDisabled()
  })

  it('shows row errors without enabling import and clears stale preview on a different file', async () => {
    const user = userEvent.setup()
    vi.spyOn(batch4Api, 'importTeachers').mockResolvedValueOnce({ ...preview, errors: [{ row: 3, message: 'The lastname field is required.' }] }).mockResolvedValue(preview)
    show()
    await user.upload(screen.getByLabelText('Teacher CSV file'), new File(['bad'], 'bad.csv', { type: 'text/csv' }))
    await user.click(screen.getByRole('button', { name: 'Preview teachers' }))
    expect(await screen.findByText('Row 3: The lastname field is required.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Send teacher invitations' })).toBeDisabled()
    await user.click(screen.getByRole('button', { name: 'Preview teachers' }))
    await waitFor(() => expect(screen.getByRole('button', { name: 'Send teacher invitations' })).toBeEnabled())
    await user.upload(screen.getByLabelText('Teacher CSV file'), new File(['changed'], 'changed.csv', { type: 'text/csv' }))
    expect(screen.getByRole('button', { name: 'Send teacher invitations' })).toBeDisabled()
  })
})
