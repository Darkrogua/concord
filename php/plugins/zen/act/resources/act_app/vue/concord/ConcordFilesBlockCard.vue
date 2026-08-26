<template>
  <article class="concord-block concord-files-block">
    <div class="concord-block__plate">Файлы</div>
    <header class="concord-block__header">
      <input
        v-model="block.title"
        type="text"
        class="concord-block__title-input"
        placeholder="Название блока"
        aria-label="Название блока"
      >
    </header>

    <div v-if="(block.files || []).length" class="concord-files-block__grid">
      <div
        v-for="file in block.files"
        :key="file.id"
        class="concord-files-block__item"
      >
        <div
          class="concord-files-block__preview"
          role="button"
          tabindex="0"
          :aria-label="`Открыть ${file.name || 'файл'}`"
          @click="openFilePreview(file)"
          @keydown.enter.prevent="openFilePreview(file)"
          @keydown.space.prevent="openFilePreview(file)"
        >
          <img
            v-if="file.previewUrl"
            :src="file.previewUrl"
            :alt="file.name"
            class="concord-files-block__image"
          >
          <div v-else class="concord-files-block__doc" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none">
              <path d="M8 3h6l5 5v13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.4"/>
              <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.4"/>
            </svg>
          </div>
          <button
            type="button"
            class="concord-files-block__view"
            aria-label="Открыть файл"
            @click.stop="openFilePreview(file)"
          >
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" aria-hidden="true">
              <path d="M2.8 12s3.3-5.5 9.2-5.5 9.2 5.5 9.2 5.5-3.3 5.5-9.2 5.5S2.8 12 2.8 12Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
              <circle cx="12" cy="12" r="2.8" stroke="currentColor" stroke-width="1.8"/>
            </svg>
          </button>
          <button
            type="button"
            class="concord-files-block__delete"
            aria-label="Удалить файл"
            @click.stop="removeFile(file.id)"
          >
            <ConcordGroupDeleteIcon />
          </button>
          <button
            type="button"
            class="concord-files-block__comment-btn"
            :class="{ 'concord-files-block__comment-btn--filled': hasFileComment(file) }"
            :aria-label="hasFileComment(file) ? 'Изменить комментарий' : 'Добавить комментарий'"
            @click.stop="openCommentSheet(file)"
          >
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true">
              <path d="M7 9h10M7 12.5h6.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
              <path d="M5 4.5h14a1.5 1.5 0 0 1 1.5 1.5v9.8a1.5 1.5 0 0 1-1.5 1.5H10l-4.2 3.2a.8.8 0 0 1-1.3-.65V6A1.5 1.5 0 0 1 5 4.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
        <p class="concord-files-block__name">{{ file.name }}</p>
        <button
          type="button"
          class="concord-files-block__caption-chip"
          :class="{ 'concord-files-block__caption-chip--filled': hasFileComment(file) }"
          @click.stop="openCommentSheet(file)"
        >
          <span class="concord-files-block__caption-chip-text">
            {{ fileCommentLabel(file) }}
          </span>
        </button>
      </div>
    </div>

    <input
      ref="fileInput"
      type="file"
      class="concord-files-block__input"
      multiple
      @change="onFilesSelected"
    >

    <button type="button" class="concord-block__add-btn" @click="openFilePicker">
      Добавить
    </button>

    <ConcordImageViewer
      :open="imageViewerOpen"
      :photos="imageViewerPhotos"
      :index="0"
      caption-editable
      @close="closeImageViewer"
    />

    <ConcordTextFilePreviewSheet
      :open="textPreviewOpen"
      :file-name="textPreviewName"
      :content="textPreviewContent"
      @close="textPreviewOpen = false"
      @download="downloadTextPreview"
    />

    <ConcordGalleryCommentSheet
      :open="commentSheetOpen"
      :item="commentFile"
      title="Комментарий к файлу"
      hint="Пояснение видно согласователям при просмотре документа."
      placeholder="Что важно знать об этом файле для согласования…"
      @close="closeCommentSheet"
    />
  </article>
</template>

<script>
import { computed, ref } from 'vue'
import ConcordGalleryCommentSheet from './ConcordGalleryCommentSheet.vue'
import ConcordGroupDeleteIcon from './ConcordGroupDeleteIcon.vue'
import ConcordImageViewer from './ConcordImageViewer.vue'
import ConcordTextFilePreviewSheet from './ConcordTextFilePreviewSheet.vue'
import { cacheAgreementFileContent } from './agreement-file-content.js'
import {
  downloadAgreementFile,
  getAgreementFileTextContent,
  isImageAgreementFile,
  isTextLikeAgreementFile,
  isTextPreviewableAgreementFile,
  openAgreementFile,
} from './file-download-utils.js'
import { normalizeFilesBlock } from './mock-agreements.js'

