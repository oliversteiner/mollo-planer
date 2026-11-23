<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { projectStatisticsQuery } from '~/graphql/statistics'
import { formatDate } from '~/utils/dates'

/**
 * The statistics of the project: key numbers, tasks by status, label and person, due dates, one row per
 * milestone and a few more counts. Everyone who may see the project may see them (also an archived one).
 * The numbers come counted from the backend (the trash left out); every bar also has its number next to it.
 * The course shows the tasks done per week; tasks done before the time was recorded have none and are not in it.
 */
const { t, localeProperties } = useI18n()
const { projectId } = useProject()
const language = computed(() => localeProperties.value.language ?? 'de-CH')

const { result, loading } = useQuery(projectStatisticsQuery, () => ({ projectId: projectId.value }), { fetchPolicy: 'cache-and-network' })
const stats = computed(() => result.value?.projectStatistics)

/** A project without milestones, todos and tasks has nothing to count. */
const isEmpty = computed(() => stats.value !== undefined && stats.value.milestones.total === 0 && stats.value.todos === 0 && stats.value.tasks === 0)

/** The most done tasks of one week: the longest bar of the course. */
const busiestWeek = computed(() => Math.max(0, ...(stats.value?.doneByWeek ?? []).map(week => week.count)))

/** The length of a bar: the share of a part in the whole, 0 of nothing. The number beside it is the real value. */
function share(part: number, whole: number): number {
  return whole === 0 ? 0 : Math.round(part * 100 / whole)
}
</script>

