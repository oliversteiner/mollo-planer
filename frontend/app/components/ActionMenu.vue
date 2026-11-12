<script setup lang="ts">
/**
 * Menu behind a "…" button (wa-dropdown). With `text` (and `icon`) the button
 * shows a labelled button with a caret instead. The entries are data: a label,
 * an optional Font Awesome icon, `color: 'error'` for something destructive and
 * `onSelect`. Several groups (an array of arrays) are separated by a line.
 */
export interface ActionMenuItem {
  label: string
  /** Font Awesome name, for example "pen". */
  icon?: string
  color?: 'error'
  disabled?: boolean
  onSelect?: () => void
}

const props = withDefaults(defineProps<{
  items: readonly ActionMenuItem[] | ReadonlyArray<readonly ActionMenuItem[]>
  /** Name of the button for screen readers. */
  label: string
  /** Text on the button instead of the "…" icon, for example "Share". */
  text?: string
  /** Font Awesome name of the icon in front of `text`. */
  icon?: string
  disabled?: boolean
  size?: 's' | 'm'
  placement?: 'bottom-start' | 'bottom-end'
}>(), {
  size: 's',
  placement: 'bottom-end'
})

/** The entries in groups, each with its running number (the value of its menu item). */
const groups = computed(() => {
  const nested = (props.items.length > 0 && Array.isArray(props.items[0])
    ? props.items
    : [props.items]) as ReadonlyArray<readonly ActionMenuItem[]>
  let index = 0

  return nested
    .filter(group => group.length > 0)
    .map(group => group.map(item => ({ item, index: index++ })))
})

function onSelect(event: CustomEvent<{ item: { value: string } }>): void {
  const wanted = Number(event.detail.item.value)

  for (const group of groups.value) {
    group.find(entry => entry.index === wanted)?.item.onSelect?.()
  }
}
</script>

<template>
  <wa-dropdown
    :placement="placement"
    @wa-select="onSelect"
  >
    <wa-button
      slot="trigger"
      :appearance="text ? 'outlined' : 'plain'"
      variant="neutral"
      :size="size"
      :disabled="disabled"
      :with-caret="!!text"
      :aria-label="label"
    >
      <template v-if="text">
        <wa-icon
          v-if="icon"
          :name="icon"
          class="action-menu-icon"
        ></wa-icon>
        {{ text }}
      </template>
      <wa-icon
        v-else
        name="ellipsis"
      ></wa-icon>
    </wa-button>
    <template
      v-for="(group, groupIndex) in groups"
      :key="groupIndex"
    >
      <wa-divider v-if="groupIndex > 0"></wa-divider>
      <wa-dropdown-item
        v-for="entry in group"
        :key="entry.index"
        :value="String(entry.index)"
        :variant="entry.item.color === 'error' ? 'danger' : 'default'"
        :disabled="entry.item.disabled"
      >
        <wa-icon
          v-if="entry.item.icon"
          slot="icon"
          :name="entry.item.icon"
        ></wa-icon>
        {{ entry.item.label }}
      </wa-dropdown-item>
    </template>
  </wa-dropdown>
</template>

<style scoped>
.action-menu-icon {
  margin-inline-end: var(--wa-space-3xs);
}
</style>
