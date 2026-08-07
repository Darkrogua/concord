<template>
  <article class="concord-search-result">
    <button type="button" class="concord-search-result__button" @click="$emit('open', result)">
      <div class="concord-search-result__header">
        <span class="concord-search-result__number">#{{ agreement.number }}</span>
        <span class="concord-search-result__status">{{ statusLabel }}</span>
        <span v-if="agreement.deadline" class="concord-search-result__deadline">до {{ agreement.deadline }}</span>
      </div>

      <h2 class="concord-search-result__title">
        <template v-for="(part, index) in highlightedTitle" :key="index">
          <mark v-if="part.match" class="concord-search-result__highlight">{{ part.text }}</mark>
          <template v-else>{{ part.text }}</template>
        </template>
      </h2>

      <p v-if="match" class="concord-search-result__context">
        <span class="concord-search-result__context-label">{{ match.label }}</span>
        <span class="concord-search-result__context-text">
          <template v-for="(part, index) in highlightedPreview" :key="index">
            <mark v-if="part.match" class="concord-search-result__highlight">{{ part.text }}</mark>
            <template v-else>{{ part.text }}</template>
          </template>
        </span>
      </p>

      <svg class="concord-search-result__arrow" viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
        <path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
  </article>
</template>

<script>
function highlightParts(value, query) {
  const text = String(value || '')
  const needle = String(query || '').trim()
  if (!needle) {
    return [{ text, match: false }]
  }

  const parts = []
  const lowerText = text.toLocaleLowerCase()
  const lowerNeedle = needle.toLocaleLowerCase()
  let position = 0
  let index = lowerText.indexOf(lowerNeedle, position)

  while (index !== -1) {
    if (index > position) {
      parts.push({ text: text.slice(position, index), match: false })
    }
    parts.push({ text: text.slice(index, index + needle.length), match: true })
    position = index + needle.length
    index = lowerText.indexOf(lowerNeedle, position)
  }

  if (position < text.length) {
    parts.push({ text: text.slice(position), match: false })
  }
  return parts.length ? parts : [{ text, match: false }]
}

export default {
  name: 'ConcordSearchResultCard',
  props: {
    result: {
      type: Object,
      required: true,
    },
    query: {
      type: String,
      default: '',
    },
  },
  emits: ['open'],
  computed: {
    agreement() {
      return this.result.agreement
    },
    match() {
      return this.result.match
    },
    statusLabel() {
      if (this.agreement.status === 'draft') {
        return 'Черновик'
      }
      if (this.agreement.status === 'approved' || this.agreement.status === 'completed') {
        return 'Согласовано'
      }
      return this.agreement.isOwner ? 'Создано мной' : 'Ждёт решения'
    },
    highlightedTitle() {
      return highlightParts(this.agreement.title, this.query)
    },
    highlightedPreview() {
      return highlightParts(this.match?.preview, this.query)
    },
  },
}
</script>
