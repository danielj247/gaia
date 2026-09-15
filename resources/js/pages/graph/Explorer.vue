<script setup lang="ts">
import { colorForLabel } from '@/lib/graphColors'
import { explorer } from '@/routes'
import { neighborhood, sameAs, search } from '@/routes/explorer'
import type { BreadcrumbItem } from '@/types'

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

type SearchHit = {
  id: string
  label: string
  caption: string
}

defineProps<{
  stats: App.Data.GraphStatsData
}>()

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Explorer',
    href: explorer(),
  },
]

const query = ref('')
const hits = ref<SearchHit[]>([])
const nodes = ref<GraphNode[]>([])
const edges = ref<GraphEdge[]>([])
const selected = ref<GraphNode | null>(null)
const loading = ref(false)
const truncated = ref(false)
const errorMessage = ref<string | null>(null)
const emptyResults = ref(false)
let requestId = 0

const searchHttp = useHttp<Record<string, never>, { hits: SearchHit[] }>()
const neighborhoodHttp = useHttp<
  Record<string, never>,
  { nodes: GraphNode[]; edges: GraphEdge[]; truncated?: boolean }
>()
const sameAsHttp = useHttp<{ from: string; to: string }, { ok: boolean }>({
  from: '',
  to: '',
})
const sameAsTarget = ref('')

function formatProperty(value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return '—'
  }

  if (
    typeof value === 'string' ||
    typeof value === 'number' ||
    typeof value === 'boolean'
  ) {
    return String(value)
  }

  return JSON.stringify(value)
}

async function runSearch(): Promise<void> {
  const q = query.value.trim()

  if (q.length < 2) {
    hits.value = []
    emptyResults.value = false
    return
  }

  errorMessage.value = null
  emptyResults.value = false

  try {
    const response = await searchHttp.get(search.url({ query: { q } }))

    if (response === null || !('hits' in response)) {
      errorMessage.value = 'Search failed. Try a shorter query.'
      hits.value = []
      return
    }

    hits.value = response.hits ?? []
    emptyResults.value = hits.value.length === 0
  } catch {
    errorMessage.value = 'Search failed. Try again.'
  }
}

