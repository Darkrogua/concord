<template>
  <EditFieldModal
    :open="open"
    :title="title"
    :title-id="titleId"
    @close="$emit('close')"
  >
    <template v-if="type === 'owners'">
      <div class="tg-field">
        <span class="tg-field__label">Владельцы</span>
        <UserPicker
          v-model="draft.owners.users"
          input-id="acts-filter-owners-input"
          :show-all-chip="false"
        />
        <p class="tg-field__hint">Можно выбрать несколько пользователей.</p>
      </div>
    </template>

    <template v-else-if="type === 'created'">
      <label class="tg-field">
        <span class="tg-field__label">От</span>
        <input v-model="draft.created.from" class="tg-input" type="date">
      </label>
      <label class="tg-field">
        <span class="tg-field__label">До</span>
        <input v-model="draft.created.to" class="tg-input" type="date">
      </label>
      <p class="tg-field__hint">Можно указать только одну границу.</p>
    </template>

    <template v-else-if="type === 'sortCreated'">
      <div class="tg-field">
        <span class="tg-field__label">Порядок</span>
        <label class="tg-radio-row">
          <input v-model="draft.sortCreated.order" type="radio" value="desc">
          <span>Сначала новые</span>
        </label>
        <label class="tg-radio-row">
          <input v-model="draft.sortCreated.order" type="radio" value="asc">
          <span>Сначала старые</span>
        </label>
      </div>
    </template>

    <template v-else-if="type === 'tags'">
      <div v-if="draft.tags.items.length > 0" class="tg-tags-filter-chain">
        <template v-for="(tag, index) in draft.tags.items" :key="`${tag}-${index}`">
          <button
            v-if="index > 0"
            type="button"
            class="tg-tag-op-toggle"
            @click="toggleOp(index - 1)"
          >
            {{ draft.tags.ops[index - 1] === 'and' ? 'AND' : 'OR' }}
          </button>
          <span class="tg-tag-chip">
            {{ tag }}
            <button
              type="button"
              class="tg-tag-chip__remove"
              :aria-label="`Удалить тег ${tag}`"
              @click="removeTag(index)"
            >
              ×
            </button>
          </span>
        </template>
      </div>

      <label class="tg-field">
        <span class="tg-field__label">Добавить тег</span>
        <input
          v-model="tagQuery"
          class="tg-input"
          type="search"
          maxlength="30"
          placeholder="Поиск или новый тег…"
          @keydown.enter.prevent="addTagFromInput"
        >
      </label>

      <ul v-if="tagSuggestions.length > 0" class="tg-tags-modal__suggestions">
        <li v-for="tag in tagSuggestions" :key="tag">
          <button type="button" class="tg-tags-modal__suggestion" @click="addTag(tag)">
            {{ tag }}
          </button>
        </li>
      </ul>
      <p v-else-if="tagQuery.trim() && canCreateTag" class="tg-field__hint">
        Enter — добавить «{{ normalizedTagQuery }}»
      </p>
      <p class="tg-field__hint">Между тегами можно переключать OR и AND.</p>
    </template>

    <p v-if="error" class="tg-error tg-error--inline">{{ error }}</p>
    <button class="tg-btn tg-btn--block" type="button" @click="save">
      Применить
    </button>
  </EditFieldModal>
</template>

<script>
import { computed, reactive, ref, watch } from 'vue'
import { useAppState } from '../app-state'
import EditFieldModal from './EditFieldModal.vue'
import UserPicker from './UserPicker.vue'

const capitalizeFirst = (value) => {
  const trimmed = value.trim().replace(/\s+/g, ' ')
  if (!trimmed) {
    return ''
  }
  return trimmed.charAt(0).toUpperCase() + trimmed.slice(1)
}

