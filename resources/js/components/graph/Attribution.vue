<script setup lang="ts">
import { ChevronDown, Info } from '@lucide/vue'

defineProps<{
  stats: App.Data.GraphStatsData
}>()
</script>

<template>
  <UiCollapsible
    class="shrink-0 rounded-xl border border-sidebar-border bg-card"
  >
    <UiCollapsibleTrigger
      class="group flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium transition-colors hover:bg-accent/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
    >
      <Info class="size-3.5 text-muted-foreground" aria-hidden="true" />
      <span class="flex-1">Data, licensing and sources</span>
      <ChevronDown
        class="size-3.5 text-muted-foreground transition-transform group-data-[state=open]:rotate-180"
      />
    </UiCollapsibleTrigger>
    <UiCollapsibleContent>
      <div class="space-y-2 border-t border-sidebar-border p-3">
        <div v-if="stats.datasets.length > 0" class="flex flex-wrap gap-1">
          <UiBadge
            v-for="dataset in stats.datasets"
            :key="dataset"
            variant="outline"
            class="font-normal"
          >
            {{ dataset }}
          </UiBadge>
        </div>
        <p
          class="max-h-44 overflow-y-auto text-xs leading-relaxed text-muted-foreground"
        >
          {{ stats.attribution }}
        </p>
      </div>
    </UiCollapsibleContent>
  </UiCollapsible>
</template>
