<template>
  <ConcordPageShell title="Группы" show-back>
    <form class="concord-form" @submit.prevent="create">
      <InputText v-model="name" placeholder="Название группы" class="w-full" />
      <Button type="submit" label="Создать" />
    </form>
    <article v-for="group in groups" :key="group.id" class="concord-card concord-card--flat">
      <h2 class="concord-card__title">{{ group.name }}</h2>
      <p>{{ (group.users || []).map((u) => u.name).join(', ') || 'Пусто' }}</p>
      <Button size="small" severity="danger" text label="Удалить" @click="remove(group)" />
    </article>
    <p v-if="!groups.length" class="concord-list__empty">Групп пока нет.</p>
  </ConcordPageShell>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import { useConfirm } from 'primevue/useconfirm'
import ConcordPageShell from '../components/ConcordPageShell.vue'
import api from '../services/api'

const groups = ref([])
const name = ref('')
const confirm = useConfirm()

async function load() {
  const { data } = await api.get('/api/user-groups')
  groups.value = data.data
}

async function create() {
  if (!name.value.trim()) return
  await api.post('/api/user-groups', { name: name.value })
  name.value = ''
  await load()
}

function remove(group) {
  confirm.require({
    message: `Удалить группу «${group.name}»?`,
    header: 'Удаление',
    acceptLabel: 'Удалить',
    rejectLabel: 'Отмена',
    accept: async () => {
      await api.delete(`/api/user-groups/${group.id}`)
      await load()
    },
  })
}

onMounted(load)
</script>

<style scoped>
.concord-form { display: flex; gap: 0.65rem; margin-bottom: 1rem; }
.concord-card--flat { margin-bottom: 0.75rem; padding: 1rem; }
.concord-list__empty { color: var(--concord-text-muted); text-align: center; margin-top: 2rem; }
.w-full { flex: 1; }
</style>
