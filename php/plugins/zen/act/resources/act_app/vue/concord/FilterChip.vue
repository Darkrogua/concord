<template>
  <span class="concord-settings__chip">
    <FilterChipIcon :category="filter.category || 'status'" />
    <span v-if="filter.avatars?.length" class="concord-settings__chip-avatars" aria-hidden="true">
      <span
        v-for="(initial, index) in filter.avatars"
        :key="`${initial}-${index}`"
        class="concord-settings__chip-avatar"
      >
        {{ initial }}
      </span>
    </span>
    <span class="concord-settings__chip-label">{{ filter.label }}</span>
    <button
      v-if="removable"
      type="button"
      class="concord-settings__chip-remove"
      aria-label="Удалить фильтр"
      @click="$emit('remove')"
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
  },
  emits: ['remove'],
}
</script>
