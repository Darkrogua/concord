<template>
  <template v-if="open">
    <div class="concord-sheet-backdrop" @click="$emit('close')" />
    <div class="concord-sheet concord-reason-sheet" role="dialog" aria-label="Комментарий">
      <div class="concord-sheet__handle" aria-hidden="true" />
      <h2 class="concord-sheet__title">Комментарий</h2>
      <p class="concord-reason-sheet__text">{{ reason }}</p>
      <div class="concord-reason-sheet__actions">
        <button type="button" class="concord-btn concord-btn--primary" @click="$emit('close')">
          Закрыть
        </button>
      </div>
    </div>
  </template>
</template>

<script>
export default {
  name: 'ConcordReasonViewModal',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    reason: {
      type: String,
      default: '',
    },
  },
  emits: ['close'],
}
</script>
