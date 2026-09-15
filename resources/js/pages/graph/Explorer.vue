<script setup lang="ts">
import { Waypoints } from '@lucide/vue'
import { colorForLabel } from '@/lib/graphColors'
import { isExpandable } from '@/lib/graphFormat'
import { explorer } from '@/routes'
import { neighborhood, sameAs, search } from '@/routes/explorer'
import type {
  BreadcrumbItem,
  GraphEdge,
  GraphLabelCount,
  GraphNode,
  GraphSearchHit,
} from '@/types'

defineProps<{
  stats: App.Data.GraphStatsData
}>()

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Explorer',
    href: explorer(),
  },
]

const examples = ['Rosneft', 'Gazprom', 'Sovcomflot']

const query = ref('')
const depth = ref('1')
const hits = ref<GraphSearchHit[]>([])
const nodes = ref<GraphNode[]>([])
const edges = ref<GraphEdge[]>([])
const selected = ref<GraphNode | null>(null)
const hiddenLabels = ref<string[]>([])
const sameAsTarget = ref('')
const loading = ref(false)
const truncated = ref(false)
const searched = ref(false)
const errorMessage = ref<string | null>(null)

let requestId = 0
let lastQuery = ''

const searchHttp = useHttp<Record<string, never>, { hits: GraphSearchHit[] }>()
const neighborhoodHttp = useHttp<
  Record<string, never>,
  { nodes: GraphNode[]; edges: GraphEdge[]; truncated?: boolean }
>()
const sameAsHttp = useHttp<{ from: string; to: string }, { ok: boolean }>({
  from: '',
  to: '',
})

const labelCounts = computed<GraphLabelCount[]>(() => {
  const counts = new Map<string, number>()

  nodes.value.forEach((node) => {
    counts.set(node.label, (counts.get(node.label) ?? 0) + 1)
  })

  return [...counts.entries()]
    .sort(
      ([leftLabel, left], [rightLabel, right]) =>
        right - left || leftLabel.localeCompare(rightLabel),
    )
    .map(([label, count]) => ({ label, color: colorForLabel(label), count }))
})

const visibleNodes = computed(() =>
  nodes.value.filter((node) => !hiddenLabels.value.includes(node.label)),
)

const visibleEdges = computed(() => {
  const drawn = new Set(visibleNodes.value.map((node) => node.id))

  return edges.value.filter(
    (edge) => drawn.has(edge.source) && drawn.has(edge.target),
  )
})

async function runSearch(): Promise<void> {
  const term = query.value.trim()

  if (term.length < 2) {
    hits.value = []
    searched.value = false
    lastQuery = ''

    return
  }

  if (term === lastQuery) {
    return
  }

  lastQuery = term
  errorMessage.value = null

  try {
    const response = await searchHttp.get(
      search.url({ query: { q: term, limit: 25 } }),
    )

    if (response === null || !('hits' in response)) {
      errorMessage.value = 'Search failed. Try a shorter query.'
      hits.value = []
      lastQuery = ''

      return
    }

    hits.value = response.hits ?? []
    searched.value = true
  } catch {
    errorMessage.value = 'Search failed. Try again.'
    lastQuery = ''
  }
}

async function loadNeighborhood(id: string): Promise<void> {
  const current = ++requestId
  loading.value = true
  errorMessage.value = null

  try {
    const response = await neighborhoodHttp.get(
      neighborhood.url(encodeURIComponent(id), {
        query: { hops: Number(depth.value), limit: 200 },
      }),
    )

    if (current !== requestId) {
      return
    }

    if (response === null || !('nodes' in response)) {
      errorMessage.value = 'Could not load that neighborhood.'

      return
    }

    const incomingNodes = response.nodes ?? []
    const incomingEdges = response.edges ?? []
    const byId = new Map(nodes.value.map((node) => [node.id, node]))

    incomingNodes.forEach((node) => {
      byId.set(node.id, node)

      if (selected.value?.id === node.id) {
        selected.value = node
      }
    })

    nodes.value = [...byId.values()]
    truncated.value = response.truncated === true

    const edgeIds = new Set(edges.value.map((edge) => edge.id))

    incomingEdges.forEach((edge) => {
      if (!edgeIds.has(edge.id)) {
        edges.value = [...edges.value, edge]
        edgeIds.add(edge.id)
      }
    })

    if (incomingNodes.length === 0) {
      errorMessage.value = 'That entity has no visible neighborhood yet.'
    }
  } catch {
    if (current === requestId) {
      errorMessage.value = 'Could not load that neighborhood.'
    }
  } finally {
    if (current === requestId) {
      loading.value = false
    }
  }
}

async function openHit(hit: GraphSearchHit): Promise<void> {
  clearCanvas()
  selected.value = { ...hit, properties: {} }

  await loadNeighborhood(hit.id)
}

async function expand(node: GraphNode): Promise<void> {
  if (!isExpandable(node.label)) {
    return
  }

  selected.value = node

  await loadNeighborhood(node.id)
}

async function expandSelected(): Promise<void> {
  if (selected.value) {
    await expand(selected.value)
  }
}

async function runExample(example: string): Promise<void> {
  query.value = example

  await runSearch()
}

function selectNode(node: GraphNode): void {
  selected.value = node
}

