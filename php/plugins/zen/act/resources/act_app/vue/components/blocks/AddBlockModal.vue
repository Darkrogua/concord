<template>
  <EditFieldModal
    :open="open"
    title="Добавить блок"
    title-id="add-block-modal-title"
    @close="$emit('close')"
  >
    <ul class="tg-block-type-list">
      <li v-for="item in blockTypes" :key="item.type">
        <button
          type="button"
          class="tg-block-type-list__item"
          :disabled="creating"
          @click="$emit('select', item.type)"
        >
          <span class="tg-block-type-list__icon" aria-hidden="true">
            <svg v-if="item.type === 'text'" viewBox="0 0 24 24" fill="none">
              <path d="M4 6h16M4 12h10M4 18h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            <svg v-else-if="item.type === 'markdown'" viewBox="0 0 24 24" fill="none">
              <path d="M4 7h4l2 3 2-3h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M4 12h16M4 17h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            <svg v-else-if="item.type === 'checklist'" viewBox="0 0 24 24" fill="none">
              <path d="M9 7.5l2 2L19 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M5 12h14M5 17h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            <svg v-else-if="item.type === 'gallery'" viewBox="0 0 24 24" fill="none">
              <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/>
              <circle cx="8.5" cy="10.5" r="1.8" stroke="currentColor" stroke-width="1.4"/>
              <path d="M3 16l4.5-4.5 3 3L14 11l7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="none">
              <rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/>
              <path d="M3 9h18" stroke="currentColor" stroke-width="1.8"/>
              <path d="M8 4v16" stroke="currentColor" stroke-width="1.8"/>
            </svg>
          </span>
          <span class="tg-block-type-list__body">
            <span class="tg-block-type-list__title">{{ item.title }}</span>
            <span class="tg-block-type-list__desc">{{ item.description }}</span>
          </span>
        </button>
      </li>
    </ul>
  </EditFieldModal>
</template>

<script>
import EditFieldModal from '../EditFieldModal.vue'
import { BLOCK_TYPES } from './block-types'

export default {
  name: 'AddBlockModal',
  components: { EditFieldModal },
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    creating: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['close', 'select'],
  setup() {
    return {
      blockTypes: BLOCK_TYPES,
    }
  },
}
</script>