<template>
  <div class="wa-stack wa-gap-l statistics-page">
    <wa-skeleton
      v-if="loading && !stats"
      effect="sheen"
      class="statistics-skeleton"
    ></wa-skeleton>
    <EmptyState
      v-else-if="isEmpty"
      icon="chart-simple"
      :title="t('project.statistics.emptyTitle')"
      :description="t('project.statistics.emptyDescription')"
    />
    <template v-else-if="stats">
      <ul class="statistics-kpis wa-list-plain">
        <li>
          <wa-card>
            <span class="statistics-kpi">
              <span class="statistics-kpi-label">{{ t('project.statistics.kpiMilestones') }}</span>
              <span class="statistics-kpi-value">{{ stats.milestones.total }}</span>
              <span class="statistics-sub">{{ t('project.statistics.kpiMilestonesDetail', stats.milestones) }}</span>
            </span>
          </wa-card>
        </li>
        <li>
          <wa-card>
            <span class="statistics-kpi">
              <span class="statistics-kpi-label">{{ t('project.statistics.kpiTodos') }}</span>
              <span class="statistics-kpi-value">{{ stats.todos }}</span>
            </span>
          </wa-card>
        </li>
        <li>
          <wa-card>
            <span class="statistics-kpi">
              <span class="statistics-kpi-label">{{ t('project.statistics.kpiTasks') }}</span>
              <span class="statistics-kpi-value">{{ stats.tasks }}</span>
            </span>
          </wa-card>
        </li>
        <li>
          <wa-card>
            <span class="statistics-kpi">
              <span class="statistics-kpi-label">{{ t('project.statistics.kpiDone') }}</span>
              <span class="statistics-kpi-value">{{ t('project.statistics.percent', { value: stats.progress.percent }) }}</span>
              <span class="statistics-sub">{{ t('project.statistics.kpiDoneDetail', { done: stats.progress.done, total: stats.progress.total }) }}</span>
            </span>
          </wa-card>
        </li>
      </ul>

      <div class="statistics-columns">
        <wa-card>
          <h2
            slot="header"
            class="wa-heading-m"
          >
            {{ t('project.statistics.byStatus') }}
          </h2>
          <ul class="statistics-rows wa-list-plain">
            <li
              v-for="row in stats.tasksByStatus"
              :key="row.status"
              class="statistics-row"
            >
              <StatusBadge :status="row.status" />
              <wa-progress-bar
                :value="row.percent"
                :label="t('project.statistics.countAndPercent', { count: row.count, percent: row.percent })"
              ></wa-progress-bar>
              <span class="statistics-number">{{ t('project.statistics.countAndPercent', { count: row.count, percent: row.percent }) }}</span>
            </li>
          </ul>
        </wa-card>

        <wa-card>
          <h2
            slot="header"
            class="wa-heading-m"
          >
            {{ t('project.statistics.byLabel') }}
          </h2>
          <ul class="statistics-rows wa-list-plain">
            <li
              v-for="row in stats.tasksByLabel"
              :key="row.label?.id ?? 'none'"
              class="statistics-row"
            >
              <TaskLabels
                v-if="row.label"
                :labels="[row.label]"
              />
              <span v-else>{{ t('project.statistics.noLabel') }}</span>
              <wa-progress-bar
                :value="share(row.count, stats.tasks)"
                :label="String(row.count)"
              ></wa-progress-bar>
              <span class="statistics-number">{{ row.count }}</span>
            </li>
          </ul>
        </wa-card>

        <wa-card>
          <h2
            slot="header"
            class="wa-heading-m"
          >
            {{ t('project.statistics.byPerson') }}
          </h2>
          <ul class="statistics-rows wa-list-plain">
            <li
              v-for="row in stats.tasksByPerson"
              :key="row.user?.id ?? 'none'"
              class="statistics-row"
            >
              <span class="statistics-person">
                <template v-if="row.user">
                  <UserAvatar
                    :name="row.user.name"
                    :src="row.user.avatarUrl"
                    size="xs"
                  />
                  <span class="statistics-person-name">{{ row.user.name }}</span>
                  <AgentBadge v-if="row.user.isAgent" />
                </template>
                <span v-else>{{ t('project.statistics.unassigned') }}</span>
              </span>
              <wa-progress-bar
                :value="share(row.done, row.open + row.done)"
                :label="t('project.statistics.openAndDone', { open: row.open, done: row.done })"
              ></wa-progress-bar>
              <span class="statistics-number">{{ t('project.statistics.openAndDone', { open: row.open, done: row.done }) }}</span>
            </li>
          </ul>
        </wa-card>

        <wa-card>
          <h2
            slot="header"
            class="wa-heading-m"
          >
            {{ t('project.statistics.due') }}
          </h2>
          <ul class="statistics-rows wa-list-plain">
            <li class="statistics-line">
              <span>{{ t('project.statistics.overdue') }}</span>
              <span class="statistics-number">{{ stats.due.overdue }}</span>
            </li>
            <li class="statistics-line">
              <span>{{ t('project.statistics.soon') }}</span>
              <span class="statistics-number">{{ stats.due.soon }}</span>
            </li>
            <li class="statistics-line">
              <span>{{ t('project.statistics.undated') }}</span>
              <span class="statistics-number">{{ stats.due.undated }}</span>
            </li>
          </ul>
        </wa-card>
      </div>

      <wa-card>
        <h2
          slot="header"
          class="wa-heading-m"
        >
          {{ t('project.statistics.perMilestone') }}
        </h2>
        <div
          class="statistics-frame"
          role="region"
          tabindex="0"
          :aria-label="t('project.statistics.table')"
        >
          <table class="statistics-table">
            <thead>
              <tr>
                <th scope="col">
                  {{ t('project.statistics.colMilestone') }}
                </th>
                <th scope="col">
                  {{ t('project.statistics.colVersion') }}
                </th>
                <th scope="col">
                  {{ t('project.statistics.colStatus') }}
                </th>
                <th scope="col">
                  {{ t('project.statistics.colDate') }}
                </th>
                <th
                  scope="col"
                  class="statistics-cell-number"
                >
                  {{ t('project.statistics.colTodos') }}
                </th>
                <th
                  scope="col"
                  class="statistics-cell-number"
                >
                  {{ t('project.statistics.colTasks') }}
                </th>
                <th scope="col">
                  {{ t('project.statistics.colDone') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in stats.perMilestone"
                :key="row.milestone.id"
              >
                <th scope="row">
                  <NuxtLink
                    :to="`/projects/${projectId}#milestone-${row.milestone.id}`"
                    class="statistics-link"
                  >
                    {{ row.milestone.title }}
                  </NuxtLink>
                </th>
                <td>
                  <template v-if="row.milestone.release">
                    {{ row.milestone.release.version }} · {{ t(`release.${row.milestone.release.status.toLowerCase()}`) }}
                  </template>
                  <template v-else>
                    {{ t('project.statistics.noVersion') }}
                  </template>
                </td>
                <td>
                  <StatusBadge :status="row.milestone.status" />
                </td>
                <td>
                  <template v-if="row.milestone.release?.date">
                    {{ formatDate(row.milestone.release.date, language, true) }}
                  </template>
                  <template v-else>
                    {{ t('project.statistics.noVersion') }}
                  </template>
                </td>
                <td class="statistics-cell-number">
                  {{ row.todos }}
                </td>
                <td class="statistics-cell-number">
                  {{ row.tasks }}
                </td>
                <td>
                  <span class="statistics-done">
                    <wa-progress-bar
                      :value="row.progress.percent"
                      :label="t('project.statistics.percent', { value: row.progress.percent })"
                    ></wa-progress-bar>
                    <span class="statistics-number">{{ t('project.statistics.percent', { value: row.progress.percent }) }}</span>
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </wa-card>

      <wa-card>
        <h2
          slot="header"
          class="wa-heading-m"
        >
          {{ t('project.statistics.course') }}
        </h2>
        <ul class="statistics-rows wa-list-plain">
          <li
            v-for="week in stats.doneByWeek"
            :key="week.weekStart"
            class="statistics-row"
          >
            <span>{{ t('project.statistics.courseWeek', { date: formatDate(week.weekStart, language, true) }) }}</span>
            <wa-progress-bar
              :value="share(week.count, busiestWeek)"
              :label="String(week.count)"
            ></wa-progress-bar>
            <span class="statistics-number">{{ week.count }}</span>
          </li>
        </ul>
        <p class="statistics-sub statistics-hint">
          {{ t('project.statistics.courseHint') }}
        </p>
      </wa-card>

      <wa-card>
        <h2
          slot="header"
          class="wa-heading-m"
        >
          {{ t('project.statistics.more') }}
        </h2>
        <ul class="statistics-rows wa-list-plain">
          <li class="statistics-line">
            <span>{{ t('project.statistics.withCommit') }}</span>
            <span class="statistics-number">{{ stats.withCommit }}</span>
          </li>
          <li class="statistics-line">
            <span>{{ t('project.statistics.comments') }}</span>
            <span class="statistics-number">{{ stats.comments }}</span>
          </li>
        </ul>
      </wa-card>
    </template>
  </div>
</template>

<style scoped>
.statistics-page {
  max-inline-size: 72rem;
}

.statistics-skeleton {
  block-size: 8rem;
}

.statistics-kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr));
  gap: var(--wa-space-m);
  margin: 0;
  padding: 0;
}

