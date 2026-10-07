import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { SlideView } from './slides'
import type { PlayerService, VideoSlide } from './types'

const slide: VideoSlide = { id: 'v1', componentId: 1, kind: 'video', completed: false, title: 'Watch',
  src: '/video.mp4', poster: null, sourceType: 'upload', externalUrl: null, requireWatch: true, resumeAt: 0, alreadyCompleted: false }

function mount(extra: Partial<VideoSlide> = {}) {
  const trackVideo = vi.fn().mockResolvedValue(undefined)
  const completeStep = vi.fn().mockResolvedValue(undefined)
  const advance = vi.fn()
  const service = { trackVideo, completeStep, isPreview: true } as unknown as PlayerService
  const view = render(<SlideView slide={{ ...slide, ...extra }} service={service} isLast={false} onAdvance={advance}
    onGraded={vi.fn()} onHearts={vi.fn()} onPracticeMode={vi.fn()} />)
  const video = view.container.querySelector('video')!
  Object.defineProperty(video, 'duration', { configurable: true, value: 10 })
  fireEvent.loadedMetadata(video)
  return { ...view, video, trackVideo, completeStep, advance }
}

function play(video: HTMLVideoElement, start = 0, end = 10) {
  video.currentTime = start
  fireEvent.play(video)
  for (let t = start + 0.5; t <= end; t += 0.5) { video.currentTime = t; fireEvent.timeUpdate(video) }
  fireEvent.ended(video)
}

describe('required video coverage', () => {
  it('blocks seeking to the end and paused playhead changes', async () => {
    const { video } = mount()
    fireEvent.play(video)
    fireEvent.seeking(video)
    video.currentTime = 10
    fireEvent.seeked(video)
    fireEvent.ended(video)
    await act(async () => {})
    expect(screen.getByRole('button', { name: 'Continue' })).toBeDisabled()
  })

  it('saves full coverage before enabling Continue and waits before advancing', async () => {
    const { video, trackVideo, completeStep, advance } = mount()
    play(video)
    await waitFor(() => expect(screen.getByRole('button', { name: 'Continue' })).toBeEnabled())
    expect(trackVideo).toHaveBeenLastCalledWith(expect.anything(), expect.objectContaining({ completed: true, watchedRanges: [[0, 10]] }))
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }))
    await waitFor(() => expect(advance).toHaveBeenCalledOnce())
    expect(completeStep).toHaveBeenCalledOnce()
  })

  it('resumes persisted coverage while keeping skipped sections locked', async () => {
    const { video } = mount({ resumeAt: 5, watchedRanges: [[0, 5]] })
    fireEvent.seeked(video)
    play(video, 5)
    await waitFor(() => expect(screen.getByRole('button', { name: 'Continue' })).toBeEnabled())
  })

  it('repeating the same half does not cover the remaining half', async () => {
    const { video } = mount()
    play(video, 0, 5)
    fireEvent.seeking(video)
    video.currentTime = 0
    fireEvent.seeked(video)
    play(video, 0, 5)
    await act(async () => {})
    expect(screen.getByRole('button', { name: 'Continue' })).toBeDisabled()
  })

  it('offers only actual quality files and retains the playhead on switching', () => {
    const { video } = mount({ renditions: [{ quality: '240p', src: '/240.mp4' }, { quality: '360p', src: '/360.mp4' }] })
    video.currentTime = 4
    fireEvent.change(screen.getByLabelText('Video quality'), { target: { value: '240p' } })
    expect(video).toHaveAttribute('src', '/240.mp4')
    fireEvent.loadedMetadata(video)
    expect(video.currentTime).toBe(4)
    expect(screen.queryByRole('option', { name: '720p' })).not.toBeInTheDocument()
  })

  it('keeps required videos locked when the source fails', () => {
    const { video } = mount()
    fireEvent.error(video)
    expect(screen.getByRole('button', { name: 'Continue' })).toBeDisabled()
  })

  it('retries saving completed coverage after a connection failure', async () => {
    const { video, trackVideo, advance } = mount()
    trackVideo.mockImplementation((_slide, beat) => beat.completed ? Promise.reject(new Error('offline')) : Promise.resolve())
    play(video)
    await screen.findByRole('alert')
    expect(screen.getByRole('button', { name: 'Continue' })).toBeEnabled()
    trackVideo.mockResolvedValue(undefined)
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }))
    await waitFor(() => expect(advance).toHaveBeenCalledOnce())
  })

  it('does not count paused timeupdate events as playback', async () => {
    const { video } = mount()
    for (let time = 0.5; time <= 10; time += 0.5) { video.currentTime = time; fireEvent.timeUpdate(video) }
    fireEvent.ended(video)
    await act(async () => {})
    expect(screen.getByRole('button', { name: 'Continue' })).toBeDisabled()
  })
})
