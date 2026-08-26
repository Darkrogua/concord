<template>
  <Teleport to="body">
    <template v-if="open">
      <div
        class="concord-sheet-backdrop concord-sheet-backdrop--top concord-reason-sheet__backdrop"
        @click="$emit('close')"
      />
      <section
        class="concord-sheet concord-reason-sheet concord-reason-sheet--top"
        role="dialog"
        aria-modal="true"
        aria-labelledby="concord-reason-sheet-title"
        @click.stop
      >
        <div class="concord-sheet__handle" aria-hidden="true" />
        <h2 id="concord-reason-sheet-title" class="concord-sheet__title">Комментарий</h2>
        <p class="concord-reason-sheet__text">{{ reason }}</p>
        <div class="concord-reason-sheet__actions">
          <button type="button" class="concord-btn concord-btn--primary" @click="$emit('close')">
            Закрыть
          </button>
        </div>
      </section>
    </template>
  </Teleport>
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
