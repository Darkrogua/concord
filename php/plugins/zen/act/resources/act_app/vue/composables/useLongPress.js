import { onBeforeUnmount } from 'vue'

const LONG_PRESS_MS = 550
const MOVE_TOLERANCE_PX = 12

export function useLongPress(onLongPress) {
  let timer = null
  let startX = 0
  let startY = 0
  let suppressedClick = false

  const clearTimer = () => {
    if (timer !== null) {
      clearTimeout(timer)
      timer = null
    }
  }

  const onPointerDown = (event, payload) => {
    if (event.button !== undefined && event.button !== 0) {
      return
    }

    clearTimer()
    startX = event.clientX ?? 0
    startY = event.clientY ?? 0

    timer = setTimeout(() => {
      timer = null
      suppressedClick = true
      onLongPress(payload)
      if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
        navigator.vibrate(10)
      }
    }, LONG_PRESS_MS)
  }

  const onPointerMove = (event) => {
    if (timer === null) {
      return
    }

    const moved = Math.hypot(event.clientX - startX, event.clientY - startY)
    if (moved > MOVE_TOLERANCE_PX) {
      clearTimer()
    }
  }

  const onPointerUp = () => {
    clearTimer()
  }

  const shouldSuppressClick = () => {
    if (!suppressedClick) {
      return false
    }
    suppressedClick = false
    return true
  }

  onBeforeUnmount(clearTimer)

  return {
    onPointerDown,
    onPointerMove,
    onPointerUp,
    shouldSuppressClick,
  }
}
