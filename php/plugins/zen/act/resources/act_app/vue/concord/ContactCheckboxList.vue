<template>
  <div class="concord-contact-list">
    <label v-if="showSearch" class="concord-contact-list__search-wrap">
      <span class="concord-contact-list__search-label">Поиск</span>
      <input
        :value="searchQuery"
        type="search"
        class="concord-contact-list__search"
        placeholder="Поиск"
        @input="$emit('update:searchQuery', $event.target.value)"
      >
    </label>

    <ul class="concord-contact-list__items">
      <li
        v-for="contact in contacts"
        :key="contact.id"
        class="concord-contact-list__item"
      >
        <button
          type="button"
          class="concord-contact-list__row"
          @click="toggleContact(contact.id)"
        >
          <span class="concord-contact-list__avatar" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
              <circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.6"/>
              <path d="M6 19.5c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
          </span>
          <span class="concord-contact-list__name">{{ contact.shortName }}</span>
          <span
            class="concord-contact-list__checkbox"
            :class="{ 'concord-contact-list__checkbox--checked': isSelected(contact.id) }"
            aria-hidden="true"
          >
            <svg v-if="isSelected(contact.id)" viewBox="0 0 16 16" width="12" height="12" fill="none">
              <path d="M3.5 8.2 6.4 11 12.5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </button>
      </li>
    </ul>
  </div>
</template>

<script>
export default {
  name: 'ContactCheckboxList',
  props: {
    contacts: {
      type: Array,
      required: true,
    },
    selectedIds: {
      type: Array,
      default: () => [],
    },
    searchQuery: {
      type: String,
      default: '',
    },
    showSearch: {
      type: Boolean,
      default: true,
    },
  },
  emits: ['update:selectedIds', 'update:searchQuery'],
  methods: {
    isSelected(id) {
      return this.selectedIds.includes(id)
    },
    toggleContact(id) {
      const next = this.isSelected(id)
        ? this.selectedIds.filter((item) => item !== id)
        : [...this.selectedIds, id]
      this.$emit('update:selectedIds', next)
    },
  },
}
</script>
