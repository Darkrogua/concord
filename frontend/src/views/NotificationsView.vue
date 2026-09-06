<template>
  <div class="page">
    <div class="flex justify-content-between align-items-center">
      <h1>Уведомления</h1>
      <Button label="Прочитать все" text @click="readAll" />
    </div>
    <div v-for="item in items" :key="item.id" class="mb-2">
      <Panel :header="item.title">
        <p>{{ item.message }}</p>
        <small>{{ new Date(item.created_at).toLocaleString() }}</small>
        <Button v-if="!item.read_at" size="small" class="ml-2" label="Прочитано" @click="read(item)" />
      </Panel>
    </div>
    <p v-if="!items.length">Нет уведомлений</p>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'
import Panel from 'primevue/panel'
import api from '../services/api'

const items = ref([])

async function load() {
  const { data } = await api.get('/api/notifications')
  items.value = data.data
}

async function read(item) {
  await api.put(`/api/notifications/${item.id}/read`)
  await load()
}

async function readAll() {
  await api.put('/api/notifications/read-all')
  await load()
}

onMounted(load)
</script>
