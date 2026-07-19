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
  build: {
    rollupOptions: {
      output: {
        // Split the rarely-changing framework/data-layer deps into their own
        // chunk so app-code deploys don't bust the vendor cache entry.
        manualChunks: {
          'vendor-react': ['react', 'react-dom', 'react-router-dom'],
          'vendor-data': ['@tanstack/react-query', 'axios'],
        },
      },
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
