<template>
  <ConcordPageShell :title="isEdit ? 'Редактирование' : 'Новое согласование'" show-back>
    <form class="concord-form" @submit.prevent="save">
      <label class="concord-field"><span>Название</span><InputText v-model="form.title" class="w-full" /></label>
      <label class="concord-field"><span>Описание</span><Textarea v-model="form.description" rows="3" autoResize class="w-full" /></label>
      <label class="concord-field"><span>Дедлайн</span><DatePicker v-model="form.deadline" showTime hourFormat="24" class="w-full" /></label>
      <Message v-if="error" severity="error">{{ error }}</Message>
      <div class="concord-form-actions">
        <Button type="submit" label="Сохранить черновик" :loading="loading" />
        <Button v-if="isEdit" type="button" outlined label="Запустить" @click="publish" />
      </div>
    </form>
    <div v-if="isEdit" class="concord-editor-sections">
      <div class="concord-editor-sections__head">
        <h2>Разделы</h2>
        <Button size="small" label="Добавить" @click="addSection" />
      </div>
      <article v-for="section in sections" :key="section.id" class="concord-card concord-card--flat">
        <InputText v-model="section.name" class="w-full mb-2" @change="saveSection(section)" />
        <div v-for="block in section.blocks" :key="block.id" class="concord-form">
          <InputText v-model="block.title" placeholder="Заголовок" class="w-full" @change="saveBlock(block)" />
          <Textarea v-model="block.content.body" rows="3" class="w-full" @change="saveBlock(block)" />
        </div>
        <Button size="small" label="Добавить блок" @click="addBlock(section)" />
      </article>
    </div>
  </ConcordPageShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Message from 'primevue/message'
import ConcordPageShell from '../components/ConcordPageShell.vue'
import api from '../services/api'

const route = useRoute()
const router = useRouter()
const isEdit = computed(() => Boolean(route.params.id))
const form = reactive({ title: '', description: '', deadline: null, publish_date: null })
const sections = ref([])
const loading = ref(false)
const error = ref('')
const newBlockType = ref('text')

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
  section.blocks = [...(section.blocks || []), { ...data.data, content: { body: '' } }]
}

async function saveBlock(block) {
  await api.put(`/api/blocks/${block.id}`, {
    title: block.title,
    content: { body: block.content?.body || '' },
  })
}

onMounted(load)
</script>

<style scoped>
.concord-form { display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 1rem; }
.concord-field { display: flex; flex-direction: column; gap: 0.35rem; color: var(--concord-form-label-color); }
.concord-form-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.concord-editor-sections__head { display: flex; justify-content: space-between; align-items: center; }
.concord-card--flat { padding: 1rem; margin-bottom: 0.75rem; }
.w-full { width: 100%; }
.mb-2 { margin-bottom: 0.5rem; }
</style>
