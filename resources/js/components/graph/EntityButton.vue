<script setup lang="ts">
import { colorForLabel } from '@/lib/graphColors'

defineProps<{
  caption: string
  label: string
  meta?: string
  active?: boolean
}>()
</script>

<template>
  <button
    type="button"
    :data-active="active === true"
    class="flex w-full items-start gap-2 rounded-lg px-2 py-2 text-left transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none data-[active=true]:bg-accent"
  >
    <span
      class="mt-1.5 size-2 shrink-0 rounded-full"
      :style="{ backgroundColor: colorForLabel(label) }"
      aria-hidden="true"
    />
    <span class="min-w-0 flex-1">
      <span class="block truncate text-sm font-medium">{{ caption }}</span>
      <span class="block truncate text-xs text-muted-foreground">
        {{ label }}<template v-if="meta"> · {{ meta }}</template>
      </span>
    </span>
  </button>
</template>
