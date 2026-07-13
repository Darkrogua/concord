<template>
  <article class="concord-block concord-text-block-preview" @click="$emit('edit')">
    <button type="button" class="concord-text-block-preview__type" @click.stop="$emit('edit')">
      Текст
    </button>
    <h3 class="concord-text-block-preview__title">
      {{ block.title || 'Заголовок текстового блока' }}
    </h3>
    <p class="concord-text-block-preview__desc">
      {{ previewDescription }}
    </p>
  </article>
</template>

<script>
import { computed, onMounted } from 'vue'
import { normalizeTextBlock } from './mock-agreements.js'

export default {
  name: 'ConcordTextBlockPreview',
  props: {
    block: {
      type: Object,
      required: true,
    },
  },
  emits: ['edit'],
  setup(props) {
    onMounted(() => {
      normalizeTextBlock(props.block)
    })

    const previewDescription = computed(() => {
      if (props.block.description?.trim()) {
        return props.block.description
      }
      if (props.block.content?.trim()) {
        return props.block.content.replace(/[#*_>`[\]]/g, '').slice(0, 160)
      }
      return 'Нажмите, чтобы открыть редактор и добавить текст'
    })

    return { previewDescription }
  },
}
</script>
