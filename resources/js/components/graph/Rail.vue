<script setup lang="ts">
import { Search, Trash2, X } from '@lucide/vue'
import { useDebounceFn, useEventListener } from '@vueuse/core'
import { truncateMiddle } from '@/lib/graphFormat'
import type { GraphNode, GraphSearchHit } from '@/types'

type View = 'results' | 'canvas' | 'selected'

const props = defineProps<{
  hits: GraphSearchHit[]
  canvasNodes: GraphNode[]
  selectedId?: string | null
  searching: boolean
  searched: boolean
}>()

const query = defineModel<string>('query', { required: true })
const depth = defineModel<string>('depth', { required: true })

const emit = defineEmits<{
  search: []
  open: [hit: GraphSearchHit]
  select: [node: GraphNode]
  clearCanvas: []
}>()

const view = ref<View>('results')
const form = ref<HTMLFormElement | null>(null)
const list = ref<HTMLDivElement | null>(null)

const requestSearch = useDebounceFn(() => emit('search'), 300)

const statusText = computed(() => {
  if (props.searching) {
    return 'Searching…'
  }

  if (query.value.trim().length === 1) {
    return 'Type at least 2 characters.'
  }

  if (!props.searched) {
    return 'Names, aliases and ids are searchable.'
  }

  if (props.hits.length === 0) {
    return 'No entities matched.'
  }

  return `${props.hits.length} ${props.hits.length === 1 ? 'match' : 'matches'}`
})

const tabs = computed(() => [
  { key: 'results' as const, label: 'Results', count: props.hits.length },
  {
    key: 'canvas' as const,
    label: 'On canvas',
    count: props.canvasNodes.length,
  },
  { key: 'selected' as const, label: 'Selected', count: null },
])

watch(
  () => props.hits,
  (hits) => {
    if (hits.length > 0) {
      view.value = 'results'
    }
  },
)

watch(
  () => props.selectedId,
  (selectedId) => {
    view.value =
      selectedId === null || selectedId === undefined ? 'results' : 'selected'
  },
)

watch(query, () => {
  void requestSearch()
})

function searchField(): HTMLInputElement | null {
  return form.value?.querySelector<HTMLInputElement>('input[name="q"]') ?? null
}

function focusSearch(): void {
  const field = searchField()

  field?.focus()
  field?.select()
}

function reset(): void {
  query.value = ''
  focusSearch()
}

/** Arrow keys walk the visible list so results are reachable without tabbing through every row. */
function moveFocus(step: number, event: KeyboardEvent): void {
  const rows = [
    ...(list.value?.querySelectorAll<HTMLButtonElement>('button[data-row]') ??
      []),
  ]

  if (rows.length === 0) {
    return
  }

  event.preventDefault()

  const current = rows.indexOf(document.activeElement as HTMLButtonElement)
  const next =
    current === -1
      ? step > 0
        ? 0
        : rows.length - 1
      : (current + step + rows.length) % rows.length

  rows[next]?.focus()
}

useEventListener(window, 'keydown', (event: KeyboardEvent) => {
  const target = event.target
  const typing =
    target instanceof HTMLElement &&
    target.closest('input, textarea, [contenteditable="true"]') !== null

  if (event.key === 'k' && (event.metaKey || event.ctrlKey)) {
    event.preventDefault()
    focusSearch()

    return
  }

  if (event.key === '/' && !typing) {
    event.preventDefault()
    focusSearch()
  }
})
</script>

