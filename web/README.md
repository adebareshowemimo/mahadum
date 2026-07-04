# Mahadum.360 — Web (standalone React SPA)

Vite + React + TypeScript + React Router + TanStack Query + Tailwind v4, built on
the shared design tokens in [`tokens/`](tokens/README.md).

## Run
```bash
cd web
npm install
npm run dev        # http://localhost:5173  (proxies /api + /sanctum → :8000)
```
Start the Laravel API alongside it: `php artisan serve` (from the project root).

## Scripts
| Script | Does |
|---|---|
| `npm run dev` | Vite dev server with HMR |
| `npm run build` | typecheck (`tsc --noEmit`) + production build |
| `npm run typecheck` | types only |
| `npm run preview` | serve the production build |

## Layout
```
web/
  tokens/                 design tokens (css / ts / json) — source of truth
  src/
    components/ui/         primitives (Button, Input, Card, Badge, …)
    lib/                   cn(), theme provider
    pages/                 ComponentsPage (the live component gallery)
    styles/app.css         @import tailwindcss + tokens + brand fonts
    App.tsx                routes
    main.tsx              providers (Query, Theme, Router)
```

## What's here now (foundation)
- **Design tokens** wired into Tailwind v4 (`bg-primary`, `text-muted`, `rounded-xl`, `font-display`, dark mode via `.dark`).
- **Component primitives**: Button, Input, Badge, Card, Avatar, Switch, Alert, Progress, Spinner, Skeleton.
- **Component gallery** at `/components` (default route) with a light/dark toggle.

## Next
Typed API client (axios + Sanctum), auth context + login, app shell/layout, then feature screens.
