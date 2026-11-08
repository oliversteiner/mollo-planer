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
  <div class="space-y-3">
    <UAlert
      color="warning"
      variant="subtle"
      icon="i-fadl-triangle-exclamation"
      :title="t('security.recovery.keepSafe')"
    />
    <ul class="grid grid-cols-2 gap-1 rounded-md bg-elevated p-3 font-mono text-sm">
      <li
        v-for="code in codes"
        :key="code"
      >
        {{ code }}
      </li>
    </ul>
    <div class="flex gap-2">
      <UButton
        icon="i-fadl-copy"
        :label="t('security.recovery.copy')"
        color="neutral"
        variant="outline"
        size="sm"
        @click="copy"
      />
      <UButton
        icon="i-fadl-download"
        :label="t('security.recovery.download')"
        color="neutral"
        variant="outline"
        size="sm"
        @click="download"
      />
    </div>
  </div>
</template>
