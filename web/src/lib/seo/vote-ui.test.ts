import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fireEvent, waitFor } from '@testing-library/dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const script = readFileSync(resolve('public/vote.js'), 'utf8')
const element = <T extends HTMLElement = HTMLElement>(id: string) => document.getElementById(id) as T
const choose = (language: string) => (document.querySelector(`[data-vote="${language}"]`) as HTMLButtonElement).click()

beforeEach(() => {
  document.body.innerHTML = `
    <input id="language-search"><select id="vote-sort"><option value="name">A to Z</option><option value="votes">Most votes</option></select>
    <button id="vote-clear" hidden>Clear</button><p id="vote-results"></p><div id="vote-empty" hidden></div><button id="vote-reset">Reset</button>
    <div id="vote-grid">${[['Tiv', 5], ['Edo', 2]].map(([name, count]) => `<article data-language="${name}" data-count="${count}" data-target="10000"><span class="vote-count">${count} of 10,000 votes</span><span class="vote-percent"></span><progress max="10000" value="${count}"></progress><button data-vote="${name}">Choose ${name}</button></article>`).join('')}</div>
    <input id="other-language"><button data-vote="Other">Suggest</button><span id="vote-total-count">7</span>
    <div id="vote-feedback" hidden><p id="vote-feedback-text"></p><a id="vote-feedback-link" hidden>Sign in</a></div>
    <dialog id="vote-dialog"><span id="vote-selected-name"></span><p id="vote-dialog-description"></p><p id="vote-dialog-message"></p><p id="vote-dialog-auth"></p><button id="vote-confirm">Confirm</button><button id="vote-dialog-close">Close</button><button id="vote-dialog-back">Back</button></dialog>`
  localStorage.clear()
  vi.stubGlobal('fetch', vi.fn())
  vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: true })))
  element('vote-feedback').scrollIntoView = vi.fn()
  Object.defineProperty(element('vote-dialog'), 'showModal', { value: () => element('vote-dialog').setAttribute('open', '') })
  Object.defineProperty(element('vote-dialog'), 'close', { value: () => element('vote-dialog').removeAttribute('open') })
  // Run the same standalone script served to the public page.
  new Function(script)()
})

afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals(); document.body.innerHTML = '' })

describe('public vote interactions', () => {
  it('filters, reports no matches and restores the full shortlist', () => {
    fireEvent.input(element('language-search'), { target: { value: 'TIV' } })
    expect(element('vote-results')).toHaveTextContent('1 language matches')
    expect(document.querySelector('[data-language="Edo"]')).not.toBeVisible()
    fireEvent.input(element('language-search'), { target: { value: 'Missing' } })
    expect(element('vote-empty')).toBeVisible()
    element('vote-reset').click()
    expect(element('vote-empty')).not.toBeVisible()
    expect(document.querySelector('[data-language="Edo"]')).toBeVisible()
  })

  it('sorts by verified votes without losing the current filter', () => {
    fireEvent.change(element('vote-sort'), { target: { value: 'votes' } })
    expect(document.querySelector('#vote-grid > article')?.getAttribute('data-language')).toBe('Tiv')
    fireEvent.input(element('language-search'), { target: { value: 'Edo' } })
    expect(document.querySelector('[data-language="Tiv"]')).not.toBeVisible()
  })

  it('asks guests to review their choice before signup and validates suggestions first', () => {
    choose('Other')
    expect(element('vote-dialog')).not.toHaveAttribute('open')
    fireEvent.input(element('other-language'), { target: { value: '  Esan  ' } })
    choose('Other')
    expect(element('vote-selected-name')).toHaveTextContent('Esan')
    expect(element('vote-confirm')).toHaveTextContent('Continue to sign up')
    expect(fetch).not.toHaveBeenCalled()
  })

  it('submits once after confirmation, updates totals and prevents another vote', async () => {
    localStorage.setItem('mahadum.token', 'test-token')
    let finish!: (value: Response) => void
    vi.mocked(fetch).mockReturnValue(new Promise<Response>(resolve => { finish = resolve }))
    choose('Tiv')
    expect(fetch).not.toHaveBeenCalled()
    element('vote-confirm').click()
    element('vote-confirm').click()
    expect(fetch).toHaveBeenCalledTimes(1)
    finish({ ok: true, json: async () => ({ message: 'Counted' }) } as Response)
    await waitFor(() => expect(element('vote-feedback-text')).toHaveTextContent('vote for Tiv has been counted'))
    expect(element('vote-total-count')).toHaveTextContent('8')
    expect(document.querySelector('[data-language="Tiv"] .vote-count')).toHaveTextContent('6 of 10,000')
    expect(document.querySelector('[data-vote="Edo"]')).toBeDisabled()
  })

  it('explains verification failures without incrementing the tally', async () => {
    localStorage.setItem('mahadum.token', 'test-token')
    vi.mocked(fetch).mockResolvedValue({ ok: false, status: 403, json: async () => ({}) } as Response)
    choose('Tiv'); element('vote-confirm').click()
    await waitFor(() => expect(element('vote-feedback-text')).toHaveTextContent('Verify your email'))
    expect(element('vote-feedback-link')).toBeVisible()
    expect(element('vote-total-count')).toHaveTextContent('7')
  })

  it('keeps the confirmed choice available for retry after a network error', async () => {
    localStorage.setItem('mahadum.token', 'test-token')
    vi.mocked(fetch).mockRejectedValue(new Error('offline'))
    choose('Tiv'); element('vote-confirm').click()
    await waitFor(() => expect(element('vote-dialog-message')).toHaveTextContent('Unable to connect'))
    expect(element('vote-dialog')).toHaveAttribute('open')
    expect(element('vote-confirm')).not.toBeDisabled()
    expect(element('vote-total-count')).toHaveTextContent('7')
  })

  it('explains an already-recorded vote without adding to the total', async () => {
    localStorage.setItem('mahadum.token', 'test-token')
    vi.mocked(fetch).mockResolvedValue({ ok: false, status: 409, json: async () => ({}) } as Response)
    choose('Edo'); element('vote-confirm').click()
    await waitFor(() => expect(element('vote-feedback-text')).toHaveTextContent('already been recorded'))
    expect(element('vote-total-count')).toHaveTextContent('7')
    expect(document.querySelector('[data-vote="Tiv"]')).toBeDisabled()
  })
})
