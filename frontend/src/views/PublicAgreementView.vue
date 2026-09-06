<template>
  <div class="page" v-if="item">
    <h1>{{ item.title }}</h1>
    <p>{{ item.description }}</p>
    <Panel v-for="section in item.sections" :key="section.id" :header="section.name" class="mb-3">
      <div v-for="block in section.blocks" :key="block.id" class="mb-2">
        <h3 v-if="block.title">{{ block.title }}</h3>
        <p style="white-space: pre-wrap">{{ block.content?.body }}</p>
      </div>
    </Panel>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import Panel from 'primevue/panel'
import api from '../services/api'

const route = useRoute()
const item = ref(null)

onMounted(async () => {
  const { data } = await api.get(`/api/agreements/public/${route.params.token}`)
  item.value = data.data
})
</script>
