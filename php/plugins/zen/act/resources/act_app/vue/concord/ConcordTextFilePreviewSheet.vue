<template>
  <template v-if="open">
    <div class="concord-sheet-backdrop" @click="$emit('close')" />
    <div
      class="concord-sheet concord-text-file-preview-sheet"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
    >
      <div class="concord-sheet__handle" aria-hidden="true" />
      <h2 :id="titleId" class="concord-sheet__title">{{ fileName }}</h2>
      <pre class="concord-text-file-preview-sheet__content">{{ content }}</pre>
      <div class="concord-create-sheet__actions">
        <button
          type="button"
          class="concord-create-sheet__btn concord-create-sheet__btn--cancel"
          @click="$emit('close')"
        >
          Закрыть
        </button>
        <button
          type="button"
          class="concord-create-sheet__btn concord-create-sheet__btn--save"
          @click="$emit('download')"
        >
          Скачать
        </button>
      </div>
    </div>
  </template>
</template>

<script>
import { computed } from 'vue'

export default {
  name: 'ConcordTextFilePreviewSheet',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    fileName: {
      type: String,
      default: 'Файл',
    },
    content: {
      type: String,
      default: '',
    },
  },
  emits: ['close', 'download'],
  setup() {
    const titleId = `concord-text-file-preview-${Math.random().toString(36).slice(2, 8)}`

    return {
      titleId: computed(() => titleId),
    }
  },
}
</script>
