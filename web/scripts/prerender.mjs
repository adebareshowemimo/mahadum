import { build } from 'vite'
import { mkdir, readFile, writeFile, rm } from 'node:fs/promises'
import { resolve, sep } from 'node:path'
import { pathToFileURL } from 'node:url'

// Reuse the actual public components: crawler HTML and the interactive page
// are built from the same source, rather than a separate marketing transcript.
const output = resolve('.public-render')
if (!output.startsWith(resolve(process.cwd()) + sep)) throw new Error('Renderer output must stay in the build workspace.')
try {
  await build({ build: { ssr: 'src/entry-public.tsx', outDir: output, copyPublicDir: false, rollupOptions: { output: { manualChunks: undefined } } } })
  const { render, renderChrome, paths } = await import(pathToFileURL(resolve(output, 'entry-public.js')))
  const shell = await readFile('dist/index.html', 'utf8')
  await mkdir('dist/prerender', { recursive: true })
  const routesSource = await readFile('src/App.tsx', 'utf8')
  const navSource = await readFile('src/lib/nav/navigation.ts', 'utf8')
  const routes = [...routesSource.matchAll(/<Route path="([^"*]+)"/g)].map((m) => m[1])
  routes.push(...[...navSource.matchAll(/to: '(\/[^']+)'/g)].map((m) => m[1]))
  await writeFile('dist/prerender/spa-routes.json', JSON.stringify([...new Set(routes)]))
  for (const path of paths) {
    const body = await render(path)
    if (!body.includes('<h1')) throw new Error(`Missing H1 for ${path}`)
    await writeFile(`dist/prerender/${path === '/' ? 'home' : path.slice(1)}.html`, shell.replace('<div id="root"></div>', `<div id="root">${body}</div>`))
  }
  const manifest = JSON.parse(await readFile('dist/prerender/manifest.json', 'utf8'))
  const entry = manifest['src/public-chrome.tsx']
  if (!entry?.file) throw new Error('Missing shared website header/footer entry.')
  const css = new Set()
  const visited = new Set()
  function collectStyles(chunk) {
    if (visited.has(chunk)) return
    visited.add(chunk)
    for (const file of chunk.css ?? []) css.add('/' + file)
    for (const name of chunk.imports ?? []) collectStyles(manifest[name])
  }
  collectStyles(entry)
  await writeFile('dist/prerender/chrome.json', JSON.stringify({
    header: await renderChrome('header'), footer: await renderChrome('footer'),
    styles: [...css], script: '/' + entry.file,
  }))
  console.log(`Rendered ${paths.length} public pages without JavaScript.`)
} finally {
  await rm(output, { recursive: true, force: true })
}
