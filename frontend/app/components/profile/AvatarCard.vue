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
    const apiError = classifyApolloError(failure)
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
  <wa-card>
    <h2
      slot="header"
      class="wa-heading-m"
    >
      {{ t('profile.avatar.title') }}
    </h2>

    <div class="wa-cluster wa-gap-m wa-align-items-center">
      <UserAvatar
        :name="viewer?.name"
        :src="viewer?.avatarUrl"
        size="3xl"
      />
      <div class="wa-stack wa-gap-xs">
        <div class="wa-cluster wa-gap-s">
          <wa-button
            variant="brand"
            :loading="pending"
            @click="input?.click()"
          >
            <wa-icon
              slot="start"
              name="upload"
            ></wa-icon>
            {{ viewer?.avatarUrl ? t('profile.avatar.change') : t('profile.avatar.upload') }}
          </wa-button>
          <wa-button
            v-if="viewer?.avatarUrl"
            appearance="plain"
            variant="neutral"
            :disabled="pending"
            @click="removeAvatar"
          >
            <wa-icon
              slot="start"
              name="trash"
            ></wa-icon>
            {{ t('profile.avatar.remove') }}
          </wa-button>
        </div>
        <p class="wa-body-s wa-color-text-quiet">
          {{ t('profile.avatar.hint') }}
        </p>
        <p
          v-if="error"
          class="wa-body-s"
          data-form-error
          role="alert"
        >
          {{ error }}
        </p>
      </div>
      <input
        ref="input"
        type="file"
        accept="image/jpeg,image/png"
        hidden
        @change="onFile"
      >
    </div>
  </wa-card>
</template>
