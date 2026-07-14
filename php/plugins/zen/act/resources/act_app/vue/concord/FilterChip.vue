<template>
  <span
    class="concord-settings__chip"
    :class="{ 'concord-settings__chip--editable': editable }"
    :role="editable ? 'button' : undefined"
    :tabindex="editable ? 0 : undefined"
    @click="onChipClick"
    @keydown.enter.prevent="onChipClick"
  >
    <FilterChipIcon :category="filter.category || 'status'" />
    <span
      v-if="filter.avatars?.length && filter.category !== 'participant'"
      class="concord-settings__chip-avatars"
      aria-hidden="true"
    >
      <span
        v-for="(initial, index) in filter.avatars"
        :key="`${initial}-${index}`"
        class="concord-settings__chip-avatar"
      >
        {{ initial }}
      </span>
    </span>
    <span class="concord-settings__chip-label">
      <template v-if="filter.category === 'participant' && participantCount">
        {{ participantCount === 1 ? 'Участник' : 'Участники' }}
        <span class="concord-settings__chip-count">{{ participantCount }}</span>
      </template>
      <template v-else>{{ filter.label }}</template>
    </span>
    <button
      v-if="removable"
      type="button"
      class="concord-settings__chip-remove"
      aria-label="Удалить фильтр"
      @click.stop="$emit('remove')"
    >
      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" aria-hidden="true">
        <path d="M7 7l10 10M17 7 7 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
      </svg>
    </button>
  </span>
</template>

<script>
import FilterChipIcon from './FilterChipIcon.vue'

export default {
  name: 'FilterChip',
  components: { FilterChipIcon },
  props: {
    filter: {
      type: Object,
      required: true,
    },
    removable: {
      type: Boolean,
      default: true,
    },
    editable: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['remove', 'edit'],
  computed: {
    participantCount() {
      if (this.filter.category !== 'participant') {
        return 0
      }
      if (Array.isArray(this.filter.avatars) && this.filter.avatars.length) {
        return this.filter.avatars.length
      }
      if (Array.isArray(this.filter.value) && this.filter.value.length) {
        return this.filter.value.length
      }
      return 0
    },
  },
  methods: {
    onChipClick(event) {
      if (!this.editable) {
        return
      }
      if (event.target.closest('.concord-settings__chip-remove')) {
        return
      }
      this.$emit('edit', this.filter)
    },
  },
}
</script>