.statistics-kpi {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-3xs);
}

.statistics-kpi-label,
.statistics-sub {
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-s);
}

.statistics-kpi-value {
  font-size: var(--wa-font-size-2xl);
  font-weight: var(--wa-font-weight-semibold);
}

.statistics-columns {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 24rem), 1fr));
  gap: var(--wa-space-l);
}

.statistics-rows {
  display: flex;
  flex-direction: column;
  gap: var(--wa-space-s);
  margin: 0;
  padding: 0;
}

.statistics-row {
  display: grid;
  grid-template-columns: minmax(6rem, 10rem) minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--wa-space-s);
}

.statistics-row wa-progress-bar,
.statistics-done wa-progress-bar {
  --track-height: 0.5rem;
}

.statistics-hint {
  margin-block: var(--wa-space-m) 0;
}

.statistics-line {
  display: flex;
  justify-content: space-between;
  gap: var(--wa-space-s);
}

.statistics-number {
  font-size: var(--wa-font-size-s);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.statistics-person {
  display: flex;
  align-items: center;
  gap: var(--wa-space-xs);
  min-inline-size: 0;
}

.statistics-person-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.statistics-frame {
  overflow: auto;
}

.statistics-frame:focus-visible {
  outline: var(--wa-focus-ring);
  outline-offset: var(--wa-focus-ring-offset);
}

.statistics-table {
  inline-size: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: var(--wa-font-size-s);
}

.statistics-table th,
.statistics-table td {
  padding: var(--wa-space-xs) var(--wa-space-s);
  border-block-end: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  text-align: start;
  vertical-align: middle;
  white-space: nowrap;
}

.statistics-table tbody tr:last-child th,
.statistics-table tbody tr:last-child td {
  border-block-end: none;
}

.statistics-table thead th {
  color: var(--wa-color-text-quiet);
  font-weight: var(--wa-font-weight-semibold);
}

.statistics-table tbody th {
  font-weight: var(--wa-font-weight-semibold);
}

.statistics-cell-number {
  font-variant-numeric: tabular-nums;
  text-align: end;
}

.statistics-link {
  color: inherit;
  text-decoration: none;
}

.statistics-link:hover {
  color: var(--wa-color-brand-on-quiet);
}

.statistics-done {
  display: grid;
  grid-template-columns: minmax(5rem, 8rem) auto;
  align-items: center;
  gap: var(--wa-space-s);
}
</style>
