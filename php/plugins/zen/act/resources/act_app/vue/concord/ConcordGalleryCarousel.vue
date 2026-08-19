<template>
  <div class="concord-gallery-carousel">
    <div class="concord-gallery-carousel__main">
      <div
        ref="trackRef"
        class="concord-gallery-carousel__track"
        @scroll.passive="updateActiveIndex"
      >
        <figure
          v-for="(photo, index) in photos"
          :key="photo.id || `${photo.previewUrl}-${index}`"
          class="concord-gallery-carousel__slide"
          @click="$emit('open', index)"
        >
          <img
            v-if="photo.previewUrl"
            :src="photo.previewUrl"
            :alt="photo.name || ''"
            class="concord-gallery-carousel__image"
            loading="lazy"
          >
          <figcaption v-if="galleryPhotoCaption(photo)" class="concord-gallery-carousel__caption">
            {{ galleryPhotoCaption(photo) }}
          </figcaption>
        </figure>
      </div>

      <div
        v-if="showThumbnails"
        ref="thumbsRef"
        class="concord-gallery-carousel__thumbs"
      >
        <button
          v-for="(photo, index) in photos"
          :key="`thumb-${photo.id || index}`"
          type="button"
          class="concord-gallery-carousel__thumb"
          :class="{ 'concord-gallery-carousel__thumb--active': index === activeIndex }"
          :aria-label="photo.name || `Фото ${index + 1}`"
          :aria-current="index === activeIndex ? 'true' : undefined"
          @click="scrollTo(index)"
        >
          <img
            v-if="photo.previewUrl"
            :src="photo.previewUrl"
            alt=""
            class="concord-gallery-carousel__thumb-image"
            loading="lazy"
          >
        </button>
      </div>
    </div>

    <div v-if="photos.length > 1" class="concord-gallery-carousel__footer">
      <button
        type="button"
        class="concord-gallery-carousel__nav concord-gallery-carousel__nav--prev"
        aria-label="Предыдущее фото"
        :disabled="activeIndex === 0"
        @click="scrollTo(activeIndex - 1)"
      >
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>

      <div class="concord-gallery-carousel__meta">
        <span class="concord-gallery-carousel__counter">{{ activeIndex + 1 }} / {{ photos.length }}</span>
        <div class="concord-gallery-carousel__dots" aria-hidden="true">
          <span
            v-for="(_, dotIndex) in photos"
            :key="dotIndex"
            class="concord-gallery-carousel__dot"
            :class="{ 'concord-gallery-carousel__dot--active': dotIndex === activeIndex }"
          />
        </div>
      </div>

      <button
        type="button"
        class="concord-gallery-carousel__nav concord-gallery-carousel__nav--next"
        aria-label="Следующее фото"
        :disabled="activeIndex === photos.length - 1"
        @click="scrollTo(activeIndex + 1)"
      >
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
          <path d="m10 6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<script>
import { computed, nextTick, ref, watch } from 'vue'

const THUMBNAILS_MIN_COUNT = 6

export default {
  name: 'ConcordGalleryCarousel',
  props: {
    photos: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['open'],
  setup(props) {
    const trackRef = ref(null)
    const thumbsRef = ref(null)
    const activeIndex = ref(0)

    const showThumbnails = computed(() => props.photos.length > 5)

    function galleryPhotoCaption(photo) {
      return String(photo?.comment || '').trim()
    }

    function scrollThumbIntoView(index) {
      const thumbs = thumbsRef.value
      if (!thumbs) {
        return
      }
      const thumb = thumbs.children[index]
      thumb?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' })
    }

    function scrollTo(index) {
      const track = trackRef.value
      if (!track || index < 0 || index >= props.photos.length) {
        return
      }
      const slide = track.children[index]
      if (!slide) {
        return
      }
      slide.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' })
      activeIndex.value = index
      nextTick(() => scrollThumbIntoView(index))
    }

    function updateActiveIndex() {
      const track = trackRef.value
      if (!track || !track.children.length) {
        return
      }
      const slideWidth = track.clientWidth || 1
      const nextIndex = Math.round(track.scrollLeft / slideWidth)
      const clampedIndex = Math.min(Math.max(nextIndex, 0), props.photos.length - 1)
      if (clampedIndex === activeIndex.value) {
        return
      }
      activeIndex.value = clampedIndex
      nextTick(() => scrollThumbIntoView(clampedIndex))
    }

    watch(
      () => props.photos.length,
      () => {
        activeIndex.value = 0
        if (trackRef.value) {
          trackRef.value.scrollLeft = 0
        }
      }
    )

    return {
      trackRef,
      thumbsRef,
      activeIndex,
      showThumbnails,
      galleryPhotoCaption,
      scrollTo,
      updateActiveIndex,
    }
  },
}
</script>
