<template>
  <template v-if="open">
    <div
      class="concord-image-viewer"
      :class="{ 'concord-image-viewer--caption-editable': captionEditable }"
      @click="onBackdropClick"
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

      <div
        v-if="captionEditable && currentPhoto"
        class="concord-image-viewer__caption-panel"
        @click.stop
      >
        <label class="concord-image-viewer__caption-label" :for="captionInputId">Подпись</label>
        <textarea
          :id="captionInputId"
          v-model="currentPhoto.comment"
          class="concord-image-viewer__caption-input"
          rows="2"
          maxlength="500"
          placeholder="Добавьте подпись к фото…"
          @click.stop
          @focus="captionFocused = true"
          @blur="captionFocused = false"
        />
      </div>
      <p v-else-if="currentCaption" class="concord-image-viewer__caption">{{ currentCaption }}</p>
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
    captionEditable: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['close'],
  setup(props, { emit }) {
    const currentIndex = ref(0)
    const touchStartX = ref(0)
    const captionFocused = ref(false)
    const captionInputId = `concord-image-caption-${Math.random().toString(36).slice(2, 8)}`

    const galleryPhotos = computed(() =>
      props.photos.filter((photo) => photo?.previewUrl)
    )

    const hasMultiple = computed(() => galleryPhotos.value.length > 1)

    const currentPhoto = computed(() => {
      if (galleryPhotos.value.length) {
        return galleryPhotos.value[currentIndex.value] || galleryPhotos.value[0]
      }
      return null
    })

    const currentSrc = computed(() => currentPhoto.value?.previewUrl || props.src)
    const currentAlt = computed(() => currentPhoto.value?.name || props.alt)
    const currentCaption = computed(() => {
      const comment = String(currentPhoto.value?.comment || '').trim()
      return comment || currentAlt.value
    })

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
      if (!hasMultiple.value || captionFocused.value) {
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

    function onBackdropClick() {
      if (captionFocused.value) {
        return
      }
      emit('close')
    }

    return {
      galleryPhotos,
      hasMultiple,
      currentIndex,
      currentPhoto,
      currentSrc,
      currentAlt,
      currentCaption,
      captionFocused,
      captionInputId,
      showPrev,
      showNext,
      onTouchStart,
      onTouchEnd,
      onBackdropClick,
    }
  },
}
</script>
