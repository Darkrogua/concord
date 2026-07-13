<template>
  <span
    class="concord-filter-chip-icon"
    :class="`concord-filter-chip-icon--${category}`"
    aria-hidden="true"
  >
    <svg v-if="category === 'urgency'" viewBox="0 0 24 24" fill="none">
      <path
        d="M12 20.5C7.4 16.8 5 12.8 6.5 8.8 7.4 6.5 9 7.8 9.8 9.5 9.6 7.2 10.8 5.2 12 4 13.2 5.2 14.4 7.2 14.6 9.5 15.4 7.8 17 6.5 18.5 8.8 20 12.8 16.6 16.8 12 20.5Z"
        fill="currentColor"
      />
    </svg>
    <svg v-else-if="category === 'created'" viewBox="0 0 24 24" fill="none">
      <circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.6"/>
      <path d="M12 7.5v5l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
    </svg>
    <svg v-else-if="category === 'participant'" viewBox="0 0 24 24" fill="none">
      <circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.6"/>
      <path d="M6 19.5c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
    </svg>
    <svg v-else viewBox="0 0 24 24" fill="none">
      <path
        d="M4 5.5h16l-2.2 7.2a2 2 0 0 1-1.9 1.3H8.1a2 2 0 0 1-1.9-1.3L4 5.5Z"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linejoin="round"
      />
      <path d="M9.5 14v4M14.5 14v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
    </svg>
  </span>
</template>

<script>
export default {
  name: 'FilterChipIcon',
  props: {
    category: {
      type: String,
      default: 'status',
      validator: (v) => ['urgency', 'created', 'participant', 'status'].includes(v),
    },
  },
}
</script>
