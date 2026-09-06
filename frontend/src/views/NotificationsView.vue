<template>
  <div class="page">
    <div class="page-head">
      <div>
        <h1>Уведомления</h1>
        <p>Голоса, отказы и запуски по вашим документам.</p>
      </div>
      <Button v-if="items.length" label="Прочитать все" text @click="readAll" />
    </div>
    <article v-for="item in items" :key="item.id" class="notice" :class="{ 'is-unread': !item.read_at }">
      <strong>{{ item.title }}</strong>
      <p>{{ item.message }}</p>
      <time>{{ formatDateTime(item.created_at) }}</time>
      <div v-if="!item.read_at">
        <Button size="small" text label="Отметить прочитанным" @click="read(item)" />
      </div>
    </article>
    <p v-if="!items.length" class="empty">Новых уведомлений нет.</p>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'
import api from '../services/api'
import { formatDateTime } from '../utils/format'

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
