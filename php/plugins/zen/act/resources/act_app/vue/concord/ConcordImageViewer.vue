<template>
  <template v-if="open">
    <div
      class="concord-image-viewer"
      @click="$emit('close')"
      @touchstart.passive="onTouchStart"
      @touchend.passive="onTouchEnd"
    >
      <button type="button" class="concord-image-viewer__close" aria-label="Закрыть" @click.stop="$emit('close')">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" aria-hidden="true">
          <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>

      <button
        v-if="hasMultiple"
        type="button"
        class="concord-image-viewer__nav concord-image-viewer__nav--prev"
        aria-label="Предыдущее фото"
        @click.stop="showPrev"
      >
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>

      <img
        :src="currentSrc"
        :alt="currentAlt"
        class="concord-image-viewer__image"
        @click.stop
      >

      <button
        v-if="hasMultiple"
        type="button"
        class="concord-image-viewer__nav concord-image-viewer__nav--next"
        aria-label="Следующее фото"
        @click.stop="showNext"
      >
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" aria-hidden="true">
          <path d="m10 6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>

      <p v-if="hasMultiple" class="concord-image-viewer__counter">
        {{ currentIndex + 1 }} / {{ galleryPhotos.length }}
      </p>
      <p v-if="currentAlt" class="concord-image-viewer__caption">{{ currentAlt }}</p>
    </div>
  </template>
</template>

<script>
import { computed, ref, watch } from 'vue'

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
    photos: {
      type: Array,
      default: () => [],
    },
    index: {
      type: Number,
      default: 0,
    },
  },
  emits: ['close'],
  setup(props) {
    const currentIndex = ref(0)
    const touchStartX = ref(0)

    const galleryPhotos = computed(() =>
      props.photos.filter((photo) => photo?.previewUrl)
    )

    const hasMultiple = computed(() => galleryPhotos.value.length > 1)

    const currentPhoto = computed(() => {
      if (galleryPhotos.value.length) {
        return galleryPhotos.value[currentIndex.value] || galleryPhotos.value[0]
      }
      return { previewUrl: props.src, name: props.alt }
    })

    const currentSrc = computed(() => currentPhoto.value?.previewUrl || props.src)
    const currentAlt = computed(() => currentPhoto.value?.name || props.alt)

    watch(
      () => [props.open, props.index],
      ([isOpen]) => {
        if (!isOpen) {
          return
        }
        const maxIndex = Math.max(galleryPhotos.value.length - 1, 0)
        currentIndex.value = Math.min(Math.max(props.index, 0), maxIndex)
      }
    )

    function showPrev() {
      if (!hasMultiple.value) {
        return
      }
      currentIndex.value =
        (currentIndex.value - 1 + galleryPhotos.value.length) % galleryPhotos.value.length
    }

    function showNext() {
      if (!hasMultiple.value) {
        return
      }
      currentIndex.value = (currentIndex.value + 1) % galleryPhotos.value.length
    }

    function onTouchStart(event) {
      touchStartX.value = event.changedTouches?.[0]?.clientX || 0
    }

    function onTouchEnd(event) {
      if (!hasMultiple.value) {
        return
      }
      const endX = event.changedTouches?.[0]?.clientX || 0
      const delta = endX - touchStartX.value
      if (Math.abs(delta) < 48) {
        return
      }
      if (delta > 0) {
        showPrev()
        return
      }
      showNext()
    }

    return {
      galleryPhotos,
      hasMultiple,
      currentIndex,
      currentSrc,
      currentAlt,
      showPrev,
      showNext,
      onTouchStart,
      onTouchEnd,
    }
  },
}
</script>
