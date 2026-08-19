import { getCachedAgreementFileContent } from './agreement-file-content.js'

/**
 * @param {{ id?: string, name?: string, mime?: string, downloadUrl?: string, previewUrl?: string | null, textContent?: string }} file
 * @returns {string}
 */
export function getAgreementFileSource(file) {
  const cached = getCachedAgreementFileContent(file?.id)
  const downloadUrl = String(cached?.downloadUrl || file?.downloadUrl || '').trim()
  if (downloadUrl) {
    return downloadUrl
  }

  const previewUrl = String(cached?.previewUrl || file?.previewUrl || '').trim()
  if (previewUrl) {
    return previewUrl
  }

  const textContent = cached?.textContent ?? file?.textContent
  if (textContent != null && textContent !== '') {
    const mime = cached?.mime || file?.mime || 'text/plain;charset=utf-8'
    return `data:${mime},${encodeURIComponent(String(textContent))}`
  }

  return ''
}

/**
 * @param {{ id?: string, name?: string, mime?: string, downloadUrl?: string, previewUrl?: string | null, textContent?: string }} file
 * @returns {Blob | null}
 */
export function resolveAgreementFileBlob(file) {
  const cached = getCachedAgreementFileContent(file?.id)
  const textContent = cached?.textContent ?? file?.textContent
  if (textContent != null && textContent !== '') {
    const mime = cached?.mime || file?.mime || 'text/plain;charset=utf-8'
    return new Blob([String(textContent)], { type: mime.split(';')[0] || 'text/plain' })
  }

  const source = getAgreementFileSource(file)
  if (!source.startsWith('data:')) {
    return null
  }

  const parsed = parseDataUrl(source)
  if (!parsed) {
    return null
  }

  return new Blob([parsed.bytes], { type: parsed.mime.split(';')[0] || 'application/octet-stream' })
}

/**
 * @param {{ id?: string, name?: string, mime?: string, downloadUrl?: string, previewUrl?: string | null, textContent?: string }} file
 * @returns {boolean}
 */
export function downloadAgreementFile(file) {
  const blob = resolveAgreementFileBlob(file)
  if (!blob) {
    const source = getAgreementFileSource(file)
    if (!source) {
      return false
    }
    return triggerDownload(source, file?.name || 'file', false)
  }

  const blobUrl = URL.createObjectURL(blob)
  const ok = triggerDownload(blobUrl, file?.name || 'file', true)
  if (!ok) {
    URL.revokeObjectURL(blobUrl)
  }
  return ok
}

/**
 * @param {{ id?: string, name?: string, mime?: string, downloadUrl?: string, previewUrl?: string | null, textContent?: string }} file
 * @returns {boolean}
 */
export function openAgreementFile(file) {
  const blob = resolveAgreementFileBlob(file)
  if (!blob) {
    return false
  }

  const blobUrl = URL.createObjectURL(blob)
  const opened = window.open(blobUrl, '_blank', 'noopener,noreferrer')
  window.setTimeout(() => URL.revokeObjectURL(blobUrl), 60_000)
  return Boolean(opened)
}

function parseDataUrl(url) {
  const commaIndex = url.indexOf(',')
  if (commaIndex === -1) {
    return null
  }

  const header = url.slice(0, commaIndex)
  const payload = url.slice(commaIndex + 1)
  const mime = header.slice(5).split(';')[0] || 'application/octet-stream'
  const isBase64 = /;base64/i.test(header)
  const bytes = isBase64 ? decodeBase64(payload) : decodeURIComponentPayload(payload)
  return { mime, bytes }
}

function decodeBase64(payload) {
  const binary = atob(payload)
  const bytes = new Uint8Array(binary.length)
  for (let i = 0; i < binary.length; i += 1) {
    bytes[i] = binary.charCodeAt(i)
  }
  return bytes
}

function decodeURIComponentPayload(payload) {
  return new TextEncoder().encode(decodeURIComponent(payload))
}

function triggerDownload(url, fileName, revokeAfter) {
  const link = document.createElement('a')
  link.href = url
  link.download = fileName
  link.rel = 'noopener'
  link.style.display = 'none'
  document.body.appendChild(link)
  link.click()

  window.setTimeout(() => {
    document.body.removeChild(link)
    if (revokeAfter) {
      URL.revokeObjectURL(url)
    }
  }, 60_000)

  return true
}

/**
 * @param {File} file
 * @returns {boolean}
 */
export function isTextLikeAgreementFile(file) {
  const mime = String(file.type || '').toLowerCase()
  const name = String(file.name || '').toLowerCase()
  return mime.startsWith('text/')
    || mime === 'application/json'
    || mime === 'application/xml'
    || name.endsWith('.md')
    || name.endsWith('.txt')
    || name.endsWith('.csv')
    || name.endsWith('.json')
    || name.endsWith('.xml')
}

/**
 * @param {{ downloadUrl?: string, textContent?: string, mime?: string }} file
 */
export function hydrateAgreementFileTextContent(file) {
  if (!file || (file.textContent != null && file.textContent !== '')) {
    return
  }

  const source = String(file.downloadUrl || '').trim()
  if (!source.startsWith('data:')) {
    return
  }

  try {
    const parsed = parseDataUrl(source)
    if (!parsed) {
      return
    }
    const mime = parsed.mime.toLowerCase()
    if (mime.startsWith('text/') || mime.includes('json') || mime.includes('xml')) {
      file.textContent = new TextDecoder().decode(parsed.bytes)
    }
  } catch {
    // ignore malformed data urls
  }
}
