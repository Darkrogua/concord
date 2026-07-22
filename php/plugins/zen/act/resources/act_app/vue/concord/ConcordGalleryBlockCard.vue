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

    <div v-if="(block.photos || []).length" class="concord-gallery-block__grid">
      <div
        v-for="photo in block.photos || []"
        :key="photo.id"
        class="concord-gallery-block__item"
      >
        <div class="concord-gallery-block__preview">
          <img
            v-if="photo.previewUrl"
            :src="photo.previewUrl"
            :alt="photo.name"
            class="concord-gallery-block__image"
          >
          <button
            type="button"
            class="concord-gallery-block__delete"
            aria-label="Удалить фото"
            @click="removePhoto(photo.id)"
          >
            <ConcordGroupDeleteIcon />
          </button>
        </div>
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
  </article>
</template>

<script>
import { ref } from 'vue'
import ConcordGroupDeleteIcon from './ConcordGroupDeleteIcon.vue'
import { normalizeGalleryBlock } from './mock-agreements.js'

export default {
  name: 'ConcordGalleryBlockCard',
  components: { ConcordGroupDeleteIcon },
  props: {
    block: {
      type: Object,
      required: true,
    },
  },
  setup(props) {
    const photoInput = ref(null)

    normalizeGalleryBlock(props.block)

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
        }
        props.block.photos.push(entry)

        const reader = new FileReader()
        reader.onload = () => {
          entry.previewUrl = reader.result
        }
        reader.readAsDataURL(file)
      }
      event.target.value = ''
    }

    function removePhoto(photoId) {
      props.block.photos = props.block.photos.filter((item) => item.id !== photoId)
    }

    return {
      photoInput,
      openPhotoPicker,
      onPhotosSelected,
      removePhoto,
    }
  },
}
</script>
