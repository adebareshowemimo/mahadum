import type { MouseEvent } from 'react'
import { ConceptHeader } from './ConceptHeader'
import { ConceptFooter } from './ConceptFooter'

export type WebsiteRegion = 'header' | 'footer'

// These components also appear around server-rendered articles. Let anchors
// navigate the document there so React Router cannot leave the article stale.
export function DocumentWebsiteChrome({ region }: { region: WebsiteRegion }) {
  function navigateDocument(event: MouseEvent<HTMLDivElement>) {
    if (!(event.target instanceof Element)) return
    const link = event.target.closest('a[href]')
    if (link instanceof HTMLAnchorElement && link.origin === window.location.origin) event.stopPropagation()
  }

  return <div className="contents" onClickCapture={navigateDocument}>
    {region === 'header' ? <ConceptHeader /> : <ConceptFooter />}
  </div>
}
