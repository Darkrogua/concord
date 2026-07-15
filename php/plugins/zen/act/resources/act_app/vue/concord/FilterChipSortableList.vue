<template>
  <div class="concord-settings__chips-wrap">
    <p v-if="canReorder" class="concord-settings__drag-hint concord-settings__drag-hint--chips">
      <span class="concord-settings__drag-hint-touch">Удерживайте чип и перетащите</span>
      <span class="concord-settings__drag-hint-mouse">Потяните чип за ⋮⋮</span>
    </p>

    <div
      ref="listRef"
      class="concord-settings__chips"
      :class="{ 'concord-settings__chips--sortable': canReorder }"
    >
      <div
        v-for="filter in filters"
        :key="filter.id"
        class="concord-settings__chip-item"
        :data-filter-id="filter.id"
      >
        <button
          v-if="canReorder"
          type="button"
          class="concord-settings__chip-drag"
          aria-label="Перетащить фильтр"
          tabindex="-1"
        >
          <svg viewBox="0 0 8 14" width="8" height="14" fill="currentColor" aria-hidden="true">
            <circle cx="2" cy="2" r="1.2" />
            <circle cx="6" cy="2" r="1.2" />
            <circle cx="2" cy="7" r="1.2" />
            <circle cx="6" cy="7" r="1.2" />
            <circle cx="2" cy="12" r="1.2" />
            <circle cx="6" cy="12" r="1.2" />
          </svg>
        </button>
        <FilterChip :filter="filter" @remove="$emit('remove', filter.id)" />
      </div>
    </div>
  </div>
</template>

<script>
import { computed, nextTick, ref, toRef, watch } from 'vue'
import FilterChip from './FilterChip.vue'
import { useFilterChipSortable } from './useFilterChipSortable.js'

export default {
  name: 'FilterChipSortableList',
  components: { FilterChip },
  props: {
    filters: {
      type: Array,
      required: true,
    },
  },
  emits: ['remove', 'reorder'],
  setup(props, { emit }) {
    const listRef = ref(null)
    const filtersRef = toRef(props, 'filters')

    const canReorder = computed(() => props.filters.length >= 2)

    function handleReorder(filterIds) {
      emit('reorder', filterIds)
    }

    const { initSortable } = useFilterChipSortable(listRef, {
      canReorder,
      onReorder: handleReorder,
    })

    watch(filtersRef, () => {
      nextTick(() => initSortable())
    }, { flush: 'post' })

    return {
      listRef,
      canReorder,
    }
  },
}
</script>
