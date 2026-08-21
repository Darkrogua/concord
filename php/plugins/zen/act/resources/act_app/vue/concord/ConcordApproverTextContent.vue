<template>
  <div v-if="fullText" class="concord-approver__text-content">
    <p class="concord-approver__block-content">{{ visibleText }}</p>
    <button
      v-if="needsExpand"
      type="button"
      class="concord-approver__text-more"
      @click="expanded = !expanded"
    >
      {{ expanded ? 'Свернуть' : 'Смотреть больше' }}
    </button>
  </div>
</template>

<script>
import { computed, ref } from 'vue'

const PREVIEW_CHAR_LIMIT = 240

function stripContent(value) {
  return String(value || '')
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

export default {
  name: 'ConcordApproverTextContent',
  props: {
    content: {
      type: String,
      default: '',
    },
  },
  setup(props) {
    const expanded = ref(false)

    const fullText = computed(() => stripContent(props.content))

    const needsExpand = computed(() => fullText.value.length > PREVIEW_CHAR_LIMIT)

    const visibleText = computed(() => {
      if (!needsExpand.value || expanded.value) {
        return fullText.value
      }
      return `${fullText.value.slice(0, PREVIEW_CHAR_LIMIT).trimEnd()}…`
    })

    return {
      expanded,
      fullText,
      needsExpand,
      visibleText,
    }
  },
}
</script>
