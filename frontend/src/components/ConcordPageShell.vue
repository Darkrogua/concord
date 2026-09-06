<template>
  <div class="concord-page" :class="pageClass">
    <ConcordPageHeader :title="title" :show-back="showBack" @back="router.back()" />
    <component :is="mainTag" :class="mainClass">
      <slot />
    </component>
    <footer v-if="$slots.footer" class="concord-page-footer">
      <slot name="footer" />
    </footer>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import ConcordPageHeader from './concord/ConcordPageHeader.vue'

const props = defineProps({
  title: { type: String, required: true },
  showBack: { type: Boolean, default: false },
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'form', 'settings', 'alerts', 'editor'].includes(value),
  },
})

const router = useRouter()

const pageClass = computed(() => {
  if (props.variant === 'form') return 'concord-page--agreement-form'
  if (props.variant === 'settings') return 'concord-page--settings'
  if (props.variant === 'alerts') return 'concord-page--alerts'
  if (props.variant === 'editor') return 'concord-page--editor'
  return ''
})

const mainClass = computed(() => {
  if (props.variant === 'editor') return 'concord-agreement-editor'
  if (props.variant === 'form' || props.variant === 'settings') return 'concord-notifications'
  if (props.variant === 'alerts') return 'concord-alerts'
  return 'concord-page__content'
})

const mainTag = computed(() => (props.variant === 'default' ? 'div' : 'main'))
</script>

<style scoped>
.concord-page__content {
  padding: var(--concord-page-content-top) var(--concord-page-gutter) 2rem;
}
</style>
