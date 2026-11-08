<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { projectHeaderQuery } from '~/graphql/projects'

/** Placeholder until step 6 (milestones and todos). */
const { t } = useI18n()
const route = useRoute()

const { result, loading } = useQuery(projectHeaderQuery, () => ({ id: String(route.params.id) }))
const project = computed(() => result.value?.project)

useHead({ title: () => project.value?.name ?? t('projects.title') })
</script>

<template>
  <UDashboardPanel id="project">
    <template #header>
      <UDashboardNavbar :title="project?.name ?? ''">
        <template #leading>
          <UButton
            to="/projects"
            icon="i-fadl-arrow-left"
            color="neutral"
            variant="ghost"
            :aria-label="t('projects.title')"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <USkeleton
        v-if="loading && !result"
        class="h-16"
      />
      <UEmpty
        v-else-if="!project"
        icon="i-fadl-folder-open"
        :title="t('projects.notFound')"
      />
      <template v-else>
        <p
          v-if="project.description"
          class="text-muted"
        >
          {{ project.description }}
        </p>
        <UAlert
          color="info"
          variant="subtle"
          :title="t('projects.comingSoon')"
        />
      </template>
    </template>
  </UDashboardPanel>
</template>
