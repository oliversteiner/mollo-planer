<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import { deleteUserAvatarMutation, updateMyAvatarMutation } from '~/graphql/profile'
import { avatarProblem } from '~/utils/avatar'
import { classifyApolloError } from '~/utils/api'

/**
 * Own avatar: JPG or PNG up to 5 MB, cropped by the server to a square of
 * 500 x 500 pixels. Checked here first so large photos are not sent.
 */
const { t } = useI18n()
const toast = useToast()
const { viewer, load } = useViewer()

const input = ref<HTMLInputElement | null>(null)
const pending = ref(false)
const error = ref('')

const { mutate: upload } = useMutation(updateMyAvatarMutation)
const { mutate: remove } = useMutation(deleteUserAvatarMutation)

async function onFile(event: Event): Promise<void> {
  const file = (event.target as HTMLInputElement).files?.[0]
  ;(event.target as HTMLInputElement).value = ''
  error.value = ''

  if (!file) {
    return
  }
  const problem = avatarProblem(file)

  if (problem) {
    error.value = t(`profile.avatar.${problem}`)

    return
  }

  pending.value = true
  try {
    await upload({ avatar: file })
    await load()
    toast.add({ title: t('profile.avatar.saved'), color: 'success' })
  } catch (failure) {
    const apiError = classifyApolloError(failure as Parameters<typeof classifyApolloError>[0])
    error.value = apiError.fields.avatar?.[0] ?? ''
  } finally {
    pending.value = false
  }
}

async function removeAvatar(): Promise<void> {
  if (!viewer.value) {
    return
  }
  pending.value = true
  try {
    await remove({ id: viewer.value.id })
    await load()
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <UCard>
    <template #header>
      <h2 class="font-semibold text-highlighted">
        {{ t('profile.avatar.title') }}
      </h2>
    </template>

    <div class="flex flex-wrap items-center gap-4">
      <UAvatar
        :src="viewer?.avatarUrl ?? undefined"
        :alt="viewer?.name"
        size="3xl"
      />
      <div class="space-y-2">
        <div class="flex flex-wrap gap-2">
          <UButton
            icon="i-fadl-upload"
            :label="viewer?.avatarUrl ? t('profile.avatar.change') : t('profile.avatar.upload')"
            :loading="pending"
            @click="input?.click()"
          />
          <UButton
            v-if="viewer?.avatarUrl"
            icon="i-fadl-trash"
            :label="t('profile.avatar.remove')"
            color="neutral"
            variant="ghost"
            :disabled="pending"
            @click="removeAvatar"
          />
        </div>
        <p class="text-sm text-muted">
          {{ t('profile.avatar.hint') }}
        </p>
        <p
          v-if="error"
          class="text-sm text-error"
          role="alert"
        >
          {{ error }}
        </p>
      </div>
      <input
        ref="input"
        type="file"
        accept="image/jpeg,image/png"
        class="hidden"
        @change="onFile"
      >
    </div>
  </UCard>
</template>
