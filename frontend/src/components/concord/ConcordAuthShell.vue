<template>
  <div class="concord-page concord-page--auth">
    <header class="concord-auth-header">
      <router-link to="/" class="concord-auth-brand">
        <ConcordLogoMark size="sm" />
        <span>Concord</span>
      </router-link>
    </header>

    <main class="concord-auth-main">
      <div class="concord-auth-card">
        <h1 class="concord-auth-title">{{ title }}</h1>
        <p v-if="subtitle" class="concord-auth-subtitle">{{ subtitle }}</p>
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup>
import ConcordLogoMark from './ConcordLogoMark.vue'

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
})
</script>
