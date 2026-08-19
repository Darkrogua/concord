/** @type {Map<string, { downloadUrl?: string, textContent?: string, mime?: string, name?: string }>} */
const fileContentCache = new Map()

/**
 * @param {string} fileId
 * @param {{ downloadUrl?: string, textContent?: string, mime?: string, name?: string }} payload
 */
export function cacheAgreementFileContent(fileId, payload) {
  if (!fileId) {
    return
  }
  fileContentCache.set(fileId, { ...payload })
}

/**
 * @param {string | undefined} fileId
 */
export function getCachedAgreementFileContent(fileId) {
  if (!fileId) {
    return null
  }
  return fileContentCache.get(fileId) || null
}
