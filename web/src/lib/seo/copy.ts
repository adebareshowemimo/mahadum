// The server includes the same published copy it inserts into the rendered
// HTML. Reading that snapshot avoids reverting editorial changes on hydration.
export function websiteCopy(field: 'heading' | 'intro', fallback: string): string {
  if (typeof document === 'undefined') return fallback
  try {
    const copy = JSON.parse(document.getElementById('public-page-copy')?.textContent ?? '{}')
    return copy[window.location.pathname]?.[field] || fallback
  } catch { return fallback }
}
