<template>
  <div
    v-if="hasContent"
    class="tg-markdown-body"
    v-html="html"
  />
  <p v-else-if="showEmpty" class="tg-markdown-body tg-markdown-body--empty">
    {{ emptyLabel }}
  </p>
</template>

<script>
import { computed } from 'vue'
import { useMarkdownRenderer } from '../../composables/useMarkdownRenderer.js'

export default {
  name: 'MarkdownView',
  props: {
    markdown: {
      type: String,
      default: '',
    },
    showEmpty: {
      type: Boolean,
      default: false,
    },
    emptyLabel: {
      type: String,
      default: 'Пустой Markdown-блок',
    },
  },
  setup(props) {
    const { renderMarkdown } = useMarkdownRenderer()

    const hasContent = computed(() => (props.markdown || '').trim().length > 0)
    const html = computed(() => renderMarkdown(props.markdown))

    return {
      hasContent,
      html,
    }
  },
}
</script>
