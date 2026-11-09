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
  <wa-card>
    <h2
      slot="header"
      class="wa-heading-m"
    >
      {{ t('profile.details.title') }}
    </h2>

    <form
      class="wa-stack wa-gap-m"
      @submit.prevent="submit"
    >
      <FormInput
        v-model="state.name"
        name="name"
        autocomplete="name"
        :label="t('projects.fields.name')"
        :error="errors.name"
        required
      />
      <FormInput
        v-model="state.email"
        type="email"
        name="email"
        autocomplete="email"
        :label="t('auth.email')"
        :error="errors.email"
        required
      />
      <div>
        <wa-button
          type="submit"
          variant="brand"
          :loading="pending"
        >
          {{ t('common.save') }}
        </wa-button>
      </div>
    </form>
  </wa-card>
</template>
