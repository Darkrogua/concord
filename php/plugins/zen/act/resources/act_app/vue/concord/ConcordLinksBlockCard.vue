<template>
  <article class="concord-block concord-links-block">
    <div class="concord-block__plate">Ссылки</div>
    <header class="concord-block__header">
      <input
        v-model="block.title"
        type="text"
        class="concord-block__title-input"
        placeholder="Название блока"
        aria-label="Название блока"
      >
    </header>

    <div v-if="(block.links || []).length" class="concord-links-block__list">
      <div
        v-for="link in block.links"
        :key="link.id"
        class="concord-links-block__item"
      >
        <input
          v-model="link.title"
          type="text"
          class="concord-links-block__input"
          placeholder="Название ссылки"
          aria-label="Название ссылки"
        >
        <input
          v-model="link.url"
          type="url"
          class="concord-links-block__input"
          placeholder="https://..."
          aria-label="Адрес ссылки"
        >
        <input
          v-model="link.description"
          type="text"
          class="concord-links-block__input"
          placeholder="Описание (необязательно)"
          aria-label="Описание ссылки"
        >
        <button
          type="button"
          class="concord-links-block__remove"
          aria-label="Удалить ссылку"
          @click="removeLink(link.id)"
        >
          <ConcordGroupDeleteIcon />
        </button>
      </div>
    </div>

    <p v-else class="concord-links-block__empty">
      Ссылки пока не добавлены
    </p>

    <button type="button" class="concord-block__add-btn" @click="addLink">
      Добавить ссылку
    </button>
  </article>
</template>

<script>
import ConcordGroupDeleteIcon from './ConcordGroupDeleteIcon.vue'
import { normalizeLinksBlock } from './mock-agreements.js'

export default {
  name: 'ConcordLinksBlockCard',
  components: { ConcordGroupDeleteIcon },
  props: {
    block: {
      type: Object,
      required: true,
    },
  },
  setup(props) {
    normalizeLinksBlock(props.block)

    function addLink() {
      if (!Array.isArray(props.block.links)) {
        props.block.links = []
      }
      props.block.links.push({
        id: `link-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
        title: '',
        url: '',
        description: '',
      })
    }

    function removeLink(linkId) {
      props.block.links = props.block.links.filter((link) => link.id !== linkId)
    }

    return { addLink, removeLink }
  },
}
</script>
