import { onBeforeUnmount, watch } from 'vue'
import Sortable from 'sortablejs'

function usesTouchReorder() {
  if (typeof window === 'undefined') {
    return true
  }
  return window.matchMedia('(pointer: coarse)').matches
}

/**
 * @param {import('vue').Ref<HTMLElement | null>} listRef
 * @param {{ canReorder: import('vue').Ref<boolean> | import('vue').ComputedRef<boolean>, onReorder: (sectionIds: string[]) => void }} options
 */
export function useFilterSectionSortable(listRef, { canReorder, onReorder }) {
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
    if (!element || !canReorder.value) {
      return
    }

    const touchReorder = usesTouchReorder()

    sortable = Sortable.create(element, {
      animation: 240,
      easing: 'cubic-bezier(0.2, 0, 0, 1)',
      delay: touchReorder ? 300 : 0,
      delayOnTouchOnly: true,
      touchStartThreshold: 6,
      draggable: '.concord-settings__section',
      handle: '.concord-settings__section-head',
      ghostClass: 'concord-filter-section-sortable-ghost',
      chosenClass: 'concord-filter-section-sortable-chosen',
      dragClass: 'concord-filter-section-sortable-drag',
      fallbackTolerance: 4,
      onChoose() {
        if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
          navigator.vibrate(12)
        }
      },
      onEnd() {
        const items = Array.from(element.querySelectorAll('.concord-settings__section'))
        const sectionIds = items
          .map((item) => item.getAttribute('data-section-id'))
          .filter((id) => typeof id === 'string' && id !== '')

        if (sectionIds.length < 2) {
          return
        }

        onReorder(sectionIds)
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
