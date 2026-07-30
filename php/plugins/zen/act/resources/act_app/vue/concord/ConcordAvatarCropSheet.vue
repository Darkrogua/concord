<template>
  <div v-if="open" class="concord-avatar-crop" role="dialog" aria-label="Обрезка фото">
    <header class="concord-avatar-crop__header">
      <button type="button" class="concord-avatar-crop__action" @click="$emit('close')">
        Отмена
      </button>
      <span class="concord-avatar-crop__title">Фото профиля</span>
      <button type="button" class="concord-avatar-crop__action concord-avatar-crop__action--primary" @click="save">
        Готово
      </button>
    </header>

    <div
      ref="viewportRef"
      class="concord-avatar-crop__viewport"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
      @pointerleave="onPointerUp"
      @wheel.prevent="onWheel"
    >
      <img
        v-if="imageSrc"
        ref="imageRef"
        :src="imageSrc"
        alt=""
        draggable="false"
        class="concord-avatar-crop__image"
        :style="imageStyle"
        @load="onImageLoad"
      >
      <div class="concord-avatar-crop__mask" :style="cropOverlayStyle" aria-hidden="true">
        <span class="concord-avatar-crop__ring" />
      </div>
    </div>

    <div class="concord-avatar-crop__zoom">
      <input
        v-model.number="userScale"
        class="concord-avatar-crop__slider"
        type="range"
        :min="minScale"
        :max="maxScale"
        step="0.01"
        aria-label="Масштаб"
        @input="onZoomInput"
      >
    </div>
  </div>
</template>

<script>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { clampAvatarOffset, cropAvatarToDataUrl, getAvatarCropMetrics } from './avatar-crop.js'

export default {
  name: 'ConcordAvatarCropSheet',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    imageSrc: {
      type: String,
      default: '',
    },
  },
  emits: ['close', 'save'],
  setup(props, { emit }) {
    const viewportRef = ref(null)
    const imageRef = ref(null)
    const viewportWidth = ref(320)
    const viewportHeight = ref(320)
    const userScale = ref(1)
    const minScale = ref(1)
    const maxScale = ref(3)
    const offsetX = ref(0)
    const offsetY = ref(0)
    const dragStart = ref(null)
    const imageReady = ref(0)
    let resizeObserver = null

    function currentMetrics() {
      const image = imageRef.value
      if (!image?.naturalWidth) {
        return null
      }
      return getAvatarCropMetrics(
        image,
        viewportWidth.value,
        viewportHeight.value,
        userScale.value,
        offsetX.value,
        offsetY.value
      )
    }

    const imageStyle = computed(() => {
      imageReady.value
      const metrics = currentMetrics()
      if (!metrics) {
        return {}
      }
      return {
        width: `${metrics.displayWidth}px`,
        height: `${metrics.displayHeight}px`,
        left: `${metrics.imageLeft}px`,
        top: `${metrics.imageTop}px`,
      }
    })

    const cropOverlayStyle = computed(() => {
      imageReady.value
      const metrics = currentMetrics()
      if (!metrics) {
        return {}
      }
      return {
        '--crop-size': `${metrics.cropSize}px`,
        '--crop-x': `${metrics.centerX}px`,
        '--crop-y': `${metrics.centerY}px`,
      }
    })

    function readViewportSize() {
      const rect = viewportRef.value?.getBoundingClientRect()
      viewportWidth.value = rect?.width || 320
      viewportHeight.value = rect?.height || 320
      return rect
    }

    function clampOffset() {
      const image = imageRef.value
      if (!image?.naturalWidth) {
        return
      }
      const next = clampAvatarOffset(
        image,
        viewportWidth.value,
        viewportHeight.value,
        userScale.value,
        offsetX.value,
        offsetY.value
      )
      offsetX.value = next.offsetX
      offsetY.value = next.offsetY
    }

    function resetTransform() {
      userScale.value = 1
      offsetX.value = 0
      offsetY.value = 0
      clampOffset()
    }

    function onImageLoad() {
      readViewportSize()
      resetTransform()
      imageReady.value += 1
    }

    function onZoomInput() {
      clampOffset()
    }

    function onWheel(event) {
      const delta = event.deltaY > 0 ? -0.08 : 0.08
      userScale.value = Math.min(maxScale.value, Math.max(minScale.value, userScale.value + delta))
      clampOffset()
    }

    function onPointerDown(event) {
      if (!imageRef.value) {
        return
      }
      dragStart.value = {
        pointerId: event.pointerId,
        x: event.clientX,
        y: event.clientY,
        offsetX: offsetX.value,
        offsetY: offsetY.value,
      }
      viewportRef.value?.setPointerCapture(event.pointerId)
    }

    function onPointerMove(event) {
      if (!dragStart.value || dragStart.value.pointerId !== event.pointerId) {
        return
      }
      offsetX.value = dragStart.value.offsetX + (event.clientX - dragStart.value.x)
      offsetY.value = dragStart.value.offsetY + (event.clientY - dragStart.value.y)
      clampOffset()
    }

    function onPointerUp(event) {
      if (!dragStart.value || dragStart.value.pointerId !== event.pointerId) {
        return
      }
      dragStart.value = null
      viewportRef.value?.releasePointerCapture(event.pointerId)
      clampOffset()
    }

    function save() {
      const image = imageRef.value
      if (!image?.naturalWidth) {
        return
      }
      const dataUrl = cropAvatarToDataUrl(
        image,
        viewportWidth.value,
        viewportHeight.value,
        userScale.value,
        offsetX.value,
        offsetY.value
      )
      if (dataUrl) {
        emit('save', dataUrl)
      }
    }

    function attachResizeObserver() {
      detachResizeObserver()
      if (!viewportRef.value || typeof ResizeObserver === 'undefined') {
        return
      }
      resizeObserver = new ResizeObserver(() => {
        readViewportSize()
        clampOffset()
        imageReady.value += 1
      })
      resizeObserver.observe(viewportRef.value)
    }

    function detachResizeObserver() {
      resizeObserver?.disconnect()
      resizeObserver = null
    }

    watch(
      () => props.open,
      (isOpen) => {
        if (isOpen) {
          requestAnimationFrame(() => {
            readViewportSize()
            attachResizeObserver()
            if (imageRef.value?.complete) {
              resetTransform()
            }
          })
          return
        }
        detachResizeObserver()
      }
    )

    watch(
      () => props.imageSrc,
      () => {
        resetTransform()
      }
    )

    onBeforeUnmount(() => {
      detachResizeObserver()
    })

    return {
      viewportRef,
      imageRef,
      userScale,
      minScale,
      maxScale,
      imageStyle,
      cropOverlayStyle,
      onImageLoad,
      onZoomInput,
      onWheel,
      onPointerDown,
      onPointerMove,
      onPointerUp,
      save,
    }
  },
}
</script>
