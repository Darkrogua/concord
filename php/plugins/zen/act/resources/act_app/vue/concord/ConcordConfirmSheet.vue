<template>
  <template v-if="open">
    <div class="concord-sheet-backdrop" @click="$emit('cancel')" />
    <div class="concord-sheet concord-confirm-sheet" role="alertdialog" :aria-labelledby="titleId">
      <div class="concord-sheet__handle" aria-hidden="true" />
      <h2 :id="titleId" class="concord-confirm-sheet__title">{{ title }}</h2>
      <p v-if="message" class="concord-confirm-sheet__message">{{ message }}</p>

      <div class="concord-create-sheet__actions">
        <button
          type="button"
          class="concord-create-sheet__btn concord-create-sheet__btn--cancel"
          @click="$emit('cancel')"
        >
          {{ cancelLabel }}
        </button>
        <button
          type="button"
          class="concord-create-sheet__btn"
          :class="confirmTone === 'primary' ? 'concord-create-sheet__btn--save' : 'concord-create-sheet__btn--danger'"
          @click="$emit('confirm')"
        >
          {{ confirmLabel }}
        </button>
      </div>
    </div>
  </template>
</template>

<script>
export default {
  name: 'ConcordConfirmSheet',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    title: {
      type: String,
      default: 'Подтвердите действие',
    },
    message: {
      type: String,
      default: '',
    },
    confirmLabel: {
      type: String,
      default: 'Удалить',
    },
    cancelLabel: {
      type: String,
      default: 'Отмена',
    },
    confirmTone: {
      type: String,
      default: 'danger',
      validator: (value) => ['danger', 'primary'].includes(value),
    },
  },
  emits: ['confirm', 'cancel'],
  setup() {
    const titleId = `concord-confirm-sheet-title-${Math.random().toString(36).slice(2, 9)}`
    return { titleId }
  },
}
</script>
