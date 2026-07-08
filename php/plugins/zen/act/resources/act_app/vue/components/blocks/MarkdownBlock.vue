<template>
  <div v-if="mode === 'site'">
    <div v-if="originalMarkdown.trim()" class="tg-site-markdown">
      <MarkdownView :markdown="originalMarkdown" />
    </div>
  </div>

  <article v-else :class="['tg-block-card', { 'tg-block-card--editing': isEdit }]">
    <header class="tg-block-card__header">
      <BlockTitleEdit
        :name="block.name"
        type-label="MD"
        fallback="Markdown"
        :editable="viewer === 'owner'"
        @save-name="saveName"
      />
      <span v-if="isEdit && dirty" class="tg-block-card__status">Есть изменения</span>
    </header>

    <template v-if="isEdit">
      <MarkdownEditor
        v-model="markdown"
        :disabled="saving"
      />
      <p v-if="error" class="tg-error tg-error--inline">{{ error }}</p>
      <div class="tg-block-actions tg-block-actions--compact">
        <button class="tg-btn" type="button" :disabled="saving || !dirty" @click="save">
          {{ saving ? '…' : 'Сохранить' }}
        </button>
        <button class="tg-btn tg-btn--ghost" type="button" :disabled="saving" @click="cancelEdit">
          Готово
        </button>
        <button class="tg-btn tg-btn--danger" type="button" :disabled="saving" @click="remove">
          Удалить
        </button>
      </div>
    </template>

    <template v-else>
      <div
        :class="[
          'tg-block-content',
          'tg-block-content--markdown',
          { 'tg-block-content--empty': !originalMarkdown.trim() },
        ]"
      >
        <MarkdownView
          :markdown="originalMarkdown"
          :show-empty="!originalMarkdown.trim()"
        />
      </div>
      <div v-if="viewer === 'owner'" class="tg-block-actions tg-block-actions--compact">
        <BlockSettingsButton @click="$emit('settings', block)" />
        <BlockEditButton @click="startEdit" />
      </div>
    </template>
  </article>
</template>

<script>
import { computed, defineAsyncComponent, ref, watch } from 'vue'
import BlockEditButton from './BlockEditButton.vue'
import BlockSettingsButton from './BlockSettingsButton.vue'
import BlockTitleEdit from './BlockTitleEdit.vue'
import MarkdownView from './MarkdownView.vue'

const MarkdownEditor = defineAsyncComponent(() => import('./MarkdownEditor.vue'))

export default {
  name: 'MarkdownBlock',
  components: {
    BlockEditButton,
    BlockSettingsButton,
    BlockTitleEdit,
    MarkdownEditor,
    MarkdownView,
  },
  props: {
    block: {
      type: Object,
      required: true,
    },
    viewer: {
      type: String,
      default: 'guest',
      validator: (value) => ['owner', 'guest'].includes(value),
    },
    mode: {
      type: String,
      default: 'edit',
      validator: (value) => ['edit', 'site'].includes(value),
    },
    startInEditMode: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['save', 'delete', 'settings'],
  setup(props, { emit }) {
    const markdown = ref(props.block?.data?.markdown || '')
    const saving = ref(false)
    const error = ref('')
    const editing = ref(props.startInEditMode)

    const originalMarkdown = computed(() => props.block?.data?.markdown || '')
    const dirty = computed(() => markdown.value !== originalMarkdown.value)
    const isEdit = computed(() => props.viewer === 'owner' && editing.value)

    watch(
      () => props.block,
      () => {
        markdown.value = props.block?.data?.markdown || ''
        error.value = ''
        if (!props.startInEditMode) {
          editing.value = false
        }
      },
      { immediate: true }
    )

    watch(
      () => props.startInEditMode,
      (value) => {
        if (value) {
          editing.value = true
        }
      }
    )

    const startEdit = () => {
      editing.value = true
    }

    const cancelEdit = () => {
      markdown.value = originalMarkdown.value
      error.value = ''
      editing.value = false
    }

    const save = () => {
      if (!dirty.value || saving.value) {
        return
      }

      saving.value = true
      error.value = ''
      emit('save', {
        block: props.block,
        data: {
          ...(props.block.data || {}),
          type: 'markdown',
          markdown: markdown.value,
        },
        done: () => {
          saving.value = false
          editing.value = false
        },
        fail: (message) => {
          saving.value = false
          error.value = message || 'Не удалось сохранить блок'
        },
      })
    }

    const remove = () => {
      if (saving.value) {
        return
      }
      emit('delete', props.block)
    }

    const saveName = ({ name, done, fail }) => {
      emit('save', {
        block: props.block,
        data: props.block.data || { type: 'markdown', markdown: originalMarkdown.value },
        name,
        done,
        fail,
      })
    }

    return {
      markdown,
      saving,
      error,
      dirty,
      isEdit,
      originalMarkdown,
      startEdit,
      cancelEdit,
      save,
      remove,
      saveName,
    }
  },
}
</script>
