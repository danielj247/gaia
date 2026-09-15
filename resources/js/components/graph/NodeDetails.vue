<script setup lang="ts">
import {
  Check,
  ChevronDown,
  Copy,
  ExternalLink,
  Link2,
  Network,
  X,
} from '@lucide/vue'
import { useClipboard } from '@vueuse/core'
import { colorForLabel } from '@/lib/graphColors'
import {
  formatEdgeType,
  graphProperties,
  isExpandable,
  splitList,
  truncateMiddle,
} from '@/lib/graphFormat'
import type { GraphEdge, GraphNode } from '@/types'

type RelationRow = {
  id: string
  caption: string
  label: string
}

type RelationGroup = {
  key: string
  title: string
  rows: RelationRow[]
}

const props = defineProps<{
  node: GraphNode
  nodes: GraphNode[]
  edges: GraphEdge[]
  expanding: boolean
  linking: boolean
}>()

const sameAsTarget = defineModel<string>('sameAsTarget', { required: true })

const emit = defineEmits<{
  close: []
  expand: []
  select: [id: string]
  assertSameAs: []
}>()

const { copy, copied } = useClipboard({ copiedDuring: 1500 })

const expandable = computed(() => isExpandable(props.node.label))

const sourceUrl = computed(() => {
  const value = props.node.properties.sourceUrl

  return typeof value === 'string' && value.startsWith('https://')
    ? value
    : null
})

const topics = computed(() => splitList(props.node.properties.topics))

const properties = computed(() =>
  graphProperties(props.node.properties).filter(
    (property) => property.key !== 'topics',
  ),
)

const groups = computed<RelationGroup[]>(() => {
  const loaded = new Map(props.nodes.map((node) => [node.id, node]))
  const collected = new Map<string, RelationGroup>()

  for (const edge of props.edges) {
    const outgoing = edge.source === props.node.id

    if (!outgoing && edge.target !== props.node.id) {
      continue
    }

    const otherId = outgoing ? edge.target : edge.source
    const other = loaded.get(otherId)
    const key = `${edge.type}|${outgoing ? 'out' : 'in'}`
    const group = collected.get(key) ?? {
      key,
      title: outgoing
        ? formatEdgeType(edge.type)
        : `${formatEdgeType(edge.type)} (incoming)`,
      rows: [],
    }

    group.rows.push({
      id: otherId,
      caption: other?.caption ?? truncateMiddle(otherId, 26),
      label: other?.label ?? 'Other',
    })

    collected.set(key, group)
  }

  return [...collected.values()]
})
</script>

<template>
  <aside
    class="flex min-h-0 flex-col overflow-hidden"
    aria-label="Entity inspector"
  >
    <header class="flex items-start gap-2 border-b border-sidebar-border p-3">
      <span
        class="mt-1.5 size-2.5 shrink-0 rounded-full"
        :style="{ backgroundColor: colorForLabel(node.label) }"
        aria-hidden="true"
      />
      <div class="min-w-0 flex-1">
        <h2 class="text-sm leading-snug font-semibold break-words">
          {{ node.caption }}
        </h2>
        <p class="mt-0.5 text-xs text-muted-foreground">{{ node.label }}</p>
      </div>
      <UiButton
        size="icon-sm"
        variant="ghost"
        aria-label="Close inspector"
        @click="emit('close')"
      >
        <X />
      </UiButton>
    </header>

    <div
      class="max-h-72 min-h-0 flex-1 space-y-4 overflow-y-auto p-3 lg:max-h-none"
    >
      <div
        v-if="topics.length > 0 || sourceUrl"
        class="flex flex-wrap items-center gap-1.5"
      >
        <UiBadge
          v-for="topic in topics"
          :key="topic"
          variant="secondary"
          class="font-normal"
        >
          {{ topic }}
        </UiBadge>
        <a
          v-if="sourceUrl"
          :href="sourceUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-1 text-xs font-medium text-primary underline-offset-4 hover:underline"
        >
          Source record
          <ExternalLink class="size-3" />
        </a>
      </div>

      <div
        class="rounded-lg border border-sidebar-border bg-muted/40 px-2.5 py-2"
      >
        <div class="flex items-center justify-between gap-2">
          <p class="text-xs font-medium text-muted-foreground">Graph id</p>
          <button
            type="button"
            class="rounded-sm p-0.5 text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            :aria-label="copied ? 'Graph id copied' : 'Copy graph id'"
            @click="copy(node.id)"
          >
            <Check v-if="copied" class="size-3.5" />
            <Copy v-else class="size-3.5" />
          </button>
        </div>
        <p class="mt-0.5 font-mono text-xs break-all">{{ node.id }}</p>
      </div>

      <section v-if="properties.length > 0">
        <h3 class="text-xs font-medium text-muted-foreground">Attributes</h3>
        <dl class="mt-1.5 space-y-1.5">
          <div
            v-for="property in properties"
            :key="property.key"
            class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-2 text-xs"
          >
            <dt class="text-muted-foreground">{{ property.label }}</dt>
            <dd class="break-words">{{ property.value }}</dd>
          </div>
        </dl>
      </section>

      <section v-if="groups.length > 0">
        <h3 class="text-xs font-medium text-muted-foreground">Relationships</h3>
        <div v-for="group in groups" :key="group.key" class="mt-1.5">
          <p class="px-2 text-[11px] font-medium text-muted-foreground">
            {{ group.title }}
          </p>
          <ul class="mt-0.5 space-y-0.5">
            <li v-for="row in group.rows" :key="`${group.key}-${row.id}`">
              <GraphEntityButton
                :caption="row.caption"
                :label="row.label"
                @click="emit('select', row.id)"
              />
            </li>
          </ul>
        </div>
      </section>
    </div>

    <footer class="space-y-2 border-t border-sidebar-border p-3">
      <UiButton
        variant="secondary"
        class="w-full"
        :disabled="expanding || !expandable"
        @click="emit('expand')"
      >
        <Network />
        Expand neighbors
      </UiButton>
      <p class="text-xs text-muted-foreground">
        {{
          expandable
            ? 'Double-click a node on the canvas to expand it as well.'
            : 'Hub nodes stay read-only so one click cannot pull in a whole dataset.'
        }}
      </p>

      <UiCollapsible v-if="node.label === 'Person'">
        <UiCollapsibleTrigger
          class="group flex w-full items-center justify-between rounded-md px-1 py-1.5 text-xs font-medium transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        >
          Analyst SAME_AS
          <ChevronDown
            class="size-3.5 transition-transform group-data-[state=open]:rotate-180"
          />
        </UiCollapsibleTrigger>
        <UiCollapsibleContent class="pt-2">
          <form class="space-y-2" @submit.prevent="emit('assertSameAs')">
            <label class="block text-xs text-muted-foreground" for="same-as-to">
              Assert that another person id is the same human. Records stay
              separate.
            </label>
            <UiInput
              id="same-as-to"
              v-model="sameAsTarget"
              name="to"
              maxlength="80"
              autocomplete="off"
              placeholder="Other person id"
              class="h-8 text-xs"
            />
            <UiButton
              type="submit"
              size="sm"
              variant="outline"
              class="w-full"
              :disabled="linking || sameAsTarget.trim().length < 1"
            >
              <Link2 />
              Link identities
            </UiButton>
          </form>
        </UiCollapsibleContent>
      </UiCollapsible>
    </footer>
  </aside>
</template>
