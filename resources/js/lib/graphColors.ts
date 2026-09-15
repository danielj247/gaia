import type { ResolvedAppearance } from '@/types'

/**
 * Mid-tone hues so the same swatch reads on the dark canvas, the light canvas,
 * and as an HTML dot in either theme.
 */
export const nodeColors: Record<string, string> = {
  Person: '#3b82f6',
  Organization: '#a855f7',
  Identifier: '#f59e0b',
  Address: '#10b981',
  Country: '#f43f5e',
  Sanction: '#ef4444',
  Dump: '#64748b',
  Vessel: '#06b6d4',
  Aircraft: '#84cc16',
  CryptoWallet: '#d946ef',
  Security: '#eab308',
  Other: '#94a3b8',
}

export function colorForLabel(label: string): string {
  return nodeColors[label] ?? nodeColors.Other
}

export type GraphSurface = {
  background: string
  grid: string
  label: string
  labelBackground: string
  labelBorder: string
  edgeLabel: string
  edge: string
  edgeStrong: string
  faded: string
}

export function surfaceForAppearance(
  appearance: ResolvedAppearance,
): GraphSurface {
  if (appearance === 'light') {
    return {
      background: '#f8fafc',
      grid: '#e2e8f0',
      label: '#1e2939',
      labelBackground: '#ffffff',
      labelBorder: '#cad5e2',
      edgeLabel: '#62748e',
      edge: '#cad5e2',
      edgeStrong: '#62748e',
      faded: '#e2e8f0',
    }
  }

  return {
    background: '#0a0c11',
    grid: '#1c2230',
    label: '#e2e8f0',
    labelBackground: '#161b26',
    labelBorder: '#2b3648',
    edgeLabel: '#8c9bad',
    edge: '#45556c',
    edgeStrong: '#90a1b9',
    faded: '#2b3648',
  }
}
