<template>
  <div :class="['tg-skeleton', `tg-skeleton--${pattern}`]" aria-hidden="true">
    <template v-if="pattern === 'acts'">
      <div class="tg-skeleton__bar tg-skeleton__bar--toolbar" />
      <div class="tg-skeleton__acts-grid">
        <div v-for="n in 4" :key="n" class="tg-skeleton__tile" />
      </div>
    </template>

    <template v-else-if="pattern === 'blocks'">
      <div class="tg-skeleton__bar tg-skeleton__bar--panel" />
      <div class="tg-skeleton__block" />
      <div class="tg-skeleton__block tg-skeleton__block--short" />
    </template>

    <template v-else-if="pattern === 'profile'">
      <div class="tg-skeleton__profile-hero">
        <div class="tg-skeleton__avatar" />
        <div class="tg-skeleton__bar tg-skeleton__bar--title" />
        <div class="tg-skeleton__bar tg-skeleton__bar--subtitle" />
      </div>
      <div class="tg-skeleton__bar" />
      <div class="tg-skeleton__bar" />
      <div class="tg-skeleton__bar tg-skeleton__bar--short" />
    </template>

    <template v-else-if="pattern === 'modal'">
      <div class="tg-skeleton__bar tg-skeleton__bar--switch" />
      <div class="tg-skeleton__bar" />
      <div class="tg-skeleton__bar tg-skeleton__bar--short" />
    </template>

    <template v-else-if="pattern === 'list'">
      <div v-for="n in 3" :key="n" class="tg-skeleton__list-row" />
    </template>

    <template v-else>
      <div class="tg-skeleton__bar" />
      <div class="tg-skeleton__bar" />
      <div class="tg-skeleton__bar tg-skeleton__bar--short" />
    </template>
  </div>
</template>

<script>
export default {
  name: 'AsyncRegionSkeleton',
  props: {
    pattern: {
      type: String,
      default: 'default',
    },
  },
}
</script>
