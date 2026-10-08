import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, expect, it, vi } from 'vitest'
import { SettingsPage } from './SettingsPage'

const fixture = vi.hoisted(() => ({ save: vi.fn(), more: vi.fn(), media: vi.fn(), settings: { groups: [{ key: 'rewarded_video', label: 'Rewarded video', settings: [
  { key: 'ads.managed_video_asset_id', label: 'Video', type: 'int', value: 0 },
  { key: 'ads.managed_video_duration_seconds', label: 'Rewarded video length (seconds)', type: 'int', value: 0 },
  { key: 'ads.managed_video_enabled', label: 'Enable rewarded video', type: 'bool', value: false },
] }] } }))
vi.mock('@/lib/admin/queries', () => ({
  useSettings: () => ({ data: fixture.settings }),
  useUpdateSettings: () => ({ mutateAsync: fixture.save, isPending: false }),
}))
vi.mock('@/lib/content/queries', () => ({ useMediaLibraryInfinite: fixture.media }))

beforeEach(() => {
  vi.clearAllMocks()
  fixture.save.mockResolvedValue(undefined)
  fixture.media.mockReturnValue({ data: { pages: [{ data: [{ id: 42, title: 'Learning together', type: 'video' }] }] }, hasNextPage: true, fetchNextPage: fixture.more })
})

it('lets an administrator find an uploaded video by name and save the full-refill configuration', async () => {
  render(<SettingsPage />)
  fireEvent.change(screen.getByLabelText('Find a rewarded video'), { target: { value: 'Learning' } })
  expect(fixture.media).toHaveBeenLastCalledWith({ type: 'video', q: 'Learning', per_page: 25 })
  fireEvent.click(screen.getByRole('button', { name: 'Load more videos' }))
  expect(fixture.more).toHaveBeenCalledOnce()
  fireEvent.change(screen.getByLabelText('Rewarded video'), { target: { value: '42' } })
  fireEvent.change(screen.getByLabelText('Rewarded video length (seconds)'), { target: { value: '30' } })
  fireEvent.click(screen.getByRole('switch', { name: 'Enable rewarded video' }))
  fireEvent.click(screen.getByRole('button', { name: 'Save changes' }))
  await waitFor(() => expect(fixture.save).toHaveBeenCalledWith({ 'ads.managed_video_asset_id': 42, 'ads.managed_video_duration_seconds': 30, 'ads.managed_video_enabled': true }))
})
