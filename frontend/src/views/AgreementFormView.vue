<template>
  <div class="page">
    <h1>{{ isEdit ? 'Редактирование' : 'Новое согласование' }}</h1>
    <Card>
      <template #content>
        <form class="flex flex-column gap-3" @submit.prevent="save">
          <InputText v-model="form.title" placeholder="Название" class="w-full" />
          <Textarea v-model="form.description" placeholder="Описание" rows="3" autoResize class="w-full" />
          <label>Дедлайн</label>
          <DatePicker v-model="form.deadline" showTime hourFormat="24" class="w-full" />
          <label>Отложенный запуск (необязательно)</label>
          <DatePicker v-model="form.publish_date" showTime hourFormat="24" class="w-full" showButtonBar />
          <Message v-if="error" severity="error">{{ error }}</Message>
          <div class="flex gap-2">
            <Button type="submit" label="Сохранить черновик" :loading="loading" />
            <Button v-if="isEdit" type="button" outlined label="Запустить" @click="publish" />
          </div>
        </form>
      </template>
    </Card>

    <div v-if="isEdit" class="mt-4">
      <div class="flex justify-content-between align-items-center">
        <h2>Разделы</h2>
        <Button size="small" label="Добавить раздел" @click="addSection" />
      </div>
      <div v-for="section in sections" :key="section.id" class="mb-3">
        <Panel :header="section.name">
          <div class="flex flex-column gap-2">
            <InputText v-model="section.name" @change="saveSection(section)" />
            <div class="flex gap-3">
              <div class="flex align-items-center gap-2">
                <Checkbox v-model="section.participants_see_each_other" binary @change="saveSection(section)" />
                <span>Участники видят друг друга</span>
              </div>
              <div class="flex align-items-center gap-2">
                <Checkbox v-model="section.show_results_before_vote" binary @change="saveSection(section)" />
                <span>Результаты до голоса</span>
              </div>
            </div>
            <h4>Блоки</h4>
            <div v-for="block in section.blocks" :key="block.id" class="mb-2">
              <InputText v-model="block.title" placeholder="Заголовок (необязательно)" class="w-full mb-2" @change="saveBlock(block)" />
              <Textarea v-model="block.content.body" rows="4" class="w-full" placeholder="Текст" @change="saveBlock(block)" />
            </div>
            <Select v-model="newBlockType" :options="blockTypes" optionLabel="label" optionValue="value" placeholder="Тип блока" />
            <Button size="small" label="Добавить блок" @click="addBlock(section)" />
            <h4>Участники</h4>
            <ul>
              <li v-for="p in section.participants" :key="p.id">
                {{ p.user?.name }} ({{ p.user?.email }})
                <Button size="small" text icon="pi pi-times" @click="removeParticipant(section, p.user_id)" />
              </li>
            </ul>
            <AutoComplete
              v-model="userQuery"
              :suggestions="userSuggestions"
              optionLabel="name"
              placeholder="Найти пользователя"
              @complete="searchUsers"
              @item-select="(e) => addParticipant(section, e.value)"
            />
          </div>
        </Panel>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Panel from 'primevue/panel'
import Checkbox from 'primevue/checkbox'
import Select from 'primevue/select'
import AutoComplete from 'primevue/autocomplete'
import api from '../services/api'

const route = useRoute()
const router = useRouter()
const isEdit = computed(() => Boolean(route.params.id))
const form = reactive({ title: '', description: '', deadline: null, publish_date: null })
const sections = ref([])
const loading = ref(false)
const error = ref('')
const newBlockType = ref('text')
const userQuery = ref(null)
const userSuggestions = ref([])
const blockTypes = [
  { label: 'Текст', value: 'text' },
  { label: 'Ссылки', value: 'links' },
  { label: 'Код', value: 'code' },
  { label: 'Файлы', value: 'files' },
  { label: 'Галерея', value: 'gallery' },
]

function toIso(value) {
  return value ? new Date(value).toISOString() : null
}

async function load() {
  if (!isEdit.value) return
  const { data } = await api.get(`/api/agreements/${route.params.id}`)
  const item = data.data
  form.title = item.title
  form.description = item.description
  form.deadline = item.deadline ? new Date(item.deadline) : null
  form.publish_date = item.publish_date ? new Date(item.publish_date) : null
  sections.value = (item.sections || []).map((section) => ({
    ...section,
    blocks: (section.blocks || []).map((block) => ({
      ...block,
      content: { body: block.content?.body || block.content?.text || '' },
    })),
  }))
}

async function save() {
  loading.value = true
  error.value = ''
  try {
    const payload = {
      title: form.title,
      description: form.description,
      deadline: toIso(form.deadline),
      publish_date: toIso(form.publish_date),
    }
    if (isEdit.value) {
      await api.put(`/api/agreements/${route.params.id}`, payload)
    } else {
      const { data } = await api.post('/api/agreements', payload)
      await router.replace(`/agreements/${data.data.id}/edit`)
    }
  } catch (e) {
    error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || 'Ошибка сохранения'
  } finally {
    loading.value = false
  }
}

async function publish() {
  await save()
  await api.post(`/api/agreements/${route.params.id}/publish`)
  router.push(`/agreements/${route.params.id}`)
}

async function addSection() {
  const { data } = await api.post(`/api/agreements/${route.params.id}/sections`, { name: 'Новый раздел' })
  sections.value.push(data.data)
}

async function saveSection(section) {
  await api.put(`/api/sections/${section.id}`, {
    name: section.name,
    participants_see_each_other: section.participants_see_each_other,
    show_results_before_vote: section.show_results_before_vote,
    reset_votes: true,
  })
}

async function addBlock(section) {
  const { data } = await api.post(`/api/sections/${section.id}/blocks`, {
    type: newBlockType.value,
    content: { body: '' },
  })
  section.blocks = [...(section.blocks || []), { ...data, content: { body: '' } }]
}

async function saveBlock(block) {
  await api.put(`/api/blocks/${block.id}`, {
    title: block.title,
    content: { body: block.content?.body || '' },
  })
}

async function searchUsers(event) {
  const { data } = await api.get('/api/users/search', { params: { q: event.query } })
  userSuggestions.value = data.data
}

async function addParticipant(section, user) {
  const signatureId = user.signatures?.find((s) => s.is_active)?.id || user.signatures?.[0]?.id
  if (!signatureId) return
  await api.post(`/api/sections/${section.id}/participants`, {
    participants: [{ user_id: user.id, signature_id: signatureId }],
  })
  await load()
  userQuery.value = null
}

async function removeParticipant(section, userId) {
  await api.delete(`/api/sections/${section.id}/participants/${userId}`)
  await load()
}

onMounted(load)
</script>
