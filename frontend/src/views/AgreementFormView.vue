<template>
  <div class="page">
    <div class="page-head">
      <div>
        <h1>{{ isEdit ? 'Редактирование' : 'Новое согласование' }}</h1>
        <p>{{ isEdit ? 'Сначала черновик, затем разделы и участники.' : 'Сохраните черновик — разделы появятся на следующем шаге.' }}</p>
      </div>
    </div>
    <div class="sheet">
        <form class="form-stack" @submit.prevent="save">
          <div class="field">
            <label for="title">Название</label>
            <InputText id="title" v-model="form.title" name="title" autocomplete="off" class="w-full" />
          </div>
          <div class="field">
            <label for="description">Описание</label>
            <Textarea id="description" v-model="form.description" rows="3" autoResize class="w-full" />
          </div>
          <div class="field">
            <label for="deadline">Дедлайн</label>
            <DatePicker inputId="deadline" v-model="form.deadline" showTime hourFormat="24" class="w-full" />
          </div>
          <div class="field">
            <label for="publish_date">Отложенный запуск</label>
            <DatePicker inputId="publish_date" v-model="form.publish_date" showTime hourFormat="24" class="w-full" showButtonBar />
          </div>
          <Message v-if="error" severity="error">{{ error }}</Message>
          <div class="vote-row">
            <Button type="submit" label="Сохранить черновик" :loading="loading" :disabled="loading" />
            <Button v-if="isEdit" type="button" outlined label="Запустить" @click="publish" />
          </div>
        </form>
    </div>

    <div v-if="isEdit" class="mt-4">
      <div class="page-head">
        <h2>Разделы</h2>
        <Button size="small" label="Добавить раздел" @click="addSection" />
      </div>
      <div v-for="section in sections" :key="section.id" class="sheet">
          <div class="form-stack">
            <div class="field">
              <label :for="`section-${section.id}`">Название раздела</label>
              <InputText :id="`section-${section.id}`" v-model="section.name" @change="saveSection(section)" />
            </div>
            <label class="field" style="flex-direction: row; align-items: center; gap: 0.6rem">
              <Checkbox v-model="section.participants_see_each_other" binary @change="saveSection(section)" />
              Участники видят друг друга
            </label>
            <label class="field" style="flex-direction: row; align-items: center; gap: 0.6rem">
              <Checkbox v-model="section.show_results_before_vote" binary @change="saveSection(section)" />
              Результаты до голоса
            </label>
            <h3>Блоки</h3>
            <div v-for="block in section.blocks" :key="block.id" class="form-stack">
              <div class="field">
                <label :for="`block-title-${block.id}`">Заголовок блока</label>
                <InputText :id="`block-title-${block.id}`" v-model="block.title" class="w-full" @change="saveBlock(block)" />
              </div>
              <div class="field">
                <label :for="`block-body-${block.id}`">Текст</label>
                <Textarea :id="`block-body-${block.id}`" v-model="block.content.body" rows="4" class="w-full" @change="saveBlock(block)" />
              </div>
            </div>
            <Select v-model="newBlockType" :options="blockTypes" optionLabel="label" optionValue="value" aria-label="Тип блока" />
            <Button size="small" label="Добавить блок" @click="addBlock(section)" />
            <h3>Участники</h3>
            <ul>
              <li v-for="p in section.participants" :key="p.id">
                {{ p.user?.name }} ({{ p.user?.email }})
                <Button size="small" text aria-label="Убрать участника" icon="pi pi-times" @click="removeParticipant(section, p.user_id)" />
              </li>
            </ul>
            <AutoComplete
              v-model="userQuery"
              :suggestions="userSuggestions"
              optionLabel="name"
              placeholder="Найти пользователя…"
              aria-label="Найти пользователя"
              @complete="searchUsers"
              @item-select="(e) => addParticipant(section, e.value)"
            />
          </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Message from 'primevue/message'
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
