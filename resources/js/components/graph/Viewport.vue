<script setup lang="ts">
import { Maximize2, Minus, Plus, Shuffle } from '@lucide/vue'
import { usePreferredDark } from '@vueuse/core'
import Graph from 'graphology'
import { random } from 'graphology-layout'
import forceAtlas2 from 'graphology-layout-forceatlas2'
import Sigma from 'sigma'
import type { Settings } from 'sigma/settings'
import type {
  EdgeDisplayData,
  NodeDisplayData,
  PartialButFor,
} from 'sigma/types'
import {
  colorForLabel,
  nodeColors,
  surfaceForAppearance,
} from '@/lib/graphColors'
import { formatEdgeType, truncateEnd } from '@/lib/graphFormat'
import type { GraphEdge, GraphNode } from '@/types'

const props = defineProps<{
  nodes: GraphNode[]
  edges: GraphEdge[]
  selectedId?: string | null
  loading?: boolean
}>()

const emit = defineEmits<{
  select: [node: GraphNode]
  expand: [node: GraphNode]
  deselect: []
  error: [message: string]
}>()

const { appearance } = useAppearance()
const preferredDark = usePreferredDark()

const container = ref<HTMLDivElement | null>(null)
const legend = ref<HTMLDivElement | null>(null)
const controls = ref<HTMLDivElement | null>(null)

let graph: Graph | null = null
let sigma: Sigma | null = null
let observer: ResizeObserver | null = null
let hovered: string | null = null
let hoveredNeighbors: Set<string> | null = null

const surface = computed(() =>
  surfaceForAppearance(
    (
      appearance.value === 'system'
        ? preferredDark.value
        : appearance.value === 'dark'
    )
      ? 'dark'
      : 'light',
  ),
)

const backdrop = computed(() => ({
  backgroundColor: surface.value.background,
  backgroundImage: `radial-gradient(circle at center, ${surface.value.grid} 1px, transparent 1px)`,
  backgroundSize: '22px 22px',
}))

function graphInstance(): Graph {
  graph ??= new Graph({ multi: true, type: 'directed' })

  return graph
}

function teardown(): void {
  sigma?.kill()
  sigma = null
  graph = null
  hovered = null
  hoveredNeighbors = null
}

function nodeFrom(id: string): GraphNode {
  const attributes = graph?.getNodeAttributes(id) ?? {}

  return {
    id,
    label: String(attributes.nodeLabel ?? 'Other'),
    caption: String(attributes.caption ?? id),
    properties: (attributes.properties ?? {}) as Record<string, unknown>,
  }
}

function setHovered(id: string | null): void {
  hovered = id
  hoveredNeighbors =
    id !== null && graph?.hasNode(id) ? new Set(graph.neighbors(id)) : null

  if (container.value) {
    container.value.style.cursor = id === null ? 'grab' : 'pointer'
  }

  sigma?.refresh({ skipIndexation: true })
}

/** Fades everything outside the hovered node's own circle so one entity can be read at a time. */
function nodeReducer(
  id: string,
  data: Partial<NodeDisplayData>,
): Partial<NodeDisplayData> {
  const display: Partial<NodeDisplayData> = { ...data }

  if (id === props.selectedId) {
    display.highlighted = true
    display.forceLabel = true
    display.zIndex = 2
  }

  if (hovered === null) {
    return display
  }

  if (id === hovered || hoveredNeighbors?.has(id) === true) {
    display.forceLabel = true
    display.zIndex = 2

    return display
  }

  display.color = surface.value.faded
  display.label = ''
  display.zIndex = 0

  return display
}

/** Edge captions only appear for the hovered or selected entity, otherwise they smother the canvas. */
function edgeReducer(
  id: string,
  data: Partial<EdgeDisplayData>,
): Partial<EdgeDisplayData> {
  const display: Partial<EdgeDisplayData> = { ...data }
  const current = graph

  if (!current) {
    return display
  }

  const touches = (node: string | null | undefined): boolean =>
    node !== null &&
    node !== undefined &&
    (current.source(id) === node || current.target(id) === node)

  if (hovered !== null && !touches(hovered)) {
    display.color = surface.value.faded
    display.label = ''
    display.zIndex = 0

    return display
  }

  if (touches(hovered)) {
    display.color = surface.value.edgeStrong
    display.size = 2
    display.zIndex = 1

    return display
  }

  if (touches(props.selectedId)) {
    display.color = surface.value.edgeStrong
    display.size = 2
  }

  display.label = ''

  return display
}

type LabelData = PartialButFor<
  NodeDisplayData,
  'x' | 'y' | 'size' | 'label' | 'color'
>

function labelBaseline(data: LabelData, settings: Settings): number {
  return data.y + data.size + settings.labelSize
}

