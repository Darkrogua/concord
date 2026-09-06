<template>
  <div class="page" v-if="item">
    <div class="flex justify-content-between align-items-start gap-2 flex-wrap">
      <div>
        <h1 class="mt-0">{{ item.title }}</h1>
        <Tag :value="item.status_label" />
        <p>{{ item.description }}</p>
        <p class="text-sm">Дедлайн: {{ formatDate(item.deadline) }}</p>
      </div>
      <div class="flex gap-2">
        <Button v-if="item.is_author && item.status === 'draft'" label="Редактировать" @click="$router.push(`/agreements/${item.id}/edit`)" />
        <Button v-if="item.is_author && item.public_token" outlined label="Копировать ссылку" @click="copyShare" />
        <Button v-if="item.is_author && ['completed','expired','archived'].includes(item.status)" outlined label="Перезапустить" @click="restart" />
      </div>
    </div>

    <Panel v-for="section in item.sections" :key="section.id" :header="section.name" class="mb-3">
      <div v-for="block in section.blocks" :key="block.id" class="mb-3">
        <h3 v-if="block.title">{{ block.title }}</h3>
        <pre v-if="block.type === 'code'">{{ block.content?.body }}</pre>
        <div v-else-if="block.type === 'links'">
          <a v-for="(link, i) in block.content?.items || []" :key="i" :href="link.url" target="_blank">{{ link.title || link.url }}</a>
        </div>
        <div v-else-if="block.type === 'files'">
          <div v-for="file in block.files" :key="file.id">
            <a :href="`/api/files/${file.id}`" target="_blank">{{ file.name }}</a>
          </div>
          <FileUpload v-if="item.is_author" mode="basic" auto customUpload @uploader="(e) => upload(block, e)" chooseLabel="Файл" />
        </div>
        <div v-else-if="block.type === 'gallery'" class="flex gap-2 flex-wrap">
          <img v-for="(img, i) in block.content?.items || []" :key="i" :src="img.url" alt="" style="max-width:160px" />
        </div>
        <p v-else style="white-space: pre-wrap">{{ block.content?.body }}</p>
      </div>

      <Divider />
      <h3>Согласовать?</h3>
      <div v-if="section.can_vote" class="flex flex-column gap-2">
        <div class="flex gap-2">
          <Button label="Да" severity="success" @click="vote(section, 'yes')" />
          <Button label="Нет" severity="danger" outlined @click="rejecting = section.id" />
        </div>
        <div v-if="rejecting === section.id">
          <Textarea v-model="comment" placeholder="Почему?" maxlength="500" rows="3" class="w-full" />
          <Button class="mt-2" label="Отправить отказ" @click="vote(section, 'no')" />
        </div>
      </div>
      <p v-else-if="section.own_vote">Ваш голос: {{ section.own_vote === 'yes' ? 'Да' : 'Нет' }}</p>
      <p v-else>Голосование недоступно</p>

      <div v-if="section.votes?.length" class="mt-3">
        <h4>Результаты</h4>
        <ul>
          <li v-for="v in section.votes" :key="v.id">
            {{ v.user?.name }} — {{ v.vote === 'yes' ? 'Да' : 'Нет' }}
            <span v-if="v.comment"> · {{ v.comment }}</span>
          </li>
        </ul>
      </div>
    </Panel>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Panel from 'primevue/panel'
import Divider from 'primevue/divider'
import Textarea from 'primevue/textarea'
import FileUpload from 'primevue/fileupload'
import api from '../services/api'

const route = useRoute()
const item = ref(null)
const rejecting = ref(null)
const comment = ref('')

async function load() {
  const { data } = await api.get(`/api/agreements/${route.params.id}`)
  item.value = data.data
}

function formatDate(value) {
  return value ? new Date(value).toLocaleString() : '—'
}

async function vote(section, choice) {
  await api.post(`/api/sections/${section.id}/vote`, {
    vote: choice,
    comment: choice === 'no' ? comment.value : null,
  })
  rejecting.value = null
  comment.value = ''
  await load()
}

async function upload(block, event) {
  const form = new FormData()
  form.append('file', event.files[0])
  await api.post(`/api/blocks/${block.id}/files`, form)
  await load()
}

async function copyShare() {
  const url = `${window.location.origin}/share/${item.value.public_token}`
  await navigator.clipboard.writeText(url)
}

async function restart() {
  await api.post(`/api/agreements/${item.value.id}/restart`)
  await load()
}

onMounted(load)
</script>
