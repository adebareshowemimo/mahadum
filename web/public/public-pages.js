// Keep the public content pages readable without a browser application bundle.
try {
  const key = 'mahadum.attribution'
  if (!sessionStorage.getItem(key)) {
    const params = new URLSearchParams(window.location.search)
    const tags = Object.fromEntries(['utm_source', 'utm_medium', 'utm_campaign'].flatMap((name) => {
      const value = params.get(name)?.trim().slice(0, 255)
      return value ? [[name, value]] : []
    }))
    if (Object.keys(tags).length) sessionStorage.setItem(key, JSON.stringify(tags))
  }
} catch { /* Browser storage is optional. */ }
document.addEventListener('click', (event) => {
  const button = event.target.closest?.('[data-youtube]')
  if (!button || !/^[a-zA-Z0-9_-]{11}$/.test(button.dataset.youtube)) return
  const frame = document.createElement('iframe')
  frame.src = `https://www.youtube-nocookie.com/embed/${button.dataset.youtube}?autoplay=1`
  frame.title = button.getAttribute('aria-label')
  frame.allow = 'autoplay; encrypted-media; picture-in-picture'
  frame.allowFullscreen = true
  button.replaceWith(frame)
  frame.focus()
})
