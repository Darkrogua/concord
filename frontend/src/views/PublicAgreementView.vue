<template>
  <div v-if="item" class="page">
    <div class="page-head">
      <div>
        <h1>{{ item.title }}</h1>
        <p class="dossier-desc">{{ item.description }}</p>
      </div>
    </div>
    <section v-for="section in item.sections" :key="section.id" class="sheet">
      <h2>{{ section.name }}</h2>
      <div v-for="block in section.blocks" :key="block.id">
        <h3 v-if="block.title">{{ block.title }}</h3>
        <p style="white-space: pre-wrap">{{ block.content?.body }}</p>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '../services/api'

const route = useRoute()
const item = ref(null)

onMounted(async () => {
  const { data } = await api.get(`/api/agreements/public/${route.params.token}`)
  item.value = data.data
})
</script>
