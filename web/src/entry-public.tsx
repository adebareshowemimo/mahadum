import { StrictMode } from 'react'
import { renderToPipeableStream } from 'react-dom/server'
import { PassThrough } from 'node:stream'
import { StaticRouter } from 'react-router-dom/server'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AuthProvider } from '@/lib/auth/AuthProvider'
import { ActiveProfileProvider } from '@/lib/profile/ActiveProfile'
import { ThemeProvider } from '@/lib/theme'
import { App } from '@/App'

export const paths = ['/', '/families', '/schools', '/institutions', '/about', '/pricing', '/contact', '/privacy', '/terms', '/child-safety', '/accessibility']

export async function render(path: string): Promise<string> {
  if (!paths.includes(path)) throw new Error(`No public renderer for ${path}`)
  const client = new QueryClient({ defaultOptions: { queries: { enabled: false, retry: false } } })
  try {
    return await new Promise((resolve, reject) => {
      const output = new PassThrough()
      let html = ''
      output.on('data', (chunk) => { html += chunk.toString() })
      output.on('end', () => resolve(html))
      output.on('error', reject)
      const stream = renderToPipeableStream(
        <StrictMode><QueryClientProvider client={client}><ThemeProvider>
          <StaticRouter location={path}><AuthProvider><ActiveProfileProvider><App /></ActiveProfileProvider></AuthProvider></StaticRouter>
        </ThemeProvider></QueryClientProvider></StrictMode>,
        { onAllReady() { stream.pipe(output) }, onError: reject },
      )
    })
  } finally { client.clear() }
}
