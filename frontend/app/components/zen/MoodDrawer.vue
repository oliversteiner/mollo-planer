<script setup lang="ts">
import { MOODS, conformance, contrastRatio, ramp, toneKey } from '~/utils/steingarten'

/**
 * "Stimmung" (docs/zen-modus.md §4.6): a panel from the right (from below on the phone) with sliders for brightness
 * and contrast, four moods, a colour strip, the contrast of text on the ground and "Zurücksetzen". The panel does
 * not dim the page (zen.css), so the change shows live behind it. Closing: Esc or a click next to it.
 */
const { t } = useI18n()
const { open } = useMoodDrawer()
const { brightness, contrast, colours, setMood, resetMood } = useZenMood()

const narrow = ref(false)

onMounted(() => {
  const query = window.matchMedia('(max-width: 40em)')
  const update = () => {
    narrow.value = query.matches
  }

  update()
  query.addEventListener('change', update)
  onBeforeUnmount(() => query.removeEventListener('change', update))
})

const tone = computed(() => t(`zen.mood.tones.${toneKey(brightness.value)}`))
const brightnessText = computed(() => `${tone.value}, ${t('zen.mood.percent', { value: brightness.value })}`)
const contrastText = computed(() => t('zen.mood.percent', { value: contrast.value }))
const ratio = computed(() => contrastRatio(colours.value.text, colours.value.bg))
const level = computed(() => conformance(ratio.value))

/** Nine fields, evenly over the ramp; the one closest to the brightness is marked. */
const strip = computed(() => Array.from({ length: 9 }, (_, index) => ({ position: index * 12.5, colour: ramp(index * 12.5) })))
const current = computed(() => Math.round(brightness.value / 12.5))

function onBrightness(event: Event): void {
  setMood(Number((event.target as HTMLInputElement).value), contrast.value)
}

function onContrast(event: Event): void {
  setMood(brightness.value, Number((event.target as HTMLInputElement).value))
}

/** The focus goes to the first slider when the drawer opens (the drawer gives it back to the opener when it closes). */
watch(open, async (value) => {
  if (value) {
    await nextTick()
    setTimeout(() => document.getElementById('mood-brightness')?.focus(), 150)
  }
})
</script>

<template>
  <AppDrawer
    v-model:open="open"
    :label="t('zen.mood.title')"
    :placement="narrow ? 'bottom' : 'end'"
    class="mood-drawer"
  >
    <div class="mood wa-stack wa-gap-l">
      <wa-slider
        id="mood-brightness"
        :label="t('zen.mood.brightness')"
        :hint="brightnessText"
        min="0"
        max="100"
        step="1"
        :value="brightness"
        with-tooltip
        @input="onBrightness"
      ></wa-slider>

      <wa-slider
        id="mood-contrast"
        :label="t('zen.mood.contrast')"
        :hint="contrastText"
        min="0"
        max="100"
        step="1"
        :value="contrast"
        with-tooltip
        @input="onContrast"
      ></wa-slider>

      <div
        class="wa-cluster wa-gap-xs"
        role="group"
        :aria-label="t('zen.mood.presets')"
      >
        <wa-button
          v-for="mood in MOODS"
          :key="mood.key"
          size="small"
          appearance="outlined"
          variant="neutral"
          :aria-pressed="brightness === mood.brightness && contrast === mood.contrast"
          @click="setMood(mood.brightness, mood.contrast)"
        >
          {{ t(`zen.mood.tones.${mood.key}`) }}
        </wa-button>
      </div>

      <div
        class="mood-strip"
        role="img"
        :aria-label="t('zen.mood.strip', { tone })"
      >
        <span
          v-for="(field, index) in strip"
          :key="field.position"
          class="mood-field"
          :data-current="index === current ? '' : undefined"
          :style="{ backgroundColor: field.colour }"
        />
      </div>

      <p
        class="mood-ratio"
        aria-live="polite"
      >
        {{ t('zen.mood.ratio', { ratio: ratio.toFixed(1) }) }}<template v-if="level">
          · {{ level }}
        </template>
      </p>

      <div>
        <wa-button
          appearance="plain"
          variant="neutral"
          size="small"
          @click="resetMood()"
        >
          <wa-icon
            slot="start"
            name="arrow-rotate-left"
          ></wa-icon>
          {{ t('zen.mood.reset') }}
        </wa-button>
      </div>
    </div>
  </AppDrawer>
</template>

<style scoped>
.mood-strip {
  display: grid;
  grid-template-columns: repeat(9, 1fr);
  overflow: hidden;
  border: var(--wa-border-width-s) var(--wa-border-style) var(--wa-color-surface-border);
  border-radius: var(--wa-border-radius-m);
}

.mood-field {
  block-size: 1.75rem;
}

.mood-field[data-current] {
  outline: 2px solid var(--wa-color-text-normal);
  outline-offset: -3px;
}

.mood-ratio {
  margin: 0;
  color: var(--wa-color-text-quiet);
  font-size: var(--wa-font-size-xs);
}
</style>
