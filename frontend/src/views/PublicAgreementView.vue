<template>
  <div v-if="item" class="concord-page concord-page--public">
    <header class="concord-landing__header">
      <router-link to="/" class="concord-landing__brand">
        <ConcordLogoMark size="sm" />
        <span>Concord</span>
      </router-link>
    </header>

    <main class="concord-page__content">
      <div class="concord-detail-meta">
        <span class="concord-detail-badge concord-detail-badge--active">Публичный просмотр</span>
      </div>

      <h1 class="concord-landing__title">{{ item.title }}</h1>
      <p v-if="item.description" class="concord-detail-description">{{ item.description }}</p>

      <section v-for="section in item.sections" :key="section.id" class="concord-profile-card concord-section-card">
        <h2 class="concord-section-card__title">{{ section.name }}</h2>
        <div class="concord-section-card__body">
          <article v-for="block in section.blocks" :key="block.id" class="concord-block">
            <h3 v-if="block.title" class="concord-block__title">{{ block.title }}</h3>
            <p class="concord-block__text">{{ block.content?.body }}</p>
          </article>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ConcordLogoMark from '../components/concord/ConcordLogoMark.vue'
import api from '../services/api'

const route = useRoute()
const item = ref(null)

onMounted(async () => {
  const { data } = await api.get(`/api/agreements/public/${route.params.token}`)
  item.value = data.data
})
</script>

<style scoped>
.concord-page--public {
  min-height: 100dvh;
  background: var(--concord-shell-muted);
}

.concord-page--public .concord-landing__title {
  margin-bottom: 12px;
  font-size: clamp(1.5rem, 4vw, 2rem);
}
</style>
