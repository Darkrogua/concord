<template>
  <div class="concord-page concord-page--settings">
    <header class="concord-header concord-header--editor">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="$emit('back')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title">Настройки</h1>
    </header>

    <main ref="sectionsRef" class="concord-settings">
      <p v-if="canReorderSections" class="concord-settings__sections-hint">
        <span class="concord-settings__sections-hint-touch">Удерживайте заголовок раздела и перетащите — порядок табов на главной изменится</span>
        <span class="concord-settings__sections-hint-mouse">Потяните раздел за ⋮⋮ у заголовка — порядок табов на главной изменится</span>
      </p>

      <section
        v-for="section in sections"
        :key="section.id"
        class="concord-settings__section"
        :class="{ 'concord-settings__section--sortable': canReorderSections }"
        :data-section-id="section.id"
      >
        <div class="concord-settings__section-head">
          <button
            v-if="canReorderSections"
            type="button"
            class="concord-settings__section-drag"
            aria-label="Перетащить раздел"
            tabindex="-1"
          >
            <svg viewBox="0 0 8 14" width="8" height="14" fill="currentColor" aria-hidden="true">
              <circle cx="2" cy="2" r="1.2" />
              <circle cx="6" cy="2" r="1.2" />
              <circle cx="2" cy="7" r="1.2" />
              <circle cx="6" cy="7" r="1.2" />
              <circle cx="2" cy="12" r="1.2" />
              <circle cx="6" cy="12" r="1.2" />
            </svg>
          </button>
          <h2 class="concord-settings__title">{{ section.title }}</h2>
        </div>

        <FilterChipSortableList
          :filters="section.filters"
          @remove="removeFilter(section.id, $event)"
          @reorder="reorderFilters(section.id, $event)"
        />

        <button
          type="button"
          class="concord-settings__edit"
          @click="editSection(section.id)"
        >
          <span class="concord-settings__edit-plus" aria-hidden="true">+</span>
          Редактировать
        </button>
      </section>
    </main>

    <div class="concord-settings__footer">
      <button type="button" class="concord-settings__add" @click="$emit('add-filter')">
        Добавить фильтр +
      </button>
    </div>
  </div>
</template>

<script>
import { computed, nextTick, ref, toRef, watch } from 'vue'
import FilterChipSortableList from '../concord/FilterChipSortableList.vue'
import { useFilterSectionSortable } from '../concord/useFilterSectionSortable.js'

export default {
  name: 'FilterSettingsView',
  components: { FilterChipSortableList },
  props: {
    sections: {
      type: Array,
      required: true,
    },
  },
  emits: ['back', 'edit-section', 'add-filter', 'remove-filter', 'reorder-filters', 'reorder-sections'],
  setup(props, { emit }) {
    const sectionsRef = ref(null)
    const sectionsProp = toRef(props, 'sections')

    const canReorderSections = computed(() => props.sections.length >= 2)

    function handleReorderSections(sectionIds) {
      emit('reorder-sections', sectionIds)
    }

    const { initSortable } = useFilterSectionSortable(sectionsRef, {
      canReorder: canReorderSections,
      onReorder: handleReorderSections,
    })

    watch(sectionsProp, () => {
      nextTick(() => initSortable())
    }, { flush: 'post' })

    function editSection(sectionId) {
      emit('edit-section', sectionId)
    }

    function removeFilter(sectionId, filterId) {
      emit('remove-filter', { sectionId, filterId })
    }

    function reorderFilters(sectionId, filterIds) {
      emit('reorder-filters', { sectionId, filterIds })
    }

    return {
      sectionsRef,
      canReorderSections,
      editSection,
      removeFilter,
      reorderFilters,
    }
  },
}
</script>
