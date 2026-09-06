<template>
  <ConcordPageShell
    :title="isEdit ? 'Редактирование' : 'Новое согласование'"
    show-back
    :variant="isEdit ? 'editor' : 'form'"
  >
    <AgreementFormFields v-if="!isEdit" :model="form" @update:model="Object.assign(form, $event)" />

    <template v-if="isEdit">
      <p v-if="statusLabel" class="concord-editor-status">
        Статус: <strong>{{ statusLabel }}</strong>
      </p>

      <section class="concord-agreement-editor__intro-section">
        <div class="concord-agreement-editor__panel">
          <AgreementFormFields :model="form" @update:model="Object.assign(form, $event)" />
        </div>
      </section>

      <SectionEditor
        v-for="section in sections"
        :key="section.id"
        :section="section"
        :expanded-block-id="expandedBlockId"
        @save-section="saveSection(section)"
        @delete-section="deleteSection(section)"
        @save-block="saveBlock"
        @add-block="(type) => addBlock(section, type)"
        @delete-block="deleteBlock"
        @delete-file="deleteFile"
        @upload-file="uploadFile"
      />

      <section class="concord-agreement-editor__add-zone concord-agreement-editor__add-zone--section">
        <p class="concord-agreement-editor__add-section-label">Добавить раздел</p>
        <button
          type="button"
          class="concord-agreement-editor__add-btn concord-agreement-editor__add-btn--section"
          aria-label="Добавить раздел"
          :disabled="sectionBusy || !canEditStructure"
          @click="addSection"
        >
          <ConcordPlusIcon :size="24" />
        </button>
        <p v-if="!canEditStructure" class="concord-agreement-editor__launch-hint">
          Разделы можно менять только в черновике или пока согласование ждёт голосов.
        </p>

        <div class="concord-agreement-editor__launch-group">
          <p v-if="error" class="concord-form-error" role="alert">{{ error }}</p>
          <p v-if="notice" class="concord-editor-notice" role="status">{{ notice }}</p>
          <div class="concord-agreement-create__actions">
            <button
              type="button"
              class="concord-agreement-create__btn concord-agreement-create__btn--cancel"
              :disabled="loading"
              @click="save"
            >
              {{ loading ? 'Сохраняем…' : 'Сохранить черновик' }}
            </button>
            <button
              type="button"
              class="concord-agreement-create__btn concord-agreement-create__btn--save"
              :disabled="loading || !canPublish"
              @click="publish"
            >
              Запустить
            </button>
          </div>
          <p class="concord-agreement-editor__launch-hint">
            После запуска участники смогут голосовать по разделам.
          </p>
        </div>
      </section>
    </template>

    <p v-if="!isEdit && error" class="concord-form-error" role="alert">{{ error }}</p>

    <template v-if="!isEdit" #footer>
      <button
        type="button"
        class="concord-agreement-form__submit"
        :disabled="loading || !form.title.trim()"
        @click="save"
      >
        {{ loading ? 'Сохраняем…' : 'Сохранить черновик' }}
      </button>
    </template>
  </ConcordPageShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ConcordPageShell from '../components/ConcordPageShell.vue'
import AgreementFormFields from '../components/concord/AgreementFormFields.vue'
import SectionEditor from '../components/concord/SectionEditor.vue'
import ConcordPlusIcon from '../components/concord/ConcordPlusIcon.vue'
import { defaultBlockContent, getEditorBlockTypeLabel, normalizeBlock } from '../components/concord/editor-block-utils.js'
import api, { csrf, extractApiError } from '../services/api'

const route = useRoute()
const router = useRouter()
const isEdit = computed(() => Boolean(route.params.id))
const form = reactive({ title: '', description: '', deadline: null, publish_date: null })
const sections = ref([])
const agreementStatus = ref('draft')
const statusLabel = ref('')
const loading = ref(false)
const sectionBusy = ref(false)
const error = ref('')
const notice = ref('')
const expandedBlockId = ref(null)

const canEditStructure = computed(() => ['draft', 'awaiting'].includes(agreementStatus.value))
const canPublish = computed(() => agreementStatus.value === 'draft')

function toIso(value) {
  return value ? new Date(value).toISOString() : null
}

function normalizeSections(rawSections) {
  return (rawSections || []).map((section) => ({
    ...section,
    blocks: (section.blocks || []).map((block) => normalizeBlock(block)),
  }))
}

function showError(message) {
  error.value = message
  notice.value = ''
}

function showNotice(message) {
  notice.value = message
  error.value = ''
}

async function load() {
  if (!isEdit.value) return
  error.value = ''
  try {
    const { data } = await api.get(`/api/agreements/${route.params.id}`)
    const item = data.data
    form.title = item.title
    form.description = item.description || ''
    form.deadline = item.deadline ? new Date(item.deadline) : null
    form.publish_date = item.publish_date ? new Date(item.publish_date) : null
    agreementStatus.value = item.status
    statusLabel.value = item.status_label || item.status
    sections.value = normalizeSections(item.sections)
  } catch (e) {
    showError(extractApiError(e, 'Не удалось загрузить согласование'))
  }
}

