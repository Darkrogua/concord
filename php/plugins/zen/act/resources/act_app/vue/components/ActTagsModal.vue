<template>
  <EditFieldModal
    :open="open"
    title="Теги"
    title-id="act-tags-modal-title"
    @close="$emit('close')"
  >
    <div class="tg-tags-modal">
      <div v-if="draft.length > 0" class="tg-tags-modal__chips">
        <span v-for="(tag, index) in draft" :key="`${tag}-${index}`" class="tg-tag-chip">
          {{ tag }}
          <button
            type="button"
            class="tg-tag-chip__remove"
            :aria-label="`Удалить тег ${tag}`"
            :disabled="saving"
            @click="removeTag(index)"
          >
            ×
          </button>
        </span>
      </div>
      <p v-else class="tg-field__hint">Теги не заданы</p>

      <label class="tg-field">
        <span class="tg-field__label">Добавить тег</span>
        <input
          ref="inputRef"
          v-model="query"
          class="tg-input"
          type="search"
          maxlength="30"
          placeholder="Поиск или новый тег…"
          :disabled="saving || draft.length >= maxTags"
          @keydown.enter.prevent="addFromInput"
        >
      </label>

      <ul v-if="suggestions.length > 0" class="tg-tags-modal__suggestions">
        <li v-for="tag in suggestions" :key="tag">
          <button type="button" class="tg-tags-modal__suggestion" @click="addTag(tag)">
            {{ tag }}
          </button>
        </li>
      </ul>
      <p v-else-if="query.trim() && canCreateFromQuery" class="tg-field__hint">
        Enter — добавить «{{ normalizedQuery }}»
      </p>

      <p class="tg-field__hint">От 3 до 30 символов. Не более {{ maxTags }} тегов на акт.</p>
      <p v-if="error" class="tg-error tg-error--inline">{{ error }}</p>

      <div class="tg-block-actions">
        <button class="tg-btn" type="button" :disabled="saving || !dirty" @click="save">
          {{ saving ? '…' : 'Сохранить' }}
        </button>
        <button class="tg-btn tg-btn--ghost" type="button" :disabled="saving" @click="$emit('close')">
          Отмена
        </button>
      </div>
    </div>
  </EditFieldModal>
</template>

<script>
import { computed, ref, watch } from 'vue'
import { useAppState } from '../app-state'
import EditFieldModal from './EditFieldModal.vue'

const MAX_TAGS = 20

const capitalizeFirst = (value) => {
  const trimmed = value.trim().replace(/\s+/g, ' ')
  if (!trimmed) {
    return ''
  }
  return trimmed.charAt(0).toUpperCase() + trimmed.slice(1)
}

export default {
  name: 'ActTagsModal',
  components: { EditFieldModal },
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    actId: {
      type: String,
      default: '',
    },
    tags: {
      type: Array,
      default: () => [],
    },
    saving: {
      type: Boolean,
      default: false,
    },
    error: {
      type: String,
      default: '',
    },
  },
  emits: ['close', 'save'],
  setup(props, { emit }) {
    const { api } = useAppState()
    const draft = ref([])
    const query = ref('')
    const suggestions = ref([])
    const inputRef = ref(null)
    let searchTimer = null

    const maxTags = MAX_TAGS

    const normalizedQuery = computed(() => capitalizeFirst(query.value))

    const dirty = computed(() => draft.value.join('|') !== (props.tags || []).join('|'))

    const canCreateFromQuery = computed(() => {
      const tag = normalizedQuery.value
      if (tag.length < 3 || tag.length > 30) {
        return false
      }
      return !draft.value.includes(tag)
    })

    const resetDraft = () => {
      draft.value = Array.isArray(props.tags) ? [...props.tags] : []
      query.value = ''
      suggestions.value = []
    }

    const loadSuggestions = () => {
      const q = query.value.trim()
      if (!q) {
        suggestions.value = []
        return
      }
      api({
        api: `Tags:catalog?q=${encodeURIComponent(q)}`,
        then: (res) => {
          if (!res?.ok) {
            suggestions.value = []
            return
          }
          const list = Array.isArray(res.data?.tags) ? res.data.tags : []
          suggestions.value = list.filter((tag) => !draft.value.includes(tag))
        },
        catch: () => {
          suggestions.value = []
        },
      })
    }

    watch(
      () => props.open,
      (value) => {
        if (value) {
          resetDraft()
        }
      }
    )

    watch(
      () => props.tags,
      () => {
        if (props.open) {
          resetDraft()
        }
      },
      { deep: true }
    )

    watch(query, () => {
      if (searchTimer) {
        clearTimeout(searchTimer)
      }
      searchTimer = setTimeout(loadSuggestions, 200)
    })

    const addTag = (tag) => {
      if (props.saving || draft.value.length >= MAX_TAGS) {
        return
      }
      const value = capitalizeFirst(tag)
      if (!value || draft.value.includes(value)) {
        return
      }
      draft.value = [...draft.value, value]
      query.value = ''
      suggestions.value = []
    }

    const addFromInput = () => {
      const fromSuggestion = suggestions.value[0]
      if (fromSuggestion) {
        addTag(fromSuggestion)
        return
      }
      if (canCreateFromQuery.value) {
        addTag(normalizedQuery.value)
      }
    }

    const removeTag = (index) => {
      if (props.saving) {
        return
      }
      draft.value = draft.value.filter((_, i) => i !== index)
    }

    const save = () => {
      emit('save', [...draft.value])
    }

    return {
      draft,
      query,
      suggestions,
      inputRef,
      maxTags,
      normalizedQuery,
      dirty,
      canCreateFromQuery,
      addTag,
      addFromInput,
      removeTag,
      save,
    }
  },
}
</script>
