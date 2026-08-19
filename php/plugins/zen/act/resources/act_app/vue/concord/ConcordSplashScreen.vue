<template>
  <Transition name="concord-splash">
    <div
      v-if="visible"
      class="concord-splash"
      :class="{ 'concord-splash--leaving': leaving }"
      :style="splashVars"
      role="status"
      aria-live="polite"
      aria-label="Загрузка Concord"
    >
      <div class="concord-splash__stage">
        <div class="concord-splash__mark" aria-hidden="true">
          <span
            v-for="index in tickCount"
            :key="index"
            class="concord-splash__tick"
            :style="tickStyle(index - 1)"
          />

          <svg class="concord-splash__check" viewBox="0 0 64 64" fill="none">
            <defs>
              <linearGradient id="concord-splash-check-grad" x1="0%" y1="50%" x2="100%" y2="50%">
                <stop offset="0%" :stop-color="colors.gradientStart" />
                <stop offset="100%" :stop-color="colors.gradientEnd" />
              </linearGradient>
            </defs>
            <path
              class="concord-splash__check-short"
              pathLength="1"
              d="M20 34.5 28.5 42.5"
              stroke="url(#concord-splash-check-grad)"
              stroke-width="5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
            <path
              class="concord-splash__check-long"
              pathLength="1"
              d="M28.5 42.5 46 24.5"
              stroke="url(#concord-splash-check-grad)"
              stroke-width="5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </div>

        <p class="concord-splash__name">Concord</p>
      </div>
    </div>
  </Transition>
</template>

<script>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  SPLASH_COLORS,
  SPLASH_EASING,
  SPLASH_TIMING,
  splashTickAngle,
  splashTickColor,
  splashTickDelay,
} from './concord-splash-timing.js'

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
    const tickCount = SPLASH_TIMING.TICK_COUNT
    const colors = SPLASH_COLORS
    let minTimer = null
    let exitTimer = null
    let minElapsed = false

    const splashVars = computed(() => ({
      '--splash-bg': SPLASH_COLORS.background,
      '--splash-brand': SPLASH_COLORS.brand,
      '--splash-check-delay': `${SPLASH_TIMING.CHECK_START_MS}ms`,
      '--splash-check-draw-ms': `${SPLASH_TIMING.CHECK_DRAW_MS}ms`,
      '--splash-check-short-ms': `${Math.round(SPLASH_TIMING.CHECK_DRAW_MS * 0.42)}ms`,
      '--splash-check-long-ms': `${Math.round(SPLASH_TIMING.CHECK_DRAW_MS * 0.58)}ms`,
      '--splash-check-spring-delay': `${SPLASH_TIMING.CHECK_SPRING_MS}ms`,
      '--splash-check-spring-ms': `${SPLASH_TIMING.CHECK_SPRING_DURATION_MS}ms`,
      '--splash-brand-delay': `${SPLASH_TIMING.BRAND_START_MS}ms`,
      '--splash-brand-duration': `${SPLASH_TIMING.BRAND_DURATION_MS}ms`,
      '--splash-tick-appear-ms': `${SPLASH_TIMING.TICK_APPEAR_MS}ms`,
      '--splash-ease-out-cubic': SPLASH_EASING.outCubic,
      '--splash-ease-out-expo': SPLASH_EASING.outExpo,
      '--splash-ease-spring': SPLASH_EASING.spring,
      '--splash-exit-ms': `${SPLASH_TIMING.EXIT_FADE_MS}ms`,
    }))

    function tickStyle(index) {
      return {
        '--tick-angle': `${splashTickAngle(index)}deg`,
        '--tick-color': splashTickColor(index),
        '--tick-delay': `${splashTickDelay(index)}ms`,
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
      }, SPLASH_TIMING.EXIT_FADE_MS)
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
      }, SPLASH_TIMING.TOTAL_MS)
    })

    onBeforeUnmount(() => {
      window.clearTimeout(minTimer)
      window.clearTimeout(exitTimer)
    })

    return {
      visible,
      leaving,
      tickCount,
      colors,
      splashVars,
      tickStyle,
    }
  },
}
</script>
