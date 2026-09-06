<template>
  <div class="auth-page">
    <a class="skip-link" href="#auth-main">К форме</a>
    <header class="auth-brand">
      <router-link class="brand" to="/" translate="no">Concord</router-link>
    </header>
    <main id="auth-main" class="auth-card">
      <h1>{{ title }}</h1>
      <p v-if="lead" class="auth-lead">{{ lead }}</p>
      <slot />
    </main>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  lead: { type: String, default: '' },
})
</script>
