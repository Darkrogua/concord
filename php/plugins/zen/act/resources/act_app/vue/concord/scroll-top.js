export function resetConcordScrollPosition() {
  if (typeof window === 'undefined') {
    return
  }

  window.scrollTo(0, 0)

  if (document.documentElement) {
    document.documentElement.scrollTop = 0
  }

  if (document.body) {
    document.body.scrollTop = 0
  }
}
