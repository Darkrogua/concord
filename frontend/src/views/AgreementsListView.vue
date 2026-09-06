<template>
  <div class="page">
    <div class="flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h1 class="m-0">Согласования</h1>
      <Button label="Создать" icon="pi pi-plus" @click="$router.push('/agreements/create')" />
    </div>

    <div class="flex flex-wrap gap-2 mb-3">
      <SelectButton v-model="group" :options="groups" optionLabel="label" optionValue="value" @change="load" />
      <InputText v-model="q" placeholder="Поиск" @keyup.enter="load" />
      <Select v-model="sort" :options="sorts" optionLabel="label" optionValue="value" @change="load" />
    </div>

    <div class="flex flex-column gap-2">
      <Panel v-for="item in items" :key="item.id" :header="item.title" toggleable collapsed>
        <template #icons>
          <Tag :value="item.status_label" :severity="severity(item.status)" />
        </template>
        <p class="m-0 mb-2">{{ item.description }}</p>
        <p class="text-sm">Дедлайн: {{ formatDate(item.deadline) }} · Автор: {{ item.author?.name }}</p>
        <div v-for="section in item.sections || []" :key="section.id" class="mb-2">
          <div class="flex justify-content-between">
            <span>{{ section.name }}</span>
            <span>{{ section.progress?.yes || 0 }} / {{ section.progress?.total || 0 }}</span>
          </div>
          <ProgressBar :value="progressValue(section)" />
        </div>
        <div class="flex gap-2 mt-3">
          <Button size="small" label="Открыть" @click="$router.push(`/agreements/${item.id}`)" />
          <Button v-if="item.is_author && item.status === 'draft'" size="small" outlined label="Редактировать" @click="$router.push(`/agreements/${item.id}/edit`)" />
          <Button size="small" text icon="pi pi-star" @click="favorite(item)" />
          <Button size="small" text icon="pi pi-copy" @click="duplicate(item)" />
        </div>
      </Panel>
      <p v-if="!items.length">Пока нет согласований</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Panel from 'primevue/panel'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import api from '../services/api'

const items = ref([])
const q = ref('')
const group = ref('incoming')
const sort = ref('newest')
const groups = [
  { label: 'Входящие', value: 'incoming' },
  { label: 'Исходящие', value: 'outgoing' },
  { label: 'Избранное', value: 'favorites' },
  { label: 'Архив', value: 'archive' },
  { label: 'Все', value: 'all' },
]
const sorts = [
  { label: 'Сначала новые', value: 'newest' },
  { label: 'По дедлайну', value: 'deadline' },
  { label: 'По активности', value: 'activity' },
]

async function load() {
  const { data } = await api.get('/api/agreements', { params: { group: group.value, q: q.value, sort: sort.value } })
  items.value = data.data
}

function severity(status) {
  return { awaiting: 'info', completed: 'success', expired: 'danger', draft: 'secondary', archived: 'contrast' }[status] || 'secondary'
}

function formatDate(value) {
  return value ? new Date(value).toLocaleString() : '—'
}

function progressValue(section) {
  const total = section.progress?.total || 0
  const yes = section.progress?.yes || 0
  return total ? Math.round((yes / total) * 100) : 0
}

async function favorite(item) {
  await api.post(`/api/agreements/${item.id}/favorite`)
  await load()
}

async function duplicate(item) {
  const { data } = await api.post(`/api/agreements/${item.id}/duplicate`)
  await load()
  return data
}

onMounted(load)
</script>
