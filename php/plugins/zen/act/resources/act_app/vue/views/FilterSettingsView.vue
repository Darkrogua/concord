<template>
  <div class="concord-page concord-page--settings">
    <ConcordPageHeader title="Настройки" show-back @back="$emit('back')" />

    <main ref="sectionsRef" class="concord-settings">
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
          <button
            type="button"
            class="concord-settings__section-title-button"
            :aria-expanded="isSectionExpanded(section.id)"
            :aria-label="isSectionExpanded(section.id) ? 'Свернуть раздел' : 'Развернуть раздел'"
            @click="toggleSection(section.id)"
          >
            <span class="concord-settings__title">{{ section.title }}</span>
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
import ConcordPageHeader from '../concord/ConcordPageHeader.vue'
import { useFilterSectionSortable } from '../concord/useFilterSectionSortable.js'

export default {
  name: 'FilterSettingsView',
  components: { FilterChipSortableList, ConcordPageHeader },
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
