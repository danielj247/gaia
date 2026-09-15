<script setup lang="ts">
import { dumps as dumpsIndex } from '@/routes'
import { show } from '@/routes/dumps'
import type { BreadcrumbItem } from '@/types'

defineProps<{
  dumps: App.Data.DumpSummaryData[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dumps',
    href: dumpsIndex(),
  },
]

function badgeVariant(
  status: App.Enums.DumpStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' {
  if (status === 'completed') {
    return 'default'
  }

  if (status === 'failed') {
    return 'destructive'
  }

  if (status === 'ingesting' || status === 'downloading') {
    return 'secondary'
  }

  return 'outline'
}

function progressPercent(dump: App.Data.DumpSummaryData): number {
  if (dump.chunks_total === 0) {
    return 0
  }

  return Math.round((dump.chunks_completed / dump.chunks_total) * 100)
}
</script>

<template>
  <Head title="Dumps" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-1 flex-col gap-4 p-4">
      <Heading
        variant="small"
        title="Dump ingest"
        description="Chunk progress and parse errors for OpenSanctions FollowTheMoney files staged in MySQL. Graph MERGE runs in queue workers."
      />

      <div
        v-if="dumps.length === 0"
        class="rounded-xl border border-sidebar-border p-6 text-sm text-muted-foreground"
      >
        No dumps yet. Queue one with
        <code class="rounded bg-muted px-1 py-0.5"
          >php artisan graph:ingest-opensanctions</code
        >
        then run
        <code class="rounded bg-muted px-1 py-0.5">php artisan queue:work</code
        >.
      </div>

      <div v-else class="space-y-3">
        <UiCard v-for="dump in dumps" :key="dump.id">
          <UiCardHeader class="gap-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <UiCardTitle class="text-base">
                {{ dump.dataset }}
              </UiCardTitle>
              <UiBadge :variant="badgeVariant(dump.status)">{{
                dump.status
              }}</UiBadge>
            </div>
            <UiCardDescription>
              {{ dump.source }} · {{ dump.chunks_completed }}/{{
                dump.chunks_total
              }}
              chunks · {{ dump.error_count }} errors
            </UiCardDescription>
          </UiCardHeader>
          <UiCardContent class="space-y-3">
            <div
              class="h-2 overflow-hidden rounded-full bg-muted"
              aria-hidden="true"
            >
              <div
                class="h-full rounded-full bg-primary"
                :style="{ width: `${progressPercent(dump)}%` }"
              />
            </div>
            <p class="text-sm text-muted-foreground">
              {{ dump.entities_read }} entities read,
              {{ dump.nodes_upserted }} node writes,
              {{ dump.edges_upserted }} edge writes.
            </p>
            <p v-if="dump.error" class="text-sm text-destructive">
              {{ dump.error }}
            </p>
            <Link
              :href="show(dump.id)"
              class="text-sm font-medium underline-offset-4 hover:underline"
            >
              View chunks and errors
            </Link>
          </UiCardContent>
        </UiCard>
      </div>
    </div>
  </AppLayout>
</template>
