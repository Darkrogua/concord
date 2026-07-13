<template>
  <div v-if="open && block" class="concord-text-editor-page">
    <header class="concord-text-editor-page__header">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="$emit('close')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-text-editor-page__title">Текст</h1>
      <button type="button" class="concord-text-editor-page__done" @click="$emit('close')">
        Готово
      </button>
    </header>

    <main class="concord-text-editor-page__body">
      <label class="concord-text-block__field">
        <span class="concord-text-block__label">заголовок</span>
        <input
          v-model="block.title"
          type="text"
          class="concord-text-block__input"
          placeholder="Заголовок блока"
        >
      </label>

      <label class="concord-text-block__field">
        <span class="concord-text-block__label">описание</span>
        <textarea
          v-model="block.description"
          class="concord-text-block__input concord-text-block__textarea"
          rows="3"
          placeholder="Краткое описание блока"
        />
      </label>

      <div class="concord-text-block__field">
        <span class="concord-text-block__label">текст</span>
        <ConcordSimpleEditor v-model="block.content" placeholder="Введите текст блока…" />
      </div>
    </main>
  </div>
</template>

<script>
import { watch } from 'vue'
import ConcordSimpleEditor from './ConcordSimpleEditor.vue'
import { normalizeTextBlock } from './mock-agreements.js'

export default {
  name: 'TextBlockEditorSheet',
  components: { ConcordSimpleEditor },
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    block: {
      type: Object,
      default: null,
    },
  },
  emits: ['close'],
  setup(props) {
    watch(
      () => props.block,
      (block) => {
        if (block) {
          normalizeTextBlock(block)
        }
      },
      { immediate: true }
    )

    return {}
  },
}
</script>
