<script setup lang="ts">
import Graph from 'graphology'
import { random } from 'graphology-layout'
import forceAtlas2 from 'graphology-layout-forceatlas2'
import Sigma from 'sigma'
import { colorForLabel, nodeColors } from '@/lib/graphColors'

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
  selectedId?: string | null
}>()

const emit = defineEmits<{
  select: [node: GraphNode]
  error: [message: string]
}>()

const container = ref<HTMLDivElement | null>(null)
let graph: Graph | null = null
let sigma: Sigma | null = null
let observer: ResizeObserver | null = null

function graphInstance(): Graph {
  graph ??= new Graph({ multi: true, type: 'directed' })

  return graph
}

function teardown(): void {
  sigma?.kill()
  sigma = null
  graph = null
}

function ensureRenderer(host: HTMLDivElement, next: Graph): Sigma | null {
  if (sigma) {
    return sigma
  }

  try {
    sigma = new Sigma(next, host, {
      allowInvalidContainer: true,
      renderLabels: true,
      renderEdgeLabels: true,
      defaultEdgeType: 'arrow',
      labelRenderedSizeThreshold: 4,
      defaultNodeColor: nodeColors.Other,
      defaultEdgeColor: '#64748b',
      labelColor: { color: '#e2e8f0' },
      edgeLabelColor: { color: '#94a3b8' },
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

    return sigma
  } catch {
    teardown()
    emit(
      'error',
      'The graph canvas failed to start. Resize the window and try again.',
    )

    return null
  }
}

function seedPosition(
  next: Graph,
  selectedId: string | null | undefined,
): { x: number; y: number } {
  if (selectedId && next.hasNode(selectedId)) {
    const seed = next.getNodeAttributes(selectedId)

    return {
      x: Number(seed.x ?? 0) + (Math.random() - 0.5) * 40,
      y: Number(seed.y ?? 0) + (Math.random() - 0.5) * 40,
    }
  }

  return {
    x: (Math.random() - 0.5) * 200,
    y: (Math.random() - 0.5) * 200,
  }
}

function layout(next: Graph, added: number): void {
  if (next.order === 0) {
    return
  }

  const missing = next.filterNodes(
    (_id, attributes) =>
      typeof attributes.x !== 'number' || typeof attributes.y !== 'number',
  )

  if (missing.length > 0) {
    random.assign(next)
  }

  if (added === 0) {
    return
  }

  const settings = forceAtlas2.inferSettings(next)
  settings.barnesHutOptimize = next.order > 200

  forceAtlas2.assign(next, {
    iterations: Math.min(80, 30 + added),
    settings,
  })
}

function animateToSelected(selectedId: string | null | undefined): void {
  if (!sigma || !selectedId || !graph?.hasNode(selectedId)) {
    return
  }

  const display = sigma.getNodeDisplayData(selectedId)

  if (!display) {
    return
  }

  sigma.getCamera().animate(display, { duration: 500 })
}

function sync(nextNodes: GraphNode[], nextEdges: GraphEdge[]): void {
  const host = container.value

  if (!host || host.clientWidth === 0 || host.clientHeight === 0) {
    return
  }

  if (nextNodes.length === 0) {
    graph?.clear()
    sigma?.refresh()

    return
  }

  const next = graphInstance()
  const incomingNodes = new Set(nextNodes.map((node) => node.id))
  let added = 0

  next.forEachNode((id) => {
    if (!incomingNodes.has(id)) {
      next.dropNode(id)
    }
  })

  nextNodes.forEach((node) => {
    const shared = {
      size: node.label === 'Person' || node.label === 'Organization' ? 10 : 6,
      label: node.caption,
      color: colorForLabel(node.label),
      caption: node.caption,
      nodeLabel: node.label,
      properties: node.properties,
    }

    if (next.hasNode(node.id)) {
      next.mergeNode(node.id, shared)

      return
    }

    const position = seedPosition(next, props.selectedId)
    next.mergeNode(node.id, { ...shared, ...position })
    added++
  })

  const incomingEdges = new Set(nextEdges.map((edge) => edge.id))

  next.forEachEdge((id) => {
    if (!incomingEdges.has(id)) {
      next.dropEdge(id)
    }
  })

  nextEdges.forEach((edge) => {
    if (
      !next.hasNode(edge.source) ||
      !next.hasNode(edge.target) ||
      edge.source === edge.target
    ) {
      return
    }

    next.mergeEdgeWithKey(edge.id, edge.source, edge.target, {
      kind: edge.type,
      label: edge.type.replaceAll('_', ' '),
      size: 1,
      color: '#64748b',
    })
  })

  layout(next, added)

  const renderer = ensureRenderer(host, next)

  if (!renderer) {
    return
  }

  renderer.refresh()
  animateToSelected(props.selectedId)
}

watch(
  () => [props.nodes, props.edges] as const,
  ([nodes, edges]) => {
    sync(nodes, edges)
  },
  { deep: true },
)

watch(
  () => props.selectedId,
  (selectedId) => {
    animateToSelected(selectedId)
  },
)

onMounted(() => {
  observer = new ResizeObserver(() => {
    if (sigma) {
      sigma.resize()
      return
    }

    sync(props.nodes, props.edges)
  })

  if (container.value) {
    observer.observe(container.value)
  }

  sync(props.nodes, props.edges)
})

onBeforeUnmount(() => {
  observer?.disconnect()
  observer = null
  teardown()
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
