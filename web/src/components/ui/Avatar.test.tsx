import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Avatar, AVATAR_PRESETS, learnerAvatarPresetId } from './Avatar'

describe('Avatar', () => {
  it('renders initials when no picture is configured', () => {
    render(<Avatar name="Ada Obi" />)
    expect(screen.getByText('AO')).toBeInTheDocument()
  })

  it('renders each built-in avatar preset', () => {
    const { container } = render(
      <div>{AVATAR_PRESETS.map((preset) => <Avatar key={preset.id} name={preset.label} avatarId={preset.id} />)}</div>,
    )
    expect(container.querySelectorAll('img')).toHaveLength(AVATAR_PRESETS.length)
    expect(container.querySelector('img')).toHaveAttribute('src', '/Mahadam%20avatar%20images/BAT.png')
  })

  it('prefers an uploaded photo over a preset', () => {
    const { container } = render(<Avatar name="Kemi" avatarId={2} src="/storage/profile.jpg" />)
    expect(container.querySelector('img')).toHaveAttribute('src', '/storage/profile.jpg')
    expect(container.querySelectorAll('img')).toHaveLength(1)
  })

  it('assigns stable learner fallback presets across the available set', () => {
    expect(learnerAvatarPresetId(1)).toBe(1)
    expect(learnerAvatarPresetId(54)).toBe(54)
    expect(learnerAvatarPresetId(55)).toBe(1)
  })
})
