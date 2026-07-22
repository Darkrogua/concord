<template>
  <article class="concord-block concord-text-block-preview" @click="$emit('edit')">
    <div class="concord-block__plate">Текст</div>
    <h3 class="concord-text-block-preview__title">
      {{ previewTitle }}
    </h3>
    <p v-if="previewDescription" class="concord-text-block-preview__desc">
      {{ previewDescription }}
    </p>
    <p v-if="previewExcerpt" class="concord-text-block-preview__excerpt">
      {{ previewExcerpt }}
    </p>
    <p
      v-else-if="!previewDescription"
      class="concord-text-block-preview__desc concord-text-block-preview__desc--empty"
    >
      Нажмите, чтобы открыть редактор и добавить текст
    </p>
  </article>
</template>

<script>
import { computed, watch } from 'vue'
import { normalizeTextBlock } from './mock-agreements.js'

const DEFAULT_TITLE = 'Заголовок текстового блока'
const EXCERPT_LIMIT = 180
const DESCRIPTION_LIMIT = 120

function truncateText(value, limit) {
  const text = String(value || '').trim()
  if (!text) {
    return ''
  }
  return text.length > limit ? `${text.slice(0, limit)}…` : text
}

function stripPreviewText(value) {
  return String(value || '')
    .replace(/<[^>]+>/g, ' ')
    .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
    .replace(/!\[([^\]]*)\]\([^)]+\)/g, '$1')
    .replace(/```[\s\S]*?```/g, ' ')
    .replace(/`([^`]+)`/g, '$1')
    .replace(/^#{1,6}\s+/gm, '')
    .replace(/^>\s+/gm, '')
    .replace(/^[-*+]\s+/gm, '')
    .replace(/^\d+\.\s+/gm, '')
    .replace(/[*_~#>`[\]()\\-]/g, '')
    .replace(/\s+/g, ' ')
    .trim()
}

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
    watch(
      () => props.block,
      (block) => {
        if (block) {
          normalizeTextBlock(block)
        }
      },
      { immediate: true }
    )

    const previewTitle = computed(() => {
      const title = props.block.title?.trim()
      return title || DEFAULT_TITLE
    })

    const previewDescription = computed(() =>
      truncateText(props.block.description, DESCRIPTION_LIMIT)
    )

    const previewExcerpt = computed(() => {
      const content = stripPreviewText(props.block.content)
      if (content) {
        return truncateText(content, EXCERPT_LIMIT)
      }

      const rawContent = String(props.block.content || '').trim()
      if (rawContent && !rawContent.startsWith('{')) {
        return truncateText(rawContent, EXCERPT_LIMIT)
      }

      return ''
    })

    return { previewTitle, previewDescription, previewExcerpt }
  },
}
</script>
