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
                <button
                  type="button"
                  class="concord-gallery-block__comment-btn"
                  :class="{ 'concord-gallery-block__comment-btn--filled': hasPhotoComment(photo) }"
                  :aria-label="hasPhotoComment(photo) ? 'Изменить подпись' : 'Добавить подпись'"
                  @click.stop="openCommentSheet(photo)"
                >
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true">
                    <path d="M7 9h10M7 12.5h6.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    <path d="M5 4.5h14a1.5 1.5 0 0 1 1.5 1.5v9.8a1.5 1.5 0 0 1-1.5 1.5H10l-4.2 3.2a.8.8 0 0 1-1.3-.65V6A1.5 1.5 0 0 1 5 4.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                  </svg>
                </button>
              </div>
              <button
                type="button"
                class="concord-gallery-block__caption-chip"
                :class="{ 'concord-gallery-block__caption-chip--filled': hasPhotoComment(photo) }"
                @click.stop="openCommentSheet(photo)"
              >
                <span class="concord-gallery-block__caption-chip-text">
                  {{ photoCommentLabel(photo) }}
                </span>
              </button>
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
      caption-editable
      @close="closeViewer"
    />

    <ConcordGalleryCommentSheet
      :open="commentSheetOpen"
      :photo="commentPhoto"
      @close="closeCommentSheet"
    />
  </article>
</template>

<script>
import { computed, nextTick, ref, watch } from 'vue'
import ConcordGalleryCommentSheet from './ConcordGalleryCommentSheet.vue'
import ConcordImageViewer from './ConcordImageViewer.vue'
import { normalizeGalleryBlock } from './mock-agreements.js'
import {
  deleteGalleryPhoto,
  loadGalleryPhoto,
  saveGalleryPhoto,
} from './gallery-media-storage.js'

const PAGE_SIZE = 6

export default {
  name: 'ConcordGalleryBlockCard',
  components: { ConcordGalleryCommentSheet, ConcordImageViewer },
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
    const commentSheetOpen = ref(false)
    const commentPhoto = ref(null)
    const activePage = ref(0)
    const restoringPhotoIds = new Set()

    normalizeGalleryBlock(props.block)

    const photos = computed(() => props.block.photos || [])

    async function hydratePhotoPreviews() {
      for (const photo of photos.value) {
        if (!photo.id || photo.previewUrl || restoringPhotoIds.has(photo.id)) {
          continue
        }
        restoringPhotoIds.add(photo.id)
        try {
          const blob = await loadGalleryPhoto(photo.id)
          if (blob instanceof Blob && !photo.previewUrl) {
            photo.previewUrl = URL.createObjectURL(blob)
          }
        } catch {
          // Photo metadata remains available if browser storage is unavailable.
        } finally {
          restoringPhotoIds.delete(photo.id)
        }
      }
    }

    hydratePhotoPreviews()

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
        hydratePhotoPreviews()
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
          previewUrl: URL.createObjectURL(file),
          comment: '',
        }
        props.block.photos.push(entry)
        saveGalleryPhoto(entry.id, file).catch(() => {})
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
      deleteGalleryPhoto(photoId).catch(() => {})
    }

    function openViewer(index) {
      viewerIndex.value = index
      viewerOpen.value = true
    }

    function closeViewer() {
      viewerOpen.value = false
    }

    function hasPhotoComment(photo) {
      return Boolean(String(photo?.comment || '').trim())
    }

    function photoCommentLabel(photo) {
      const text = String(photo?.comment || '').trim()
      if (!text) {
        return 'Добавить подпись'
      }
      return text.length > 42 ? `${text.slice(0, 42)}…` : text
    }

    function openCommentSheet(photo) {
      commentPhoto.value = photo
      commentSheetOpen.value = true
    }

    function closeCommentSheet() {
      commentSheetOpen.value = false
      commentPhoto.value = null
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
      commentSheetOpen,
      commentPhoto,
      openPhotoPicker,
      onPhotosSelected,
      removePhoto,
      openViewer,
      closeViewer,
      hasPhotoComment,
      photoCommentLabel,
      openCommentSheet,
      closeCommentSheet,
      updateActivePage,
      scrollToPage,
    }
  },
}
</script>
