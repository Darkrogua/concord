<template>
  <article class="concord-block concord-checkbox-block">
    <div class="concord-block__plate">Чеклист</div>
    <header class="concord-block__header">
      <input
        v-model="block.title"
        type="text"
        class="concord-block__title-input"
        placeholder="Название блока"
        aria-label="Название блока"
      >
    </header>

    <textarea
      v-model="block.prompt"
      class="concord-checkbox-block__prompt"
      rows="2"
      placeholder="Вопрос или пояснение для согласующих"
    />

    <ul v-if="(block.items || []).length" class="concord-checkbox-block__list">
      <li
        v-for="item in block.items"
        :key="item.id"
        class="concord-checkbox-block__item"
      >
        <button
          type="button"
          class="concord-checkbox-block__box"
          :class="{ 'concord-checkbox-block__box--checked': item.checked }"
          :aria-pressed="item.checked"
          @click="item.checked = !item.checked"
        />
        <input
          v-model="item.label"
          type="text"
          class="concord-checkbox-block__label-input"
          placeholder="Пункт списка"
        >
        <button
          type="button"
          class="concord-checkbox-block__remove"
          aria-label="Удалить пункт"
          @click="removeItem(item.id)"
        >
          ×
        </button>
      </li>
    </ul>

    <button type="button" class="concord-block__add-btn" @click="addItem">
      Добавить
    </button>
  </article>
</template>

<script>
import { normalizeCheckboxBlock } from './mock-agreements.js'

export default {
  name: 'ConcordCheckboxBlockCard',
  props: {
    block: {
      type: Object,
      required: true,
    },
  },
  setup(props) {
    normalizeCheckboxBlock(props.block)

    function addItem() {
      props.block.items.push({
        id: `item-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
        label: '',
        checked: false,
      })
    }

    function removeItem(itemId) {
      props.block.items = props.block.items.filter((item) => item.id !== itemId)
    }

    return { addItem, removeItem }
  },
}
</script>
