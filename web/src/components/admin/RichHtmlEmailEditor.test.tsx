import { fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { RichHtmlEmailEditor } from './RichHtmlEmailEditor'

describe('Email link modal', () => {
  afterEach(() => { vi.unstubAllGlobals(); window.getSelection()?.removeAllRanges() })
  it('restores the selected text when inserting a link and rejects unsafe URLs', () => {
    const execute = vi.fn()
    Object.defineProperty(document, 'execCommand', { value: execute, configurable: true })
    render(<RichHtmlEmailEditor value="Selected text" view="visual" onViewChange={vi.fn()} onChange={vi.fn()} />)
    const editor = screen.getByRole('textbox', { name: 'Rich HTML email body' })
    const range = document.createRange()
    range.selectNodeContents(editor)
    window.getSelection()?.addRange(range)
    fireEvent.click(screen.getByRole('button', { name: 'Add link' }))
    expect(screen.getByRole('dialog', { name: 'Add link' })).toBeInTheDocument()
    fireEvent.change(screen.getByLabelText('Link URL'), { target: { value: 'javascript:bad' } })
    fireEvent.click(screen.getByRole('button', { name: 'Insert link' }))
    expect(execute).not.toHaveBeenCalled()
    fireEvent.change(screen.getByLabelText('Link URL'), { target: { value: 'https://example.com' } })
    fireEvent.click(screen.getByRole('button', { name: 'Insert link' }))
    expect(execute).toHaveBeenCalledWith('createLink', false, 'https://example.com')
    expect(window.getSelection()?.toString()).toBe('Selected text')
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})
