import { fireEvent, render, screen } from '@testing-library/react'
import { expect, it } from 'vitest'
import { StoryContent } from './StoryContent'

const pages = [
  { id: 1, position: 1, title: 'First chapter', body: 'First chapter text' },
  { id: 2, position: 2, title: 'Second chapter', body: 'Second chapter text' },
]

it('shows one story slide at a time and reaches the preview end', () => {
  render(<StoryContent format="reading" pages={pages} endContent={<p>Unlock this story</p>} />)

  expect(screen.getByText('First chapter text')).toBeInTheDocument()
  expect(screen.queryByText('Second chapter text')).not.toBeInTheDocument()
  expect(screen.queryByText('Unlock this story')).not.toBeInTheDocument()

  fireEvent.click(screen.getByRole('button', { name: 'Next →' }))
  expect(screen.getByText('Second chapter text')).toBeInTheDocument()
  expect(screen.queryByText('First chapter text')).not.toBeInTheDocument()

  fireEvent.click(screen.getByRole('button', { name: 'Finish preview' }))
  expect(screen.getByText('Unlock this story')).toBeInTheDocument()
  expect(screen.queryByText('Second chapter text')).not.toBeInTheDocument()

  fireEvent.click(screen.getByRole('button', { name: '← Previous' }))
  expect(screen.getByText('Second chapter text')).toBeInTheDocument()

  fireEvent.keyDown(screen.getByRole('region', { name: 'Story slides' }), { key: 'ArrowLeft' })
  expect(screen.getByText('First chapter text')).toBeInTheDocument()
})