async function loadNeighborhood(id: string): Promise<void> {
  const current = ++requestId
  loading.value = true
  errorMessage.value = null

  try {
    const response = await neighborhoodHttp.get(
      neighborhood.url(encodeURIComponent(id), {
        query: { hops: 1, limit: 200 },
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

async function openHit(hit: SearchHit): Promise<void> {
  nodes.value = []
  edges.value = []
  truncated.value = false
  errorMessage.value = null
  selected.value = { ...hit, properties: {} }
  await loadNeighborhood(hit.id)
}

const legendItems = computed(() => {
  const seen = new Set<string>()
  const items: { label: string; color: string }[] = []

  for (const node of nodes.value) {
    if (seen.has(node.label)) {
      continue
    }

    seen.add(node.label)
    items.push({ label: node.label, color: colorForLabel(node.label) })
  }

  return items
})

function selectNode(node: GraphNode): void {
  selected.value = node
}

async function expandSelected(): Promise<void> {
  if (!selected.value) {
    return
  }

  await loadNeighborhood(selected.value.id)
}

async function assertSameAs(): Promise<void> {
  if (!selected.value || selected.value.label !== 'Person') {
    return
  }

  const to = sameAsTarget.value.trim()

  if (to.length < 1 || to === selected.value.id) {
    return
  }

  errorMessage.value = null

  try {
    sameAsHttp.from = selected.value.id
    sameAsHttp.to = to

    const response = await sameAsHttp.post(sameAs.url())

    if (response === null || !('ok' in response)) {
      errorMessage.value = 'Could not assert SAME_AS.'
      return
    }

    sameAsTarget.value = ''
    await loadNeighborhood(selected.value.id)
  } catch {
    errorMessage.value = 'Could not assert SAME_AS.'
  }
}

const incidentEdges = computed(() => {
  if (!selected.value) {
    return []
  }

  const id = selected.value.id

  return edges.value.filter((edge) => edge.source === id || edge.target === id)
})

const graphSummary = computed(() => {
  if (nodes.value.length === 0) {
    return ''
  }

  return `Loaded ${nodes.value.length} nodes, ${edges.value.length} edges.`
})
</script>

<template>
  <Head title="Explorer" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-1 flex-col gap-4 p-4">
      <Heading
        variant="small"
        title="People graph"
        description="Public OpenSanctions FollowTheMoney neighborhood view (US OFAC SDN and the sanctions collection). This screen shows personal data from sanctioned-entity lists. Labels stay minimal."
      />

      <p
        class="rounded-md border border-sidebar-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
      >
        {{ stats.attribution }}
        <span v-if="stats.datasets.length">
          Sources: {{ stats.datasets.join(', ') }}.</span
        >
        <span v-else-if="stats.dataset"> Dataset {{ stats.dataset }}.</span>
        {{ stats.nodes }} nodes, {{ stats.edges }} edges.
      </p>

      <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="runSearch">
        <UiInput
          v-model="query"
          type="search"
          name="q"
          maxlength="120"
          placeholder="Search a name, id, or alias"
          class="sm:max-w-md"
        />
        <UiButton
          type="submit"
          :disabled="searchHttp.processing || query.trim().length < 2"
        >
          Search
        </UiButton>
      </form>

      <p v-if="errorMessage" class="text-sm text-destructive">
        {{ errorMessage }}
      </p>
      <p v-if="truncated" class="text-sm text-muted-foreground">
        Neighborhood was truncated. Expand again or search a more specific node.
      </p>
      <p
        v-if="graphSummary"
        class="text-sm text-muted-foreground"
        aria-live="polite"
      >
        {{ graphSummary }}
      </p>

      <div class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <aside class="max-h-[32rem] space-y-2 overflow-y-auto">
          <h2 class="text-sm font-medium">Results</h2>
          <ul v-if="hits.length" class="space-y-1">
            <li v-for="hit in hits" :key="hit.id">
              <button
                type="button"
                class="w-full rounded-md border border-sidebar-border px-3 py-2 text-left text-sm hover:bg-muted"
                @click="openHit(hit)"
              >
                <span class="font-medium">{{ hit.caption }}</span>
                <span class="mt-0.5 block text-xs text-muted-foreground">
                  {{ hit.label }} · {{ hit.id }}
                </span>
              </button>
            </li>
          </ul>
          <p v-else-if="emptyResults" class="text-sm text-muted-foreground">
            No entities matched that search.
          </p>
          <p v-else class="text-sm text-muted-foreground">
            Search to load an ego network. The canvas is not in the
            accessibility tree — this list is.
          </p>

          <div v-if="nodes.length" class="space-y-1 pt-4">
            <h2 class="text-sm font-medium">Neighborhood</h2>
            <ul class="space-y-1">
              <li v-for="node in nodes" :key="node.id">
                <button
                  type="button"
                  class="w-full rounded-md px-3 py-1.5 text-left text-sm hover:bg-muted"
                  @click="selectNode(node)"
                >
                  <span class="font-medium">{{ node.caption }}</span>
                  <span class="mt-0.5 block text-xs text-muted-foreground">{{
                    node.label
                  }}</span>
                </button>
              </li>
            </ul>
          </div>

          <div
            v-if="selected"
            class="space-y-2 border-t border-sidebar-border pt-4"
          >
            <h2 class="text-sm font-medium">{{ selected.caption }}</h2>
            <p class="text-xs text-muted-foreground">
              {{ selected.label }} · {{ selected.id }}
            </p>
            <dl class="space-y-2 text-sm">
              <div
                v-for="(value, key) in selected.properties"
                :key="String(key)"
                class="grid gap-1"
              >
                <dt class="text-muted-foreground">{{ key }}</dt>
                <dd class="break-all">{{ formatProperty(value) }}</dd>
              </div>
            </dl>
            <div v-if="incidentEdges.length" class="space-y-1">
              <h3 class="text-xs font-medium text-muted-foreground">
                Relationships
              </h3>
              <ul class="space-y-1 text-xs">
                <li
                  v-for="edge in incidentEdges"
                  :key="edge.id"
                  class="break-all"
                >
                  {{ edge.type.replaceAll('_', ' ') }}
                  ·
                  {{ edge.source === selected.id ? edge.target : edge.source }}
                </li>
              </ul>
            </div>
            <UiButton
              variant="secondary"
              :disabled="
                loading ||
                ['Dump', 'Country', 'Sanction'].includes(selected.label)
              "
              @click="expandSelected"
            >
              Expand neighbors
            </UiButton>
            <form
              v-if="selected.label === 'Person'"
              class="space-y-2"
              @submit.prevent="assertSameAs"
            >
              <label
                class="text-xs font-medium text-muted-foreground"
                for="same-as-target"
              >
                Analyst SAME_AS
              </label>
              <UiInput
                id="same-as-target"
                v-model="sameAsTarget"
                type="text"
                name="to"
                maxlength="80"
                placeholder="Other person id"
              />
              <UiButton
                type="submit"
                variant="outline"
                :disabled="
                  sameAsHttp.processing || sameAsTarget.trim().length < 1
                "
              >
                Link identities
              </UiButton>
            </form>
          </div>
        </aside>

        <div class="space-y-2">
          <ul
            v-if="legendItems.length"
            class="flex flex-wrap gap-2"
            aria-label="Node type legend"
          >
            <li
              v-for="item in legendItems"
              :key="item.label"
              class="flex items-center gap-1.5 text-xs text-muted-foreground"
            >
              <span
                class="size-2.5 rounded-full"
                :style="{ backgroundColor: item.color }"
                aria-hidden="true"
              />
              {{ item.label }}
            </li>
          </ul>
          <GraphViewport
            :nodes="nodes"
            :edges="edges"
            :selected-id="selected?.id"
            @select="selectNode"
            @error="errorMessage = $event"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>
