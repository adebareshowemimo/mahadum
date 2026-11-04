import { describe, expect, it } from 'vitest'
import { visibleSections } from './navigation'

function titles(roles: Parameters<typeof visibleSections>[0]) {
  return visibleSections(roles).map((s) => s.title ?? '(home)')
}

describe('visibleSections', () => {
  it('shows only learning destinations while a child profile is active', () => {
    const paths = visibleSections(['parent'], ['billing.subscriptions.manage'], true).flatMap((section) => section.items.map((item) => item.to))
    expect(paths).toEqual(['/learn', '/tasks', '/learn/courses', '/achievements', '/leaderboard', '/competitions'])
  })
  it('shows adult consumer capabilities without family or teacher-only destinations', () => {
    const paths = visibleSections(['student'], ['referrals.view', 'payouts.view', 'billing.subscriptions.manage', 'billing.databundles.manage']).flatMap((section) => section.items.map((item) => item.to))
    expect(paths).toEqual(expect.arrayContaining(['/billing', '/referrals']))
    expect(paths).not.toContain('/billing/data')
    expect(paths).not.toContain('/family')
    expect(paths).not.toContain('/wallet')
    expect(paths).not.toContain('/earnings')
  })
  it('shows the Family section to parents but not School/Admin', () => {
    const t = titles(['parent'])
    expect(t).toContain('Family')
    expect(t).toContain('Learn') // parents drive a child profile
    expect(t).not.toContain('School')
    expect(t).not.toContain('Admin')
    expect(t).not.toContain('Content')
  })

  it('shows the Learn section to students only (no Family)', () => {
    const t = titles(['student'])
    expect(t).toContain('Learn')
    expect(t).not.toContain('Family')
  })

  it('shows the Admin section to super admins', () => {
    expect(titles(['super_admin'])).toContain('Admin')
  })

  it('keeps global content builders out of the school admin menu', () => {
    const paths = visibleSections(['school_admin']).flatMap((section) => section.items.map((item) => item.to))
    expect(paths).toContain('/school')
    expect(paths).not.toContain('/courses')
    expect(paths).not.toContain('/courses/stories')
    expect(paths).not.toContain('/courses/proverbs-trivia')
    expect(paths).not.toContain('/media')
  })

  it('always includes the unlabeled Home group and System', () => {
    const t = titles(['student'])
    expect(t).toContain('(home)')
    expect(t).toContain('System')
  })

  it('hides everything role-gated for an unknown/empty role set', () => {
    const t = titles([])
    expect(t).toEqual(['(home)', 'System'])
  })
})
