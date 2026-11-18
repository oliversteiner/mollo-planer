<script setup lang="ts">
import { useQuery } from '@vue/apollo-composable'
import { sidebarProjectsQuery } from '~/graphql/projects'
import { filterCommands, isZenQuery, pageCommands, visibleCommands, withFirst, type Command } from '~/utils/commands'

/**
 * The command palette (docs/zen-modus.md §4.4): Ctrl/Cmd+K or the corner button of the zen frame. Search without
 * accents; arrows move, Enter runs, Esc closes. Sources: the actions of the page (useCommands), the pages (their
 * rights are those of the menu) and the projects. An entry without the right is not there.
 * It works in every view, also the normal one.
 */
const { t } = useI18n()
const { viewer, can, logout } = useViewer()
const { open, hide } = useCommandPalette()
const { mode, focusing, setMode, leaveFocus } = useViewMode()
const zen = useZen()
const pageActions = usePageCommands()
const mood = useMoodDrawer()

const { result } = useQuery(sidebarProjectsQuery, null, { fetchPolicy: 'cache-first', enabled: computed(() => viewer.value !== null) })

const query = ref('')
const active = ref(0)
const list = ref<HTMLElement | null>(null)

const general = computed<Command[]>(() => [
  {
    id: 'zen-start',
    label: t('zen.startHit', { area: zen.areaLabel.value }),
    icon: 'leaf',
    group: 'action',
    keywords: ['zen', 'ruhig', 'calm'],
    visible: () => mode.value === 'full',
    run: () => zen.start()
  },
  {
    id: 'zen-leave',
    label: t('zen.leave'),
    icon: 'expand',
    group: 'action',
    keywords: ['zen', 'ruhig', 'calm'],
    visible: () => mode.value !== 'full',
    run: () => {
      setMode('full')

      return focusing.value ? leaveFocus() : undefined
    }
  },
  {
    id: 'focus-leave',
    label: t('zen.leaveFocus'),
    icon: 'arrow-left',
    group: 'action',
    visible: () => mode.value === 'focus',
    run: () => leaveFocus()
  },
  {
    id: 'mood',
    label: t('zen.mood.title'),
    icon: 'palette',
    group: 'action',
    keywords: ['stimmung', 'mood', 'farbe', 'colour', 'zen'],
    run: async () => {
      // Without zen the colours would not show: it starts first (docs/zen-modus.md §4.6).
      if (mode.value === 'full') {
        await zen.start()
      }

      mood.show()
    }
  },
  { id: 'logout', label: t('auth.logout'), icon: 'right-from-bracket', group: 'action', run: () => logout() }
])

const projects = computed<Command[]>(() => [...(result.value?.projects ?? [])]
  .sort((a, b) => a.name.localeCompare(b.name))
  .map(project => ({
    id: `project-${project.id}`,
    label: project.name,
    icon: 'folder',
    group: 'project' as const,
    run: async () => {
      await navigateTo(`/projects/${project.id}`)
    }
  })))

const all = computed(() => visibleCommands(
  [...pageActions.value, ...general.value, ...pageCommands(key => t(key), async (to) => {
    await navigateTo(to)
  }), ...projects.value],
  can
))
/** Zen entries first: for "zen" and its relatives, and in Zen with an empty input (leave and mood at the top). */
const zenIds = computed(() => (mode.value !== 'full' ? ['zen-leave', 'mood'] : ['zen-start', 'mood']))
const shown = computed(() => {
  const found = filterCommands(all.value, query.value)
  const first = isZenQuery(query.value) || (mode.value !== 'full' && query.value.trim() === '')

  return withFirst(found, first ? zenIds.value : []).slice(0, 50)
})

const groups = computed(() => (['action', 'page', 'project'] as const)
  .map(group => ({ group, items: shown.value.filter(command => command.group === group) }))
  .filter(entry => entry.items.length > 0))

watch(shown, () => {
  active.value = 0
})

watch(open, async (value) => {
  if (value) {
    query.value = ''
    active.value = 0
    // wa-dialog shows with an animation; the field takes the focus when it is there.
    setTimeout(() => document.getElementById('command-search')?.focus(), 120)
  }
})

function indexOf(command: Command): number {
  return shown.value.indexOf(command)
}

async function run(command: Command): Promise<void> {
  hide()
  await command.run()
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    active.value = Math.min(active.value + 1, shown.value.length - 1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    active.value = Math.max(active.value - 1, 0)
  } else if (event.key === 'Enter' && shown.value[active.value]) {
    event.preventDefault()
    void run(shown.value[active.value]!)
  }
}

watch(active, async () => {
  await nextTick()
  list.value?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' })
})
</script>

<template>
  <AppDialog
    v-model:open="open"
    :label="t('command.title')"
    light-dismiss
  >
    <div class="wa-stack wa-gap-s">
      <FormInput
        id="command-search"
        v-model="query"
        :label="t('command.search')"
        hide-label
        :placeholder="t('command.placeholder')"
        autofocus
        autocomplete="off"
        role="combobox"
        aria-controls="command-list"
        aria-expanded="true"
        :aria-activedescendant="shown[active] ? `command-${shown[active]!.id}` : undefined"
        @keydown="onKeydown"
      />

      <div
        id="command-list"
        ref="list"
        class="command-list"
        role="listbox"
        :aria-label="t('command.title')"
      >
        <p
          v-if="!shown.length"
          class="command-empty"
        >
          {{ t('command.none') }}
        </p>
        <template
          v-for="entry in groups"
          :key="entry.group"
        >
          <h3 class="command-group">
            {{ t(`command.group.${entry.group}`) }}
          </h3>
          <div
            v-for="command in entry.items"
            :id="`command-${command.id}`"
            :key="command.id"
            class="command-item"
            role="option"
            :aria-selected="indexOf(command) === active"
            @click="run(command)"
            @mousemove="active = indexOf(command)"
          >
            <wa-icon :name="command.icon ?? 'angle-right'"></wa-icon>
            <span>{{ command.label }}</span>
          </div>
        </template>
      </div>
    </div>
  </AppDialog>
</template>

<style scoped>
.command-list {
  max-block-size: 50dvh;
  overflow-y: auto;
}

.command-group {
  margin: var(--wa-space-s) 0 var(--wa-space-3xs);
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
  font-weight: var(--wa-font-weight-semibold);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.command-item {
  display: flex;
  align-items: center;
  gap: var(--wa-space-s);
  padding: var(--wa-space-xs) var(--wa-space-s);
  border-radius: var(--wa-border-radius-m);
  cursor: pointer;
}

.command-item[aria-selected='true'] {
  background-color: var(--wa-color-brand-fill-quiet);
}

.command-empty {
  margin: 0;
  color: var(--wa-color-text-quiet);
}
</style>
