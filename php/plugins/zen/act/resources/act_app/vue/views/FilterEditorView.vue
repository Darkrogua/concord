<template>
  <div class="concord-page concord-page--editor">
    <header class="concord-header concord-header--editor">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="$emit('back')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title">{{ sectionTitle }}</h1>
    </header>

    <main class="concord-filter-editor">
      <label class="concord-filter-editor__field">
        <span class="concord-filter-editor__label">название фильтра</span>
        <input
          v-model="name"
          class="concord-filter-editor__input"
          type="text"
          placeholder="Ведите текст"
        >
      </label>

      <div v-if="filters.length" class="concord-filter-editor__chips">
        <FilterChip
          v-for="filter in filters"
          :key="filter.id"
          :filter="filter"
          @remove="removeFilter(filter.id)"
        />
      </div>

      <button type="button" class="concord-filter-editor__add-link" @click="$emit('open-picker')">
        + Добавить фильтр
      </button>

      <button type="button" class="concord-filter-editor__save" @click="save">
        Сохранить
      </button>
    </main>

    <FilterPickerModal
      :open="pickerOpen"
      :step="pickerStep"
      :selected-category="selectedCategory"
      @close="$emit('close-picker')"
      @select-category="onSelectCategory"
      @add-urgency="onAddUrgency"
      @add-created="onAddCreated"
      @add-participant="onAddParticipant"
      @add-status="onAddStatus"
      @back="onPickerBack"
    />
  </div>
</template>

<script>
import { ref, watch } from 'vue'
import FilterChip from '../concord/FilterChip.vue'
import FilterPickerModal from '../concord/FilterPickerModal.vue'
import { formatCreatedFilterLabel, formatParticipantsFilterLabel, formatStatusFilterLabel } from '../concord/mock-agreements.js'

export default {
  name: 'FilterEditorView',
  components: { FilterChip, FilterPickerModal },
  props: {
    sectionTitle: {
      type: String,
      default: 'Новый фильтр',
    },
    initialName: {
      type: String,
      default: '',
    },
    initialFilters: {
      type: Array,
      default: () => [],
    },
    pickerOpen: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['back', 'save', 'open-picker', 'close-picker'],
  setup(props, { emit }) {
    const name = ref(props.initialName)
    const filters = ref([...props.initialFilters])
    const pickerStep = ref('categories')
    const selectedCategory = ref(null)

    watch(
      () => props.initialName,
      (value) => {
        name.value = value
      }
    )

    watch(
      () => props.initialFilters,
      (value) => {
        filters.value = [...value]
      },
      { deep: true }
    )

    watch(
      () => props.pickerOpen,
      (open) => {
        if (open) {
          pickerStep.value = 'categories'
          selectedCategory.value = null
        }
      }
    )

    function removeFilter(filterId) {
      filters.value = filters.value.filter((item) => item.id !== filterId)
    }

    function onSelectCategory(category) {
      selectedCategory.value = category
      if (category.id === 'urgency') {
        pickerStep.value = 'urgency'
      } else if (category.id === 'created') {
        pickerStep.value = 'created'
      } else if (category.id === 'participant') {
        pickerStep.value = 'participant'
      } else if (category.id === 'status') {
        pickerStep.value = 'status'
      }
    }

    function onAddUrgency(option) {
      filters.value.push({
        id: `urgency-${Date.now()}`,
        label: option.id === 'set' ? 'Срочность установлена' : 'Срочность не установлена',
        category: 'urgency',
        value: option.id,
      })
      emit('close-picker')
    }

    function onAddCreated({ option, date }) {
      filters.value.push({
        id: `created-${Date.now()}`,
        label: formatCreatedFilterLabel(option, date || undefined),
        category: 'created',
        value: option.id,
        date: date || null,
      })
      emit('close-picker')
    }

    function onAddParticipant({ participants, match }) {
      filters.value.push({
        id: `participant-${Date.now()}`,
        label: formatParticipantsFilterLabel(participants, match),
        category: 'participant',
        value: participants.map((item) => item.id),
        avatars: participants.map((item) => item.initial),
      })
      emit('close-picker')
    }

    function onAddStatus({ status, match }) {
      filters.value.push({
        id: `status-${Date.now()}`,
        label: formatStatusFilterLabel(status, match),
        category: 'status',
        value: status.id,
        match,
      })
      emit('close-picker')
    }

    function onPickerBack() {
      pickerStep.value = 'categories'
      selectedCategory.value = null
    }

    function save() {
      emit('save', {
        name: name.value.trim() || props.sectionTitle,
        filters: [...filters.value],
      })
    }

    return {
      name,
      filters,
      pickerStep,
      selectedCategory,
      removeFilter,
      onSelectCategory,
      onAddUrgency,
      onAddCreated,
      onAddParticipant,
      onAddStatus,
      onPickerBack,
      save,
    }
  },
}
</script>
