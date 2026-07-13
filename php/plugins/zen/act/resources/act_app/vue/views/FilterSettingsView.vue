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

    <main class="concord-settings">
      <section
        v-for="section in sections"
        :key="section.id"
        class="concord-settings__section"
      >
        <h2 class="concord-settings__title">{{ section.title }}</h2>

        <div class="concord-settings__chips">
          <FilterChip
            v-for="filter in section.filters"
            :key="filter.id"
            :filter="filter"
            @remove="removeFilter(section.id, filter.id)"
          />
        </div>

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
import FilterChip from '../concord/FilterChip.vue'

export default {
  name: 'FilterSettingsView',
  components: { FilterChip },
  props: {
    sections: {
      type: Array,
      required: true,
    },
  },
  emits: ['back', 'edit-section', 'add-filter', 'remove-filter'],
  methods: {
    editSection(sectionId) {
      this.$emit('edit-section', sectionId)
    },
    removeFilter(sectionId, filterId) {
      this.$emit('remove-filter', { sectionId, filterId })
    },
  },
}
</script>