/** Captions sit under the node so they clear the spokes and clip evenly at the stage edges. */
function drawLabel(
  context: CanvasRenderingContext2D,
  data: LabelData,
  settings: Settings,
): void {
  if (typeof data.label !== 'string') {
    return
  }

  context.font = `${settings.labelWeight} ${settings.labelSize}px ${settings.labelFont}`
  context.textAlign = 'center'
  context.fillStyle = surface.value.label
  context.fillText(data.label, data.x, labelBaseline(data, settings))
  context.textAlign = 'left'
}

/**
 * Sigma paints highlighted nodes on a hardcoded white plate, which is
 * unreadable on the dark canvas, so the plate follows the theme here.
 */
function drawHighlight(
  context: CanvasRenderingContext2D,
  data: LabelData,
  settings: Settings,
): void {
  const theme = surface.value

  context.beginPath()
  context.arc(data.x, data.y, data.size + 3, 0, Math.PI * 2)
  context.closePath()
  context.strokeStyle = data.color
  context.lineWidth = 2
  context.stroke()

  if (typeof data.label !== 'string') {
    return
  }

  context.font = `${settings.labelWeight} ${settings.labelSize}px ${settings.labelFont}`

  const width = context.measureText(data.label).width + 12
  const height = settings.labelSize + 8
  const top =
    labelBaseline(data, settings) - height / 2 - settings.labelSize / 3

  context.beginPath()
  context.roundRect(data.x - width / 2, top, width, height, 6)
  context.fillStyle = theme.labelBackground
  context.fill()
  context.strokeStyle = theme.labelBorder
  context.lineWidth = 1
  context.stroke()

  drawLabel(context, data, settings)
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
      labelFont: 'Instrument Sans, ui-sans-serif, sans-serif',
      labelSize: 12,
      labelWeight: '500',
      // Wide grid cells caption one entity per neighbourhood at a glance;
      // zooming or hovering earns the rest.
      labelGridCellSize: 220,
      labelDensity: 0.6,
      labelRenderedSizeThreshold: 8,
      stagePadding: 60,
      defaultDrawNodeLabel: drawLabel,
      defaultDrawNodeHover: drawHighlight,
      edgeLabelFont: 'Instrument Sans, ui-sans-serif, sans-serif',
      edgeLabelSize: 10,
      defaultNodeColor: nodeColors.Other,
      defaultEdgeColor: surface.value.edge,
      labelColor: { color: surface.value.label },
      edgeLabelColor: { color: surface.value.edgeLabel },
      minCameraRatio: 0.05,
      maxCameraRatio: 4,
      zIndex: true,
      nodeReducer,
      edgeReducer,
    })

    host.style.cursor = 'grab'

    sigma.on('enterNode', ({ node }) => setHovered(node))
    sigma.on('leaveNode', () => setHovered(null))
    sigma.on('clickNode', ({ node }) => emit('select', nodeFrom(node)))
    sigma.on('doubleClickNode', ({ node, preventSigmaDefault }) => {
      preventSigmaDefault()
      emit('expand', nodeFrom(node))
    })
    sigma.on('clickStage', () => emit('deselect'))

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

/** New nodes land on a ring around the entity they came from, so the layout starts untangled. */
function seedPosition(next: Graph): { x: number; y: number } {
  const anchor = props.selectedId
  const seed =
    anchor !== null && anchor !== undefined && next.hasNode(anchor)
      ? next.getNodeAttributes(anchor)
      : { x: 0, y: 0 }
  const angle = Math.random() * Math.PI * 2
  const distance = 60 + Math.random() * 120

  return {
    x: Number(seed.x ?? 0) + Math.cos(angle) * distance,
    y: Number(seed.y ?? 0) + Math.sin(angle) * distance,
  }
}

function runForceAtlas(next: Graph, iterations: number): void {
  const settings = forceAtlas2.inferSettings(next)
  settings.barnesHutOptimize = next.order > 200

  forceAtlas2.assign(next, { iterations, settings })
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

  runForceAtlas(next, Math.min(240, 80 + added * 6))
}

/** Well connected entities read as the hubs they are. */
function applySizes(next: Graph): void {
  next.forEachNode((id, attributes) => {
    const label = String(attributes.nodeLabel ?? 'Other')
    const base = label === 'Identifier' || label === 'Address' ? 4 : 6

    next.setNodeAttribute(
      id,
      'size',
      base + Math.min(9, Math.sqrt(next.degree(id)) * 3),
    )
  })
}

/**
 * Room framing has to leave free: the legend and zoom controls, plus enough
 * slack for the node circles and the captions drawn beneath them.
 */
