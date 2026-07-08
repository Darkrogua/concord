import { onBeforeUnmount, watch } from 'vue'
import Sortable from 'sortablejs'

export function useBlockSortable(listRef, { canReorder, reordering, onReorder }) {
  let sortable = null

  const destroySortable = () => {
    if (sortable) {
      sortable.destroy()
      sortable = null
    }
  }

  const initSortable = () => {
    destroySortable()

    const element = listRef.value
    if (!element || !canReorder.value) {
      return
    }

    sortable = Sortable.create(element, {
      animation: 150,
      handle: '.tg-block-drag-handle',
      draggable: '.tg-block-item',
      delay: 150,
      delayOnTouchOnly: true,
      touchStartThreshold: 5,
      ghostClass: 'tg-block-sortable-ghost',
      chosenClass: 'tg-block-sortable-chosen',
      dragClass: 'tg-block-sortable-drag',
      disabled: reordering.value,
      onEnd(evt) {
        const { oldIndex, newIndex, from, to } = evt
        if (oldIndex == null || newIndex == null || oldIndex === newIndex || from !== to) {
          return
        }

        const items = Array.from(to.querySelectorAll('.tg-block-item'))
        const blockIds = items
          .map((item) => item.getAttribute('data-block-id'))
          .filter((id) => typeof id === 'string' && id !== '')

        if (blockIds.length === 0) {
          return
        }

        onReorder(blockIds, { oldIndex, newIndex })
      },
    })
  }

  const syncDisabled = () => {
    if (sortable) {
      sortable.option('disabled', !canReorder.value || reordering.value)
    }
  }

  watch([listRef, canReorder], () => {
    initSortable()
  }, { flush: 'post' })

  watch(reordering, () => {
    syncDisabled()
  })

  onBeforeUnmount(() => {
    destroySortable()
  })

  return {
    initSortable,
    destroySortable,
  }
}