async function save() {
  loading.value = true
  error.value = ''
  notice.value = ''
  try {
    await csrf()
    const payload = {
      title: form.title,
      description: form.description,
      deadline: toIso(form.deadline) || (isEdit.value ? null : new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString()),
      publish_date: toIso(form.publish_date),
    }
    if (isEdit.value) {
      await api.put(`/api/agreements/${route.params.id}`, payload)
      showNotice('Изменения сохранены')
    } else {
      const { data } = await api.post('/api/agreements', payload)
      await router.replace(`/agreements/${data.data.id}/edit`)
    }
  } catch (e) {
    showError(extractApiError(e, 'Ошибка сохранения'))
  } finally {
    loading.value = false
  }
}

async function publish() {
  loading.value = true
  error.value = ''
  notice.value = ''
  try {
    await csrf()
    const payload = {
      title: form.title,
      description: form.description,
      deadline: toIso(form.deadline),
      publish_date: toIso(form.publish_date),
    }
    await api.put(`/api/agreements/${route.params.id}`, payload)
    await api.post(`/api/agreements/${route.params.id}/publish`)
    router.push(`/agreements/${route.params.id}`)
  } catch (e) {
    showError(extractApiError(e, 'Не удалось запустить согласование'))
  } finally {
    loading.value = false
  }
}

async function addSection() {
  if (!canEditStructure.value) {
    showError('Согласование нельзя менять в текущем статусе')
    return
  }
  sectionBusy.value = true
  error.value = ''
  try {
    await csrf()
    const { data } = await api.post(`/api/agreements/${route.params.id}/sections`, { name: 'Новый раздел' })
    const section = normalizeSections([data.data])[0]
    sections.value.push(section)
    showNotice(`Раздел «${section.name}» добавлен`)
  } catch (e) {
    showError(extractApiError(e, 'Не удалось добавить раздел'))
  } finally {
    sectionBusy.value = false
  }
}

async function deleteSection(section) {
  if (!confirm(`Удалить раздел «${section.name}»?`)) return
  try {
    await csrf()
    await api.delete(`/api/sections/${section.id}`)
    sections.value = sections.value.filter((item) => item.id !== section.id)
    showNotice('Раздел удалён')
  } catch (e) {
    showError(extractApiError(e, 'Не удалось удалить раздел'))
  }
}

async function saveSection(section) {
  try {
    await csrf()
    await api.put(`/api/sections/${section.id}`, {
      name: section.name,
      participants_see_each_other: section.participants_see_each_other,
      show_results_before_vote: section.show_results_before_vote,
      reset_votes: true,
    })
  } catch (e) {
    showError(extractApiError(e, 'Не удалось сохранить раздел'))
  }
}

async function addBlock(section, type) {
  try {
    await csrf()
    const { data } = await api.post(`/api/sections/${section.id}/blocks`, {
      type,
      content: defaultBlockContent(type),
    })
    const block = normalizeBlock(data.data)
    section.blocks = [...(section.blocks || []), block]
    expandedBlockId.value = block.id
    showNotice(`Блок «${getEditorBlockTypeLabel(block)}» добавлен`)
  } catch (e) {
    showError(extractApiError(e, 'Не удалось добавить блок'))
  }
}

async function saveBlock(block) {
  try {
    await csrf()
    await api.put(`/api/blocks/${block.id}`, {
      title: block.title || block.label || '',
      content: block.content || {},
    })
  } catch (e) {
    showError(extractApiError(e, 'Не удалось сохранить блок'))
  }
}

async function deleteBlock(block) {
  if (!confirm('Удалить этот блок?')) return
  try {
    await csrf()
    await api.delete(`/api/blocks/${block.id}`)
    for (const section of sections.value) {
      section.blocks = (section.blocks || []).filter((item) => item.id !== block.id)
    }
    showNotice('Блок удалён')
  } catch (e) {
    showError(extractApiError(e, 'Не удалось удалить блок'))
  }
}

async function uploadFile(block, file) {
  try {
    await csrf()
    const formData = new FormData()
    formData.append('file', file)
    const { data } = await api.post(`/api/blocks/${block.id}/files`, formData)
    block.files = [...(block.files || []), data.data]
    showNotice('Файл загружен')
  } catch (e) {
    showError(extractApiError(e, 'Не удалось загрузить файл'))
  }
}

async function deleteFile(block, file) {
  try {
    await csrf()
    await api.delete(`/api/files/${file.id}`)
    block.files = (block.files || []).filter((item) => item.id !== file.id)
  } catch (e) {
    showError(extractApiError(e, 'Не удалось удалить файл'))
  }
}

onMounted(load)
</script>

<style scoped>
.concord-editor-status {
  margin: 0 0 12px;
  padding: 0 var(--concord-page-gutter);
  color: var(--concord-text-muted);
  font-size: var(--concord-text-body);
}

.concord-editor-notice {
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: var(--concord-radius-xs);
  background: var(--concord-status-green-bg);
  color: var(--concord-status-green);
  font-size: var(--concord-text-body);
}

.concord-agreement-editor__add-btn--section:disabled {
  opacity: 0.45;
  cursor: default;
}
</style>
