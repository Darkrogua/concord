<template>
  <ConcordPageShell title="Уведомления">
    <div v-for="item in items" :key="item.id" class="concord-alert" :class="{ 'concord-alert--unread': !item.read_at }">
      <strong>{{ item.title }}</strong>
      <p>{{ item.message }}</p>
      <time>{{ formatDateTime(item.created_at) }}</time>
      <Button v-if="!item.read_at" size="small" text label="Прочитано" @click="read(item)" />
    </div>
    <p v-if="!items.length" class="concord-list__empty">Нет уведомлений</p>
  </ConcordPageShell>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'
import ConcordPageShell from '../components/ConcordPageShell.vue'
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

onMounted(load)
</script>

<style scoped>
.concord-alert { padding: 0.9rem; border: 1px solid var(--concord-border); border-radius: var(--concord-radius-sm); margin-bottom: 0.65rem; background: var(--concord-alert-read-bg); }
.concord-alert--unread { background: var(--concord-alert-unread-bg); box-shadow: var(--concord-card-shadow); }
.concord-list__empty { color: var(--concord-text-muted); text-align: center; margin-top: 2rem; }
</style>
