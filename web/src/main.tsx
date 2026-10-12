import { StrictMode } from 'react'
import { createRoot, hydrateRoot } from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter } from 'react-router-dom'
import { ThemeProvider } from '@/lib/theme'
import { AuthProvider } from '@/lib/auth/AuthProvider'
import { ActiveProfileProvider } from '@/lib/profile/ActiveProfile'
import { App } from '@/App'
import './styles/app.css'
import { captureAttribution } from '@/lib/seo/discovery'
import { tokenStore } from '@/lib/api'

captureAttribution()

const queryClient = new QueryClient({
  defaultOptions: { queries: { staleTime: 30_000, retry: 1, refetchOnWindowFocus: false } },
})

const root = document.getElementById('root')!
const app = (
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <BrowserRouter>
          <AuthProvider>
            <ActiveProfileProvider>
              <App />
            </ActiveProfileProvider>
          </AuthProvider>
        </BrowserRouter>
      </ThemeProvider>
    </QueryClientProvider>
  </StrictMode>
)

// Keep rendered content visible while its interactive route chunk loads.
// Signed-in visitors use a client render because their account controls differ.
if (root.hasChildNodes() && !tokenStore.get()) hydrateRoot(root, app)
else createRoot(root).render(app)
