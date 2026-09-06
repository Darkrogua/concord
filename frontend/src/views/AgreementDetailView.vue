<template>
  <div v-if="item" class="page">
    <div class="page-head">
      <div>
        <h1>{{ item.title }}</h1>
        <p>
          <span class="status-pill">{{ item.status_label }}</span>
          &nbsp;Дедлайн {{ formatDateTime(item.deadline) }}
        </p>
        <p class="dossier-desc">{{ item.description }}</p>
      </div>
      <div class="vote-row">
        <router-link
          v-if="item.is_author && item.status === 'draft'"
          class="btn btn-ghost"
          :to="`/agreements/${item.id}/edit`"
        >
          Редактировать
        </router-link>
        <Button v-if="item.is_author && item.public_token" outlined label="Копировать ссылку" @click="copyShare" />
        <Button
          v-if="item.is_author && ['completed', 'expired', 'archived'].includes(item.status)"
          outlined
          label="Перезапустить"
          @click="restart"
        />
      </div>
    </div>

    <section v-for="section in item.sections" :key="section.id" class="sheet">
      <h2>{{ section.name }}</h2>
      <div v-for="block in section.blocks" :key="block.id" class="mb-3">
        <h3 v-if="block.title">{{ block.title }}</h3>
        <pre v-if="block.type === 'code'" class="document-code">{{ block.content?.body }}</pre>
        <div v-else-if="block.type === 'links'" class="form-stack">
          <a v-for="(link, i) in block.content?.items || []" :key="i" :href="link.url" target="_blank" rel="noreferrer">
            {{ link.title || link.url }}
          </a>
        </div>
        <div v-else-if="block.type === 'files'" class="form-stack">
          <a v-for="file in block.files" :key="file.id" :href="`/api/files/${file.id}`" target="_blank" rel="noreferrer">
            {{ file.name }}
          </a>
          <FileUpload v-if="item.is_author" mode="basic" auto customUpload chooseLabel="Загрузить файл" @uploader="(e) => upload(block, e)" />
        </div>
        <div v-else-if="block.type === 'gallery'" class="vote-row">
          <img
            v-for="(img, i) in block.content?.items || []"
            :key="i"
            :src="img.url"
            :alt="img.alt || ''"
            width="160"
            height="120"
            loading="lazy"
            style="object-fit: cover; max-width: 160px; height: auto"
          />
        </div>
        <p v-else style="white-space: pre-wrap">{{ block.content?.body }}</p>
      </div>

      <h3>Согласовать раздел</h3>
      <div v-if="section.can_vote" class="form-stack">
        <div class="vote-row">
          <Button label="Да" @click="vote(section, 'yes')" />
          <Button label="Нет" outlined severity="danger" @click="rejecting = section.id" />
        </div>
        <div v-if="rejecting === section.id" class="field">
          <label :for="`comment-${section.id}`">Почему отказываете</label>
          <Textarea :id="`comment-${section.id}`" v-model="comment" maxlength="500" rows="3" class="w-full" />
          <Button class="mt-2" label="Отправить отказ" @click="vote(section, 'no')" />
        </div>
      </div>
      <p v-else-if="section.own_vote">Ваш голос: {{ section.own_vote === 'yes' ? 'да' : 'нет' }}</p>
      <p v-else class="dossier-meta">Голосование сейчас недоступно.</p>

      <div v-if="section.votes?.length" class="mt-3">
        <h3>Результаты</h3>
        <ul>
          <li v-for="v in section.votes" :key="v.id">
            {{ v.user?.name }} — {{ v.vote === 'yes' ? 'да' : 'нет' }}
            <span v-if="v.comment"> ({{ v.comment }})</span>
          </li>
        </ul>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import FileUpload from 'primevue/fileupload'
import api from '../services/api'
import { formatDateTime } from '../utils/format'

const route = useRoute()
const item = ref(null)
const rejecting = ref(null)
const comment = ref('')

async function load() {
  const { data } = await api.get(`/api/agreements/${route.params.id}`)
  item.value = data.data
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
