import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { SlideView } from './slides'
import type { PlayerService, VideoSlide } from './types'

const video: VideoSlide = {
  id: 'video-12', componentId: 12, kind: 'video', completed: false,
  title: 'Tone demonstration', lessonTitle: 'Greetings', src: '/video.mp4', poster: null,
  sourceType: 'upload', externalUrl: null, requireWatch: true, resumeAt: 0, alreadyCompleted: false,
}

function mount(slide = video, preview = false) {
  const invite = vi.fn().mockResolvedValue(undefined)
  const service = { isPreview: preview, inviteTonePractice: invite, trackVideo: vi.fn().mockResolvedValue(undefined) } as unknown as PlayerService
  const view = render(<SlideView slide={slide} service={service} isLast={false} onAdvance={vi.fn()}
    onGraded={vi.fn()} onHearts={vi.fn()} onPracticeMode={vi.fn()} />)
  return { ...view, invite }
}

describe('Invite to Practice after video', () => {
  it('appears when the video ends and sends the exact video component with a clear message', async () => {
    const { container, invite } = mount()
    expect(screen.queryByRole('button', { name: 'Invite to Practice' })).not.toBeInTheDocument()
    fireEvent.ended(container.querySelector('video')!)
    const user = userEvent.setup()
    await user.click(screen.getByRole('button', { name: 'Invite to Practice' }))
    expect(screen.getByText(/Invitation message:/)).toHaveTextContent('Greetings')
    expect(screen.getByText(/private link to the language video/)).toBeInTheDocument()
    await user.type(screen.getByLabelText('Recipient email'), 'parent@example.com')
    await user.click(screen.getByRole('button', { name: 'Send invitation' }))
    expect(await screen.findByRole('status')).toHaveTextContent('Invitation sent')
    expect(invite).toHaveBeenCalledWith(video, 'parent@example.com')
    expect(screen.getByRole('button', { name: 'Continue' })).toBeEnabled()
  })

  it('supports embedded videos without relying on unavailable end events', () => {
    mount({ ...video, sourceType: 'youtube', externalUrl: 'https://www.youtube.com/watch?v=abcdefghijk' })
    expect(screen.getByRole('button', { name: 'Invite to Practice' })).toBeInTheDocument()
  })

  it('does not send invitations from author previews', () => {
    mount({ ...video, alreadyCompleted: true }, true)
    expect(screen.queryByRole('button', { name: 'Invite to Practice' })).not.toBeInTheDocument()
  })
})
