<template>
  <div class="page">
    <div class="page-head">
      <div>
        <h1>Согласования</h1>
        <p>Входящие ждут вашего голоса. Исходящие — те, что вы запустили.</p>
      </div>
      <router-link class="btn btn-primary" to="/agreements/create">Создать</router-link>
    </div>

    <div class="toolbar">
      <SelectButton v-model="group" :options="groups" optionLabel="label" optionValue="value" @change="load" />
      <div class="field" style="min-width: 12rem">
        <label for="q" class="sr-only">Поиск</label>
        <InputText id="q" v-model="q" placeholder="Название…" @keyup.enter="load" />
      </div>
      <Select v-model="sort" :options="sorts" optionLabel="label" optionValue="value" aria-label="Сортировка" @change="load" />
    </div>

    <div class="dossier">
      <article v-for="item in items" :key="item.id" class="dossier-item" :class="`is-${item.status}`">
        <i />
        <div class="dossier-body">
          <h2>
            <router-link :to="`/agreements/${item.id}`">{{ item.title }}</router-link>
          </h2>
          <p class="dossier-desc">{{ item.description }}</p>
          <p class="dossier-meta">
            {{ item.status_label }}, дедлайн {{ formatDateTime(item.deadline) }}, {{ item.author?.name }}
          </p>
          <div v-for="section in item.sections || []" :key="section.id" class="section-progress">
            <span>{{ section.name }}</span>
            <span>{{ section.progress?.yes || 0 }} / {{ section.progress?.total || 0 }}</span>
            <ProgressBar :value="progressValue(section)" />
          </div>
        </div>
        <div class="dossier-actions">
          <Button
            v-if="item.is_author && item.status === 'draft'"
            size="small"
            outlined
            label="Редактировать"
            @click="$router.push(`/agreements/${item.id}/edit`)"
          />
          <Button size="small" text :aria-label="item.is_favorite ? 'Убрать из избранного' : 'В избранное'" icon="pi pi-star" @click="favorite(item)" />
          <Button size="small" text aria-label="Дублировать" icon="pi pi-copy" @click="duplicate(item)" />
        </div>
      </article>
      <p v-if="!items.length" class="empty">Пока нет согласований в этом списке. Создайте документ или смените фильтр.</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import ProgressBar from 'primevue/progressbar'
import api from '../services/api'
import { formatDateTime } from '../utils/format'

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
  await api.post(`/api/agreements/${item.id}/duplicate`)
  await load()
}

onMounted(load)
</script>

<style scoped>
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}
</style>
