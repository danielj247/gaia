<script setup lang="ts">
import { dumps as dumpsIndex } from '@/routes'
import { progress, show } from '@/routes/dumps'
import type { BreadcrumbItem } from '@/types'

const props = defineProps<{
  dump: App.Data.DumpProgressData
}>()

const dump = ref(props.dump)
const http = useHttp<Record<string, never>, App.Data.DumpProgressData>()

watch(
  () => props.dump,
  (value) => {
    dump.value = value
  },
)

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  {
    title: 'Dumps',
    href: dumpsIndex(),
  },
  {
    title: dump.value.dataset,
    href: show(dump.value.id),
  },
])

const live = computed(() =>
  ['pending', 'downloading', 'ingesting'].includes(dump.value.status),
)

function badgeVariant(
  status: App.Enums.DumpStatus | App.Enums.DumpChunkStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' {
  if (status === 'completed') {
    return 'default'
  }

  if (status === 'failed') {
    return 'destructive'
  }

  if (
    status === 'ingesting' ||
    status === 'downloading' ||
    status === 'processing'
  ) {
    return 'secondary'
  }

  return 'outline'
}

function progressPercent(): number {
  if (dump.value.chunks_total === 0) {
    return 0
  }

  return Math.round(
    (dump.value.chunks_completed / dump.value.chunks_total) * 100,
  )
}

async function refresh(): Promise<void> {
  const response = await http.get(progress.url(dump.value.id))

  if (response !== null && 'id' in response) {
    dump.value = response
  }
}

onMounted(() => {
  const timer = window.setInterval(() => {
    if (live.value) {
      void refresh()
    }
  }, 2000)

  onUnmounted(() => {
    window.clearInterval(timer)
  })
})
</script>

<template>
  <Head title="Dump status" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-1 flex-col gap-4 p-4">
      <Heading
        variant="small"
        :title="dump.dataset"
        description="Chunk ledger and parse errors. Messages stay schema-safe and do not repeat list captions."
      />

      <UiCard>
        <UiCardHeader class="gap-2">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <UiCardTitle class="text-base">Progress</UiCardTitle>
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
              :style="{ width: `${progressPercent()}%` }"
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
          <p
            v-if="live"
            class="flex items-center gap-2 text-sm text-muted-foreground"
          >
            <UiSpinner class="size-4" />
            Workers are still parsing this dump.
          </p>
        </UiCardContent>
      </UiCard>

      <section class="space-y-2">
        <h2 class="text-sm font-medium">Chunks</h2>
        <div class="overflow-x-auto rounded-xl border border-sidebar-border">
          <table class="w-full text-left text-sm">
            <thead class="bg-muted/40 text-muted-foreground">
              <tr>
                <th class="px-3 py-2 font-medium">Index</th>
                <th class="px-3 py-2 font-medium">Pass</th>
                <th class="px-3 py-2 font-medium">Lines</th>
                <th class="px-3 py-2 font-medium">Status</th>
                <th class="px-3 py-2 font-medium">Entities</th>
                <th class="px-3 py-2 font-medium">Writes</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="chunk in dump.chunks"
                :key="chunk.id"
                class="border-t border-sidebar-border"
              >
                <td class="px-3 py-2">{{ chunk.chunk_index }}</td>
                <td class="px-3 py-2">{{ chunk.pass }}</td>
                <td class="px-3 py-2">
                  {{ chunk.line_start }}–{{ chunk.line_end }}
                </td>
                <td class="px-3 py-2">
                  <UiBadge :variant="badgeVariant(chunk.status)">{{
                    chunk.status
                  }}</UiBadge>
                </td>
                <td class="px-3 py-2">{{ chunk.entities_read }}</td>
                <td class="px-3 py-2">
                  {{ chunk.nodes_upserted }}n / {{ chunk.edges_upserted }}e
                  <span
                    v-if="chunk.error"
                    class="mt-1 block text-xs text-destructive"
                    >{{ chunk.error }}</span
                  >
                </td>
              </tr>
              <tr v-if="dump.chunks.length === 0">
                <td colspan="6" class="px-3 py-4 text-muted-foreground">
                  No chunks yet.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="space-y-2">
        <h2 class="text-sm font-medium">Parse errors</h2>
        <ul v-if="dump.errors.length" class="space-y-2">
          <li
            v-for="item in dump.errors"
            :key="item.id"
            class="rounded-md border border-sidebar-border px-3 py-2 text-sm"
          >
            <p class="font-medium">{{ item.code }}</p>
            <p class="text-muted-foreground">
              Line {{ item.line_number ?? '—' }} · {{ item.message }}
            </p>
          </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">
          No parse errors recorded.
        </p>
      </section>
    </div>
  </AppLayout>
</template>
