<template>
  <ConcordPageShell title="События" variant="alerts">
    <p v-if="!items.length" class="concord-alerts__empty">Пока нет уведомлений</p>

    <article
      v-for="item in items"
      :key="item.id"
      :class="[
        'concord-alerts__message',
        item.read_at ? 'concord-alerts__message--read' : 'concord-alerts__message--unread',
      ]"
    >
      <div class="concord-alerts__avatar-wrap">
        <span class="concord-alerts__avatar" aria-hidden="true">C</span>
        <span v-if="!item.read_at" class="concord-alerts__unread-dot" aria-hidden="true" />
      </div>

      <div class="concord-alerts__bubble">
        <p v-if="item.title" class="concord-alerts__bubble-sender">{{ item.title }}</p>
        <p class="concord-alerts__bubble-text">{{ item.message }}</p>
        <div class="concord-alerts__bubble-footer">
          <time class="concord-alerts__bubble-time">{{ formatDateTime(item.created_at) }}</time>
          <button
            v-if="!item.read_at"
            type="button"
            class="concord-alerts__link"
            @click="read(item)"
          >
            Прочитано
          </button>
        </div>
      </div>
    </article>
  </ConcordPageShell>
</template>

<script setup>
import { onMounted, ref } from 'vue'
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
.concord-alerts__bubble-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 6px;
}
</style>
