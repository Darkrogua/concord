<template>
  <div v-if="mode === 'site'">
    <div v-if="originalText.trim()" class="tg-site-text">{{ originalText }}</div>
  </div>

  <article v-else :class="['tg-block-card', { 'tg-block-card--editing': isEdit }]">
    <header class="tg-block-card__header">
      <BlockTitleEdit
        :name="block.name"
        type-label="Текст"
        fallback="Текст"
        :editable="viewer === 'owner'"
        @save-name="saveName"
      />
      <span v-if="isEdit && dirty" class="tg-block-card__status">Есть изменения</span>
    </header>

    <template v-if="isEdit">
      <textarea
        ref="textarea"
        v-model="text"
        class="tg-block-textarea"
        :disabled="saving"
        rows="1"
        placeholder="Введите текст…"
        @input="resize"
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
          'tg-block-content--text',
          { 'tg-block-content--empty': !originalText.trim() },
        ]"
      >
        {{ displayText }}
      </div>
      <div v-if="viewer === 'owner'" class="tg-block-actions tg-block-actions--compact">
        <BlockSettingsButton @click="$emit('settings', block)" />
        <BlockEditButton @click="startEdit" />
      </div>
    </template>
  </article>
</template>

<script>
import { computed, nextTick, ref, watch } from 'vue'
import BlockEditButton from './BlockEditButton.vue'
import BlockSettingsButton from './BlockSettingsButton.vue'
import BlockTitleEdit from './BlockTitleEdit.vue'

export default {
  name: 'TextBlock',
  components: {
    BlockEditButton,
    BlockSettingsButton,
    BlockTitleEdit,
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
    const textarea = ref(null)
    const text = ref(props.block?.data?.text || '')
    const saving = ref(false)
    const error = ref('')
    const editing = ref(props.startInEditMode)

    const originalText = computed(() => props.block?.data?.text || '')
    const dirty = computed(() => text.value !== originalText.value)
    const isEdit = computed(() => props.viewer === 'owner' && editing.value)
    const displayText = computed(() => {
      const value = originalText.value.trim()
      return value || 'Пустой текстовый блок'
    })

    const resize = () => {
      nextTick(() => {
        if (!textarea.value) {
          return
        }
        textarea.value.style.height = 'auto'
        textarea.value.style.height = `${textarea.value.scrollHeight}px`
      })
    }

    watch(
      () => props.block,
      () => {
        text.value = props.block?.data?.text || ''
        error.value = ''
        if (!props.startInEditMode) {
          editing.value = false
        }
        resize()
      },
      { immediate: true }
    )

    watch(
      () => props.startInEditMode,
      (value) => {
        if (value) {
          editing.value = true
          resize()
        }
      }
    )

    const startEdit = () => {
      editing.value = true
      resize()
    }

    const cancelEdit = () => {
      text.value = originalText.value
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
          type: 'text',
          text: text.value,
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
        data: props.block.data || { type: 'text', text: originalText.value },
        name,
        done,
        fail,
      })
    }

    return {
      textarea,
      text,
      saving,
      error,
      dirty,
      isEdit,
      originalText,
      displayText,
      resize,
      startEdit,
      cancelEdit,
      save,
      remove,
      saveName,
    }
  },
}
</script>
