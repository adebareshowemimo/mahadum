import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, contentApi, type LibrarySetSummary } from '@/lib/api'
import { ProverbsTriviaPage } from './ProverbsTriviaPage'
import { LibrarySetBuilderPage } from './LibrarySetBuilderPage'
import { LibrarySetPreviewPage } from './LibrarySetPreviewPage'

const permissions = vi.hoisted(() => ({ manage: true }))
vi.mock('@/lib/content/permissions', () => ({ useCanManageContent: () => permissions.manage }))
vi.mock('@/lib/config/useConfig', () => ({ useConfig: () => ({ data: { languages: [{ id: 1, code: 'yo', name: 'Yoruba' }] } }) }))
const fixture: LibrarySetSummary = { id: 7, kind: 'proverb', title: 'Wisdom', description: 'Sayings', language_id: 1, language: 'yo', status: 'draft', owner: 'Author', level_band: 'A1', levels_count: 1, lessons_count: 0, completed_count: 0, levels: [{ id: 4, title: 'First level', position: 1, is_free: true, lessons: [{ id: 10, title: 'First saying', summary: 'Read this wisdom.', position: 1, est_minutes: 6, published_at: null, completed: false, access_reason: null }, { id: 11, title: 'Second saying', summary: null, position: 2, published_at: null, completed: false, access_reason: null }] }] }

function show(path: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<QueryClientProvider client={client}><MemoryRouter initialEntries={[path]}><Routes>
    <Route path="/courses/proverbs-trivia" element={<ProverbsTriviaPage />} />
    <Route path="/courses/proverbs-trivia/:setId" element={<LibrarySetBuilderPage />} />
    <Route path="/courses/proverbs-trivia/:setId/preview" element={<LibrarySetPreviewPage />} />
  </Routes></MemoryRouter></QueryClientProvider>)
}

beforeEach(() => {
  vi.restoreAllMocks(); permissions.manage = true
  vi.spyOn(contentApi, 'librarySets').mockResolvedValue({ data: [fixture], meta: { current_page: 1, last_page: 1, per_page: 12, total: 1 } })
  vi.spyOn(contentApi, 'librarySet').mockResolvedValue(fixture)
})

describe('Proverbs and Trivia management', () => {
  it('filters status and shows detailed publication failures', async () => {
    const publish = vi.spyOn(contentApi, 'setLibraryCollectionPublished').mockRejectedValue(new ApiError('Complete the listed lessons.', 'publish_checks_failed', 422, {}, ['First saying: Add content.']))
    show('/courses/proverbs-trivia')
    await screen.findByText('Wisdom')
    fireEvent.change(screen.getByRole('combobox', { name: 'Status' }), { target: { value: 'draft' } })
    await waitFor(() => expect(contentApi.librarySets).toHaveBeenLastCalledWith(expect.objectContaining({ status: 'draft' })))
    fireEvent.click(await screen.findByRole('button', { name: 'Publish' }))
    expect(await screen.findByText('First saying: Add content.')).toBeInTheDocument()
    expect(publish).toHaveBeenCalledWith(7, true)
    expect(screen.getByText('Author')).toBeInTheDocument()
    expect(screen.getByText('Page 1 of 1 · 1 sets')).toBeInTheDocument()
  })

  it('archives from the list and confirms deletion in a modal', async () => {
    const archive = vi.spyOn(contentApi, 'setLibraryCollectionArchived').mockResolvedValue({ ...fixture, status: 'archived' })
    const remove = vi.spyOn(contentApi, 'deleteLibraryCollection').mockResolvedValue()
    show('/courses/proverbs-trivia')
    fireEvent.click(await screen.findByRole('button', { name: 'Archive' }))
    await screen.findByText('Wisdom archived.')
    expect(archive).toHaveBeenCalledWith(7, true)
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))
    const dialog = screen.getByRole('dialog')
    expect(remove).not.toHaveBeenCalled()
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete' }))
    await waitFor(() => expect(remove).toHaveBeenCalledWith(7))
  })

  it('provides contents, quick lesson duration editing and reordering', async () => {
    const update = vi.spyOn(contentApi, 'updateLesson').mockResolvedValue({} as Awaited<ReturnType<typeof contentApi.updateLesson>>)
    const reorder = vi.spyOn(contentApi, 'reorderLibraryLessons').mockResolvedValue(fixture.levels![0])
    show('/courses/proverbs-trivia/7')
    expect(await screen.findByRole('navigation', { name: 'Set table of contents' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Preview set' })).toBeInTheDocument()
    fireEvent.click(screen.getAllByRole('button', { name: 'Edit details' })[1])
    const dialog = screen.getByRole('dialog', { name: 'Edit lesson details' })
    fireEvent.change(within(dialog).getByLabelText('Estimated minutes'), { target: { value: '12' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }))
    await waitFor(() => expect(update).toHaveBeenCalledWith(10, { title: 'First saying', est_minutes: 12 }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'Move lesson Second saying up' }))
    await waitFor(() => expect(reorder).toHaveBeenCalledWith(4, [11, 10]))
  })

  it('hides mutation controls for read-only authors', async () => {
    permissions.manage = false
    show('/courses/proverbs-trivia/7')
    await screen.findByText('Set structure')
    expect(screen.queryByRole('button', { name: 'Add level' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Publish set' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Drag to reorder/ })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Preview level' })).toBeInTheDocument()
  })

  it('previews text-only draft lessons without writing learner progress', async () => {
    vi.spyOn(contentApi, 'lesson').mockResolvedValue({ id: 10, kind: 'proverb', title: 'First saying', summary: 'Read this wisdom.', position: 1, est_minutes: 6, is_locked_by_default: false, published_at: null, is_published: false, components: [] })
    show('/courses/proverbs-trivia/7/preview')
    fireEvent.click((await screen.findAllByRole('button', { name: 'Play lesson' }))[0])
    expect(await screen.findByText('Read this wisdom.')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Complete reading' }))
    expect(await screen.findByText(/Completed in preview/)).toBeInTheDocument()
    expect(contentApi.lesson).toHaveBeenCalledWith(10)
  })
})
