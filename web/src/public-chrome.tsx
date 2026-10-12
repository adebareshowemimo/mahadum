import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { createPortal } from 'react-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter } from 'react-router-dom'
import { AuthProvider } from '@/lib/auth/AuthProvider'
import { ActiveProfileProvider } from '@/lib/profile/ActiveProfile'
import { ThemeProvider } from '@/lib/theme'
import { DocumentWebsiteChrome } from '@/components/landing/DocumentWebsiteChrome'
import './styles/app.css'

const header = document.getElementById('website-header')
const footer = document.getElementById('website-footer')
const client = new QueryClient({ defaultOptions: { queries: { staleTime: 30_000, retry: 1, refetchOnWindowFocus: false } } })

if (header && footer) {
  // The build supplies readable chrome before JavaScript loads. Mount both
  // regions in one auth tree so account changes update the footer as well.
  footer.replaceChildren()
  createRoot(header).render(
    <StrictMode><QueryClientProvider client={client}><ThemeProvider><BrowserRouter>
      <AuthProvider><ActiveProfileProvider>
        <DocumentWebsiteChrome region="header" />
        {createPortal(<DocumentWebsiteChrome region="footer" />, footer)}
      </ActiveProfileProvider></AuthProvider>
    </BrowserRouter></ThemeProvider></QueryClientProvider></StrictMode>,
  )
}
