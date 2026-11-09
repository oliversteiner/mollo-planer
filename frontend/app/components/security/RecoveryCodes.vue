<script setup lang="ts">
/** Recovery codes to keep safe: copy or download as a text file. */
const props = defineProps<{ codes: string[] }>()

const { t } = useI18n()
const toast = useToast()

async function copy(): Promise<void> {
  await navigator.clipboard.writeText(props.codes.join('\n'))
  toast.add({ title: t('security.recovery.copied'), color: 'success' })
}

function download(): void {
  const url = URL.createObjectURL(new Blob([`${props.codes.join('\n')}\n`], { type: 'text/plain' }))
  const link = document.createElement('a')
  link.href = url
  link.download = 'recovery-codes.txt'
  link.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="wa-stack wa-gap-s">
    <AppCallout
      variant="warning"
      :title="t('security.recovery.keepSafe')"
    />
    <ul class="recovery-codes wa-list-plain">
      <li
        v-for="code in codes"
        :key="code"
      >
        {{ code }}
      </li>
    </ul>
    <div class="wa-cluster wa-gap-s">
      <wa-button
        appearance="outlined"
        variant="neutral"
        size="small"
        @click="copy"
      >
        <wa-icon
          slot="start"
          name="copy"
        ></wa-icon>
        {{ t('security.recovery.copy') }}
      </wa-button>
      <wa-button
        appearance="outlined"
        variant="neutral"
        size="small"
        @click="download"
      >
        <wa-icon
          slot="start"
          name="download"
        ></wa-icon>
        {{ t('security.recovery.download') }}
      </wa-button>
    </div>
  </div>
</template>

<style scoped>
.recovery-codes {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--wa-space-3xs);
  margin: 0;
  padding: var(--wa-space-s);
  border-radius: var(--wa-border-radius-m);
  background-color: var(--wa-color-surface-lowered);
  font-family: var(--wa-font-family-code);
  font-size: var(--wa-font-size-s);
}
</style>
