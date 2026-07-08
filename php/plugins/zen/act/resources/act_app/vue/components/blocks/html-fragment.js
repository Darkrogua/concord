/**
 * Патчит отложенную инициализацию: load/DOMContentLoaded уже могли произойти.
 */
export function patchDeferredInitScripts(html) {
  let content = String(html || '')

  content = content.replace(
    /window\.addEventListener\(\s*(['"])load\1\s*,\s*([A-Za-z_$][\w$]*)\s*\)/g,
    '(document.readyState === "complete" ? $2() : window.addEventListener($1load$1, $2))'
  )

  content = content.replace(
    /document\.addEventListener\(\s*(['"])DOMContentLoaded\1\s*,\s*([A-Za-z_$][\w$]*)\s*\)/g,
    '(document.readyState === "loading" ? document.addEventListener($1DOMContentLoaded$1, $2) : $2())'
  )

  content = content.replace(
    /window\.onload\s*=\s*([A-Za-z_$][\w$]*)\s*;/g,
    '(document.readyState === "complete" ? $1() : (window.onload = $1));'
  )

  return content
}

/**
 * Нормализует HTML-фрагмент: убирает doctype/html/body,
 * при необходимости подтягивает style/script/link из head.
 */
export function normalizeHtmlFragment(html) {
  let content = String(html || '').trim()
  if (!content) {
    return ''
  }

  content = content.replace(/<!doctype[^>]*>/gi, '')

  let headContent = ''
  const headMatch = content.match(/<head[^>]*>([\s\S]*?)<\/head>/i)
  if (headMatch) {
    headContent = headMatch[1]
  }

  content = content.replace(/<head[^>]*>[\s\S]*?<\/head>/gi, '')

  const bodyMatch = content.match(/<body[^>]*>([\s\S]*?)<\/body>/i)
  if (bodyMatch) {
    content = bodyMatch[1]
  } else {
    content = content.replace(/<\/?html[^>]*>/gi, '')
  }

  return patchDeferredInitScripts(`${headContent}${content}`.trim())
}

/**
 * Вставляет HTML-фрагмент в контейнер и перезапускает script-теги.
 */
function reviveScript(oldScript) {
  const newScript = document.createElement('script')
  Array.from(oldScript.attributes).forEach((attr) => {
    newScript.setAttribute(attr.name, attr.value)
  })

  const inline = oldScript.textContent || ''
  const isModule = (oldScript.getAttribute('type') || '').toLowerCase() === 'module'
  const hasSrc = oldScript.hasAttribute('src')

  // Большие inline module-бандлы (Vite и т.п.) ломаются при textContent + replaceWith.
  if (isModule && inline && !hasSrc) {
    const blob = new Blob([inline], { type: 'text/javascript' })
    const url = URL.createObjectURL(blob)
    newScript.removeAttribute('crossorigin')
    newScript.src = url
    newScript.dataset.blobUrl = url
    return newScript
  }

  newScript.textContent = inline
  return newScript
}

/**
 * Освобождает blob:-URL, созданные при монтировании module-скриптов.
 */
export function unmountHtmlFragment(container) {
  if (!container) {
    return
  }

  container.querySelectorAll('script[data-blob-url]').forEach((script) => {
    URL.revokeObjectURL(script.dataset.blobUrl)
  })
  container.innerHTML = ''
}

/**
 * Вставляет HTML-фрагмент в контейнер и перезапускает script-теги.
 */
export function mountHtmlFragment(container, html) {
  if (!container) {
    return
  }

  unmountHtmlFragment(container)

  const fragment = normalizeHtmlFragment(html)
  container.innerHTML = fragment

  container.querySelectorAll('script').forEach((oldScript) => {
    oldScript.replaceWith(reviveScript(oldScript))
  })
}
