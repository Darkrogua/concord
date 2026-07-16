<template>
  <div class="concord-group-header-actions">
    <span
      v-if="showCount"
      class="concord-group-header-actions__count"
      :aria-label="`Участников: ${memberCount}`"
    >
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
        <circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.6"/>
        <path d="M6 19.5c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
      <span class="concord-group-header-actions__badge">{{ memberCount }}</span>
    </span>

    <button
      type="button"
      class="concord-group-header-actions__save"
      aria-label="Готово"
      :disabled="disabled"
      @click="$emit('save')"
    >
      <svg viewBox="0 0 16 16" width="18" height="18" fill="none" aria-hidden="true">
        <path
          d="M3.5 8.2 6.4 11 12.5 5"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </button>
  </div>
</template>

<script>
export default {
  name: 'ConcordGroupHeaderActions',
  props: {
    memberCount: {
      type: Number,
      default: 0,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    showCount: {
      type: Boolean,
      default: true,
    },
  },
  emits: ['save'],
}
</script>
