<template>
  <article class="concord-text-block">
    <header class="concord-text-block__header">
      <span class="concord-text-block__type">Текст</span>
      <button
        type="button"
        class="concord-text-block__delete"
        aria-label="Удалить блок"
        @click="$emit('delete')"
      >
        <ConcordGroupDeleteIcon />
      </button>
    </header>

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
        rows="2"
        placeholder="Краткое описание блока"
      />
    </label>

    <div class="concord-text-block__field">
      <span class="concord-text-block__label">текст</span>
      <ConcordSimpleEditor v-model="block.content" placeholder="Введите текст блока…" />
    </div>
  </article>
</template>

<script>
import { onMounted } from 'vue'
import ConcordGroupDeleteIcon from './ConcordGroupDeleteIcon.vue'
import ConcordSimpleEditor from './ConcordSimpleEditor.vue'
import { normalizeTextBlock } from './mock-agreements.js'

export default {
  name: 'ConcordTextBlockCard',
  components: { ConcordGroupDeleteIcon, ConcordSimpleEditor },
  props: {
    block: {
      type: Object,
      required: true,
    },
  },
  emits: ['delete'],
  setup(props) {
    onMounted(() => {
      normalizeTextBlock(props.block)
    })

    return {}
  },
}
</script>
