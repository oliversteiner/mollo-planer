<script setup lang="ts">
/** Name and e-mail (Fortify: PUT /user/profile-information). */
const { t } = useI18n()
const toast = useToast()
const { $backend } = useNuxtApp()
const { viewer, load } = useViewer()
const { errors, pending, run } = useServerForm()

const state = reactive({ name: viewer.value?.name ?? '', email: viewer.value?.email ?? '' })

async function submit(): Promise<void> {
  const ok = await run(() => $backend('/user/profile-information', { method: 'PUT', body: state }))

  if (ok) {
    await load()
    toast.add({ title: t('profile.details.saved'), color: 'success' })
  }
}
</script>

<template>
  <UCard>
    <template #header>
      <h2 class="font-semibold text-highlighted">
        {{ t('profile.details.title') }}
      </h2>
    </template>

    <UForm
      :state="state"
      class="space-y-4"
      @submit="submit"
    >
      <UFormField
        :label="t('projects.fields.name')"
        name="name"
        :error="errors.name"
        required
      >
        <UInput
          v-model="state.name"
          autocomplete="name"
          class="w-full"
          required
        />
      </UFormField>
      <UFormField
        :label="t('auth.email')"
        name="email"
        :error="errors.email"
        required
      >
        <UInput
          v-model="state.email"
          type="email"
          autocomplete="email"
          class="w-full"
          required
        />
      </UFormField>
      <UButton
        type="submit"
        :label="t('common.save')"
        :loading="pending"
      />
    </UForm>
  </UCard>
</template>
