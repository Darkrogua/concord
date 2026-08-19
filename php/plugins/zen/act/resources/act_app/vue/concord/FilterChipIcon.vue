<template>
  <span
    class="concord-filter-chip-icon"
    :class="`concord-filter-chip-icon--${category}`"
    aria-hidden="true"
  >
    <ConcordUrgencyFlame v-if="category === 'urgency'" urgent />
    <svg v-else-if="category === 'created'" viewBox="0 0 24 24" fill="none">
      <path
        fill="currentColor"
        fill-rule="evenodd"
        clip-rule="evenodd"
        d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 5a1 1 0 0 0-2 0v5.2a1 1 0 0 0 .45.83l3.5 2.1a1 1 0 1 0 1.02-1.72L13 11.68V7Z"
      />
    </svg>
    <svg v-else-if="category === 'participant'" viewBox="0 0 24 24" fill="none">
      <path
        fill="currentColor"
        d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-3.87 0-7 2.13-7 4.75V21h14v-2.25C19 16.13 15.87 14 12 14Z"
      />
    </svg>
    <svg v-else viewBox="0 0 24 24" fill="none">
      <path
        fill="currentColor"
        d="M4 5.5h16l-2.2 7.2a2 2 0 0 1-1.9 1.3H8.1a2 2 0 0 1-1.9-1.3L4 5.5Zm4.6 9.5h6.8l1.1-3.5H7.5l1.1 3.5Z"
      />
    </svg>
  </span>
</template>

<script>
import ConcordUrgencyFlame from './ConcordUrgencyFlame.vue'

export default {
  name: 'FilterChipIcon',
  components: { ConcordUrgencyFlame },
  props: {
    category: {
      type: String,
      default: 'status',
      validator: (v) => ['urgency', 'created', 'participant', 'status'].includes(v),
    },
  },
}
</script>
