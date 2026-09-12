<script setup lang="ts">
import { explorer } from '@/routes'
import { neighborhood, search } from '@/routes/explorer'
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
const inspectorOpen = ref(false)
const loading = ref(false)
const errorMessage = ref<string | null>(null)

const http = useHttp()

async function runSearch(): Promise<void> {
  const q = query.value.trim()

  if (q.length < 2) {
    hits.value = []
    return
  }

  errorMessage.value = null

  const response = await http.get<{ hits: SearchHit[] }>(search.url({ query: { q } }))
  hits.value = response.hits ?? []
}

async function loadNeighborhood(id: string, hops = 1): Promise<void> {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await http.get<{
      nodes: GraphNode[]
      edges: GraphEdge[]
    }>(neighborhood.url(id, { query: { hops, limit: 200 } }))

    const incomingNodes = response.nodes ?? []
    const incomingEdges = response.edges ?? []
    const known = new Set(nodes.value.map((node) => node.id))

    incomingNodes.forEach((node) => {
      if (selected.value?.id === node.id) {
        selected.value = node
      }

      if (!known.has(node.id)) {
        nodes.value = [...nodes.value, node]
        known.add(node.id)
      }
    })

    const edgeIds = new Set(edges.value.map((edge) => edge.id))
    incomingEdges.forEach((edge) => {
      if (!edgeIds.has(edge.id)) {
        edges.value = [...edges.value, edge]
      }
    })

    if (nodes.value.length === 0) {
      nodes.value = incomingNodes
      edges.value = incomingEdges
    }
  } catch {
    errorMessage.value = 'Could not load that neighborhood.'
  } finally {
    loading.value = false
  }
}

async function openHit(hit: SearchHit): Promise<void> {
  nodes.value = []
  edges.value = []
  selected.value = { ...hit, properties: {} }
  inspectorOpen.value = true
  await loadNeighborhood(hit.id, 1)
}

function selectNode(node: GraphNode): void {
  selected.value = node
  inspectorOpen.value = true
}

async function expandSelected(): Promise<void> {
  if (!selected.value) {
    return
  }

  await loadNeighborhood(selected.value.id, 1)
}
</script>

<template>
  <Head title="Explorer" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-1 flex-col gap-4 p-4">
      <Heading
        title="People graph"
        description="Public OpenSanctions / OFAC SDN neighborhood view. This screen shows personal data from sanctioned-entity lists. Labels stay minimal."
      />

      <p class="rounded-md border border-sidebar-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
        {{ stats.attribution }}
        <span v-if="stats.dataset"> Dataset {{ stats.dataset }}.</span>
        {{ stats.nodes }} nodes, {{ stats.edges }} edges.
      </p>

      <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="runSearch">
        <UiInput
          v-model="query"
          type="search"
          name="q"
          placeholder="Search a name, id, or email"
          class="sm:max-w-md"
        />
        <UiButton type="submit" :disabled="http.processing || query.trim().length < 2">
          Search
        </UiButton>
      </form>

      <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>

      <div class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <aside class="space-y-2">
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
          <p v-else class="text-sm text-muted-foreground">
            Search to load an ego network. The canvas is not in the accessibility tree — this list is.
          </p>
        </aside>

        <GraphViewport :nodes="nodes" :edges="edges" @select="selectNode" />
      </div>
    </div>

    <UiSheet :open="inspectorOpen" @update:open="inspectorOpen = $event">
      <UiSheetContent>
        <UiSheetHeader>
          <UiSheetTitle>{{ selected?.caption ?? 'Node' }}</UiSheetTitle>
          <UiSheetDescription>
            {{ selected?.label }} · {{ selected?.id }}
          </UiSheetDescription>
        </UiSheetHeader>
        <dl v-if="selected" class="space-y-2 px-4 text-sm">
          <div
            v-for="(value, key) in selected.properties"
            :key="String(key)"
            class="grid gap-1"
          >
            <dt class="text-muted-foreground">{{ key }}</dt>
            <dd class="break-all">{{ value ?? '—' }}</dd>
          </div>
        </dl>
        <UiSheetFooter>
          <UiButton
            variant="secondary"
            :disabled="!selected || loading"
            @click="expandSelected"
          >
            Expand neighbors
          </UiButton>
        </UiSheetFooter>
      </UiSheetContent>
    </UiSheet>
  </AppLayout>
</template>
