<script setup lang="ts">
import { useMutation } from '@vue/apollo-composable'
import { addCommentMutation, deleteCommentMutation, updateCommentMutation } from '~/graphql/task'
import { formatDateTime } from '~/utils/dates'
import { isEmptyDocument } from '~/utils/rich-text'

/**
 * Comments of a task, oldest first. Edit and delete buttons follow
 * viewerCanEdit / viewerCanDelete from the backend.
 */
const props = defineProps<{
  taskId: string
  canComment: boolean
  comments: {
    id: string
    body: Record<string, unknown>
    createdAt: string
    updatedAt: string
    viewerCanEdit: boolean
    viewerCanDelete: boolean
    author?: { id: string, name: string, avatarUrl?: string | null } | null
  }[]
}>()

const { t, localeProperties } = useI18n()
const language = computed(() => localeProperties.value.language ?? 'de-CH')

const draft = ref<Record<string, unknown> | null>(null)
const editingId = ref<string | null>(null)
const editDraft = ref<Record<string, unknown> | null>(null)
const deletingId = ref<string | null>(null)

const { errors, pending, run } = useGraphqlForm()
const options = { refetchQueries: 'active' as const, awaitRefetchQueries: true }
const { mutate: addComment } = useMutation(addCommentMutation, options)
const { mutate: updateComment } = useMutation(updateCommentMutation, options)
const { mutate: deleteComment } = useMutation(deleteCommentMutation, options)

async function add(): Promise<void> {
  if (isEmptyDocument(draft.value)) {
    return
  }
  const result = await run(() => addComment({ input: { taskId: props.taskId, body: draft.value ?? {} } }))

  if (result) {
    draft.value = null
  }
}

function startEditing(comment: (typeof props.comments)[number]): void {
  editingId.value = comment.id
  editDraft.value = comment.body
}

async function saveEdit(): Promise<void> {
  if (!editingId.value || isEmptyDocument(editDraft.value)) {
    return
  }
  const id = editingId.value
  const result = await run(() => updateComment({ id, input: { body: editDraft.value ?? {} } }))

  if (result) {
    editingId.value = null
  }
}

async function confirmDelete(): Promise<void> {
  if (deletingId.value) {
    await deleteComment({ id: deletingId.value })
  }
}
</script>

<template>
  <section class="space-y-4">
    <h3 class="font-semibold text-highlighted">
      {{ t('comments.title', { count: comments.length }) }}
    </h3>

    <p
      v-if="!comments.length"
      class="text-sm text-muted"
    >
      {{ t('comments.none') }}
    </p>

    <ul class="space-y-4">
      <li
        v-for="comment in comments"
        :key="comment.id"
        class="flex gap-3"
      >
        <UAvatar
          :src="comment.author?.avatarUrl ?? undefined"
          :alt="comment.author?.name ?? t('comments.deletedAuthor')"
          size="sm"
        />
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-baseline gap-x-2 text-sm">
            <span class="font-medium text-highlighted">{{ comment.author?.name ?? t('comments.deletedAuthor') }}</span>
            <time
              :datetime="comment.createdAt"
              class="text-muted"
            >{{ formatDateTime(comment.createdAt, language) }}</time>
            <span
              v-if="comment.updatedAt !== comment.createdAt"
              class="text-muted"
            >{{ t('comments.edited') }}</span>
            <span class="ms-auto flex gap-1">
              <UButton
                v-if="comment.viewerCanEdit && editingId !== comment.id"
                icon="i-fadl-pen"
                color="neutral"
                variant="ghost"
                size="xs"
                :aria-label="t('common.edit')"
                @click="startEditing(comment)"
              />
              <UButton
                v-if="comment.viewerCanDelete"
                icon="i-fadl-trash"
                color="neutral"
                variant="ghost"
                size="xs"
                :aria-label="t('common.delete')"
                @click="deletingId = comment.id"
              />
            </span>
          </div>

          <div
            v-if="editingId === comment.id"
            class="mt-2 space-y-2"
          >
            <RichTextEditor
              v-model="editDraft"
              compact
            />
            <p
              v-if="errors.body"
              class="text-sm text-error"
            >
              {{ errors.body }}
            </p>
            <div class="flex justify-end gap-2">
              <UButton
                :label="t('common.cancel')"
                color="neutral"
                variant="ghost"
                size="sm"
                @click="editingId = null"
              />
              <UButton
                :label="t('common.save')"
                size="sm"
                :loading="pending"
                @click="saveEdit"
              />
            </div>
          </div>
          <RichTextView
            v-else
            :content="comment.body"
            class="mt-1 text-sm"
          />
        </div>
      </li>
    </ul>

    <form
      v-if="canComment"
      class="space-y-2"
      @submit.prevent="add"
    >
      <RichTextEditor
        v-model="draft"
        compact
        :placeholder="t('comments.placeholder')"
      />
      <p
        v-if="errors.body && !editingId"
        class="text-sm text-error"
      >
        {{ errors.body }}
      </p>
      <div class="flex justify-end">
        <UButton
          type="submit"
          :label="t('comments.add')"
          icon="i-fadl-paper-plane"
          :loading="pending && !editingId"
          :disabled="isEmptyDocument(draft)"
        />
      </div>
    </form>

    <ConfirmModal
      :open="deletingId !== null"
      :title="t('comments.deleteTitle')"
      :description="t('comments.deleteDescription')"
      :confirm-label="t('common.delete')"
      :action="confirmDelete"
      @close="deletingId = null"
    />
  </section>
</template>
