/** Splash screen timing & theme constants (ms). */
export const SPLASH_TIMING = {
  TOTAL_MS: 2600,
  EXIT_FADE_MS: 260,

  CIRCLE_START_MS: 200,
  CIRCLE_COMPLETE_MS: 1500,

  CHECK_START_MS: 1650,
  CHECK_DRAW_MS: 280,
  CHECK_SPRING_MS: 1950,
  CHECK_SPRING_DURATION_MS: 240,

  BRAND_START_MS: 2100,
  BRAND_DURATION_MS: 450,

  TICK_COUNT: 36,
  TICK_STAGGER_MS: 37,
  TICK_APPEAR_MS: 180,
}

export const SPLASH_COLORS = {
  background: '#FFFFFF',
  gradientStart: '#1F6BFF',
  gradientEnd: '#6C3BFF',
  brand: '#17233D',
}

export const SPLASH_EASING = {
  outCubic: 'cubic-bezier(0.33, 1, 0.68, 1)',
  outQuart: 'cubic-bezier(0.25, 1, 0.5, 1)',
  outExpo: 'cubic-bezier(0.16, 1, 0.3, 1)',
  /** Approximation of spring(stiffness: 220, damping: 18). */
  spring: 'cubic-bezier(0.34, 1.22, 0.64, 1)',
}

function hexToRgb(hex) {
  const value = hex.replace('#', '')
  return {
    r: parseInt(value.slice(0, 2), 16),
    g: parseInt(value.slice(2, 4), 16),
    b: parseInt(value.slice(4, 6), 16),
  }
}

/** Gradient color for tick `index` around the ring (clockwise from 12 o'clock). */
export function splashTickColor(index, tickCount = SPLASH_TIMING.TICK_COUNT) {
  const start = hexToRgb(SPLASH_COLORS.gradientStart)
  const end = hexToRgb(SPLASH_COLORS.gradientEnd)
  const t = tickCount <= 1 ? 0 : index / (tickCount - 1)
  const r = Math.round(start.r + (end.r - start.r) * t)
  const g = Math.round(start.g + (end.g - start.g) * t)
  const b = Math.round(start.b + (end.b - start.b) * t)
  return `rgb(${r}, ${g}, ${b})`
}

/** CSS rotate angle: index 0 = 12 o'clock, then clockwise. */
export function splashTickAngle(index, tickCount = SPLASH_TIMING.TICK_COUNT) {
  return -90 + (360 / tickCount) * index
}

export function splashTickDelay(index) {
  return SPLASH_TIMING.CIRCLE_START_MS + index * SPLASH_TIMING.TICK_STAGGER_MS
}
