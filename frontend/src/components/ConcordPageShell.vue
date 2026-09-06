<template>
  <div class="concord-page">
    <ConcordPageHeader :title="title" :show-back="showBack" @back="router.back()" />
    <div class="concord-page__content">
      <slot />
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import ConcordPageHeader from './concord/ConcordPageHeader.vue'

defineProps({
  title: { type: String, required: true },
  showBack: { type: Boolean, default: false },
})

const router = useRouter()
</script>

<style scoped>
.concord-page__content {
  padding: var(--concord-page-content-top) var(--concord-page-gutter) 2rem;
}
</style>
