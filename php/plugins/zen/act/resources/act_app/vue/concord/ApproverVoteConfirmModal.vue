<template>
  <template v-if="open">
    <div class="concord-sheet-backdrop" @click="$emit('close')" />
    <div class="concord-sheet concord-confirm-sheet" role="dialog" aria-label="Подтверждение">
      <div class="concord-sheet__handle" aria-hidden="true" />
      <h2 class="concord-sheet__title">Вы уверены?</h2>
      <p class="concord-confirm-sheet__text">После согласования решение можно будет изменить.</p>
      <div class="concord-confirm-sheet__actions">
        <button type="button" class="concord-btn concord-btn--primary" @click="$emit('confirm')">
          Да
        </button>
        <button type="button" class="concord-btn concord-btn--secondary" @click="$emit('close')">
          Нет
        </button>
      </div>
    </div>
  </template>
</template>

<script>
export default {
  name: 'ApproverVoteConfirmModal',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['confirm', 'close'],
}
</script>
