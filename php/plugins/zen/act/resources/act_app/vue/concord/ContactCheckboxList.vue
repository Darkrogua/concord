<template>
  <div class="concord-contact-picker">
    <div v-if="showSearch" class="concord-filter-modal__combobox">
      <input
        :value="searchQuery"
        type="search"
        class="concord-filter-modal__combobox-input"
        placeholder="Имя или email"
        @input="$emit('update:searchQuery', $event.target.value)"
      >
      <svg class="concord-filter-modal__combobox-caret" viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
        <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>

    <div class="concord-filter-modal__users">
      <div
        v-for="contact in contacts"
        :key="contact.id"
        class="concord-filter-modal__user"
        :class="{ 'concord-filter-modal__user--selected': isSelected(contact.id) }"
      >
        <button
          type="button"
          class="concord-filter-modal__user-avatar"
          :class="{ 'concord-filter-modal__user-avatar--selected': isSelected(contact.id) }"
          :aria-label="isSelected(contact.id) ? `Убрать ${contact.name}` : `Выбрать ${contact.name}`"
          :aria-pressed="isSelected(contact.id)"
          @click="toggleContact(contact.id)"
        >
          <span v-if="!isSelected(contact.id)" class="concord-filter-modal__user-initial">
            {{ contact.initial }}
          </span>
          <svg
            v-else
            class="concord-filter-modal__user-check"
            viewBox="0 0 16 16"
            width="18"
            height="18"
            fill="none"
            aria-hidden="true"
          >
            <path
              d="M3.5 8.2 6.4 11 12.5 5"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </button>
        <button
          type="button"
          class="concord-filter-modal__user-info"
          @click="toggleContact(contact.id)"
        >
          <span class="concord-filter-modal__user-name">{{ contact.name }}</span>
          <span v-if="contactSubtitle(contact)" class="concord-filter-modal__user-email">
            {{ contactSubtitle(contact) }}
          </span>
        </button>
      </div>

      <p v-if="!contacts.length" class="concord-filter-modal__empty">
        Ничего не найдено
      </p>
    </div>
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
    contactSubtitle(contact) {
      return contact.email || contact.shortName || ''
    },
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
