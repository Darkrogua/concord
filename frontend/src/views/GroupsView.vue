<template>
  <div class="page">
    <h1>Группы пользователей</h1>
    <div class="flex gap-2 mb-3">
      <InputText v-model="name" placeholder="Название группы" />
      <Button label="Создать" @click="create" />
    </div>
    <Panel v-for="group in groups" :key="group.id" :header="group.name" class="mb-2">
      <p>{{ (group.users || []).map((u) => u.name).join(', ') || 'Пусто' }}</p>
      <Button size="small" severity="danger" text label="Удалить" @click="remove(group)" />
    </Panel>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Panel from 'primevue/panel'
import api from '../services/api'

const groups = ref([])
const name = ref('')

async function load() {
  const { data } = await api.get('/api/user-groups')
  groups.value = data.data
}

async function create() {
  await api.post('/api/user-groups', { name: name.value })
  name.value = ''
  await load()
}

async function remove(group) {
  await api.delete(`/api/user-groups/${group.id}`)
  await load()
}

onMounted(load)
</script>
