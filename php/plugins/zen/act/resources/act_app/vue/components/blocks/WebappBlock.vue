<template>
  <template v-if="mode === 'site'">
    <HtmlFragment v-if="html.trim()" :html="html" />
  </template>

  <article v-else :class="['tg-block-card', { 'tg-block-card--editing': isEdit }]">
    <header class="tg-block-card__header">
      <BlockTitleEdit
        :name="block.name"
        type-label="Web-приложение"
        fallback="Web-приложение"
        :editable="viewer === 'owner'"
        @save-name="saveName"
      />
      <span v-if="isEdit && dirty" class="tg-block-card__status">Есть изменения</span>
    </header>

    <template v-if="isEdit">
      <textarea
        ref="textarea"
        v-model="html"
        class="tg-block-textarea tg-block-textarea--code"
        :disabled="saving"
        rows="8"
        placeholder="<div>…</div>, <style>…</style>, <script>…</script>"
        spellcheck="false"
        @input="resize"
      />
      <p v-if="error" class="tg-error tg-error--inline">{{ error }}</p>
      <div class="tg-block-actions tg-block-actions--compact">
        <button class="tg-btn" type="button" :disabled="saving || !dirty" @click="save">
          {{ saving ? '…' : 'Сохранить' }}
        </button>
        <button class="tg-btn tg-btn--ghost" type="button" :disabled="saving" @click="cancelEdit">
          Готово
        </button>
        <button class="tg-btn tg-btn--danger" type="button" :disabled="saving" @click="remove">
          Удалить
        </button>
      </div>
    </template>

    <template v-else-if="isOwnerView">
      <dl class="tg-block-meta">
        <div class="tg-block-meta__row">
          <dt>Тип</dt>
          <dd>HTML-приложение</dd>
        </div>
        <div class="tg-block-meta__row">
          <dt>Размер</dt>
          <dd>{{ sizeLabel }}</dd>
        </div>
      </dl>
      <div class="tg-block-actions tg-block-actions--compact">
        <BlockSettingsButton @click="$emit('settings', block)" />
        <BlockEditButton @click="startEdit" />
      </div>
    </template>

    <template v-else>
      <p class="tg-block-content tg-block-content--empty">Приложение пока пустое</p>
    </template>
  </article>
</template>

<script>
import { computed, nextTick, ref, watch } from 'vue'
import { formatHtmlSizeMb } from './block-utils'
import BlockEditButton from './BlockEditButton.vue'
import BlockSettingsButton from './BlockSettingsButton.vue'
import BlockTitleEdit from './BlockTitleEdit.vue'
import HtmlFragment from './HtmlFragment.vue'

export default {
  name: 'WebappBlock',
  components: {
    BlockEditButton,
    BlockSettingsButton,
    BlockTitleEdit,
    HtmlFragment,
  },
  props: {
    block: {
      type: Object,
      required: true,
    },
    viewer: {
      type: String,
      default: 'guest',
      validator: (value) => ['owner', 'guest'].includes(value),
    },
    mode: {
      type: String,
      default: 'edit',
      validator: (value) => ['edit', 'site'].includes(value),
    },
    startInEditMode: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['save', 'delete', 'settings'],
  setup(props, { emit }) {
    const textarea = ref(null)
    const html = ref(props.block?.data?.html || '')
    const saving = ref(false)
    const error = ref('')
    const editing = ref(props.startInEditMode)

    const originalHtml = computed(() => props.block?.data?.html || '')
    const dirty = computed(() => html.value !== originalHtml.value)
    const isEdit = computed(() => props.viewer === 'owner' && editing.value)
    const isOwnerView = computed(() => props.viewer === 'owner' && !editing.value)
    const sizeLabel = computed(() => formatHtmlSizeMb(originalHtml.value))

    const resize = () => {
      nextTick(() => {
        if (!textarea.value) {
          return
        }
        textarea.value.style.height = 'auto'
        textarea.value.style.height = `${Math.max(textarea.value.scrollHeight, 160)}px`
      })
    }

    watch(
      () => props.block,
      () => {
        html.value = props.block?.data?.html || ''
        error.value = ''
        if (!props.startInEditMode) {
          editing.value = false
        }
        resize()
      },
      { immediate: true }
    )

    watch(
      () => props.startInEditMode,
      (value) => {
        if (value) {
          editing.value = true
          resize()
        }
      }
    )

    const startEdit = () => {
      editing.value = true
      resize()
    }

    const cancelEdit = () => {
      html.value = originalHtml.value
      error.value = ''
      editing.value = false
    }

    const save = () => {
      if (!dirty.value || saving.value) {
        return
      }

      saving.value = true
      error.value = ''
      emit('save', {
        block: props.block,
        data: {
          ...(props.block.data || {}),
          type: 'webapp',
          html: html.value,
        },
        done: () => {
          saving.value = false
          editing.value = false
        },
        fail: (message) => {
          saving.value = false
          error.value = message || 'Не удалось сохранить блок'
        },
      })
    }

    const remove = () => {
      if (saving.value) {
        return
      }
      emit('delete', props.block)
    }

    const saveName = ({ name, done, fail }) => {
      emit('save', {
        block: props.block,
        data: props.block.data || { type: 'webapp', html: originalHtml.value },
        name,
        done,
        fail,
      })
    }

    return {
      textarea,
      html,
      saving,
      error,
      dirty,
      isEdit,
      isOwnerView,
      sizeLabel,
      resize,
      startEdit,
      cancelEdit,
      save,
      remove,
      saveName,
    }
  },
}
</script>
