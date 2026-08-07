<template>
  <span class="concord-logo-mark" :class="sizeClass" aria-hidden="true">
    <span
      v-for="index in tickCount"
      :key="index"
      class="concord-logo-mark__tick"
      :style="tickStyle(index - 1)"
    />
    <svg class="concord-logo-mark__check" viewBox="0 0 64 64" fill="none">
      <defs>
        <linearGradient :id="gradId" x1="0%" y1="50%" x2="100%" y2="50%">
          <stop offset="0%" stop-color="#2563eb" />
          <stop offset="100%" stop-color="#7c3aed" />
        </linearGradient>
      </defs>
      <path
        d="M18 33.5 28.2 43.2 46.5 22"
        :stroke="`url(#${gradId})`"
        stroke-width="5.5"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
    </svg>
  </span>
</template>

<script>
let logoMarkUid = 0

const TICK_COUNT = 32

function gradientColor(angleDeg) {
  const t = (Math.sin((angleDeg * Math.PI) / 180) + 1) / 2
  const r = Math.round(37 + (124 - 37) * t)
  const g = Math.round(99 + (58 - 99) * t)
  const b = Math.round(235 + (237 - 235) * t)
  return `rgb(${r}, ${g}, ${b})`
}

export default {
  name: 'ConcordLogoMark',
  props: {
    size: {
      type: String,
      default: 'md',
      validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
  },
  setup(props) {
    logoMarkUid += 1
    const gradId = `concord-logo-grad-${logoMarkUid}`
    const tickCount = TICK_COUNT
    const sizeClass = `concord-logo-mark--${props.size}`

    function tickStyle(index) {
      const angle = (360 / TICK_COUNT) * index
      return {
        '--tick-angle': `${angle}deg`,
        '--tick-color': gradientColor(angle),
      }
    }

    return {
      gradId,
      tickCount,
      sizeClass,
      tickStyle,
    }
  },
}
</script>
