# Mahadum.360 — Design Tokens

The single source of truth for colour, type, space, radius, shadow and motion,
shared by both front-ends (`learner-web`, `console-web`).

## Files
| File | Role |
|---|---|
| `tokens.json` | **Canonical source** (DTCG-style, `{color.blue.500}` aliases). Drives Figma/Style-Dictionary later. |
| `tokens.css`  | **Runtime consumable** — CSS custom properties (`:root` + `.dark`) bridged into Tailwind v4 via `@theme inline`. Import this in the app. |
| `tokens.ts`   | Typed export for JS/TS (charts, canvas, inline styles). Mirrors the CSS. |

## Brand
- **Blue** `#3366ff` — primary (trust, learning, CTAs).
- **Navy** `#0f1b3d` — depth, headers, dark-mode surfaces.
- **Orange** `#f76014` — accent (energy, rewards, gamification highlights).
- **Rainbow** — eight fixed hues for language tags, badges and leaderboards.
- Type: **Fredoka** (display/headings, rounded & playful) + **Nunito** (body, warm & readable).

All foreground/background semantic pairs target **WCAG 2.1 AA**.

## Usage (Tailwind v4)
```css
/* app entry css */
@import "tailwindcss";
@import "../tokens/tokens.css";
```
Then use utilities backed by tokens:
```tsx
<button className="bg-primary text-primary-fg rounded-xl shadow-brand font-display">
  Start lesson
</button>
<span className="text-muted">12 lessons left</span>
<div className="bg-surface border border-border rounded-2xl shadow-md">…</div>
```

### Semantic vs primitive
Reach for **semantic** tokens (`background`, `surface`, `foreground`, `muted`, `border`,
`primary`, `accent`, `success|warning|danger|info`, `ring`) in UI — they flip
automatically in dark mode. Use **primitive** scales (`blue-500`, `navy-900`,
`rainbow-teal`) only for illustration/branding where a fixed colour is intended.

## Dark mode
Add `class="dark"` to `<html>`. Every semantic token has a dark override in
`tokens.css`; primitives never change.

## Raw values in JS
```ts
import { palette, rainbowScale, semantic } from "../tokens/tokens";
chart.colors = rainbowScale;          // language series
ctx.fillStyle = semantic.light.primary;
```

## Changing a token
Edit `tokens.json` first (source of truth), then mirror the change into
`tokens.css` (and `tokens.ts` if used in JS). Keep the three in sync until a
Style-Dictionary build step is added.
