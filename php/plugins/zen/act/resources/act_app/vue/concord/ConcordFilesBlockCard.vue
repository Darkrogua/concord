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
        </div>
        <p class="concord-files-block__name">{{ file.name }}</p>
        <input
          v-model="file.comment"
          type="text"
          class="concord-files-block__comment"
          placeholder="Комментарий"
          aria-label="Комментарий к файлу"
        >
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
      :src="imageViewerSrc"
      :alt="imageViewerAlt"
      @close="imageViewerOpen = false"
    />

    <ConcordTextFilePreviewSheet
      :open="textPreviewOpen"
      :file-name="textPreviewName"
      :content="textPreviewContent"
      @close="textPreviewOpen = false"
      @download="downloadTextPreview"
    />
  </article>
</template>

<script>
import { ref } from 'vue'
import ConcordGroupDeleteIcon from './ConcordGroupDeleteIcon.vue'
import ConcordImageViewer from './ConcordImageViewer.vue'
import ConcordTextFilePreviewSheet from './ConcordTextFilePreviewSheet.vue'
import { cacheAgreementFileContent } from './agreement-file-content.js'
import {
  downloadAgreementFile,
  getAgreementFileSource,
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
    const imageViewerSrc = ref('')
    const imageViewerAlt = ref('')
    const textPreviewOpen = ref(false)
    const textPreviewName = ref('')
    const textPreviewContent = ref('')
    const textPreviewFile = ref(null)

    normalizeFilesBlock(props.block)

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
      imageViewerSrc.value = file.previewUrl || getAgreementFileSource(file)
      imageViewerAlt.value = file.name || 'Файл'
      imageViewerOpen.value = true
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
      imageViewerSrc,
      imageViewerAlt,
      textPreviewOpen,
      textPreviewName,
      textPreviewContent,
      openFilePicker,
      onFilesSelected,
      removeFile,
      openFilePreview,
      downloadTextPreview,
    }
  },
}
</script>
