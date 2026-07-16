<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M12 15.2a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4Z"
      stroke="currentColor"
      stroke-width="1.5"
    />
    <path
      d="M19.2 14.8a1.5 1.5 0 0 0 .3 1.7l.05.05a1.8 1.8 0 1 1-2.55 2.55l-.05-.05a1.5 1.5 0 0 0-1.7-.3 1.5 1.5 0 0 0-.9 1.37V21a1.8 1.8 0 1 1-3.6 0v-.08a1.5 1.5 0 0 0-.9-1.38 1.5 1.5 0 0 0-1.7.3l-.05.05a1.8 1.8 0 1 1-2.55-2.55l.05-.05a1.5 1.5 0 0 0 .3-1.7 1.5 1.5 0 0 0-1.37-.9H3a1.8 1.8 0 1 1 0-3.6h.08a1.5 1.5 0 0 0 1.37-.9 1.5 1.5 0 0 0-.3-1.7l-.05-.05a1.8 1.8 0 1 1 2.55-2.55l.05.05a1.5 1.5 0 0 0 1.7.3h.01a1.5 1.5 0 0 0 .9-1.37V3a1.8 1.8 0 1 1 3.6 0v.08a1.5 1.5 0 0 0 .9 1.38 1.5 1.5 0 0 0 1.7-.3l.05-.05a1.8 1.8 0 1 1 2.55 2.55l-.05.05a1.5 1.5 0 0 0-.3 1.7v.01c.24.56.7.97 1.37.97H21a1.8 1.8 0 1 1 0 3.6h-.08c-.67 0-1.13.41-1.37.97Z"
      stroke="currentColor"
      stroke-width="1.5"
      stroke-linejoin="round"
    />
  </svg>
</template>

<script>
export default {
  name: 'ConcordGearIcon',
  props: {
    size: {
      type: [Number, String],
      default: 20,
    },
  },
}
</script>
