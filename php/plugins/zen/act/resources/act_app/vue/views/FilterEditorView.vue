<template>
  <div class="concord-page concord-page--editor">
    <ConcordPageHeader :title="sectionTitle" show-back @back="$emit('back')" />

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
          editable
          @edit="editFilter"
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
      :editing-filter="editingFilter"
      @close="onClosePicker"
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
import { computed, ref, watch } from 'vue'
import FilterChip from '../concord/FilterChip.vue'
import FilterPickerModal from '../concord/FilterPickerModal.vue'
import ConcordPageHeader from '../concord/ConcordPageHeader.vue'
import {
  FILTER_CATEGORIES,
  formatCreatedFilterLabel,
  formatParticipantsFilterLabel,
  formatStatusFilterLabel,
} from '../concord/mock-agreements.js'

export default {
  name: 'FilterEditorView',
  components: { FilterChip, FilterPickerModal, ConcordPageHeader },
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
    const editingFilterId = ref(null)

    const editingFilter = computed(
      () => filters.value.find((item) => item.id === editingFilterId.value) || null
    )

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
        if (!open) {
          editingFilterId.value = null
          return
        }
        if (!editingFilterId.value) {
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

    function onClosePicker() {
      editingFilterId.value = null
      emit('close-picker')
    }

    function editFilter(filter) {
      editingFilterId.value = filter.id
      selectedCategory.value = FILTER_CATEGORIES.find((item) => item.id === filter.category) || null
      if (filter.category === 'urgency') {
        pickerStep.value = 'urgency'
      } else if (filter.category === 'created') {
        pickerStep.value = 'created'
      } else if (filter.category === 'participant') {
        pickerStep.value = 'participant'
      } else if (filter.category === 'status') {
        pickerStep.value = 'status'
      } else {
        pickerStep.value = 'categories'
      }
      emit('open-picker')
    }

    function upsertFilter(filterId, payload) {
      if (filterId) {
        const index = filters.value.findIndex((item) => item.id === filterId)
        if (index !== -1) {
          filters.value[index] = {
            ...filters.value[index],
            ...payload,
          }
        }
        return
      }
      filters.value.push(payload)
    }

    function onAddUrgency(option) {
      upsertFilter(editingFilterId.value, {
        id: editingFilterId.value || `urgency-${Date.now()}`,
        label: option.id === 'set' ? 'Срочность установлена' : 'Срочность не установлена',
        category: 'urgency',
        value: option.id,
      })
      onClosePicker()
    }

    function onAddCreated({ option, date }) {
      upsertFilter(editingFilterId.value, {
        id: editingFilterId.value || `created-${Date.now()}`,
        label: formatCreatedFilterLabel(option, date || undefined),
        category: 'created',
        value: option.id,
        date: date || null,
      })
      onClosePicker()
    }

    function onAddParticipant({ participants, match }) {
      upsertFilter(editingFilterId.value, {
        id: editingFilterId.value || `participant-${Date.now()}`,
        label: formatParticipantsFilterLabel(participants, match),
        category: 'participant',
        value: participants.map((item) => item.id),
        avatars: participants.map((item) => item.initial),
        match,
      })
      onClosePicker()
    }

    function onAddStatus({ status, match }) {
      upsertFilter(editingFilterId.value, {
        id: editingFilterId.value || `status-${Date.now()}`,
        label: formatStatusFilterLabel(status, match),
        category: 'status',
        value: status.id,
        match,
      })
      onClosePicker()
    }

    function onPickerBack() {
      if (editingFilterId.value) {
        onClosePicker()
        return
      }
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
      editingFilter,
      removeFilter,
      editFilter,
      onClosePicker,
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
