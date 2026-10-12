import { beforeEach, describe, expect, it } from 'vitest'
import { applyPublicMetadata } from './metadata'
import { captureAttribution, signupAttribution } from './discovery'
import { websiteCopy } from './copy'

beforeEach(() => { document.head.innerHTML = ''; sessionStorage.clear() })

describe('public metadata and attribution', () => {
  it('uses the server copy snapshot so hydration preserves editorial updates', () => {
    const data = document.createElement('script'); data.id = 'public-page-copy'; data.type = 'application/json'
    data.textContent = JSON.stringify({ [window.location.pathname]: { heading: 'Approved headline', intro: 'Approved introduction' } }); document.head.append(data)
    expect(websiteCopy('heading', 'Old headline')).toBe('Approved headline')
    expect(websiteCopy('intro', 'Old introduction')).toBe('Approved introduction')
  })
  it('updates metadata on public navigation and removes it on private routes', () => {
    applyPublicMetadata('/families')
    expect(document.title).toContain('Families')
    expect(document.head.querySelector('link[rel="canonical"]')).toHaveAttribute('href', 'https://mahadum360.com/families')
    applyPublicMetadata('/pricing')
    expect(document.title).toContain('Pricing')
    expect(document.head.querySelectorAll('link[rel="canonical"]')).toHaveLength(1)
    applyPublicMetadata('/home')
    expect(document.head.querySelector('meta[name="robots"]')).toHaveAttribute('content', 'noindex,follow')
    expect(document.head.querySelector('link[rel="canonical"]')).toBeNull()
    expect(document.head.querySelector('meta[property="og:url"]')).toBeNull()
    applyPublicMetadata('/about')
    expect(document.head.querySelector('script[type="application/ld+json"]')).not.toBeNull()
  })
  it('preserves edited server metadata when the browser app starts', () => {
    const data = document.createElement('script'); data.id = 'public-page-metadata'; data.type = 'application/json'
    data.textContent = JSON.stringify({ '/families': { title: 'Approved family title', description: 'Approved family description' } }); document.head.append(data)
    applyPublicMetadata('/families')
    expect(document.title).toBe('Approved family title')
    expect(document.head.querySelector('meta[name="description"]')).toHaveAttribute('content', 'Approved family description')
  })
  it('retains the arriving campaign through later navigation in the same visit', () => {
    captureAttribution('?utm_source=youtube&utm_medium=social&utm_campaign=hausa')
    captureAttribution('')
    captureAttribution('?utm_source=instagram')
    expect(signupAttribution()).toEqual({ utm_source: 'youtube', utm_medium: 'social', utm_campaign: 'hausa' })
  })
})
