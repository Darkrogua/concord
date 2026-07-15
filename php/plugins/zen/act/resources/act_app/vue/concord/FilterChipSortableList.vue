<template>
  <div class="concord-settings__chips-wrap">
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
