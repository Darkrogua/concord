export const AVATAR_OUTPUT_SIZE = 320
export const AVATAR_CROP_RATIO = 0.72

export function getAvatarCropMetrics(image, viewportWidth, viewportHeight, userScale, offsetX, offsetY) {
  const cropSize = Math.round(Math.min(viewportWidth, viewportHeight) * AVATAR_CROP_RATIO)
  const centerX = viewportWidth / 2
  const centerY = viewportHeight / 2
  const baseScale = Math.max(cropSize / image.naturalWidth, cropSize / image.naturalHeight)
  const scale = baseScale * userScale
  const displayWidth = image.naturalWidth * scale
  const displayHeight = image.naturalHeight * scale
  const imageLeft = centerX - displayWidth / 2 + offsetX
  const imageTop = centerY - displayHeight / 2 + offsetY
  const cropLeft = centerX - cropSize / 2
  const cropTop = centerY - cropSize / 2

  return {
    cropSize,
    centerX,
    centerY,
    scale,
    displayWidth,
    displayHeight,
    imageLeft,
    imageTop,
    cropLeft,
    cropTop,
  }
}

export function clampAvatarOffset(image, viewportWidth, viewportHeight, userScale, offsetX, offsetY) {
  const metrics = getAvatarCropMetrics(image, viewportWidth, viewportHeight, userScale, offsetX, offsetY)
  const {
    cropSize,
    displayWidth,
    displayHeight,
    imageLeft,
    imageTop,
    cropLeft,
    cropTop,
  } = metrics

  const cropRight = cropLeft + cropSize
  const cropBottom = cropTop + cropSize
  const imageRight = imageLeft + displayWidth
  const imageBottom = imageTop + displayHeight

  let nextOffsetX = offsetX
  let nextOffsetY = offsetY

  if (displayWidth <= cropSize) {
    nextOffsetX = 0
  } else if (imageLeft > cropLeft) {
    nextOffsetX -= imageLeft - cropLeft
  } else if (imageRight < cropRight) {
    nextOffsetX += cropRight - imageRight
  }

  if (displayHeight <= cropSize) {
    nextOffsetY = 0
  } else if (imageTop > cropTop) {
    nextOffsetY -= imageTop - cropTop
  } else if (imageBottom < cropBottom) {
    nextOffsetY += cropBottom - imageBottom
  }

  return {
    offsetX: nextOffsetX,
    offsetY: nextOffsetY,
    metrics: getAvatarCropMetrics(image, viewportWidth, viewportHeight, userScale, nextOffsetX, nextOffsetY),
  }
}

export function cropAvatarToDataUrl(
  image,
  viewportWidth,
  viewportHeight,
  userScale,
  offsetX,
  offsetY,
  outputSize = AVATAR_OUTPUT_SIZE
) {
  const {
    cropSize,
    scale,
    imageLeft,
    imageTop,
    cropLeft,
    cropTop,
  } = getAvatarCropMetrics(image, viewportWidth, viewportHeight, userScale, offsetX, offsetY)

  const canvas = document.createElement('canvas')
  canvas.width = outputSize
  canvas.height = outputSize
  const context = canvas.getContext('2d')
  if (!context) {
    return ''
  }

  const sourceX = (cropLeft - imageLeft) / scale
  const sourceY = (cropTop - imageTop) / scale
  const sourceSize = cropSize / scale

  context.beginPath()
  context.arc(outputSize / 2, outputSize / 2, outputSize / 2, 0, Math.PI * 2)
  context.closePath()
  context.clip()
  context.drawImage(
    image,
    sourceX,
    sourceY,
    sourceSize,
    sourceSize,
    0,
    0,
    outputSize,
    outputSize
  )

  return canvas.toDataURL('image/jpeg', 0.9)
}
