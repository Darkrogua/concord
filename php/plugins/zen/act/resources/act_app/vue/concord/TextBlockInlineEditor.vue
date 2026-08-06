<template>
  <div class="concord-text-block-inline">
    <label class="concord-text-block__field">
      <span class="concord-text-block__label">Заголовок</span>
      <input
        v-model="block.title"
        type="text"
        class="concord-text-block__input"
        placeholder="Заголовок блока"
      >
    </label>

    <div class="concord-text-block__field">
      <span class="concord-text-block__label">Содержание</span>
      <ConcordSimpleEditor v-model="block.content" placeholder="Введите текст блока…" />
    </div>
  </div>
</template>

<script>
import { watch } from 'vue'
import ConcordSimpleEditor from './ConcordSimpleEditor.vue'
import { normalizeTextBlock } from './mock-agreements.js'

export default {
  name: 'TextBlockInlineEditor',
  components: { ConcordSimpleEditor },
  props: {
    block: {
      type: Object,
      required: true,
    },
  },
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
  },
}
</script>