function chrome(): {
  top: number
  right: number
  bottom: number
  left: number
} {
  return {
    top: 32 + (legend.value?.offsetHeight ?? 0),
    right: 60,
    bottom: 56,
    left: 60 + (controls.value?.offsetWidth ?? 0),
  }
}

/** Centres a box of framed coordinates in the canvas area the overlays leave free. */
function frame(
  box: { x: number; y: number; width: number; height: number },
  options: { ratio?: number } = {},
): void {
  const renderer = sigma

  if (!renderer) {
    return
  }

  const { width, height } = renderer.getDimensions()
  const inset = chrome()
  const freeWidth = Math.max(160, width - inset.left - inset.right)
  const freeHeight = Math.max(160, height - inset.top - inset.bottom)

  // Measured against a fixed camera so a running animation cannot skew the maths.
  const reference = { x: 0.5, y: 0.5, ratio: 1, angle: 0 }
  const origin = renderer.framedGraphToViewport(
    { x: 0, y: 0 },
    { cameraState: reference },
  )
  const corner = renderer.framedGraphToViewport(
    { x: 1, y: 1 },
    { cameraState: reference },
  )
  // Pixels one framed unit covers at ratio 1, plus which way framed y runs.
  const unit = Math.abs(corner.x - origin.x)
  const flip = corner.y < origin.y ? -1 : 1

  if (unit === 0) {
    return
  }

  const ratio =
    options.ratio ??
    Math.max(
      0.08,
      unit /
        Math.min(
          freeWidth / Math.max(box.width, 0.001),
          freeHeight / Math.max(box.height, 0.001),
        ),
    )
  const scale = unit / ratio
  const offsetX = inset.left + freeWidth / 2 - width / 2
  const offsetY = inset.top + freeHeight / 2 - height / 2

  void renderer.getCamera().animate(
    {
      x: box.x - offsetX / scale,
      y: box.y - (offsetY / scale) * flip,
      ratio,
      angle: 0,
    },
    { duration: 400 },
  )
}

function fit(): void {
  const renderer = sigma
  const current = graph

  if (!renderer || !current || current.order === 0) {
    return
  }

  let minX = Infinity
  let maxX = -Infinity
  let minY = Infinity
  let maxY = -Infinity

  current.forEachNode((id) => {
    const display = renderer.getNodeDisplayData(id)

    if (!display) {
      return
    }

    minX = Math.min(minX, display.x)
    maxX = Math.max(maxX, display.x)
    minY = Math.min(minY, display.y)
    maxY = Math.max(maxY, display.y)
  })

  if (!Number.isFinite(minX)) {
    return
  }

  frame({
    x: (minX + maxX) / 2,
    y: (minY + maxY) / 2,
    width: maxX - minX,
    height: maxY - minY,
  })
}

function focusSelected(): void {
  const id = props.selectedId

  if (
    !sigma ||
    id === null ||
    id === undefined ||
    graph?.hasNode(id) !== true
  ) {
    return
  }

  const display = sigma.getNodeDisplayData(id)

  if (!display) {
    return
  }

  frame(
    { x: display.x, y: display.y, width: 0, height: 0 },
    { ratio: Math.min(sigma.getCamera().getState().ratio, 0.7) },
  )
}

function zoomIn(): void {
  void sigma?.getCamera().animatedZoom({ duration: 300 })
}

function zoomOut(): void {
  void sigma?.getCamera().animatedUnzoom({ duration: 300 })
}

function shuffle(): void {
  if (!graph || graph.order === 0) {
    return
  }

  random.assign(graph)
  runForceAtlas(graph, 120)
  applySizes(graph)
  sigma?.refresh()
  fit()
}

/** Mirrors the incoming entities into graphology and reports how many are new. */
function sync(nextNodes: GraphNode[], nextEdges: GraphEdge[]): number {
  const host = container.value

  if (!host || host.clientWidth === 0 || host.clientHeight === 0) {
    return 0
  }

  if (nextNodes.length === 0) {
    graph?.clear()
    setHovered(null)
    sigma?.refresh()

    return 0
  }

  const next = graphInstance()
  const incomingNodes = new Set(nextNodes.map((node) => node.id))
  let added = 0

  next
    .filterNodes((id) => !incomingNodes.has(id))
    .forEach((id) => next.dropNode(id))

  nextNodes.forEach((node) => {
    const shared = {
      label: truncateEnd(node.caption, 26),
      color: colorForLabel(node.label),
      caption: node.caption,
      nodeLabel: node.label,
      properties: node.properties,
    }

    if (next.hasNode(node.id)) {
      next.mergeNode(node.id, shared)

      return
    }

    next.mergeNode(node.id, { ...shared, ...seedPosition(next) })
    added++
  })

  const incomingEdges = new Set(nextEdges.map((edge) => edge.id))

  next
    .filterEdges((id) => !incomingEdges.has(id))
    .forEach((id) => next.dropEdge(id))

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
      label: formatEdgeType(edge.type),
      size: 1,
    })
  })

  layout(next, added)
  applySizes(next)

  const renderer = ensureRenderer(host, next)

  if (!renderer) {
    return 0
  }

  renderer.refresh()

  return added
}

