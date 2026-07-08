<template>
  <div class="tg-acts-toolbar" role="toolbar" aria-label="Список актов">
    <div class="tg-acts-toolbar__left">
      <button
        type="button"
        class="tg-acts-toolbar__btn"
        :class="{ 'tg-acts-toolbar__btn--active': filterActive }"
        :aria-label="filterAriaLabel"
        title="Фильтры и сортировка"
        @click="$emit('open-filters')"
      >
        <svg class="tg-acts-toolbar__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        <span v-if="filterCount > 0" class="tg-acts-toolbar__badge">{{ filterCount }}</span>
      </button>
      <button
        type="button"
        class="tg-acts-toolbar__btn"
        :aria-label="viewMode === 'tiles' ? 'Показать списком' : 'Показать плиткой'"
        :title="viewMode === 'tiles' ? 'Список' : 'Плитка'"
        @click="$emit('toggle-view')"
      >
        <svg v-if="viewMode === 'tiles'" class="tg-acts-toolbar__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        <svg v-else class="tg-acts-toolbar__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
          <rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
          <rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
          <rect x="14" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
        </svg>
      </button>
    </div>

    <div v-if="presets.length > 0" class="tg-acts-toolbar__presets">
      <button
        v-for="preset in presets"
        :key="preset.id"
        type="button"
        class="tg-filter-preset"
        :class="{ 'tg-filter-preset--active': isPresetActive(preset.id) }"
        :style="{ '--preset-color': presetColorHex(preset.color) }"
        :aria-pressed="isPresetActive(preset.id)"
        :title="`Пресет «${preset.name}». Удерживайте для удаления`"
        @click="onPresetClick(preset)"
        @pointerdown="onPresetPointerDown($event, preset)"
        @pointermove="onPresetPointerMove"
        @pointerup="onPresetPointerUp"
        @pointercancel="onPresetPointerUp"
        @contextmenu.prevent
      >
        <span class="tg-filter-preset__dot" aria-hidden="true" />
        <span class="tg-filter-preset__label">{{ preset.name }}</span>
      </button>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue'
import { presetColorHex } from '../composables/filter-preset-colors.js'
import { useLongPress } from '../composables/useLongPress.js'

export default {
  name: 'ActsListToolbar',
  props: {
    viewMode: {
      type: String,
      required: true,
    },
    filterCount: {
      type: Number,
      default: 0,
    },
    presets: {
      type: Array,
      default: () => [],
    },
    activePresetId: {
      type: String,
      default: '',
    },
    matchingPresetId: {
      type: String,
      default: '',
    },
  },
  emits: ['open-filters', 'toggle-view', 'toggle-preset', 'delete-preset'],
  setup(props, { emit }) {
    const filterActive = computed(() => props.filterCount > 0)
    const filterAriaLabel = computed(() => (
      props.filterCount > 0
        ? `Фильтры и сортировка, активно: ${props.filterCount}`
        : 'Фильтры и сортировка'
    ))

    const isPresetActive = (id) => (
      id === props.activePresetId || id === props.matchingPresetId
    )

    const {
      onPointerDown: onPresetPointerDown,
      onPointerMove: onPresetPointerMove,
      onPointerUp: onPresetPointerUp,
      shouldSuppressClick,
    } = useLongPress((preset) => {
      emit('delete-preset', preset)
    })

    const onPresetClick = (preset) => {
      if (shouldSuppressClick()) {
        return
      }
      emit('toggle-preset', preset.id)
    }

    return {
      filterActive,
      filterAriaLabel,
      isPresetActive,
      presetColorHex,
      onPresetClick,
      onPresetPointerDown,
      onPresetPointerMove,
      onPresetPointerUp,
    }
  },
}
</script>
