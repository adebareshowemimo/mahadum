import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AdModal } from './AdModal'

const fixture = vi.hoisted(() => ({
  eligible: true,
  request: vi.fn(),
  progress: vi.fn(),
  complete: vi.fn(),
}))
vi.mock('@/lib/api', async (importOriginal) => ({
  ...await importOriginal<object>(),
  gamificationApi: { recordAdProgress: fixture.progress },
}))
vi.mock('@/lib/gamification/queries', () => ({
  useRequestAd: () => ({ mutate: fixture.request }),
  useCompleteAd: () => ({ mutateAsync: fixture.complete }),
}))

beforeEach(() => {
  vi.clearAllMocks()
  fixture.eligible = true
  fixture.request.mockImplementation((_placement, options) => options.onSuccess(fixture.eligible
    ? { eligible: true, impression_id: 12, video: { url: '/storage/media/reward.mp4', duration_seconds: 10 } }
    : { eligible: false, reason: 'unavailable' }))
  fixture.progress.mockResolvedValue(undefined)
  fixture.complete.mockResolvedValue({ shown: true })
  vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => {})
})

function setup(rewarded = vi.fn().mockResolvedValue(undefined)) {
  const props = { open: true, learnerId: 1, placement: 'rewarded_heart' as const, onClose: vi.fn(), onRewarded: rewarded }
  const view = render(<AdModal {...props} />)
  const video = screen.queryByLabelText('Rewarded video') as HTMLVideoElement | null
  if (video) Object.defineProperties(video, {
    paused: { configurable: true, value: false, writable: true },
    seeking: { configurable: true, value: false },
    duration: { configurable: true, value: 10 },
  })
  return { ...view, props, video, rewarded }
}

async function watch(video: HTMLVideoElement) {
  for (const position of [2, 4, 6, 8, 10]) {
    video.currentTime = position
    fireEvent.timeUpdate(video)
    await waitFor(() => expect(fixture.progress).toHaveBeenLastCalledWith(12, position))
  }
}

describe('managed rewarded video', () => {
  it('plays the configured video and waits for the actual refill before claiming success', async () => {
    let resolveReward!: () => void
    const rewarded = vi.fn(() => new Promise<void>((resolve) => { resolveReward = resolve }))
    const { video } = setup(rewarded)
    expect(video).toHaveAttribute('src', '/storage/media/reward.mp4')
    expect(fixture.complete).not.toHaveBeenCalled()
    await watch(video!)
    fireEvent.ended(video!)
    await waitFor(() => expect(rewarded).toHaveBeenCalledWith(12))
    expect(screen.queryByText('All five hearts are refilled!')).not.toBeInTheDocument()
    await act(async () => resolveReward())
    expect(screen.getByText('All five hearts are refilled!')).toBeInTheDocument()
    expect(fixture.complete).toHaveBeenCalledTimes(1)
  })

  it('does not reward a seek or a playback failure', () => {
    const { video, rewarded } = setup()
    video!.currentTime = 10
    fireEvent.seeking(video!)
    fireEvent.ended(video!)
    expect(screen.getByText(/couldn’t verify playback/)).toBeInTheDocument()
    expect(fixture.complete).not.toHaveBeenCalled()
    expect(rewarded).not.toHaveBeenCalled()
  })

  it('shows unavailable without a countdown or claim control when inventory is empty', () => {
    fixture.eligible = false
    const { rewarded } = setup()
    expect(screen.getByText(/No video is available/)).toBeInTheDocument()
    expect(screen.queryByLabelText('Rewarded video')).not.toBeInTheDocument()
    expect(fixture.complete).not.toHaveBeenCalled()
    expect(rewarded).not.toHaveBeenCalled()
  })

  it('does not credit paused playback and stops after a media error', () => {
    const { video, rewarded } = setup()
    Object.defineProperty(video, 'paused', { configurable: true, value: true })
    video!.currentTime = 2
    fireEvent.timeUpdate(video!)
    expect(fixture.progress).not.toHaveBeenCalled()
    fireEvent.error(video!)
    expect(screen.getByText(/couldn’t verify playback/)).toBeInTheDocument()
    expect(rewarded).not.toHaveBeenCalled()
  })

  it('does not complete after a rejected progress sample', async () => {
    fixture.progress.mockRejectedValue(new Error('rejected'))
    const { video, rewarded } = setup()
    video!.currentTime = 2
    fireEvent.timeUpdate(video!)
    await screen.findByText(/couldn’t verify playback/)
    expect(fixture.complete).not.toHaveBeenCalled()
    expect(rewarded).not.toHaveBeenCalled()
  })

  it('does not report success when the server refuses completion or refill fails', async () => {
    fixture.complete.mockResolvedValue({ shown: false })
    const { video, rewarded } = setup()
    await watch(video!)
    fireEvent.ended(video!)
    await screen.findByText(/couldn’t verify playback/)
    expect(rewarded).not.toHaveBeenCalled()
    expect(screen.queryByText('All five hearts are refilled!')).not.toBeInTheDocument()
  })

  it('reports a failed refill without claiming hearts were awarded', async () => {
    const { video } = setup(vi.fn().mockRejectedValue(new Error('refill failed')))
    await watch(video!)
    fireEvent.ended(video!)
    await screen.findByText(/couldn’t verify playback/)
    expect(screen.queryByText('All five hearts are refilled!')).not.toBeInTheDocument()
  })

  it('rejects a configured duration that does not match the actual video', () => {
    const { video, rewarded } = setup()
    Object.defineProperty(video, 'duration', { configurable: true, value: 60 })
    fireEvent.loadedMetadata(video!)
    expect(screen.getByText(/couldn’t verify playback/)).toBeInTheDocument()
    expect(rewarded).not.toHaveBeenCalled()
  })

  it('ignores late verification responses after the modal closes', async () => {
    let resolveCompletion!: (value: { shown: boolean }) => void
    fixture.complete.mockImplementation(() => new Promise((resolve) => { resolveCompletion = resolve }))
    const { video, props, rerender, rewarded } = setup()
    await watch(video!)
    fireEvent.ended(video!)
    await waitFor(() => expect(fixture.complete).toHaveBeenCalled())
    rerender(<AdModal {...props} open={false} />)
    await act(async () => resolveCompletion({ shown: true }))
    expect(rewarded).not.toHaveBeenCalled()
  })
})
