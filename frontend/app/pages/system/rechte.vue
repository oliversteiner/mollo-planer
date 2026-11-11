<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { permissionMatrixQuery } from '~/graphql/rights'

/**
 * The rights table: rows are abilities, columns are roles. Read from the
 * backend (the same rules the policies use). Admins only.
 */
definePageMeta({ middleware: 'admin' })

const { t } = useI18n()

useHead({ title: () => t('rights.title') })

const { result, loading } = useQuery(permissionMatrixQuery, null, { fetchPolicy: 'cache-and-network' })

const SYSTEM_ROLES = ['ADMIN', 'PROJEKTLEITUNG', 'KOORDINATION', 'MITGLIED', 'LESEND'] as const
const PROJECT_ROLES = ['OWNER', 'MANAGER', 'MEMBER'] as const

const system = computed(() => result.value?.permissionMatrix.system ?? [])
const project = computed(() => result.value?.permissionMatrix.project ?? [])

/** Whether a role has the ability in the row (the rows are the backend answer). */
function has(roles: readonly string[], role: string): boolean {
  return roles.includes(role)
}
</script>

<template>
  <PageFrame
    :title="t('rights.title')"
    :breadcrumbs="[{ label: t('nav.admin') }, { label: t('rights.title') }]"
    :description="t('rights.subtitle')"
  >
    <wa-skeleton
      v-if="loading && !result"
      effect="sheen"
    ></wa-skeleton>

    <div
      v-else
      class="wa-stack wa-gap-l"
    >
      <section class="wa-stack wa-gap-xs">
        <h2 class="wa-heading-m">
          {{ t('rights.system') }}
        </h2>
        <p class="rights-hint wa-body-s">
          {{ t('rights.systemHint') }}
        </p>
        <div class="rights-scroll">
          <table class="rights-table">
            <thead>
              <tr>
                <th scope="col">
                  {{ t('rights.ability') }}
                </th>
                <th
                  v-for="role in SYSTEM_ROLES"
                  :key="role"
                  scope="col"
                >
                  {{ t(`userRole.${role}`) }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in system"
                :key="row.ability"
              >
                <th scope="row">
                  {{ t(`rights.abilities.${row.ability}`) }}
                </th>
                <td
                  v-for="role in SYSTEM_ROLES"
                  :key="role"
                  class="rights-cell"
                >
                  <wa-icon
                    v-if="has(row.roles, role)"
                    name="check"
                    :label="t('rights.yes')"
                    class="rights-yes"
                  ></wa-icon>
                  <span
                    v-else
                    class="rights-no"
                  >
                    <span class="wa-visually-hidden">{{ t('rights.no') }}</span>
                    –
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="wa-stack wa-gap-xs">
        <h2 class="wa-heading-m">
          {{ t('rights.project') }}
        </h2>
        <p class="rights-hint wa-body-s">
          {{ t('rights.projectHint') }}
        </p>
        <div class="rights-scroll">
          <table class="rights-table">
            <thead>
              <tr>
                <th scope="col">
                  {{ t('rights.ability') }}
                </th>
                <th
                  v-for="role in PROJECT_ROLES"
                  :key="role"
                  scope="col"
                >
                  {{ t(`role.${role}`) }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in project"
                :key="row.ability"
              >
                <th scope="row">
                  {{ t(`rights.abilities.${row.ability}`) }}
                </th>
                <td
                  v-for="role in PROJECT_ROLES"
                  :key="role"
                  class="rights-cell"
                >
                  <wa-icon
                    v-if="has(row.roles, role)"
                    name="check"
                    :label="t('rights.yes')"
                    class="rights-yes"
                  ></wa-icon>
                  <span
                    v-else
                    class="rights-no"
                  >
                    <span class="wa-visually-hidden">{{ t('rights.no') }}</span>
                    –
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <wa-callout variant="neutral">
        <p>{{ t('rights.admin') }}</p>
        <p>{{ t('rights.readOnly') }}</p>
      </wa-callout>
    </div>
  </PageFrame>
</template>

<style scoped>
.rights-hint {
  margin: 0;
  color: var(--wa-color-text-quiet);
}

.rights-scroll {
  overflow-x: auto;
}

.rights-table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--wa-font-size-s);
}

.rights-table th,
.rights-table td {
  padding: var(--wa-space-xs) var(--wa-space-s);
  border-block-end: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  text-align: start;
  vertical-align: middle;
}

.rights-table thead th {
  color: var(--wa-color-text-quiet);
  font-weight: var(--wa-font-weight-semibold);
}

.rights-table th[scope='row'] {
  font-weight: normal;
}

.rights-cell {
  text-align: center;
}

.rights-yes {
  color: var(--wa-color-success-on-normal);
}

.rights-no {
  color: var(--wa-color-text-quiet);
}
</style>
