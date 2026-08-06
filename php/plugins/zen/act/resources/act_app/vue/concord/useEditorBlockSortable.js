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
 * @param {{
 *   canReorder?: import('vue').Ref<boolean> | import('vue').ComputedRef<boolean>,
 *   onReorder: (blockIds: string[]) => void,
 *   onDragStart?: () => void,
 *   onDragEnd?: () => void,
 * }} options
 */
export function useEditorBlockSortable(listRef, { canReorder, onReorder, onDragStart, onDragEnd }) {
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
    const allowed = canReorder ? canReorder.value : true
    if (!element || !allowed) {
      return
    }

    const touchReorder = usesTouchReorder()

    sortable = Sortable.create(element, {
      animation: 220,
      easing: 'cubic-bezier(0.2, 0, 0, 1)',
      delay: touchReorder ? 120 : 0,
      delayOnTouchOnly: true,
      touchStartThreshold: 4,
      draggable: '.concord-editor-block',
      handle: '.concord-editor-block__handle',
      forceFallback: touchReorder,
      fallbackOnBody: true,
      fallbackTolerance: 6,
      swapThreshold: 0.65,
      ghostClass: 'concord-editor-block--sortable-ghost',
      chosenClass: 'concord-editor-block--sortable-chosen',
      dragClass: 'concord-editor-block--sortable-drag',
      onChoose() {
        onDragStart?.()
        if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
          navigator.vibrate(12)
        }
      },
      onUnchoose() {
        onDragEnd?.()
      },
      onEnd(evt) {
        try {
          if (evt.oldIndex == null || evt.newIndex == null || evt.oldIndex === evt.newIndex) {
            return
          }

          const items = Array.from(element.querySelectorAll('.concord-editor-block'))
          const blockIds = items
            .map((item) => item.getAttribute('data-block-id'))
            .filter((id) => typeof id === 'string' && id !== '')

          if (blockIds.length < 2) {
            return
          }

          onReorder(blockIds)
        } finally {
          onDragEnd?.()
        }
      },
    })
  }

  watch([listRef, ...(canReorder ? [canReorder] : [])], () => {
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
