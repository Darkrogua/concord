<template>
  <EditFieldModal
    :open="open"
    title="Фильтры и сортировка"
    title-id="acts-filter-hub-title"
    @close="$emit('close')"
  >
    <ul class="tg-filter-hub">
      <li v-for="item in items" :key="item.type" class="tg-filter-hub__row">
        <label class="tg-filter-hub__switch">
          <input
            type="checkbox"
            :checked="item.enabled"
            @change="$emit('toggle', item.type, $event.target.checked)"
          >
          <span class="tg-filter-hub__switch-ui" aria-hidden="true" />
        </label>
        <button
          type="button"
          class="tg-filter-hub__body"
          @click="$emit('configure', item.type)"
        >
          <span class="tg-filter-hub__label">{{ item.label }}</span>
          <span class="tg-filter-hub__summary">{{ item.summary }}</span>
        </button>
        <button
          type="button"
          class="tg-filter-hub__chevron"
          :aria-label="`Настроить: ${item.label}`"
          @click="$emit('configure', item.type)"
        >
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </button>
      </li>
    </ul>
    <button
      type="button"
      class="tg-btn tg-btn--ghost tg-btn--block tg-filter-hub__save"
      @click="$emit('save-preset')"
    >
      Сохранить фильтры
    </button>
    <button
      v-if="activeCount > 0"
      type="button"
      class="tg-btn tg-btn--ghost tg-btn--block tg-filter-hub__reset"
      @click="$emit('reset-all')"
    >
      Сбросить все
    </button>
  </EditFieldModal>
</template>

<script>
import EditFieldModal from './EditFieldModal.vue'

export default {
  name: 'ActsFilterHubModal',
  components: { EditFieldModal },
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    items: {
      type: Array,
      default: () => [],
    },
    activeCount: {
      type: Number,
      default: 0,
    },
  },
  emits: ['close', 'toggle', 'configure', 'reset-all', 'save-preset'],
}
</script>
