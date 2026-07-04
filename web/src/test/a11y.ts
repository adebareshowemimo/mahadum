import axe from 'axe-core'
import { expect } from 'vitest'

/**
 * Assert a rendered subtree has no WCAG 2.1 A/AA violations axe can detect in
 * jsdom. Color-contrast (1.4.3) needs real layout, so it's excluded here and
 * belongs in a browser-based audit.
 */
export async function expectNoA11yViolations(container: HTMLElement): Promise<void> {
  const results = await axe.run(container, {
    runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
    rules: { 'color-contrast': { enabled: false } },
  })

  if (results.violations.length > 0) {
    const summary = results.violations
      .map((v) => `• [${v.id}] ${v.help} — ${v.nodes.length} node(s)\n    ${v.nodes[0]?.html ?? ''}`)
      .join('\n')
    throw new Error(`axe found ${results.violations.length} violation(s):\n${summary}`)
  }
  expect(results.violations).toHaveLength(0)
}
