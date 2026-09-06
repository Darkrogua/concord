<template>
  <svg
    class="concord-plus-icon"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M12 5v14M5 12h14"
      stroke="currentColor"
      stroke-width="2"
      stroke-linecap="round"
    />
  </svg>
</template>

<script>
export default {
  name: 'ConcordPlusIcon',
  props: {
    size: {
      type: [Number, String],
      default: 18,
    },
  },
}
</script>
