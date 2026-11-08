<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { projectsQuery } from '~/graphql/projects'

const { t } = useI18n()
const { viewer } = useViewer()

useHead({ title: () => t('projects.title') })

const { result, loading } = useQuery(projectsQuery, null, { fetchPolicy: 'cache-and-network' })

const projects = computed(() => result.value?.projects ?? [])
const creating = ref(false)
</script>

<template>
  <UDashboardPanel id="projects">
    <template #header>
      <UDashboardNavbar :title="t('projects.title')">
        <template #right>
          <UButton
            v-if="viewer?.isAdmin"
            icon="i-fadl-folder-plus"
            :label="t('projects.create.open')"
            @click="creating = true"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <USkeleton
        v-if="loading && !result"
        class="h-32"
      />
      <UEmpty
        v-else-if="!projects.length"
        icon="i-fadl-folder-open"
        :title="t('projects.none')"
        :description="viewer?.isAdmin ? t('projects.noneAdmin') : t('projects.noneMember')"
      />
      <ul
        v-else
        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
      >
        <li
          v-for="project in projects"
          :key="project.id"
        >
          <NuxtLink
            :to="`/projects/${project.id}`"
            class="flex h-full flex-col gap-3 rounded-lg border border-default p-4 hover:border-primary"
          >
            <span class="flex items-start justify-between gap-2">
              <span class="font-semibold text-highlighted">{{ project.name }}</span>
              <UBadge
                v-if="project.viewerRole"
                color="neutral"
                variant="outline"
                size="sm"
              >
                {{ t(`role.${project.viewerRole}`) }}
              </UBadge>
            </span>
            <span
              v-if="project.description"
              class="line-clamp-2 text-sm text-muted"
            >{{ project.description }}</span>
            <span class="mt-auto">
              <UProgress
                :model-value="project.progress.percent"
                size="sm"
              />
              <span class="mt-1 flex items-center justify-between gap-2 text-sm text-muted">
                <span>{{ t('projects.progress', { done: project.progress.done, total: project.progress.total }) }}</span>
                <UAvatarGroup
                  size="2xs"
                  :max="5"
                >
                  <UAvatar
                    v-for="member in project.members"
                    :key="member.user.id"
                    :src="member.user.avatarUrl ?? undefined"
                    :alt="member.user.name"
                  />
                </UAvatarGroup>
              </span>
            </span>
          </NuxtLink>
        </li>
      </ul>

      <ProjectCreateModal v-model:open="creating" />
    </template>
  </UDashboardPanel>
</template>
