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

const { errors, pending, run } = useGraphqlForm()
const options = { refetchQueries: 'active' as const, awaitRefetchQueries: true }
const { mutate: addComment } = useMutation(addCommentMutation, options)
const { mutate: updateComment } = useMutation(updateCommentMutation, options)
const { mutate: deleteComment } = useMutation(deleteCommentMutation, options)

const deletion = useConfirmedDelete<string>(id => deleteComment({ id }))

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
</script>

<template>
  <section class="wa-stack wa-gap-m">
    <h3 class="wa-heading-s">
      {{ t('comments.title', { count: comments.length }) }}
    </h3>

    <p
      v-if="!comments.length"
      class="wa-body-s wa-color-text-quiet"
    >
      {{ t('comments.none') }}
    </p>

    <ul class="wa-stack wa-gap-m wa-list-plain comment-list">
      <li
        v-for="comment in comments"
        :key="comment.id"
        class="comment"
      >
        <UserAvatar
          :name="comment.author?.name ?? t('comments.deletedAuthor')"
          :src="comment.author?.avatarUrl"
          size="sm"
        />
        <div class="comment-main">
          <div class="comment-head">
            <span class="comment-author">{{ comment.author?.name ?? t('comments.deletedAuthor') }}</span>
            <time
              :datetime="comment.createdAt"
              class="comment-quiet"
            >{{ formatDateTime(comment.createdAt, language) }}</time>
            <span
              v-if="comment.updatedAt !== comment.createdAt"
              class="comment-quiet"
            >{{ t('comments.edited') }}</span>
            <span class="comment-tools">
              <wa-button
                v-if="comment.viewerCanEdit && editingId !== comment.id"
                appearance="plain"
                variant="neutral"
                size="s"
                :aria-label="t('common.edit')"
                @click="startEditing(comment)"
              >
                <wa-icon name="pen"></wa-icon>
              </wa-button>
              <wa-button
                v-if="comment.viewerCanDelete"
                appearance="plain"
                variant="neutral"
                size="s"
                :aria-label="t('common.delete')"
                @click="deletion.ask(comment.id)"
              >
                <wa-icon name="trash"></wa-icon>
              </wa-button>
            </span>
          </div>

          <div
            v-if="editingId === comment.id"
            class="wa-stack wa-gap-xs"
          >
            <RichTextEditor
              v-model="editDraft"
              compact
            />
            <p
              v-if="errors.body"
              data-form-error
              class="wa-body-s"
            >
              {{ errors.body }}
            </p>
            <div class="wa-cluster wa-gap-s wa-justify-content-end">
              <wa-button
                appearance="plain"
                variant="neutral"
                size="s"
                @click="editingId = null"
              >
                {{ t('common.cancel') }}
              </wa-button>
              <wa-button
                variant="brand"
                size="s"
                :loading="pending"
                @click="saveEdit"
              >
                {{ t('common.save') }}
              </wa-button>
            </div>
          </div>
          <RichTextView
            v-else
            :content="comment.body"
            class="wa-body-s"
          />
        </div>
      </li>
    </ul>

    <form
      v-if="canComment"
      class="wa-stack wa-gap-xs"
      @submit.prevent="add"
    >
      <RichTextEditor
        v-model="draft"
        compact
        :placeholder="t('comments.placeholder')"
      />
      <p
        v-if="errors.body && !editingId"
        data-form-error
        class="wa-body-s"
      >
        {{ errors.body }}
      </p>
      <div class="wa-cluster wa-justify-content-end">
        <wa-button
          type="submit"
          variant="brand"
          :loading="pending && !editingId"
          :disabled="isEmptyDocument(draft)"
        >
          <wa-icon
            slot="start"
            name="paper-plane"
          ></wa-icon>
          {{ t('comments.add') }}
        </wa-button>
      </div>
    </form>

    <ConfirmModal
      :open="deletion.isOpen"
      :title="t('comments.deleteTitle')"
      :description="t('comments.deleteDescription')"
      :confirm-label="t('common.delete')"
      :action="deletion.confirm"
      @close="deletion.close"
    />
  </section>
</template>

<style scoped>
.comment-list {
  margin: 0;
  padding: 0;
}

.comment {
  display: flex;
  gap: var(--wa-space-s);
}

.comment-main {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: var(--wa-space-3xs);
  min-inline-size: 0;
}

.comment-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: var(--wa-space-3xs) var(--wa-space-xs);
  font-size: var(--wa-font-size-s);
}

.comment-author {
  font-weight: var(--wa-font-weight-semibold);
}

.comment-quiet {
  color: var(--wa-color-text-quiet);
}

.comment-tools {
  display: flex;
  gap: var(--wa-space-3xs);
  margin-inline-start: auto;
}
</style>
