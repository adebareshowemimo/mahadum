/**
 * Mahadum.360 design tokens — typed export. The CSS in `tokens.css` is the
 * runtime source of truth; these mirror it for JS use (charts, canvas, gauges).
 *
 * Brand meaning: Heritage Green (primary/correct), African Gold (reward/coin/
 * premium — always with charcoal text), Deep Navy (premium/dark), Ivory (light
 * base), Charcoal (text). Blue is demoted to an AI / learning-support role.
 */

export const palette = {
  heritage: {
    50: '#ebf7f0', 100: '#cdebd9', 200: '#9fd8b6', 300: '#67bd8d', 400: '#379e69',
    500: '#1a7d49', 600: '#14653b', 700: '#115231', 800: '#114228', 900: '#0e3622', 950: '#061d12',
  },
  gold: {
    50: '#fdf8e7', 100: '#faedbf', 200: '#f6da83', 300: '#f1c544', 400: '#ecb01d',
    500: '#d9970f', 600: '#b6740b', 700: '#91530d', 800: '#784213', 900: '#663715', 950: '#3b1d07',
  },
  navy: {
    50: '#eef1f7', 100: '#d6dded', 200: '#b0bedb', 300: '#8195c1', 400: '#566ca3',
    500: '#3c4f86', 600: '#2e3d6b', 700: '#253158', 800: '#1a2440', 900: '#111a33', 950: '#0a1124',
  },
  ivory: { 50: '#fdfbf6', 100: '#f8f2e6', 200: '#efe6d3', 300: '#e2d4ba', 400: '#cbb896' },
  charcoal: {
    50: '#f6f4f1', 100: '#e9e5df', 200: '#d6cfc5', 300: '#b3a99b', 400: '#8c8175',
    500: '#6e655b', 600: '#564e46', 700: '#433d37', 800: '#2f2b27', 900: '#221f1b', 950: '#14120f',
  },
  chore: { 50: '#eef4ff', 100: '#d9e6ff', 200: '#bcd2fb', 300: '#8eb2f5', 400: '#5b8aec', 500: '#2f6fe0', 600: '#2156c2', 700: '#1c459b', 800: '#1b3c7e', 900: '#1b3568' },
  ai:    { 50: '#eef0ff', 100: '#e0e2ff', 200: '#c6c8ff', 300: '#a3a4fc', 400: '#837ff7', 500: '#6359ee', 600: '#5340d4', 700: '#4632ab', 800: '#3a2c89', 900: '#322a6e' },
  clay:  { 50: '#fdf3ef', 100: '#fbe2d8', 200: '#f6c4ae', 300: '#ee9c7c', 400: '#e57650', 500: '#d65a35', 600: '#bb4324', 700: '#9b3420' },
  leaf:  { 50: '#edf9ef', 100: '#d1f0d8', 200: '#a6e0b3', 300: '#6fcb87', 400: '#43b063', 500: '#2f9b50', 600: '#237e40', 700: '#1d6535' },
  red:   { 50: '#fdf2f2', 100: '#fbe1e1', 200: '#f6c4c5', 300: '#ee9a9c', 400: '#e56164', 500: '#dc3b40', 600: '#c12529', 700: '#a01e22' },
  rainbow: {
    red: '#e5484d', orange: '#e8833a', yellow: '#e0a91b', green: '#2f9b50',
    teal: '#149e8e', blue: '#2f6fe0', purple: '#7c5cd6', pink: '#d65a8f',
  },
} as const

/** Ordered rainbow for language tags / chart series. */
export const rainbowScale = [
  palette.rainbow.green, palette.rainbow.teal, palette.rainbow.blue, palette.rainbow.yellow,
  palette.rainbow.orange, palette.rainbow.red, palette.rainbow.pink, palette.rainbow.purple,
] as const

/** Brand-meaning aliases (the names from the brief). */
export const brand = {
  heritage: palette.heritage[500],
  gold: palette.gold[500],
  navy: palette.navy[900],
  ivory: palette.ivory[50],
  charcoal: palette.charcoal[900],
  choreBlue: palette.chore[500],
  clay: palette.clay[500],
  leaf: palette.leaf[500],
  ai: palette.ai[500],
  error: palette.red[500],
} as const

export const semantic = {
  light: {
    background: palette.ivory[50], surface: '#ffffff', surfaceMuted: palette.ivory[100],
    foreground: palette.charcoal[900], muted: palette.charcoal[500], border: palette.ivory[300],
    primary: palette.heritage[500], accent: palette.gold[500], accentText: palette.charcoal[900],
    success: palette.leaf[500], warning: palette.gold[500], danger: palette.red[500], info: palette.chore[500],
    correct: palette.heritage[500], wrong: palette.red[500], coin: palette.gold[500],
    grace: palette.gold[500], chorePending: palette.chore[500], speakingScore: palette.ai[500],
  },
  dark: {
    background: palette.navy[950], surface: palette.navy[900], surfaceMuted: palette.navy[800],
    foreground: palette.ivory[100], muted: palette.navy[300], border: '#26345c',
    primary: palette.heritage[400], accent: palette.gold[400], accentText: palette.charcoal[950],
    success: palette.leaf[500], warning: palette.gold[400], danger: palette.red[500], info: palette.chore[400],
    correct: palette.heritage[400], wrong: palette.red[500], coin: palette.gold[400],
    grace: palette.gold[400], chorePending: palette.chore[400], speakingScore: palette.ai[400],
  },
} as const

export const typography = {
  fontDisplay: "'Fredoka', ui-rounded, system-ui, sans-serif",
  fontSans: "'Nunito', 'Inter', ui-sans-serif, system-ui, sans-serif",
  fontMono: "'JetBrains Mono', ui-monospace, monospace",
  size: { xs: '0.75rem', sm: '0.875rem', base: '1rem', lg: '1.125rem', xl: '1.25rem', '2xl': '1.5rem', '3xl': '1.875rem', '4xl': '2.25rem', '5xl': '3rem' },
  weight: { normal: 400, medium: 500, semibold: 600, bold: 700, extrabold: 800 },
} as const

export const radius = { xs: '0.25rem', sm: '0.375rem', md: '0.625rem', lg: '0.875rem', xl: '1.25rem', '2xl': '1.75rem', full: '9999px' } as const

export const shadow = {
  xs: '0 1px 2px 0 rgb(34 31 27 / 0.05)',
  sm: '0 1px 3px 0 rgb(34 31 27 / 0.08), 0 1px 2px -1px rgb(34 31 27 / 0.08)',
  md: '0 4px 12px -2px rgb(34 31 27 / 0.10), 0 2px 6px -2px rgb(34 31 27 / 0.06)',
  lg: '0 12px 28px -6px rgb(34 31 27 / 0.14), 0 6px 12px -6px rgb(34 31 27 / 0.08)',
  xl: '0 24px 48px -12px rgb(34 31 27 / 0.22)',
  gold: '0 8px 24px -6px rgb(217 151 15 / 0.40)',
  heritage: '0 8px 24px -6px rgb(26 125 73 / 0.35)',
} as const

export const motion = {
  duration: { fast: '150ms', base: '200ms', slow: '300ms' },
  ease: { out: 'cubic-bezier(0.16, 1, 0.3, 1)', spring: 'cubic-bezier(0.34, 1.56, 0.64, 1)' },
} as const

export const breakpoints = { sm: 640, md: 768, lg: 1024, xl: 1280, '2xl': 1536 } as const

export const tokens = { palette, rainbowScale, brand, semantic, typography, radius, shadow, motion, breakpoints } as const
export default tokens
