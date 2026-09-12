<script setup lang="ts">
import Graph from 'graphology'
import Sigma from 'sigma'

type GraphNode = {
  id: string
  label: string
  caption: string
  properties: Record<string, unknown>
}

type GraphEdge = {
  id: string
  type: string
  source: string
  target: string
  properties: Record<string, unknown>
}

const props = defineProps<{
  nodes: GraphNode[]
  edges: GraphEdge[]
}>()

const emit = defineEmits<{
  select: [node: GraphNode]
}>()

const container = ref<HTMLDivElement | null>(null)
let sigma: Sigma | null = null

const colors: Record<string, string> = {
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
  Other: '#cbd5e1',
}

function paint(nextNodes: GraphNode[], nextEdges: GraphEdge[]): void {
  const next = new Graph({ multi: true, type: 'directed' })

  nextNodes.forEach((node, index) => {
    const angle = (2 * Math.PI * index) / Math.max(nextNodes.length, 1)
    next.addNode(node.id, {
      x: Math.cos(angle) * 120,
      y: Math.sin(angle) * 120,
      size: node.label === 'Person' || node.label === 'Organization' ? 10 : 6,
      label: node.caption,
      color: colors[node.label] ?? colors.Other,
      caption: node.caption,
      nodeLabel: node.label,
      properties: node.properties,
    })
  })

  nextEdges.forEach((edge) => {
    if (!next.hasNode(edge.source) || !next.hasNode(edge.target)) {
      return
    }

    next.addEdge(edge.source, edge.target, {
      id: edge.id,
      type: edge.type,
      label: edge.type,
      size: 1,
      color: '#64748b',
    })
  })

  if (sigma) {
    sigma.kill()
    sigma = null
  }

  if (!container.value) {
    return
  }

  sigma = new Sigma(next, container.value, {
    renderLabels: true,
    labelRenderedSizeThreshold: 4,
    defaultNodeColor: colors.Other,
    defaultEdgeColor: '#64748b',
    labelColor: { color: '#e2e8f0' },
  })

  sigma.on('clickNode', ({ node }) => {
    const attributes = next.getNodeAttributes(node)
    emit('select', {
      id: node,
      label: String(attributes.nodeLabel ?? 'Other'),
      caption: String(attributes.caption ?? node),
      properties: (attributes.properties ?? {}) as Record<string, unknown>,
    })
  })
}

watch(
  () => [props.nodes, props.edges] as const,
  ([nodes, edges]) => {
    paint(nodes, edges)
  },
  { deep: true },
)

onMounted(() => {
  paint(props.nodes, props.edges)
})

onBeforeUnmount(() => {
  sigma?.kill()
  sigma = null
})
</script>

<template>
  <div
    ref="container"
    class="h-[32rem] w-full overflow-hidden rounded-xl border border-sidebar-border bg-zinc-950"
    role="img"
    aria-label="Neighborhood graph canvas. Use the neighbor list for keyboard access."
  />
</template>
