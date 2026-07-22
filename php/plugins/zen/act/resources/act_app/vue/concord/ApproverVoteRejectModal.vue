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
      <p v-if="error" class="concord-reject-sheet__error">{{ error }}</p>
      <div class="concord-reject-sheet__actions">
        <button type="button" class="concord-btn concord-btn--primary" :disabled="!canConfirm" @click="confirm">
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
import { computed, ref, watch } from 'vue'

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
    const error = ref('')

    const canConfirm = computed(() => reason.value.trim().length > 0)

    watch(() => props.open, (value) => {
      if (value) {
        reason.value = ''
        error.value = ''
      }
    })

    function confirm() {
      if (!canConfirm.value) {
        error.value = 'Укажите причину отказа'
        return
      }
      error.value = ''
      emit('confirm', reason.value.trim())
    }

    return { reason, error, canConfirm, confirm }
  },
}
</script>
