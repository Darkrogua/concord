<template>
  <header
    ref="rootRef"
    class="concord-page-header"
    :class="{
      'concord-page-header--scroll-reveal': scrollReveal,
      'concord-page-header--scroll-reveal-hidden': scrollReveal && hidden,
      'concord-page-header--chrome': chrome,
      'concord-page-header--chrome-hidden': chrome && hidden,
    }"
  >
    <div class="concord-page-header__slot concord-page-header__slot--left">
      <slot name="left">
        <button
          v-if="showBack"
          type="button"
          class="concord-icon-btn"
          :aria-label="backLabel"
          @click="$emit('back')"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </slot>
    </div>

    <div class="concord-page-header__title-wrap">
      <slot name="title">
        <h1
          ref="titleRef"
          class="concord-page-header__title"
          :class="{
            'concord-page-header__title--multiline': !chrome && titleLines > 1,
            'concord-page-header__title--compact': !chrome && compactTitle,
          }"
        >
          {{ title }}
        </h1>
      </slot>
    </div>

    <div class="concord-page-header__slot concord-page-header__slot--right">
      <slot name="right" />
    </div>
  </header>
</template>

<script>
import { defineExpose, ref } from 'vue'

export default {
  name: 'ConcordPageHeader',
  props: {
    title: {
      type: String,
      default: '',
    },
    showBack: {
      type: Boolean,
      default: false,
    },
    backLabel: {
      type: String,
      default: 'Назад',
    },
    scrollReveal: {
      type: Boolean,
      default: false,
    },
    hidden: {
      type: Boolean,
      default: false,
    },
    chrome: {
      type: Boolean,
      default: false,
    },
    titleLines: {
      type: Number,
      default: 1,
    },
    compactTitle: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['back'],
  setup() {
    const rootRef = ref(null)
    const titleRef = ref(null)

    defineExpose({ rootRef, titleRef })

    return { rootRef, titleRef }
  },
}
</script>