export default {
  name: 'ConcordFilesBlockCard',
  components: {
    ConcordGalleryCommentSheet,
    ConcordGroupDeleteIcon,
    ConcordImageViewer,
    ConcordTextFilePreviewSheet,
  },
  props: {
    block: {
      type: Object,
      required: true,
    },
  },
  setup(props) {
    const fileInput = ref(null)
    const imageViewerOpen = ref(false)
    const imageViewerFile = ref(null)
    const commentSheetOpen = ref(false)
    const commentFile = ref(null)
    const textPreviewOpen = ref(false)
    const textPreviewName = ref('')
    const textPreviewContent = ref('')
    const textPreviewFile = ref(null)

    normalizeFilesBlock(props.block)

    const imageViewerPhotos = computed(() => {
      if (!imageViewerFile.value?.previewUrl) {
        return []
      }
      return [imageViewerFile.value]
    })

    function openFilePicker() {
      fileInput.value?.click()
    }

    function onFilesSelected(event) {
      const selected = Array.from(event.target.files || [])
      for (const file of selected) {
        const entry = {
          id: `file-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
          name: file.name,
          mime: file.type,
          previewUrl: null,
          downloadUrl: '',
          textContent: '',
          comment: '',
        }
        props.block.files.push(entry)

        if (isTextLikeAgreementFile(file)) {
          const textReader = new FileReader()
          textReader.onload = () => {
            entry.textContent = String(textReader.result || '')
            const mime = file.type || 'text/plain;charset=utf-8'
            entry.downloadUrl = `data:${mime},${encodeURIComponent(entry.textContent)}`
            cacheAgreementFileContent(entry.id, {
              downloadUrl: entry.downloadUrl,
              textContent: entry.textContent,
              mime: entry.mime,
              name: entry.name,
            })
          }
          textReader.readAsText(file)
        } else {
          const reader = new FileReader()
          reader.onload = () => {
            entry.downloadUrl = reader.result
            if (file.type.startsWith('image/')) {
              entry.previewUrl = reader.result
            }
            cacheAgreementFileContent(entry.id, {
              downloadUrl: entry.downloadUrl,
              previewUrl: entry.previewUrl,
              mime: entry.mime,
              name: entry.name,
            })
          }
          reader.readAsDataURL(file)
        }
      }
      event.target.value = ''
    }

    function removeFile(fileId) {
      props.block.files = props.block.files.filter((item) => item.id !== fileId)
    }

    function openImagePreview(file) {
      imageViewerFile.value = file
      imageViewerOpen.value = true
    }

    function closeImageViewer() {
      imageViewerOpen.value = false
      imageViewerFile.value = null
    }

    function hasFileComment(file) {
      return Boolean(String(file?.comment || '').trim())
    }

    function fileCommentLabel(file) {
      const text = String(file?.comment || '').trim()
      if (!text) {
        return 'Добавить комментарий'
      }
      return text.length > 42 ? `${text.slice(0, 42)}…` : text
    }

    function openCommentSheet(file) {
      commentFile.value = file
      commentSheetOpen.value = true
    }

    function closeCommentSheet() {
      commentSheetOpen.value = false
      commentFile.value = null
    }

    function openTextPreview(file) {
      textPreviewFile.value = file
      textPreviewName.value = file.name || 'Файл'
      textPreviewContent.value = getAgreementFileTextContent(file)
      textPreviewOpen.value = true
    }

    function openFilePreview(file) {
      if (isImageAgreementFile(file)) {
        openImagePreview(file)
        return
      }
      if (isTextPreviewableAgreementFile(file)) {
        openTextPreview(file)
        return
      }
      if (openAgreementFile(file)) {
        return
      }
      if (downloadAgreementFile(file)) {
        return
      }
      window.alert('Файл недоступен для просмотра. Попробуйте загрузить его заново.')
    }

    function downloadTextPreview() {
      if (textPreviewFile.value) {
        downloadAgreementFile(textPreviewFile.value)
      }
    }

    return {
      fileInput,
      imageViewerOpen,
      imageViewerPhotos,
      commentSheetOpen,
      commentFile,
      textPreviewOpen,
      textPreviewName,
      textPreviewContent,
      openFilePicker,
      onFilesSelected,
      removeFile,
      openFilePreview,
      closeImageViewer,
      hasFileComment,
      fileCommentLabel,
      openCommentSheet,
      closeCommentSheet,
      downloadTextPreview,
    }
  },
}
</script>
