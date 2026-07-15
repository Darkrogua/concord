import { onBeforeUnmount, watch } from 'vue'
import Sortable from 'sortablejs'

/**
 * @param {import('vue').Ref<HTMLElement | null>} listRef
 * @param {{ canReorder: import('vue').Ref<boolean> | import('vue').ComputedRef<boolean>, onReorder: (filterIds: string[]) => void }} options
 */
function usesTouchReorder() {
  if (typeof window === 'undefined') {
    return true
  }
  return window.matchMedia('(pointer: coarse)').matches
}

export function useFilterChipSortable(listRef, { canReorder, onReorder }) {
  let sortable = null

  function destroySortable() {
    if (sortable) {
      sortable.destroy()
      sortable = null
    }
  }

  function initSortable() {
    destroySortable()

    const element = listRef.value
    if (!element || !canReorder.value || usesTouchReorder()) {
      return
    }
    sortable = Sortable.create(element, {
      animation: 220,
      easing: 'cubic-bezier(0.2, 0, 0, 1)',
      delay: 0,
      delayOnTouchOnly: false,
      touchStartThreshold: 6,
      draggable: '.concord-settings__chip-item',
      filter: '.concord-settings__chip-remove',
      preventOnFilter: true,
      ghostClass: 'concord-filter-chip-sortable-ghost',
      chosenClass: 'concord-filter-chip-sortable-chosen',
      dragClass: 'concord-filter-chip-sortable-drag',
      fallbackTolerance: 4,
      onChoose() {
        if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
          navigator.vibrate(12)
        }
      },
      onEnd() {
        const items = Array.from(element.querySelectorAll('.concord-settings__chip-item'))
        const filterIds = items
          .map((item) => item.getAttribute('data-filter-id'))
          .filter((id) => typeof id === 'string' && id !== '')

        if (filterIds.length < 2) {
          return
        }

        onReorder(filterIds)
      },
    })
  }

  watch([listRef, canReorder], () => {
    initSortable()
  }, { flush: 'post' })

  onBeforeUnmount(() => {
    destroySortable()
  })

  return {
    initSortable,
    destroySortable,
  }
}
