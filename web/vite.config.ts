import { fileURLToPath, URL } from 'node:url'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'

// Standalone SPA. Talks to the Laravel API at VITE_API_BASE_URL; in dev we proxy
// /api and /sanctum so cookies are first-party (same origin) for Sanctum auth.
export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      '@tokens': fileURLToPath(new URL('./tokens', import.meta.url)),
    },
  },
  server: {
    // Honor PORT when set (e.g. preview tooling), else default to 5173.
    port: Number(process.env.PORT) || 5173,
    proxy: {
      '/api': { target: 'http://localhost:8000', changeOrigin: true },
      '/sanctum': { target: 'http://localhost:8000', changeOrigin: true },
    },
  },
})
