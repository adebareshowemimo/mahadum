import { describe, expect, it } from 'vitest'
import { visibleSections } from './navigation'

function titles(roles: Parameters<typeof visibleSections>[0]) {
  return visibleSections(roles).map((s) => s.title ?? '(home)')
}

describe('visibleSections', () => {
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
