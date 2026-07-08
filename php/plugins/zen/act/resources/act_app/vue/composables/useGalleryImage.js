export function galleryImageUrl(actId, blockId, imageId) {
  if (!actId || !blockId || !imageId) {
    return ''
  }

  return `/act.assets/${encodeURIComponent(actId)}/${encodeURIComponent(blockId)}/${encodeURIComponent(imageId)}`
}

export function formatFileSizeMb(sizeBytes) {
  const bytes = Number(sizeBytes) || 0
  if (bytes < 1024) {
    return `${bytes} B`
  }
  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`
  }

  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`
}

export function formatImageDimensions(width, height) {
  const w = Number(width)
  const h = Number(height)
  if (!w || !h) {
    return '—'
  }

  return `${w} × ${h} px`
}
