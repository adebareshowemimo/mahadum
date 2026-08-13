import { fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AdvertLeaderboard } from './AdvertLeaderboard'
import {
  useActiveAdvert,
  useAdsAllowed,
  useRecordClick,
  useRecordImpression,
} from '@/lib/adverts/queries'

vi.mock('@/lib/adverts/queries', () => ({
  useActiveAdvert: vi.fn(),
  useAdsAllowed: vi.fn(),
  useRecordClick: vi.fn(),
  useRecordImpression: vi.fn(),
}))

const impression = vi.fn()
const click = vi.fn()

describe('AdvertLeaderboard', () => {
  beforeEach(() => {
    impression.mockReset()
    click.mockReset()
    vi.mocked(useAdsAllowed).mockReturnValue(true)
    vi.mocked(useActiveAdvert).mockReturnValue({
      data: {
        id: 12,
        image_url: '/storage/adverts/demo.svg',
        target_url: '/pricing',
        position: 'leaderboard',
        size: '970x90',
      },
    } as ReturnType<typeof useActiveAdvert>)
    vi.mocked(useRecordImpression).mockReturnValue(
      { mutate: impression } as unknown as ReturnType<typeof useRecordImpression>,
    )
    vi.mocked(useRecordClick).mockReturnValue({ mutate: click } as unknown as ReturnType<typeof useRecordClick>)
  })

  it('stays layout-stable when the page scrolls', () => {
    render(<AdvertLeaderboard />)

    const banner = screen.getByTestId('advert-leaderboard')
    const image = screen.getByRole('img', { name: 'Advertisement' })

    expect(banner).not.toHaveStyle({ maxHeight: '120px' })
    expect(image).toHaveAttribute('width', '970')
    expect(image).toHaveAttribute('height', '90')

    fireEvent.scroll(window)

    expect(screen.getByTestId('advert-leaderboard')).toBeVisible()
    expect(impression).toHaveBeenCalledOnce()
  })
})