/**
 * One place owns the camera: fresh entities get the whole neighborhood framed,
 * and a move of the selection zooms to that entity instead.
 */
watch(
  () => [props.nodes, props.edges, props.selectedId ?? null] as const,
  ([nodes, edges, selectedId], previous) => {
    const added = sync(nodes, edges)

    if (added > 0) {
      fit()

      return
    }

    if (selectedId !== (previous?.[2] ?? null)) {
      focusSelected()
      sigma?.refresh({ skipIndexation: true })
    }
  },
)

watch(surface, (next) => {
  sigma?.setSetting('defaultEdgeColor', next.edge)
  sigma?.setSetting('labelColor', { color: next.label })
  sigma?.setSetting('edgeLabelColor', { color: next.edgeLabel })
})

function draw(): void {
  if (sync(props.nodes, props.edges) > 0) {
    fit()
  }
}

onMounted(() => {
  observer = new ResizeObserver(() => {
    if (sigma) {
      sigma.resize()

      return
    }

    // The canvas had no size on mount, so this is the first real chance to draw.
    draw()
  })

  if (container.value) {
    observer.observe(container.value)
  }

  draw()
})

onBeforeUnmount(() => {
  observer?.disconnect()
  observer = null
  teardown()
})
</script>

<template>
  <div
    class="relative overflow-hidden rounded-xl border border-sidebar-border"
    :style="backdrop"
  >
    <div
      ref="container"
      class="absolute inset-0"
      role="img"
      aria-label="Neighborhood graph canvas. Use the entity lists beside it for keyboard access."
    />

    <div
      v-if="nodes.length > 0"
      ref="legend"
      class="absolute top-3 left-3 z-10"
    >
      <slot name="legend" />
    </div>

    <Transition
      enter-active-class="transition duration-150"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150"
      leave-to-class="opacity-0"
    >
      <div
        v-if="loading"
        class="absolute inset-x-0 top-3 z-10 flex justify-center"
      >
        <p
          class="flex items-center gap-2 rounded-full border border-sidebar-border bg-card/90 px-3 py-1.5 text-xs font-medium shadow-sm backdrop-blur"
        >
          <UiSpinner class="size-3.5" />
          Loading neighborhood
        </p>
      </div>
    </Transition>

    <div
      v-if="nodes.length === 0 && loading !== true"
      class="absolute inset-0 z-10 flex items-center justify-center p-6"
    >
      <slot name="empty" />
    </div>

    <div
      v-else
      ref="controls"
      class="absolute bottom-3 left-3 z-10 flex flex-col gap-0.5 rounded-lg border border-sidebar-border bg-card/90 p-1 shadow-sm backdrop-blur"
    >
      <UiTooltip>
        <UiTooltipTrigger as-child>
          <UiButton
            size="icon-sm"
            variant="ghost"
            aria-label="Zoom in"
            @click="zoomIn"
          >
            <Plus />
          </UiButton>
        </UiTooltipTrigger>
        <UiTooltipContent side="right">Zoom in</UiTooltipContent>
      </UiTooltip>
      <UiTooltip>
        <UiTooltipTrigger as-child>
          <UiButton
            size="icon-sm"
            variant="ghost"
            aria-label="Zoom out"
            @click="zoomOut"
          >
            <Minus />
          </UiButton>
        </UiTooltipTrigger>
        <UiTooltipContent side="right">Zoom out</UiTooltipContent>
      </UiTooltip>
      <UiTooltip>
        <UiTooltipTrigger as-child>
          <UiButton
            size="icon-sm"
            variant="ghost"
            aria-label="Fit graph in view"
            @click="fit"
          >
            <Maximize2 />
          </UiButton>
        </UiTooltipTrigger>
        <UiTooltipContent side="right">Fit graph in view</UiTooltipContent>
      </UiTooltip>
      <UiTooltip>
        <UiTooltipTrigger as-child>
          <UiButton
            size="icon-sm"
            variant="ghost"
            aria-label="Untangle layout"
            @click="shuffle"
          >
            <Shuffle />
          </UiButton>
        </UiTooltipTrigger>
        <UiTooltipContent side="right">Untangle layout</UiTooltipContent>
      </UiTooltip>
    </div>
  </div>
</template>
