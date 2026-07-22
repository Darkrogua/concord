<template>
  <template v-if="open">
    <div class="concord-image-viewer" @click="$emit('close')">
      <button type="button" class="concord-image-viewer__close" aria-label="Закрыть" @click.stop="$emit('close')">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" aria-hidden="true">
          <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <img :src="src" :alt="alt" class="concord-image-viewer__image" @click.stop>
    </div>
  </template>
</template>

<script>
export default {
  name: 'ConcordImageViewer',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    src: {
      type: String,
      default: '',
    },
    alt: {
      type: String,
      default: '',
    },
  },
  emits: ['close'],
}
</script>
