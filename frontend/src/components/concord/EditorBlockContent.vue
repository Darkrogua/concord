<template>
  <div>
    <template v-if="block.type === 'text' || block.type === 'code'">
      <textarea
        v-model="block.content.body"
        class="concord-agreement-form__input concord-agreement-form__textarea"
        :rows="block.type === 'code' ? 8 : 5"
        :placeholder="block.type === 'code' ? 'Вставьте код…' : 'Текст блока…'"
        @change="$emit('save')"
      />
    </template>

    <template v-else-if="block.type === 'links'">
      <article class="concord-block concord-links-block">
        <div v-if="block.content.links.length" class="concord-links-block__list">
          <div v-for="(link, index) in block.content.links" :key="link.id || index" class="concord-links-block__item">
            <input
              v-model="link.title"
              type="text"
              class="concord-links-block__input"
              placeholder="Название ссылки"
              @change="$emit('save')"
            >
            <input
              v-model="link.url"
              type="url"
              class="concord-links-block__input"
              placeholder="https://..."
              @change="$emit('save')"
            >
            <button type="button" class="concord-links-block__remove" aria-label="Удалить ссылку" @click="removeLink(index)">
              <ConcordGroupDeleteIcon />
            </button>
          </div>
        </div>
        <p v-else class="concord-links-block__empty">Ссылки пока не добавлены</p>
        <button type="button" class="concord-block__add-btn" @click="addLink">Добавить ссылку</button>
      </article>
    </template>

    <template v-else-if="block.type === 'gallery'">
      <article class="concord-block">
        <div v-if="block.content.items.length" class="concord-files-block__grid">
          <div v-for="(item, index) in block.content.items" :key="index" class="concord-files-block__item">
            <input
              v-model="item.url"
              type="url"
              class="concord-agreement-form__input"
              placeholder="URL изображения"
              @change="$emit('save')"
            >
            <input
              v-model="item.caption"
              type="text"
              class="concord-agreement-form__input"
              placeholder="Подпись"
              @change="$emit('save')"
            >
            <button type="button" class="concord-page-btn concord-page-btn--danger" @click="removeGalleryItem(index)">
              Удалить
            </button>
          </div>
        </div>
        <p v-else class="concord-links-block__empty">Фото пока не добавлены</p>
        <button type="button" class="concord-block__add-btn" @click="addGalleryItem">Добавить фото</button>
      </article>
    </template>

    <template v-else-if="block.type === 'files'">
      <article class="concord-block concord-files-block">
        <div v-if="block.files?.length" class="concord-files-block__grid">
          <div v-for="file in block.files" :key="file.id" class="concord-files-block__item">
            <span class="concord-files-block__name">{{ file.name }}</span>
            <button type="button" class="concord-links-block__remove" aria-label="Удалить файл" @click="$emit('delete-file', file)">
              <ConcordGroupDeleteIcon />
            </button>
          </div>
        </div>
        <p v-else class="concord-links-block__empty">Файлы пока не загружены</p>
        <label class="concord-block__add-btn">
          <input type="file" class="sr-only" @change="onFileSelect">
          Загрузить файл
        </label>
      </article>
    </template>
  </div>
</template>

<script setup>
import ConcordGroupDeleteIcon from './ConcordGroupDeleteIcon.vue'

const props = defineProps({
  block: { type: Object, required: true },
})

const emit = defineEmits(['save', 'delete-file', 'upload-file'])

function addLink() {
  props.block.content.links.push({
    id: `link-${Date.now()}`,
    title: '',
    url: '',
  })
  emit('save')
}

function removeLink(index) {
  props.block.content.links.splice(index, 1)
  emit('save')
}

function addGalleryItem() {
  props.block.content.items.push({ url: '', caption: '' })
  emit('save')
}

function removeGalleryItem(index) {
  props.block.content.items.splice(index, 1)
  emit('save')
}

function onFileSelect(event) {
  const file = event.target.files?.[0]
  if (file) emit('upload-file', file)
  event.target.value = ''
}
</script>

<style scoped>
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}

.concord-files-block__item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 12px;
}

.concord-files-block__name {
  font-size: var(--concord-text-body);
  font-weight: var(--concord-weight-medium);
}
</style>
