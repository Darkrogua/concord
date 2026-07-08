<template>
  <div class="tg-gallery-swiper">
    <Swiper
      class="tg-gallery-swiper__thumbs"
      :modules="modules"
      :space-between="8"
      :slides-per-view="4"
      :free-mode="true"
      :watch-slides-progress="true"
      :breakpoints="thumbBreakpoints"
      @swiper="onThumbsSwiper"
    >
      <SwiperSlide
        v-for="item in items"
        :key="`thumb-${item.id}`"
        class="tg-gallery-swiper__thumb-slide"
      >
        <img
          :src="imageUrl(item.id)"
          :alt="item.alt || ''"
          class="tg-gallery-swiper__thumb"
        >
      </SwiperSlide>
    </Swiper>

    <Swiper
      v-if="thumbsSwiper"
      class="tg-gallery-swiper__main"
      :modules="modules"
      :space-between="10"
      :slides-per-view="1"
      :navigation="true"
      :pagination="{ clickable: true }"
      :thumbs="{ swiper: thumbsSwiper }"
      :observer="true"
      :observe-parents="true"
      :resize-observer="true"
      @swiper="onMainSwiper"
    >
      <SwiperSlide
        v-for="item in items"
        :key="item.id"
        class="tg-gallery-swiper__slide"
      >
        <button
          type="button"
          class="tg-gallery-swiper__open"
          :aria-label="item.alt || 'Открыть изображение'"
          @click="openLightbox(item.id)"
        >
          <img
            :src="imageUrl(item.id)"
            :alt="item.alt || ''"
            class="tg-gallery-swiper__image"
          >
        </button>
      </SwiperSlide>
    </Swiper>

    <Teleport to="body">
      <div
        v-if="lightboxOpen"
        class="tg-gallery-lightbox"
        role="dialog"
        aria-modal="true"
        @click.self="closeLightbox"
      >
        <button
          type="button"
          class="tg-gallery-lightbox__close"
          aria-label="Закрыть"
          @click="closeLightbox"
        >
          ×
        </button>
        <Swiper
          class="tg-gallery-lightbox__swiper"
          :modules="lightboxModules"
          :initial-slide="lightboxIndex"
          :space-between="16"
          :navigation="true"
          :pagination="{ clickable: true }"
          :zoom="{ maxRatio: 3 }"
          :keyboard="{ enabled: true }"
        >
          <SwiperSlide
            v-for="item in items"
            :key="`lb-${item.id}`"
            class="tg-gallery-lightbox__slide"
          >
            <div class="swiper-zoom-container">
              <img
                :src="imageUrl(item.id)"
                :alt="item.alt || ''"
                class="tg-gallery-lightbox__image"
              >
            </div>
          </SwiperSlide>
        </Swiper>
      </div>
    </Teleport>
  </div>
</template>

<script>
import { nextTick, ref, watch } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { FreeMode, Keyboard, Navigation, Pagination, Thumbs, Zoom } from 'swiper/modules'
import { galleryImageUrl } from '../../composables/useGalleryImage.js'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import 'swiper/css/thumbs'
import 'swiper/css/zoom'

export default {
  name: 'GallerySwiper',
  components: { Swiper, SwiperSlide },
  props: {
    actId: {
      type: String,
      required: true,
    },
    blockId: {
      type: String,
      required: true,
    },
    items: {
      type: Array,
      default: () => [],
    },
  },
  setup(props) {
    const thumbsSwiper = ref(null)
    const mainSwiper = ref(null)
    const lightboxOpen = ref(false)
    const lightboxIndex = ref(0)

    const modules = [Navigation, Pagination, Thumbs, FreeMode]
    const lightboxModules = [Navigation, Pagination, Zoom, Keyboard]

    const thumbBreakpoints = {
      0: { slidesPerView: 3 },
      480: { slidesPerView: 4 },
      768: { slidesPerView: 5 },
    }

    const imageUrl = (imageId) => galleryImageUrl(props.actId, props.blockId, imageId)

    const onThumbsSwiper = (swiper) => {
      thumbsSwiper.value = swiper
    }

    const onMainSwiper = (swiper) => {
      mainSwiper.value = swiper
      nextTick(() => {
        swiper.update()
      })
    }

    watch(thumbsSwiper, (swiper) => {
      if (!swiper) {
        return
      }
      nextTick(() => {
        mainSwiper.value?.update()
      })
    })

    const openLightbox = (imageId) => {
      const index = props.items.findIndex((item) => item.id === imageId)
      lightboxIndex.value = index >= 0 ? index : 0
      lightboxOpen.value = true
    }

    const closeLightbox = () => {
      lightboxOpen.value = false
    }

    return {
      modules,
      lightboxModules,
      thumbsSwiper,
      mainSwiper,
      thumbBreakpoints,
      imageUrl,
      onThumbsSwiper,
      onMainSwiper,
      lightboxOpen,
      lightboxIndex,
      openLightbox,
      closeLightbox,
    }
  },
}
</script>
