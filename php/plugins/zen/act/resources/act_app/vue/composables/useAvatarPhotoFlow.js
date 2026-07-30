import { ref } from 'vue'

export function useAvatarPhotoFlow({ onSave, onRemove }) {
  const photoSheetOpen = ref(false)
  const cropSheetOpen = ref(false)
  const pendingImageSrc = ref('')
  const cameraInputRef = ref(null)
  const galleryInputRef = ref(null)

  function openPhotoSheet() {
    photoSheetOpen.value = true
  }

  function closePhotoSheet() {
    photoSheetOpen.value = false
  }

  function clearPendingImage() {
    if (pendingImageSrc.value.startsWith('blob:')) {
      URL.revokeObjectURL(pendingImageSrc.value)
    }
    pendingImageSrc.value = ''
  }

  function openCropWithFile(file) {
    if (!file || !file.type.startsWith('image/')) {
      return
    }
    clearPendingImage()
    pendingImageSrc.value = URL.createObjectURL(file)
    closePhotoSheet()
    cropSheetOpen.value = true
  }

  function openCamera() {
    cameraInputRef.value?.click()
  }

  function openGallery() {
    galleryInputRef.value?.click()
  }

  function onCameraSelected(event) {
    const file = event.target.files?.[0]
    event.target.value = ''
    openCropWithFile(file)
  }

  function onGallerySelected(event) {
    const file = event.target.files?.[0]
    event.target.value = ''
    openCropWithFile(file)
  }

  function closeCrop() {
    cropSheetOpen.value = false
    clearPendingImage()
  }

  function saveCroppedAvatar(dataUrl) {
    onSave?.(dataUrl)
    cropSheetOpen.value = false
    clearPendingImage()
  }

  function removeAvatar() {
    closePhotoSheet()
    onRemove?.()
  }

  return {
    photoSheetOpen,
    cropSheetOpen,
    pendingImageSrc,
    cameraInputRef,
    galleryInputRef,
    openPhotoSheet,
    closePhotoSheet,
    openCamera,
    openGallery,
    onCameraSelected,
    onGallerySelected,
    closeCrop,
    saveCroppedAvatar,
    removeAvatar,
  }
}
