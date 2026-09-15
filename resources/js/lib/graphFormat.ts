export type GraphProperty = {
  key: string
  label: string
  value: string
}

/** Mirrors GraphNodeLabel::isInspectOnlyHub(): these hubs are read-only, never expanded. */
const inspectOnlyHubLabels: readonly App.Enums.GraphNodeLabel[] = [
  'Country',
  'Dump',
  'Sanction',
]

/** Shown in the inspector header or as an action, so they never repeat as properties. */
const headerKeys = new Set(['caption', 'id', 'sourceId', 'sourceUrl'])

const propertyLabels: Record<string, string> = {
  aliases: 'Also known as',
  birthDate: 'Birth date',
  incorporationDate: 'Incorporated',
  kind: 'Identifier kind',
  name: 'Name in source',
  schema: 'FollowTheMoney schema',
}

const propertyOrder = [
  'schema',
  'kind',
  'value',
  'topics',
  'birthDate',
  'incorporationDate',
  'gender',
  'name',
  'aliases',
  'notes',
]

export function isExpandable(label: string): boolean {
  return !inspectOnlyHubLabels.includes(label as App.Enums.GraphNodeLabel)
}

export function formatEdgeType(type: string): string {
  const words = type.toLowerCase().replaceAll('_', ' ')

  return words.charAt(0).toUpperCase() + words.slice(1)
}

export function formatPropertyKey(key: string): string {
  if (key in propertyLabels) {
    return propertyLabels[key]
  }

  const words = key.replace(/([a-z0-9])([A-Z])/g, '$1 $2').toLowerCase()

  return words.charAt(0).toUpperCase() + words.slice(1)
}

export function formatPropertyValue(value: unknown): string {
  if (
    typeof value === 'string' ||
    typeof value === 'number' ||
    typeof value === 'boolean'
  ) {
    return String(value)
  }

  return JSON.stringify(value)
}

/** Splits the comma-joined multi-value properties the FollowTheMoney mapper writes. */
export function splitList(value: unknown): string[] {
  if (typeof value !== 'string') {
    return []
  }

  return value
    .split(',')
    .map((part) => part.trim())
    .filter((part) => part !== '')
}

export function graphProperties(
  properties: Record<string, unknown>,
): GraphProperty[] {
  return Object.keys(properties)
    .filter((key) => !headerKeys.has(key) && !isBlank(properties[key]))
    .sort(byPropertyOrder)
    .map((key) => ({
      key,
      label: formatPropertyKey(key),
      value: formatPropertyValue(properties[key]),
    }))
}

/** Canvas labels are drawn unclipped, so long captions have to give way. */
export function truncateEnd(value: string, max = 34): string {
  return value.length <= max ? value : `${value.slice(0, max - 1).trimEnd()}…`
}

/** Keeps opaque OpenSanctions and identifier keys readable inside narrow panels. */
export function truncateMiddle(value: string, max = 28): string {
  if (value.length <= max) {
    return value
  }

  const head = Math.ceil((max - 1) / 2)

  return `${value.slice(0, head)}…${value.slice(value.length - (max - 1 - head))}`
}

function isBlank(value: unknown): boolean {
  return value === null || value === undefined || value === ''
}

function byPropertyOrder(left: string, right: string): number {
  const leftIndex = propertyOrder.indexOf(left)
  const rightIndex = propertyOrder.indexOf(right)

  if (leftIndex === -1 && rightIndex === -1) {
    return left.localeCompare(right)
  }

  if (leftIndex === -1) {
    return 1
  }

  if (rightIndex === -1) {
    return -1
  }

  return leftIndex - rightIndex
}
