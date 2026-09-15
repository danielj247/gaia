export const nodeColors: Record<string, string> = {
  Person: '#60a5fa',
  Organization: '#c084fc',
  Identifier: '#fbbf24',
  Address: '#34d399',
  Country: '#fb7185',
  Sanction: '#f87171',
  Dump: '#94a3b8',
  Vessel: '#22d3ee',
  Aircraft: '#a3e635',
  CryptoWallet: '#e879f9',
  Security: '#f59e0b',
  Other: '#cbd5e1',
}

export function colorForLabel(label: string): string {
  return nodeColors[label] ?? nodeColors.Other
}
