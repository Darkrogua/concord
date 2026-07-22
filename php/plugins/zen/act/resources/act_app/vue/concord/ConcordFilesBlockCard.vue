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
        <div class="concord-files-block__preview">
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
            class="concord-files-block__delete"
            aria-label="Удалить файл"
            @click="removeFile(file.id)"
          >
            <ConcordGroupDeleteIcon />
          </button>
        </div>
        <p class="concord-files-block__name">{{ file.name }}</p>
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
  </article>
</template>

<script>
import { ref } from 'vue'
import ConcordGroupDeleteIcon from './ConcordGroupDeleteIcon.vue'
import { normalizeFilesBlock } from './mock-agreements.js'

export default {
  name: 'ConcordFilesBlockCard',
  components: { ConcordGroupDeleteIcon },
  props: {
    block: {
      type: Object,
      required: true,
    },
  },
  setup(props) {
    const fileInput = ref(null)

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
        }
        props.block.files.push(entry)

        if (file.type.startsWith('image/')) {
          const reader = new FileReader()
          reader.onload = () => {
            entry.previewUrl = reader.result
          }
          reader.readAsDataURL(file)
        }
      }
      event.target.value = ''
    }

    function removeFile(fileId) {
      props.block.files = props.block.files.filter((item) => item.id !== fileId)
    }

    return {
      fileInput,
      openFilePicker,
      onFilesSelected,
      removeFile,
    }
  },
}
</script>
