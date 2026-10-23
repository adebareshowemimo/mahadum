import { describe, expect, it } from 'vitest'
import appSource from './App.tsx?raw'

const pages = import.meta.glob(['./pages/**/*.tsx', '!./pages/**/*.test.tsx'])
const routes = [...appSource.matchAll(/lazy\(\(\) => import\('@\/([^']+)'\)\.then\(\(m\) => \(\{ default: m\.(\w+) \}\)\)\)/g)]

describe('all lazy page routes', () => {
  it('discovers the lazy page declarations', () => {
    expect(routes.length).toBeGreaterThan(80)
    expect(routes).toHaveLength([...appSource.matchAll(/\blazy\(/g)].length)
    expect(routes.some(([, , name]) => name === 'FamiliesPage')).toBe(true)
  })

  it.each(routes.map(([, path, name]) => [path, name]))('loads %s and its %s component', async (path, name) => {
    const load = pages[`./${path}.tsx`]
    expect(load).toBeDefined()
    const module = await load() as Record<string, unknown>
    expect(typeof module[name]).toBe('function')
  })
})
