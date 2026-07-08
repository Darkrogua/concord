<template>
  <button
    type="button"
    class="tg-btn tg-btn--icon tg-btn--surface"
    aria-label="Редактировать"
    @click="$emit('click')"
  >
    <svg class="tg-btn__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path
        d="M4 20h4l10.5-10.5a2.1 2.1 0 00-3-3L5 17v3z"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
      <path
        d="M13.5 6.5l3 3"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
      />
    </svg>
  </button>
</template>

<script>
export default {
  name: 'BlockEditButton',
  emits: ['click'],
}
</script>
