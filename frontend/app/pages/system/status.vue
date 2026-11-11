<script setup lang="ts">
/**
 * System status: does the backend answer, does the database answer, does the
 * GraphQL endpoint answer. Plain requests only; nothing is read or changed.
 */
const { t } = useI18n()
const config = useRuntimeConfig()

useHead({ title: () => t('systemStatus.title') })

type Check = 'idle' | 'checking' | 'ok' | 'error'
interface Row { state: Check, ms: number | null }

const backend = reactive<Row>({ state: 'idle', ms: null })
const database = reactive<Row>({ state: 'idle', ms: null })
const graphql = reactive<Row>({ state: 'idle', ms: null })
const lastChecked = ref<string | null>(null)

const apiBase = computed(() => config.public.apiBase.replace(/\/$/, ''))

/** One request to the status endpoint: the backend answers, and the database answers (503 if not). */
async function checkBackendAndDatabase(): Promise<void> {
  backend.state = 'checking'
  database.state = 'checking'
  const started = performance.now()

  try {
    const body = await $fetch<{ backend: string, database: string }>(`${apiBase.value}/status`, {
      timeout: 8000,
      ignoreResponseError: true
    })
    backend.state = body.backend === 'ok' ? 'ok' : 'error'
    database.state = body.database === 'ok' ? 'ok' : 'error'
  } catch {
    backend.state = 'error'
    database.state = 'error'
  }

  backend.ms = Math.round(performance.now() - started)
  database.ms = backend.ms
}

/** A plain GET with a trivial query: it changes nothing and needs no CSRF token. */
async function checkGraphql(): Promise<void> {
  graphql.state = 'checking'
  const started = performance.now()

  try {
    const body = await $fetch<{ data?: { __typename?: string } }>(`${apiBase.value}/api`, {
      query: { query: '{ __typename }' },
      credentials: 'include',
      timeout: 8000
    })
    graphql.state = body.data?.__typename ? 'ok' : 'error'
  } catch {
    graphql.state = 'error'
  }

  graphql.ms = Math.round(performance.now() - started)
}

async function runChecks(): Promise<void> {
  await Promise.all([checkBackendAndDatabase(), checkGraphql()])
  lastChecked.value = new Date().toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

onMounted(() => {
  void runChecks()
})

const checking = computed(() => [backend, database, graphql].some(row => row.state === 'checking'))

function badge(state: Check): { variant: 'success' | 'danger' | 'neutral', text: string } {
  if (state === 'ok') return { variant: 'success', text: t('systemStatus.ok') }
  if (state === 'error') return { variant: 'danger', text: t('systemStatus.error') }
  return { variant: 'neutral', text: t('systemStatus.checking') }
}

const rows = computed(() => [
  { key: 'backend', label: t('systemStatus.backend'), url: `${apiBase.value}/status`, item: backend },
  { key: 'database', label: t('systemStatus.database'), url: '', item: database },
  { key: 'graphql', label: t('systemStatus.graphql'), url: `${apiBase.value}/api`, item: graphql }
])
</script>

<template>
  <PageFrame
    :title="t('systemStatus.title')"
    :breadcrumbs="[{ label: t('nav.admin') }, { label: t('systemStatus.title') }]"
    :description="t('systemStatus.subtitle')"
  >
    <div class="wa-stack wa-gap-l">
      <wa-card
        v-for="row in rows"
        :key="row.key"
      >
        <div class="wa-cluster wa-gap-m wa-align-items-center wa-justify-content-between">
          <div class="wa-stack wa-gap-3xs">
            <span class="status-title">{{ row.label }}</span>
            <code
              v-if="row.url"
              class="status-url"
            >{{ row.url }}</code>
          </div>
          <div class="wa-cluster wa-gap-s wa-align-items-center">
            <span
              v-if="row.item.ms !== null"
              class="status-ms"
            >{{ t('systemStatus.responseTime', { ms: row.item.ms }) }}</span>
            <wa-badge :variant="badge(row.item.state).variant">
              {{ badge(row.item.state).text }}
            </wa-badge>
          </div>
        </div>
      </wa-card>

      <div class="wa-cluster wa-gap-m wa-align-items-center">
        <wa-button
          variant="neutral"
          appearance="outlined"
          :loading="checking"
          @click="runChecks"
        >
          {{ t('systemStatus.recheck') }}
        </wa-button>
        <span
          v-if="lastChecked"
          class="status-ms"
        >{{ t('systemStatus.lastChecked', { time: lastChecked }) }}</span>
      </div>
    </div>
  </PageFrame>
</template>

<style scoped>
.status-title {
  font-weight: var(--wa-font-weight-semibold);
}

.status-url {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  overflow-wrap: anywhere;
}

.status-ms {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  white-space: nowrap;
}
</style>
