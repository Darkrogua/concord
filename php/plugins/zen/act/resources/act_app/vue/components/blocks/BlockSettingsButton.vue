<template>
  <button
    type="button"
    class="tg-btn tg-btn--icon tg-btn--surface"
    aria-label="Настройки блока"
    title="Настройки блока"
    @click="$emit('click')"
  >
    <svg class="tg-btn__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path
        d="M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z"
        stroke="currentColor"
        stroke-width="1.8"
      />
      <path
        d="M19.4 15a1.8 1.8 0 00.36 1.98l.04.04a2 2 0 01-2.83 2.83l-.04-.04a1.8 1.8 0 00-1.98-.36 1.8 1.8 0 00-1.1 1.66V21a2 2 0 01-4 0v-.06A1.8 1.8 0 008.8 19.3a1.8 1.8 0 00-1.98.36l-.04.04a2 2 0 01-2.83-2.83l.04-.04A1.8 1.8 0 004.35 15a1.8 1.8 0 00-1.66-1.1H2.6a2 2 0 010-4h.06A1.8 1.8 0 004.3 8.8a1.8 1.8 0 00-.36-1.98l-.04-.04a2 2 0 012.83-2.83l.04.04A1.8 1.8 0 008.8 4.35a1.8 1.8 0 001.1-1.66V2.6a2 2 0 014 0v.06a1.8 1.8 0 001.1 1.66 1.8 1.8 0 001.98-.36l.04-.04a2 2 0 012.83 2.83l-.04.04a1.8 1.8 0 00-.36 1.98 1.8 1.8 0 001.66 1.1h.09a2 2 0 010 4h-.06A1.8 1.8 0 0019.4 15z"
        stroke="currentColor"
        stroke-width="1.5"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
    </svg>
  </button>
</template>

<script>
export default {
  name: 'BlockSettingsButton',
  emits: ['click'],
}
</script>