export default {
  name: 'ActsFilterDetailModal',
  components: { EditFieldModal, UserPicker },
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    type: {
      type: String,
      default: '',
    },
    filters: {
      type: Object,
      required: true,
    },
  },
  emits: ['close', 'apply'],
  setup(props, { emit }) {
    const { api } = useAppState()
    const draft = reactive({
      owners: { users: [] },
      created: { from: '', to: '' },
      sortCreated: { order: 'desc' },
      tags: { items: [], ops: [] },
    })
    const error = ref('')
    const tagQuery = ref('')
    const tagSuggestions = ref([])
    let searchTimer = null

    const title = computed(() => {
      if (props.type === 'owners') return 'Владелец акта'
      if (props.type === 'created') return 'Создан'
      if (props.type === 'sortCreated') return 'Сортировка по созданию'
      if (props.type === 'tags') return 'Теги'
      return 'Фильтр'
    })

    const titleId = computed(() => `acts-filter-detail-${props.type || 'unknown'}`)

    const normalizedTagQuery = computed(() => capitalizeFirst(tagQuery.value))

    const canCreateTag = computed(() => {
      const tag = normalizedTagQuery.value
      return tag.length >= 3 && tag.length <= 30 && !draft.tags.items.includes(tag)
    })

    const syncDraft = () => {
      draft.owners.users = props.filters.owners.users.map((user) => ({ ...user }))
      draft.created.from = props.filters.created.from
      draft.created.to = props.filters.created.to
      draft.sortCreated.order = props.filters.sortCreated.order
      draft.tags.items = [...props.filters.tags.items]
      draft.tags.ops = [...props.filters.tags.ops]
      tagQuery.value = ''
      tagSuggestions.value = []
      error.value = ''
    }

    const loadTagSuggestions = () => {
      const q = tagQuery.value.trim()
      if (!q) {
        tagSuggestions.value = []
        return
      }
      api({
        api: `Tags:catalog?q=${encodeURIComponent(q)}`,
        then: (res) => {
          if (!res?.ok) {
            tagSuggestions.value = []
            return
          }
          const list = Array.isArray(res.data?.tags) ? res.data.tags : []
          tagSuggestions.value = list.filter((tag) => !draft.tags.items.includes(tag))
        },
        catch: () => {
          tagSuggestions.value = []
        },
      })
    }

    watch(() => props.open, (isOpen) => {
      if (isOpen) {
        syncDraft()
      }
    })

    watch(tagQuery, () => {
      if (props.type !== 'tags') {
        return
      }
      if (searchTimer) {
        clearTimeout(searchTimer)
      }
      searchTimer = setTimeout(loadTagSuggestions, 200)
    })

    const addTag = (tag) => {
      const value = capitalizeFirst(tag)
      if (!value || draft.tags.items.includes(value)) {
        return
      }
      if (draft.tags.items.length > 0) {
        draft.tags.ops.push('or')
      }
      draft.tags.items.push(value)
      tagQuery.value = ''
      tagSuggestions.value = []
    }

    const addTagFromInput = () => {
      if (tagSuggestions.value[0]) {
        addTag(tagSuggestions.value[0])
        return
      }
      if (canCreateTag.value) {
        addTag(normalizedTagQuery.value)
      }
    }

    const removeTag = (index) => {
      draft.tags.items.splice(index, 1)
      if (index === 0) {
        if (draft.tags.ops.length > 0) {
          draft.tags.ops.splice(0, 1)
        }
      } else {
        draft.tags.ops.splice(index - 1, 1)
      }
    }

    const toggleOp = (index) => {
      draft.tags.ops[index] = draft.tags.ops[index] === 'and' ? 'or' : 'and'
    }

    const save = () => {
      error.value = ''
      if (props.type === 'owners' && draft.owners.users.length === 0) {
        error.value = 'Выберите хотя бы одного владельца'
        return
      }
      if (props.type === 'created' && !draft.created.from && !draft.created.to) {
        error.value = 'Укажите дату «от» или «до»'
        return
      }
      if (props.type === 'tags' && draft.tags.items.length === 0) {
        error.value = 'Добавьте хотя бы один тег'
        return
      }
      emit('apply', {
        type: props.type,
        owners: { users: draft.owners.users.map((user) => ({ ...user })) },
        created: { from: draft.created.from, to: draft.created.to },
        sortCreated: { order: draft.sortCreated.order },
        tags: {
          items: [...draft.tags.items],
          ops: [...draft.tags.ops],
        },
      })
    }

    return {
      draft,
      error,
      title,
      titleId,
      tagQuery,
      tagSuggestions,
      normalizedTagQuery,
      canCreateTag,
      addTag,
      addTagFromInput,
      removeTag,
      toggleOp,
      save,
    }
  },
}
</script>
