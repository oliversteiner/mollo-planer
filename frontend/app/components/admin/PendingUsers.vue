<script setup lang="ts">
import type { AdminUsersQuery } from '~/graphql/generated/graphql'

type User = AdminUsersQuery['users'][number]

/**
 * The approval queue on the users page: self-registered accounts that wait
 * for an admin. Approving needs a confirmed mail address; the parent does the
 * change and reloads the list when it fails.
 */
defineProps<{
  users: User[]
}>()

const emit = defineEmits<{
  approve: [user: User]
  reject: [user: User]
}>()

const { t } = useI18n()
</script>

<template>
  <section
    v-if="users.length > 0"
    class="pending-users"
  >
    <h2 class="wa-heading-s">
      {{ t('admin.users.pending.title', { count: users.length }) }}
    </h2>
    <ul class="pending-list wa-list-plain">
      <li
        v-for="user in users"
        :key="user.id"
        class="pending-row"
      >
        <UserAvatar
          :name="user.name"
          :src="user.avatarUrl"
          size="md"
        />
        <span class="pending-text">
          <span class="pending-name">{{ user.name }}</span>
          <span class="pending-email">{{ user.email }}</span>
        </span>
        <wa-badge
          :variant="user.emailVerified ? 'brand' : 'warning'"
          appearance="filled"
        >
          {{ user.emailVerified ? t('admin.users.pending.verified') : t('admin.users.pending.unverified') }}
        </wa-badge>
        <wa-button
          size="small"
          variant="brand"
          :disabled="!user.emailVerified"
          @click="emit('approve', user)"
        >
          {{ t('admin.users.pending.approve') }}
        </wa-button>
        <wa-button
          size="small"
          appearance="outlined"
          variant="danger"
          @click="emit('reject', user)"
        >
          {{ t('admin.users.pending.reject') }}
        </wa-button>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.pending-users {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-s);
  margin-block-end: var(--wa-space-l);
}

.pending-list {
  margin: 0;
  padding: 0;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-l);
}

.pending-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-s) var(--wa-space-m);
}

.pending-row + .pending-row {
  border-block-start: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
}

.pending-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-inline-size: 0;
}

.pending-name {
  overflow: hidden;
  font-weight: var(--wa-font-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pending-email {
  overflow: hidden;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
