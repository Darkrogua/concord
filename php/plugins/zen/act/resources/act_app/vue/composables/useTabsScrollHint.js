import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

export function useTabsScrollHint() {
  const tabsRef = ref(null)
  const showTabsOverflow = ref(false)
  let tabsResizeObserver = null

  function updateTabsOverflow() {
    const el = tabsRef.value
    if (!el) {
      showTabsOverflow.value = false
      return
    }
    const hasOverflow = el.scrollWidth > el.clientWidth + 1
    const canScrollRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 1
    showTabsOverflow.value = hasOverflow && canScrollRight
  }

  function scrollTabsRight() {
    const el = tabsRef.value
    if (!el) {
      return
    }
    el.scrollBy({
      left: Math.max(140, el.clientWidth * 0.65),
      behavior: 'smooth',
    })
    window.setTimeout(updateTabsOverflow, 320)
  }

  onMounted(() => {
    nextTick(() => {
      updateTabsOverflow()
      if (typeof ResizeObserver !== 'undefined' && tabsRef.value) {
        tabsResizeObserver = new ResizeObserver(updateTabsOverflow)
        tabsResizeObserver.observe(tabsRef.value)
      }
    })
    window.addEventListener('resize', updateTabsOverflow)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('resize', updateTabsOverflow)
    tabsResizeObserver?.disconnect()
  })

  return {
    tabsRef,
    showTabsOverflow,
    updateTabsOverflow,
    scrollTabsRight,
  }
}
