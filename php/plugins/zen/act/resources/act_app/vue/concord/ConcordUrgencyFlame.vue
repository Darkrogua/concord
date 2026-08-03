<template>
  <svg
    class="concord-urgency-flame"
    :class="{
      'concord-urgency-flame--urgent': urgent,
      'concord-urgency-flame--tall': tall,
    }"
    viewBox="0 0 48 48"
    aria-hidden="true"
  >
    <path
      class="concord-urgency-flame__outer"
      d="M25.6 2.8c1.2 8.8-1.2 13.2-5.6 17.2-2.8 2.6-4.9 5.6-4.9 10.4 0 6.8 5.1 12.3 12.2 12.3 7.7 0 13-5.9 13-13.7 0-7.2-4.5-13.5-14.7-26.2Z"
    />
    <path
      class="concord-urgency-flame__inner"
      d="M27.3 20.1c.8 4.7-1 7-3.2 9.3-1.2 1.3-1.9 2.8-1.9 4.7 0 3 2.1 5.2 5.1 5.2 3.3 0 5.5-2.4 5.5-5.7 0-3-1.8-6.2-5.5-13.5Z"
    />
  </svg>
</template>

<script>
export default {
  name: 'ConcordUrgencyFlame',
  props: {
    urgent: {
      type: Boolean,
      default: false,
    },
    tall: {
      type: Boolean,
      default: false,
    },
  },
}
</script>

<style scoped>
.concord-urgency-flame {
  display: block;
  flex-shrink: 0;
  width: 20px;
  height: 20px;
}

.concord-urgency-flame__outer {
  fill: var(--concord-card-meta);
}

.concord-urgency-flame__inner {
  fill: transparent;
}

.concord-urgency-flame--urgent .concord-urgency-flame__outer {
  fill: #ef4444;
}

.concord-urgency-flame--urgent .concord-urgency-flame__inner {
  fill: #fbbf24;
}

.concord-urgency-flame--tall {
  width: 100%;
  height: 100%;
}
</style>
