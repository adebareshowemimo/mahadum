import pages from './pages.json'

export function applyPublicMetadata(path: string): void {
  let serverPages: Record<string, { title: string; description: string }> = {}
  try { serverPages = JSON.parse(document.getElementById('public-page-metadata')?.textContent ?? '{}') } catch { /* Use build metadata if a response is incomplete. */ }
  const meta = serverPages[path] ?? (pages as Record<string, { title: string; description: string }>)[path]
  const isPublic = !!meta
  function tag(selector: string, attributes: Record<string, string>) {
    let el = document.head.querySelector(selector)
    if (!el) { el = document.createElement(selector.startsWith('link') ? 'link' : 'meta'); document.head.append(el) }
    for (const [key, value] of Object.entries(attributes)) el.setAttribute(key, value)
  }
  tag('meta[name="robots"]', { name: 'robots', content: isPublic ? 'index,follow' : 'noindex,follow' })
  if (!meta) {
    document.head.querySelector('link[rel="canonical"]')?.remove()
    document.head.querySelectorAll('meta[property^="og:"], meta[name^="twitter:"], script[type="application/ld+json"]').forEach((el) => el.remove())
    tag('meta[name="description"]', { name: 'description', content: 'Mahadum360 Nigerian language learning.' })
    return
  }
  document.title = meta.title
  document.head.querySelectorAll('script[type="application/ld+json"]').forEach((el) => el.remove())
  const schema = document.createElement('script')
  schema.type = 'application/ld+json'
  schema.textContent = JSON.stringify([
    { '@context': 'https://schema.org', '@type': 'Organization', '@id': 'https://mahadum360.com/#organization', name: 'Mahadum360', url: 'https://mahadum360.com/', logo: 'https://mahadum360.com/images/m360_logo_official.png', sameAs: ['https://www.youtube.com/@mahadum360', 'https://www.instagram.com/mahadum360/', 'https://www.facebook.com/Mahadum360/'], contactPoint: { '@type': 'ContactPoint', contactType: 'customer support', email: 'Partnerships@Mahadum360.com', telephone: '+2349028111111' } },
    { '@context': 'https://schema.org', '@type': 'WebSite', name: 'Mahadum360', url: 'https://mahadum360.com/' },
  ])
  document.head.append(schema)
  const url = `https://mahadum360.com${path}`
  tag('meta[name="description"]', { name: 'description', content: meta.description })
  tag('link[rel="canonical"]', { rel: 'canonical', href: url })
  for (const [key, value] of Object.entries({ title: meta.title, description: meta.description, url, image: 'https://mahadum360.com/images/og-cover.jpg', type: 'website' })) {
    tag(`meta[property="og:${key}"]`, { property: `og:${key}`, content: value })
  }
  for (const [key, value] of Object.entries({ card: 'summary_large_image', title: meta.title, description: meta.description, image: 'https://mahadum360.com/images/og-cover.jpg' })) {
    tag(`meta[name="twitter:${key}"]`, { name: `twitter:${key}`, content: value })
  }
}