<template>
  <section
    class="flex flex-col overflow-hidden rounded-xl border border-sidebar-border bg-card"
  >
    <form
      ref="form"
      class="space-y-2 border-b border-sidebar-border p-3"
      @submit.prevent="emit('search')"
    >
      <div class="relative">
        <Search
          class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
          aria-hidden="true"
        />
        <UiInput
          v-model="query"
          type="search"
          name="q"
          maxlength="120"
          autocomplete="off"
          placeholder="Search a name, alias or id"
          aria-label="Search entities"
          class="pr-14 pl-8 [&::-webkit-search-cancel-button]:hidden"
          @keydown.down="moveFocus(1, $event)"
        />
        <div
          class="absolute top-1/2 right-2 flex -translate-y-1/2 items-center gap-1"
        >
          <UiSpinner v-if="searching" class="size-3.5 text-muted-foreground" />
          <button
            v-if="query.length > 0"
            type="button"
            class="rounded-sm p-0.5 text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            aria-label="Clear search"
            @click="reset"
          >
            <X class="size-3.5" />
          </button>
          <kbd
            v-else
            class="hidden rounded border border-sidebar-border px-1 font-mono text-[10px] text-muted-foreground sm:block"
          >
            /
          </kbd>
        </div>
      </div>

      <div class="flex min-h-7 items-center justify-between gap-2">
        <p
          class="text-xs leading-tight text-muted-foreground"
          aria-live="polite"
        >
          {{ statusText }}
        </p>
        <UiSelect v-model="depth">
          <UiSelectTrigger
            size="sm"
            class="h-7 shrink-0 gap-1 px-2 text-xs"
            aria-label="Expansion depth"
          >
            <UiSelectValue />
          </UiSelectTrigger>
          <UiSelectContent align="end">
            <UiSelectItem value="1">1 hop</UiSelectItem>
            <UiSelectItem value="2">2 hops</UiSelectItem>
            <UiSelectItem value="3">3 hops</UiSelectItem>
          </UiSelectContent>
        </UiSelect>
      </div>
    </form>

    <div class="flex items-center gap-1 border-b border-sidebar-border p-2">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        :aria-pressed="view === tab.key"
        :disabled="tab.key === 'selected' && !selectedId"
        class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-40 aria-pressed:bg-accent aria-pressed:text-accent-foreground"
        @click="view = tab.key"
      >
        {{ tab.label }}
        <span v-if="tab.count !== null" class="tabular-nums opacity-60">
          {{ tab.count }}
        </span>
      </button>
    </div>

    <slot v-if="view === 'selected'" name="selected" />

    <div
      v-else
      ref="list"
      class="max-h-72 min-h-0 flex-1 overflow-y-auto p-2 lg:max-h-none"
      @keydown.down="moveFocus(1, $event)"
      @keydown.up="moveFocus(-1, $event)"
    >
      <template v-if="view === 'results'">
        <ul v-if="hits.length > 0" class="space-y-0.5">
          <li v-for="hit in hits" :key="hit.id">
            <GraphEntityButton
              data-row
              :caption="hit.caption"
              :label="hit.label"
              :meta="truncateMiddle(hit.id, 20)"
              :active="hit.id === selectedId"
              @click="emit('open', hit)"
            />
          </li>
        </ul>
        <p v-else class="px-2 py-6 text-center text-xs text-muted-foreground">
          {{
            searched
              ? 'Nothing matched that search. Try a surname, a vessel name or an OpenSanctions id.'
              : 'Search to draw an entity and everything it connects to.'
          }}
        </p>
      </template>

      <template v-else>
        <ul v-if="canvasNodes.length > 0" class="space-y-0.5">
          <li v-for="node in canvasNodes" :key="node.id">
            <GraphEntityButton
              data-row
              :caption="node.caption"
              :label="node.label"
              :active="node.id === selectedId"
              @click="emit('select', node)"
            />
          </li>
        </ul>
        <p v-else class="px-2 py-6 text-center text-xs text-muted-foreground">
          The canvas is empty. Open a search result to start.
        </p>
      </template>
    </div>

    <div
      v-if="canvasNodes.length > 0 && view === 'canvas'"
      class="border-t border-sidebar-border p-2"
    >
      <UiButton
        variant="ghost"
        size="sm"
        class="w-full text-muted-foreground"
        @click="emit('clearCanvas')"
      >
        <Trash2 />
        Clear canvas
      </UiButton>
    </div>
  </section>
</template>