function selectById(id: string): void {
  const match = nodes.value.find((node) => node.id === id)

  if (match) {
    selected.value = match
  }
}

function clearCanvas(): void {
  nodes.value = []
  edges.value = []
  selected.value = null
  hiddenLabels.value = []
  truncated.value = false
  errorMessage.value = null
}

async function assertSameAs(): Promise<void> {
  const from = selected.value

  if (!from || from.label !== 'Person') {
    return
  }

  const to = sameAsTarget.value.trim()

  if (to.length < 1 || to === from.id) {
    return
  }

  errorMessage.value = null

  try {
    sameAsHttp.from = from.id
    sameAsHttp.to = to

    const response = await sameAsHttp.post(sameAs.url())

    if (response === null || !('ok' in response)) {
      errorMessage.value = sameAsRejection()

      return
    }

    sameAsTarget.value = ''

    await loadNeighborhood(from.id)
  } catch {
    errorMessage.value = sameAsRejection()
  }
}

function sameAsRejection(): string {
  const rejection = sameAsHttp.errors.to

  return typeof rejection === 'string' ? rejection : 'Could not assert SAME_AS.'
}
</script>

<template>
  <Head title="Explorer" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex min-h-0 flex-1 flex-col gap-3 p-3 lg:p-4">
      <div class="flex shrink-0 flex-wrap items-start justify-between gap-3">
        <Heading
          variant="small"
          title="People graph"
          description="Search the public OpenSanctions FollowTheMoney graph, then expand an entity to see what it connects to. These lists carry personal data, so captions stay minimal."
        />
        <div class="flex flex-wrap items-center gap-1.5">
          <span
            class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs text-muted-foreground tabular-nums"
          >
            {{ stats.nodes.toLocaleString() }} nodes
          </span>
          <span
            class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs text-muted-foreground tabular-nums"
          >
            {{ stats.edges.toLocaleString() }} edges
          </span>
          <UiBadge
            v-for="dataset in stats.datasets"
            :key="dataset"
            variant="secondary"
            class="font-normal"
          >
            {{ dataset }}
          </UiBadge>
        </div>
      </div>

      <AlertError
        v-if="errorMessage"
        class="shrink-0"
        title="The explorer could not finish that request."
        :errors="errorMessage ? [errorMessage] : []"
      />

      <!-- Absolute workspace: long panel content scrolls instead of stretching the canvas. -->
      <div class="relative min-h-0 flex-1">
        <div
          class="absolute inset-0 flex flex-col gap-3 overflow-y-auto lg:grid lg:grid-cols-[20rem_minmax(0,1fr)] lg:overflow-hidden"
        >
          <div class="flex min-h-0 flex-col gap-3">
            <GraphRail
              v-model:query="query"
              v-model:depth="depth"
              class="min-h-0 lg:flex-1"
              :hits="hits"
              :canvas-nodes="visibleNodes"
              :selected-id="selected?.id"
              :searching="searchHttp.processing"
              :searched="searched"
              @search="runSearch"
              @open="openHit"
              @select="selectNode"
              @clear-canvas="clearCanvas"
            >
              <template #selected>
                <GraphNodeDetails
                  v-if="selected"
                  v-model:same-as-target="sameAsTarget"
                  class="min-h-0 flex-1"
                  :node="selected"
                  :nodes="nodes"
                  :edges="edges"
                  :expanding="loading"
                  :linking="sameAsHttp.processing"
                  @close="selected = null"
                  @expand="expandSelected"
                  @select="selectById"
                  @assert-same-as="assertSameAs"
                />
              </template>
            </GraphRail>
            <GraphAttribution :stats="stats" />
          </div>

          <section class="relative min-h-[26rem] lg:min-h-0">
            <GraphViewport
              class="size-full"
              :nodes="visibleNodes"
              :edges="visibleEdges"
              :selected-id="selected?.id"
              :loading="loading"
              @select="selectNode"
              @expand="expand"
              @deselect="selected = null"
              @error="errorMessage = $event"
            >
              <template #empty>
                <div class="max-w-sm text-center">
                  <span
                    class="mx-auto flex size-11 items-center justify-center rounded-full border border-sidebar-border bg-card/80"
                  >
                    <Waypoints
                      class="size-5 text-muted-foreground"
                      aria-hidden="true"
                    />
                  </span>
                  <p class="mt-3 text-sm font-medium">
                    Nothing on the canvas yet
                  </p>
                  <p class="mt-1 text-xs text-muted-foreground">
                    Search a name, alias, vessel or OpenSanctions id, then open
                    a result to draw its neighborhood.
                  </p>
                  <div class="mt-3 flex flex-wrap justify-center gap-1.5">
                    <UiButton
                      v-for="example in examples"
                      :key="example"
                      size="sm"
                      variant="outline"
                      class="h-7 rounded-full px-3 text-xs"
                      @click="runExample(example)"
                    >
                      {{ example }}
                    </UiButton>
                  </div>
                </div>
              </template>

              <template #legend>
                <GraphLegend
                  v-if="labelCounts.length > 0"
                  v-model:hidden="hiddenLabels"
                  :counts="labelCounts"
                  :node-count="visibleNodes.length"
                  :edge-count="visibleEdges.length"
                  :truncated="truncated"
                />
              </template>
            </GraphViewport>
          </section>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
