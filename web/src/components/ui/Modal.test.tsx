import { fireEvent, render, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { useState } from 'react'
import { Button } from './Button'
import { Input } from './Input'
import { Modal } from './Modal'

describe('Modal keyboard and focus behavior', () => {
  it('moves focus inside, traps Tab, closes with Escape, and restores focus', async () => {
    const onClose = vi.fn()
    function TestDialog() {
      const [open, setOpen] = useState(false)
      function close() { onClose(); setOpen(false) }
      return (
        <>
          <button type="button" onClick={() => setOpen(true)}>Open dialog</button>
          <Modal open={open} title="Edit learner" onClose={close}>
            <Input label="Learner name" />
            <Button>Save</Button>
          </Modal>
        </>
      )
    }
    const { getByRole, getByLabelText } = render(
      <TestDialog />,
    )
    const opener = getByRole('button', { name: 'Open dialog' })
    opener.focus()
    fireEvent.click(opener)

    await waitFor(() => expect(getByRole('button', { name: 'Close' })).toHaveFocus())
    expect(document.body.style.overflow).toBe('hidden')

    getByRole('button', { name: 'Save' }).focus()
    fireEvent.keyDown(document, { key: 'Tab' })
    expect(getByRole('button', { name: 'Close' })).toHaveFocus()

    getByLabelText('Learner name').focus()
    fireEvent.keyDown(document, { key: 'Escape' })
    expect(onClose).toHaveBeenCalledOnce()
    await waitFor(() => expect(opener).toHaveFocus())
    expect(document.body.style.overflow).toBe('')
  })
})
