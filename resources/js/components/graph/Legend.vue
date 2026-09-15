<script setup lang="ts">
import type { GraphLabelCount } from '@/types'

const props = defineProps<{
  counts: GraphLabelCount[]
  nodeCount: number
  edgeCount: number
  truncated: boolean
}>()

const hidden = defineModel<string[]>('hidden', { required: true })

function isHidden(label: string): boolean {
  return hidden.value.includes(label)
}

function toggle(label: string): void {
  hidden.value = isHidden(label)
    ? hidden.value.filter((entry) => entry !== label)
    : [...hidden.value, label]
}

const hasHidden = computed(() =>
  props.counts.some((item) => isHidden(item.label)),
)
</script>

<template>
  <div
    class="flex max-w-lg flex-wrap items-center gap-1 rounded-lg border border-sidebar-border bg-card/90 p-1.5 shadow-sm backdrop-blur"
  >
    <p class="px-1.5 text-xs text-muted-foreground tabular-nums">
      {{ nodeCount }} nodes · {{ edgeCount }} edges
    </p>

    <span class="mx-0.5 h-4 w-px bg-border" aria-hidden="true" />

    <UiTooltip v-for="item in counts" :key="item.label">
      <UiTooltipTrigger as-child>
        <button
          type="button"
          :aria-pressed="!isHidden(item.label)"
          :data-hidden="isHidden(item.label)"
          class="flex items-center gap-1.5 rounded-md px-1.5 py-1 text-xs font-medium transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none data-[hidden=true]:text-muted-foreground"
          @click="toggle(item.label)"
        >
          <span
            class="size-2 rounded-full transition-opacity"
            :class="isHidden(item.label) ? 'opacity-25' : ''"
            :style="{ backgroundColor: item.color }"
            aria-hidden="true"
          />
          <span :class="isHidden(item.label) ? 'line-through' : ''">
            {{ item.label }}
          </span>
          <span class="tabular-nums opacity-60">{{ item.count }}</span>
        </button>
      </UiTooltipTrigger>
      <UiTooltipContent side="bottom">
        {{ isHidden(item.label) ? 'Show' : 'Hide' }} {{ item.label }} nodes
      </UiTooltipContent>
    </UiTooltip>

    <UiButton
      v-if="hasHidden"
      size="sm"
      variant="ghost"
      class="h-7 px-2 text-xs"
      @click="hidden = []"
    >
      Show all
    </UiButton>

    <UiTooltip v-if="truncated">
      <UiTooltipTrigger as-child>
        <UiBadge variant="outline" class="ml-0.5 cursor-help">
          Truncated
        </UiBadge>
      </UiTooltipTrigger>
      <UiTooltipContent side="bottom" class="max-w-56">
        The neighborhood hit the node limit. Expand a specific entity instead of
        the whole hub.
      </UiTooltipContent>
    </UiTooltip>
  </div>
</template>
