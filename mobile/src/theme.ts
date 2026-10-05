/**
 * ERO mobile design tokens — single source of truth for the app's visuals.
 *
 * Palette mirrors the web Tailwind theme (web/src/styles/index.css) so both
 * surfaces share one visual language. Derived from the ERO redesign mockups:
 * a brighter blue primary (#2563eb), deep-navy surfaces, and accent hues for
 * the subject tiles / stat cards. Keep the two in sync when either changes.
 *
 * Mobile previously hardcoded hex values in every screen's StyleSheet; new and
 * restyled screens should import from here instead.
 */

export const colors = {
  // Primary — mockup blue
  primary: '#2563eb',
  primaryBright: '#3b82f6',
  primaryDark: '#1d4ed8',
  primaryTint: 'rgba(37, 99, 235, 0.15)',

  // Deep-navy surfaces
  bg: '#0a0e1a',
  bgCard: '#0f1830',
  bgElevated: '#16213a',
  border: '#243049',

  // Text
  textPrimary: '#f8fafc',
  textSecondary: '#94a3b8',
  textMuted: '#64748b',

  // Semantic
  success: '#22c55e',
  danger: '#ef4444',
  warning: '#f59e0b',

  // Accents — subject tiles / stat cards
  teal: '#14b8a6',
  purple: '#8b5cf6',
  amber: '#f59e0b',
  pink: '#ec4899',
  green: '#22c55e',
  cyan: '#06b6d4',
} as const;

export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 20,
  xxl: 24,
} as const;

export const radius = {
  sm: 8,
  md: 12,
  lg: 16,
  xl: 20,
  pill: 9999,
} as const;

export const typography = {
  h1: { fontSize: 28, fontWeight: '700' as const },
  h2: { fontSize: 22, fontWeight: '700' as const },
  h3: { fontSize: 18, fontWeight: '600' as const },
  body: { fontSize: 15, fontWeight: '400' as const },
  label: { fontSize: 13, fontWeight: '500' as const },
  caption: { fontSize: 12, fontWeight: '400' as const },
} as const;

export const theme = { colors, spacing, radius, typography } as const;
export default theme;
