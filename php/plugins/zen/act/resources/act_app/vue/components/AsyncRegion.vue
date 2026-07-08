<template>
  <div
    ref="rootRef"
    class="tg-async"
    :class="[
      `tg-async--${variant}`,
      `tg-async--loader-${loader}`,
      {
        'tg-async--loading': loading,
        'tg-async--revealed': revealed,
        'tg-async--site': site,
        'tg-async--shell-delayed': shellDelayed,
        'tg-async--shell-ready': shellReady,
      },
    ]"
    :style="regionStyle"
  >
    <div
      v-if="showShell"
      class="tg-async__shell"
      aria-hidden="true"
    >
      <slot name="shell">
        <AsyncRegionSpinner v-if="loader === 'spinner'" />
        <AsyncRegionSkeleton v-else :pattern="pattern" />
      </slot>
    </div>
    <div
      v-if="contentMounted"
      ref="contentRef"
      class="tg-async__content"
      :class="{
        'tg-async__content--measure': measuring,
        'tg-async__content--visible': revealed,
      }"
    >
      <slot />
    </div>
  </div>
</template>

<script>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import AsyncRegionSkeleton from './AsyncRegionSkeleton.vue'
import AsyncRegionSpinner from './AsyncRegionSpinner.vue'

const HEIGHT_MS = 360
const REVEAL_DELAY_MS = 100
const DEFAULT_SHELL_DELAY_MS = 0
const DEFAULT_SHELL_FADE_MS = 400

const SHELL_MIN = {
  page: () => Math.round(window.innerHeight * 0.4),
  section: () => 120,
  modal: () => 180,
  inline: () => 48,
  none: () => 0,
}

export default {
  name: 'AsyncRegion',
  components: { AsyncRegionSkeleton, AsyncRegionSpinner },
  props: {
    loading: {
      type: Boolean,
      default: false,
    },
    loader: {
      type: String,
      default: 'skeleton',
    },
    variant: {
      type: String,
      default: 'section',
    },
    pattern: {
      type: String,
      default: 'default',
    },
    site: {
      type: Boolean,
      default: false,
    },
    shellDelayMs: {
      type: Number,
      default: DEFAULT_SHELL_DELAY_MS,
    },
    shellFadeMs: {
      type: Number,
      default: DEFAULT_SHELL_FADE_MS,
    },
  },
  setup(props) {
    const rootRef = ref(null)
    const contentRef = ref(null)
    const revealed = ref(false)
    const measuring = ref(false)
    const contentMounted = ref(false)
    const lockedHeight = ref(null)
    const shellReady = ref(false)
    let revealTimer = null
    let unlockTimer = null
    let shellDelayTimer = null

    const shellDelayed = computed(() => props.loader === 'spinner' && props.shellDelayMs > 0)

    const shellMin = () => {
      const fn = SHELL_MIN[props.variant] || SHELL_MIN.section
      return fn()
    }

    const clearTimers = () => {
      if (revealTimer) {
        window.clearTimeout(revealTimer)
        revealTimer = null
      }
      if (unlockTimer) {
        window.clearTimeout(unlockTimer)
        unlockTimer = null
      }
    }

    const clearShellDelayTimer = () => {
      if (shellDelayTimer) {
        window.clearTimeout(shellDelayTimer)
        shellDelayTimer = null
      }
    }

    const scheduleShellReveal = () => {
      clearShellDelayTimer()
      shellReady.value = !shellDelayed.value

      if (!shellDelayed.value || !props.loading) {
        return
      }

      shellDelayTimer = window.setTimeout(() => {
        shellDelayTimer = null
        if (props.loading) {
          shellReady.value = true
        }
      }, props.shellDelayMs)
    }

    const beginReveal = async () => {
      contentMounted.value = true
      await nextTick()
      measuring.value = true
      await nextTick()

      window.requestAnimationFrame(() => {
        const measured = contentRef.value?.offsetHeight ?? 0
        const startHeight = Math.max(shellMin(), measuring.value ? shellMin() : 0)
        lockedHeight.value = Math.max(startHeight, measured || startHeight)

        measuring.value = false

        window.requestAnimationFrame(() => {
          if (measured > 0) {
            lockedHeight.value = measured
          }

          revealTimer = window.setTimeout(() => {
            revealed.value = true
            unlockTimer = window.setTimeout(() => {
              lockedHeight.value = null
            }, HEIGHT_MS + 40)
          }, REVEAL_DELAY_MS)
        })
      })
    }

    const reset = () => {
      clearTimers()
      clearShellDelayTimer()
      revealed.value = false
      measuring.value = false
      contentMounted.value = false
      shellReady.value = false
      lockedHeight.value = props.variant === 'page' ? shellMin() : null
    }

    watch(
      () => props.loading,
      async (isLoading) => {
        if (isLoading) {
          reset()
          if (props.variant === 'page') {
            lockedHeight.value = shellMin()
          }
          scheduleShellReveal()
          return
        }
        clearShellDelayTimer()
        shellReady.value = false
        await beginReveal()
      },
      { immediate: true }
    )

    onBeforeUnmount(() => {
      clearTimers()
      clearShellDelayTimer()
    })

    const showShell = computed(() => props.loading || !revealed.value)

    const regionStyle = computed(() => {
      const style = {}
      if (lockedHeight.value != null) {
        style.minHeight = `${lockedHeight.value}px`
      }
      if (shellDelayed.value) {
        style['--tg-async-shell-fade'] = `${props.shellFadeMs}ms`
      }
      return Object.keys(style).length ? style : null
    })

    return {
      rootRef,
      contentRef,
      revealed,
      measuring,
      contentMounted,
      showShell,
      shellReady,
      shellDelayed,
      regionStyle,
    }
  },
}
</script>
