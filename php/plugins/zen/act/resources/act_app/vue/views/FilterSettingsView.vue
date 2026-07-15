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
        Потяните ≡ слева от названия — порядок табов на главной изменится
      </p>

      <section
        v-for="section in sections"
        :key="section.id"
        class="concord-settings__section"
        :class="{
          'concord-settings__section--sortable': canReorderSections,
          'concord-settings__section--expanded': isSectionExpanded(section.id),
        }"
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
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" aria-hidden="true">
              <path d="M4 8h16M4 12h16M4 16h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </button>
          <h2 class="concord-settings__title">{{ section.title }}</h2>
          <button
            type="button"
            class="concord-settings__section-toggle"
            :aria-expanded="isSectionExpanded(section.id)"
            :aria-label="isSectionExpanded(section.id) ? 'Свернуть раздел' : 'Развернуть раздел'"
            @click="toggleSection(section.id)"
          >
            <svg
              class="concord-settings__section-chevron"
              :class="{ 'concord-settings__section-chevron--open': isSectionExpanded(section.id) }"
              viewBox="0 0 24 24"
              width="22"
              height="22"
              fill="none"
              aria-hidden="true"
            >
              <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>

        <div v-if="isSectionExpanded(section.id)" class="concord-settings__section-body">
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
        </div>
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

    function createDefaultExpandedSectionIds(sections) {
      if (!sections.length) {
        return new Set()
      }
      return new Set([sections[0].id])
    }

    const expandedSectionIds = ref(createDefaultExpandedSectionIds(props.sections))

    watch(
      () => props.sections.map((section) => section.id).join(','),
      () => {
        const validIds = new Set(props.sections.map((section) => section.id))
        const next = new Set([...expandedSectionIds.value].filter((id) => validIds.has(id)))
        if (next.size === 0 && props.sections.length > 0) {
          next.add(props.sections[0].id)
        }
        expandedSectionIds.value = next
      }
    )

    const canReorderSections = computed(() => props.sections.length >= 2)

    function isSectionExpanded(sectionId) {
      return expandedSectionIds.value.has(sectionId)
    }

    function toggleSection(sectionId) {
      const next = new Set(expandedSectionIds.value)
      if (next.has(sectionId)) {
        next.delete(sectionId)
      } else {
        next.add(sectionId)
      }
      expandedSectionIds.value = next
    }

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
      isSectionExpanded,
      toggleSection,
      editSection,
      removeFilter,
      reorderFilters,
    }
  },
}
</script>
