<template>
  <article class="concord-block concord-gallery-block">
    <div class="concord-block__plate">Галерея</div>
    <header class="concord-block__header">
      <input
        v-model="block.title"
        type="text"
        class="concord-block__title-input"
        placeholder="Галерея"
        aria-label="Название блока"
      >
    </header>

    <div v-if="photos.length" class="concord-gallery-block__slider-wrap">
      <div
        ref="sliderRef"
        class="concord-gallery-block__slider"
        @scroll.passive="updateActivePage"
      >
        <div
          v-for="(page, pageIndex) in photoPages"
          :key="`page-${pageIndex}`"
          class="concord-gallery-block__page"
        >
          <div class="concord-gallery-block__grid">
            <div
              v-for="(photo, photoIndex) in page"
              :key="photo.id"
              class="concord-gallery-block__item"
            >
              <div
                class="concord-gallery-block__preview"
                role="button"
                tabindex="0"
                :aria-label="`Открыть фото ${photo.name || ''}`"
                @click="openViewer(pageIndex * PAGE_SIZE + photoIndex)"
                @keydown.enter.prevent="openViewer(pageIndex * PAGE_SIZE + photoIndex)"
                @keydown.space.prevent="openViewer(pageIndex * PAGE_SIZE + photoIndex)"
              >
                <img
                  v-if="photo.previewUrl"
                  :src="photo.previewUrl"
                  :alt="photo.name"
                  class="concord-gallery-block__image"
                >
                <button
                  type="button"
                  class="concord-gallery-block__view"
                  aria-label="Открыть фото"
                  @click.stop="openViewer(pageIndex * PAGE_SIZE + photoIndex)"
                >
                  <svg viewBox="0 0 24 24" width="22" height="22" fill="none" aria-hidden="true">
                    <path d="M2.8 12s3.3-5.5 9.2-5.5 9.2 5.5 9.2 5.5-3.3 5.5-9.2 5.5S2.8 12 2.8 12Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <circle cx="12" cy="12" r="2.8" stroke="currentColor" stroke-width="1.8"/>
                  </svg>
                </button>
                <button
                  type="button"
                  class="concord-gallery-block__delete"
                  aria-label="Удалить фото"
                  @click.stop="removePhoto(photo.id)"
                >
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
                    <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  </svg>
                </button>
              </div>
              <input
                v-model="photo.comment"
                type="text"
                class="concord-gallery-block__comment"
                placeholder="Комментарий"
                aria-label="Комментарий к фото"
              />
            </div>
          </div>
        </div>
      </div>

      <div v-if="photoPages.length > 1" class="concord-gallery-block__pager">
        <button
          type="button"
          class="concord-gallery-block__pager-btn"
          aria-label="Предыдущая страница"
          :disabled="activePage === 0"
          @click="scrollToPage(activePage - 1)"
        >
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true">
            <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <div class="concord-gallery-block__dots" aria-hidden="true">
          <span
            v-for="(_, pageIndex) in photoPages"
            :key="`dot-${pageIndex}`"
            class="concord-gallery-block__dot"
            :class="{ 'concord-gallery-block__dot--active': pageIndex === activePage }"
          />
        </div>
        <span class="concord-gallery-block__page-label">
          {{ activePage + 1 }} / {{ photoPages.length }}
        </span>
        <button
          type="button"
          class="concord-gallery-block__pager-btn"
          aria-label="Следующая страница"
          :disabled="activePage >= photoPages.length - 1"
          @click="scrollToPage(activePage + 1)"
        >
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true">
            <path d="m10 6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>
    </div>

    <p v-else class="concord-gallery-block__empty">
      Фотографий пока нет
    </p>

    <input
      ref="photoInput"
      type="file"
      class="concord-gallery-block__input"
      accept="image/*"
      multiple
      @change="onPhotosSelected"
    >

    <button type="button" class="concord-block__add-btn" @click="openPhotoPicker">
      Добавить фото
    </button>

    <ConcordImageViewer
      :open="viewerOpen"
      :photos="photos"
      :index="viewerIndex"
      @close="closeViewer"
    />
  </article>
</template>

<script>
import { computed, nextTick, ref, watch } from 'vue'
import ConcordImageViewer from './ConcordImageViewer.vue'
import { normalizeGalleryBlock } from './mock-agreements.js'

const PAGE_SIZE = 6

export default {
  name: 'ConcordGalleryBlockCard',
  components: { ConcordImageViewer },
  props: {
    block: {
      type: Object,
      required: true,
    },
  },
  setup(props) {
    const photoInput = ref(null)
    const sliderRef = ref(null)
    const viewerOpen = ref(false)
    const viewerIndex = ref(0)
    const activePage = ref(0)

    normalizeGalleryBlock(props.block)

    const photos = computed(() => props.block.photos || [])

    const photoPages = computed(() => {
      const list = photos.value
      const pages = []
      for (let i = 0; i < list.length; i += PAGE_SIZE) {
        pages.push(list.slice(i, i + PAGE_SIZE))
      }
      return pages
    })

    watch(
      () => photos.value.length,
      () => {
        const maxPage = Math.max(photoPages.value.length - 1, 0)
        if (activePage.value > maxPage) {
          activePage.value = maxPage
          nextTick(() => scrollToPage(maxPage, false))
        }
      }
    )

    function openPhotoPicker() {
      photoInput.value?.click()
    }

    function onPhotosSelected(event) {
      const selected = Array.from(event.target.files || []).filter((file) => file.type.startsWith('image/'))
      for (const file of selected) {
        const entry = {
          id: `photo-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
          name: file.name,
          mime: file.type,
          previewUrl: '',
          comment: '',
        }
        props.block.photos.push(entry)

        const reader = new FileReader()
        reader.onload = () => {
          entry.previewUrl = reader.result
        }
        reader.readAsDataURL(file)
      }
      event.target.value = ''
      nextTick(() => {
        if (photoPages.value.length > 1) {
          scrollToPage(photoPages.value.length - 1)
        }
      })
    }

    function removePhoto(photoId) {
      props.block.photos = props.block.photos.filter((item) => item.id !== photoId)
    }

    function openViewer(index) {
      viewerIndex.value = index
      viewerOpen.value = true
    }

    function closeViewer() {
      viewerOpen.value = false
    }

    function updateActivePage() {
      const slider = sliderRef.value
      if (!slider || !slider.clientWidth) {
        return
      }
      const nextPage = Math.round(slider.scrollLeft / slider.clientWidth)
      activePage.value = Math.min(Math.max(nextPage, 0), Math.max(photoPages.value.length - 1, 0))
    }

    function scrollToPage(pageIndex, smooth = true) {
      const slider = sliderRef.value
      if (!slider) {
        return
      }
      const clamped = Math.min(Math.max(pageIndex, 0), Math.max(photoPages.value.length - 1, 0))
      activePage.value = clamped
      slider.scrollTo({
        left: clamped * slider.clientWidth,
        behavior: smooth ? 'smooth' : 'auto',
      })
    }

    return {
      PAGE_SIZE,
      photoInput,
      sliderRef,
      photos,
      photoPages,
      activePage,
      viewerOpen,
      viewerIndex,
      openPhotoPicker,
      onPhotosSelected,
      removePhoto,
      openViewer,
      closeViewer,
      updateActivePage,
      scrollToPage,
    }
  },
}
</script>
