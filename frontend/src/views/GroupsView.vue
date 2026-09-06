<template>
  <div class="page">
    <div class="page-head">
      <div>
        <h1>Группы</h1>
        <p>Наборы людей, которых удобно добавлять в раздел разом.</p>
      </div>
    </div>
    <form class="toolbar" @submit.prevent="create">
      <div class="field" style="flex: 1; min-width: 12rem">
        <label for="group-name">Название группы</label>
        <InputText id="group-name" v-model="name" name="group_name" autocomplete="off" />
      </div>
      <Button type="submit" label="Создать группу" style="align-self: end" />
    </form>
    <article v-for="group in groups" :key="group.id" class="sheet">
      <h2>{{ group.name }}</h2>
      <p>{{ (group.users || []).map((u) => u.name).join(', ') || 'Пока никого нет.' }}</p>
      <Button size="small" severity="danger" text label="Удалить группу" @click="remove(group)" />
    </article>
    <p v-if="!groups.length" class="empty">Групп ещё нет. Задайте название и создайте первую.</p>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import { useConfirm } from 'primevue/useconfirm'
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
    header: 'Удаление группы',
    rejectLabel: 'Отмена',
    acceptLabel: 'Удалить',
    accept: async () => {
      await api.delete(`/api/user-groups/${group.id}`)
      await load()
    },
  })
}

onMounted(load)
</script>
