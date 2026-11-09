<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { projectSettingsQuery } from '~/graphql/settings'

/**
 * Members of the project and their roles. Every member sees the list;
 * adding, changing roles and removing need MEMBERS_MANAGE.
 */
const { projectId, can } = useProject()

const { result } = useQuery(projectSettingsQuery, () => ({ id: projectId.value }), { fetchPolicy: 'cache-and-network' })
const project = computed(() => result.value?.project ?? null)
</script>

<template>
  <wa-skeleton
    v-if="!project"
    effect="sheen"
    class="members-skeleton"
  ></wa-skeleton>
  <ProjectMembersCard
    v-else
    :project-id="project.id"
    :members="project.members"
    :can-manage="can('MEMBERS_MANAGE')"
    class="members-page"
  />
</template>

<style scoped>
.members-skeleton {
  block-size: 10rem;
}

.members-page {
  max-inline-size: 48rem;
}
</style>
