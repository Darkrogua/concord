<template>
  <Transition name="concord-splash">
    <div
      v-if="visible"
      class="concord-splash"
      :class="{ 'concord-splash--leaving': leaving }"
      role="status"
      aria-live="polite"
      aria-label="Загрузка Concord"
    >
      <div class="concord-splash__stage">
        <div class="concord-splash__mark" aria-hidden="true">
          <!-- Phase 1–2: concentric dotted C (fades out before dashes settle) -->
          <span class="concord-splash__rings">
            <span
              v-for="dot in earlyDots"
              :key="`dot-${dot.key}`"
              class="concord-splash__dot"
              :style="dot.style"
            />
          </span>

          <!-- Phase 3–5: radial dashes C → closed ring -->
          <span class="concord-splash__ticks">
            <span
              v-for="index in tickCount"
              :key="`tick-${index}`"
              class="concord-splash__tick"
              :class="{ 'concord-splash__tick--gap': isGapTick(index - 1) }"
              :style="tickStyle(index - 1)"
            />
          </span>

          <svg class="concord-splash__check" viewBox="0 0 64 64" fill="none">
            <defs>
              <linearGradient id="concord-splash-check-grad" x1="0%" y1="50%" x2="100%" y2="50%">
                <stop offset="0%" stop-color="#2563eb" />
                <stop offset="100%" stop-color="#7c3aed" />
              </linearGradient>
            </defs>
            <path
              class="concord-splash__check-path"
              d="M18 33.5 28.2 43.2 46.5 22"
              stroke="url(#concord-splash-check-grad)"
              stroke-width="5.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </div>

        <div class="concord-splash__brand">
          <p class="concord-splash__name">Concord</p>
          <p class="concord-splash__tagline">streamlined agreement</p>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

const TICK_COUNT = 32
const GAP_START = 28
const GAP_END = 31
/** Full sequence + brief hold on the finished mark. */
const MIN_VISIBLE_MS = 4000
const EXIT_MS = 380

const RING_RADII = [28, 38, 48]
const RING_COUNTS = [10, 14, 18]

function gradientColor(angleDeg) {
  const t = (Math.sin((angleDeg * Math.PI) / 180) + 1) / 2
  const r = Math.round(37 + (124 - 37) * t)
  const g = Math.round(99 + (58 - 99) * t)
  const b = Math.round(235 + (237 - 235) * t)
  return `rgb(${r}, ${g}, ${b})`
}

function buildEarlyDots() {
  const dots = []
  RING_RADII.forEach((radius, ringIndex) => {
    const count = RING_COUNTS[ringIndex]
    for (let i = 0; i < count; i += 1) {
      const span = 290
      const start = 125
      const angle = start + (span / Math.max(count - 1, 1)) * i
      dots.push({
        key: `${ringIndex}-${i}`,
        style: {
          '--dot-angle': `${angle}deg`,
          '--dot-radius': `${radius}px`,
          '--dot-color': gradientColor(angle),
          '--dot-size': `${5 + ringIndex}px`,
        },
      })
    }
  })
  return dots
}

const EARLY_DOTS = buildEarlyDots()

export default {
  name: 'ConcordSplashScreen',
  props: {
    ready: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['done'],
  setup(props, { emit }) {
    const visible = ref(true)
    const leaving = ref(false)
    const tickCount = TICK_COUNT
    const earlyDots = EARLY_DOTS
    let minTimer = null
    let exitTimer = null
    let minElapsed = false

    function isGapTick(index) {
      return index >= GAP_START && index <= GAP_END
    }

    function tickStyle(index) {
      const angle = (360 / TICK_COUNT) * index
      return {
        '--tick-angle': `${angle}deg`,
        '--tick-color': gradientColor(angle),
      }
    }

    function tryFinish() {
      if (!minElapsed || !props.ready || leaving.value) {
        return
      }
      leaving.value = true
      exitTimer = window.setTimeout(() => {
        visible.value = false
        emit('done')
      }, EXIT_MS)
    }

    watch(
      () => props.ready,
      () => {
        tryFinish()
      }
    )

    onMounted(() => {
      minTimer = window.setTimeout(() => {
        minElapsed = true
        tryFinish()
      }, MIN_VISIBLE_MS)
    })

    onBeforeUnmount(() => {
      window.clearTimeout(minTimer)
      window.clearTimeout(exitTimer)
    })

    return {
      visible,
      leaving,
      tickCount,
      earlyDots,
      isGapTick,
      tickStyle,
    }
  },
}
</script>
