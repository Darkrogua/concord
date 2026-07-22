<template>
  <template v-if="open">
    <div class="concord-sheet-backdrop" @click="$emit('close')" />
    <div class="concord-sheet concord-reject-sheet" role="dialog" aria-label="Причина отказа">
      <div class="concord-sheet__handle" aria-hidden="true" />
      <h2 class="concord-sheet__title">Почему?</h2>
      <textarea
        v-model="reason"
        class="concord-reject-sheet__input"
        rows="4"
        placeholder="Укажите причину отказа"
      />
      <div class="concord-reject-sheet__actions">
        <button type="button" class="concord-btn concord-btn--primary" @click="confirm">
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
import { ref, watch } from 'vue'

export default {
  name: 'ApproverVoteRejectModal',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['confirm', 'close'],
  setup(props, { emit }) {
    const reason = ref('')

    watch(() => props.open, (value) => {
      if (value) {
        reason.value = ''
      }
    })

    function confirm() {
      emit('confirm', reason.value.trim())
    }

    return { reason, confirm }
  },
}
</script>
